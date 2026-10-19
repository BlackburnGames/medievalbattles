<?php
/**
 * Data-access helpers.
 *
 * Lived in the container's compat layer while Phase 2 was in flight, because
 * that layer was auto-prepended and so needed no include line at ~47 call
 * sites. It is not a shim for a removed function though -- it is app code, and
 * the app has to keep working outside this repo's Docker image -- so it now
 * lives here and is pulled in by connect.php, which every query path already
 * reaches.
 */

/*
 * mb_sql_str() and mb_sql_int() lived here: an escaped, quoted string literal
 * and a cast integer, for dropping into an interpolated query. They closed the
 * injection audit's 161 entries without moving any behaviour, and every query
 * they were used in has since moved to mb_query(), so they are gone. A value
 * goes into SQL through mb_query()'s params and nowhere else.
 */

if (!function_exists('mb_query')) {
    /**
     * Run a query with its values bound to placeholders rather than written
     * into the SQL by hand.
     *
     *     mb_query($db, "SELECT ename FROM user WHERE userid=?", array($userid))
     *
     * This is what mb_sql_str() and mb_sql_int() were standing in for. Every
     * value goes through this one function, so no call site chooses quotes,
     * remembers an escape or decides whether a value read back from the
     * database is safe. The SQL argument should be a literal:
     * tests/sql-injection-audit.php reads it as a query sink like
     * mysqli_query(), and the params array as safe.
     *
     * Each ? is replaced, in order, by its value: an int as an integer literal,
     * anything else as a string literal escaped by the driver for this
     * connection's character set -- null as '', which is what interpolating it
     * produced, and a float as the digits PHP would have interpolated.
     *
     * Substituted here rather than sent as a server-side prepared statement,
     * on purpose. A prepared statement's result comes back natively typed --
     * an INT column as a PHP int -- where every query this code was written
     * against returns strings, and the code compares with === and is_string()
     * on those strings: the guild-leader check is "$owner === $userid". Binding
     * server-side would change the type of every value read back, across 1300
     * call sites at once. Substituting keeps every result exactly as it was,
     * and is the same thing PDO does by default. Moving to server-side
     * prepares later means auditing those comparisons first.
     *
     * A ? inside a quoted literal in the SQL is left alone; the conversion
     * tool refuses any query that has one.
     *
     * Failures report the way every other query in the app does: connect.php's
     * MYSQLI_REPORT_ERROR turns one into a warning, which the crawl and the
     * tick fail on, and the return value is false.
     *
     * @param mysqli $db
     * @param string $sql    with a ? for each value
     * @param array  $params the values, in order
     * @return mysqli_result|bool as mysqli_query() returns
     */
    function mb_query($db, $sql, array $params = array())
    {
        if ($params) {
            $params = array_values($params);
            $out = '';
            $next = 0;
            $quote = null;
            $len = strlen($sql);
            for ($i = 0; $i < $len; $i++) {
                $c = $sql[$i];
                if ($quote !== null) {
                    $out .= $c;
                    if ($c === "\\" && $i + 1 < $len) {
                        $out .= $sql[++$i];
                    } elseif ($c === $quote) {
                        $quote = null;
                    }
                    continue;
                }
                if ($c === "'" || $c === '"') {
                    $quote = $c;
                    $out .= $c;
                    continue;
                }
                if ($c !== '?') {
                    $out .= $c;
                    continue;
                }
                if (!array_key_exists($next, $params)) {
                    trigger_error('mb_query(): more placeholders than values', E_USER_WARNING);
                    return false;
                }
                $value = $params[$next++];
                if (is_array($value) && array_key_exists('mb_num', $value)) {
                    // mb_num(): a bare numeric literal, as interpolation wrote it.
                    $num = is_string($value['mb_num']) ? trim($value['mb_num']) : $value['mb_num'];
                    if (!is_int($num) && !is_float($num) && !(is_string($num) && is_numeric($num))) {
                        trigger_error('mb_query(): mb_num() given something that is not a number', E_USER_WARNING);
                        return false;
                    }
                    $out .= (string) $num;
                    continue;
                }
                $out .= is_int($value) ? (string) $value
                    : "'" . mysqli_real_escape_string($db, (string) $value) . "'";
            }
            if ($next !== count($params)) {
                trigger_error('mb_query(): more values than placeholders', E_USER_WARNING);
                return false;
            }
            $sql = $out;
        }

        return mysqli_query($db, $sql); // mb-unbound-ok: the values substituted above
    }
}

if (!function_exists('mb_num')) {
    /**
     * A value for mb_query() to write as a bare number rather than a quoted
     * string:
     *
     *     mb_query($db, "UPDATE user SET gp = gp - ? WHERE userid=?", array(mb_num($cost), $id))
     *
     * For a number in a SET or in arithmetic, where the query used to have it
     * interpolated bare. MySQL reads a bare 2.5 as an exact decimal and a
     * quoted '2.5' as a double, and ROUND() -- which combat and the tick use
     * everywhere -- can give a different answer for the two. mb_num() keeps
     * the decimal. mb_query() checks that the value is a number; anything else
     * is a warning and the query does not run, as the syntax error it used to
     * produce was.
     *
     * @param mixed $value an int, a float or a numeric string
     * @return array a marker mb_query() recognises
     */
    function mb_num($value)
    {
        return array('mb_num' => $value);
    }
}

if (!function_exists('mb_db_result')) {
    /**
     * Fetch a single field from a mysqli result, mirroring mysql_result().
     *
     * Note the argument order is NOT mysql_result()'s: field comes second,
     * row third. That is deliberate, and the column-name fallback below is
     * load-bearing rather than defensive. Every original call was the
     * two-argument form, e.g.
     *
     *     mysql_result($r, "noplayers")
     *
     * where mysql_result()'s second parameter is the ROW, not the field. PHP
     * cast "noplayers" to int 0, so it returned row 0 / column 0 and the name
     * was never looked up. Most of those names are fiction: the query behind
     * "noplayers" is SELECT count(userid) FROM user, whose column is actually
     * named "count(userid)". Resolving these names strictly would return null
     * at nearly every call site and break signup, login and the guild code --
     * which is exactly how the abandoned PHP 7 port disabled the game tick.
     *
     * So: use the name when it genuinely resolves, otherwise fall back to
     * column 0, which is what the 2003 code effectively did. Both paths agree
     * at every current call site, and the name path starts doing real work as
     * queries gain proper aliases during the port.
     *
     * @param mysqli_result $result
     * @param int|string    $field column name, or offset
     * @param int|null      $row   row offset; defaults to 0
     * @return mixed null when the row is absent
     */
    function mb_db_result($result, $field = 0, $row = null)
    {
        if (!($result instanceof mysqli_result)) {
            return null;
        }
        if ($row !== null && $row > 0 && !mysqli_data_seek($result, $row)) {
            return null;
        }

        $data = mysqli_fetch_array($result, MYSQLI_BOTH);
        if (!is_array($data)) {
            return null;
        }
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }
        return array_key_exists(0, $data) ? $data[0] : null;
    }
}

if (!function_exists('mb_db_session')) {
    /**
     * Put a fresh connection into the mode this code was written for.
     *
     * Every connection the app opens -- the round's in connect.php, the
     * accounts' in accounts.php -- comes through here, because on the host
     * the server's own settings are not ours to change (docs/deployment.md).
     * MySQL 8.4's default sql_mode is strict, and the 2003 queries fail under
     * it: STRICT_TRANS_TABLES rejects their partial INSERTs and
     * ONLY_FULL_GROUP_BY their aggregates. So the session asks for what the
     * local server used to be started with, NO_ENGINE_SUBSTITUTION alone.
     * Tightening it is a Phase 3 bug list of its own, not a side effect of
     * changing servers.
     *
     * The collation is named for the same reason. 8.x's default for utf8mb4
     * is utf8mb4_0900_ai_ci, the schema's is utf8mb4_unicode_ci, and a string
     * literal compared with a column in one and a variable in the other is an
     * "Illegal mix of collations". set_charset() still comes first: it is
     * what tells the client library the character set real_escape_string()
     * escapes for.
     *
     * @param mysqli $link
     */
    function mb_db_session($link)
    {
        mysqli_set_charset($link, 'utf8mb4');
        mysqli_query($link, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        mysqli_query($link, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    }
}

if (!function_exists('mb_db')) {
    /**
     * The database connection, opened once per request.
     *
     * One database holds everything -- the round's tables and the accounts
     * beside them (docs/rounds.md, "Where a round lives") -- so there is one
     * connection, and connect.php's $db is this. Code that is not at page
     * scope, where $db is not in reach, asks for it here: the accounts in
     * include/accounts.php, and the session check before connect.php is
     * loaded.
     *
     * A failure stops the request with a 500: a page that cannot reach the
     * database cannot tell who is asking, and must not carry on as though
     * nobody is.
     *
     * @return mysqli
     */
    function mb_db()
    {
        static $link = null;
        if ($link instanceof mysqli) {
            return $link;
        }

        $name = getenv('MB_DB_NAME') ? getenv('MB_DB_NAME') : 'mbv6';
        $host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : '127.0.0.1';
        $user = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
        $pass = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';

        $link = @mysqli_connect($host, $user, $pass, $name);
        if (!$link) {
            if (!headers_sent()) {
                header('Content-Type: text/plain', true, 500);
            }
            echo "Error: Unable to connect to database.\n";
            echo mysqli_connect_error() . "\n";
            exit;
        }
        mb_db_session($link);
        return $link;
    }
}

if (!function_exists('mb_kingdom_lock')) {
    /**
     * Hold a kingdom for the rest of the request: one request at a time each.
     *
     * Every action page reads the kingdom into globals once (functions.php) and
     * writes back totals worked out from that read -- `SET gp=?`, `SET
     * warriors=?` -- in separate statements. Two requests on the same kingdom
     * at once, from two logins, each wrote over the other: a purchase raced
     * against a no-op order kept its gold, a barter listing raced against
     * disband.php doubled the army, six votes at once each counted. PHP's
     * session lock serialises one session's requests, not one kingdom's.
     *
     * So the kingdom is locked before it is read, and stays locked until the
     * connection closes at the end of the request. GET_LOCK names are global
     * to the MySQL server -- a shared host's, other tenants included -- so the
     * name carries the database's. The tick takes every kingdom's lock before
     * it runs (update.php), so it waits for requests already reading and they
     * wait for it.
     *
     * @param mysqli $db
     * @param int    $userid
     * @param int    $timeout seconds to wait for a request already holding it
     * @return bool whether the lock is held
     */
    function mb_kingdom_lock($db, $userid, $timeout = 10)
    {
        $result = mb_query($db, "SELECT GET_LOCK(CONCAT('mb:', DATABASE(), ':k', ?), ?)", array((int) $userid, (int) $timeout));
        return $result && (int) mb_db_result($result, 0) === 1;
    }
}

if (!function_exists('mb_kingdom_lock_target')) {
    /**
     * Hold the kingdom an attack is aimed at, as well as the attacker's own.
     *
     * An attack writes the defender's land, buildings and units -- several of
     * them as totals worked out from what it read -- so a defender's own order
     * running at the same moment wrote the attack away, or was written away by
     * it. The attacker already holds its own lock (functions.php), so taking
     * the target's second can meet a defender attacking back the same instant,
     * each holding one and waiting for the other. MySQL refuses the second
     * GET_LOCK of such a cycle with a deadlock error rather than waiting; that
     * is a refusal like any other here, so its warning is not reported.
     *
     * @param mysqli $db
     * @param int    $target
     * @param int    $self the acting kingdom, whose lock is already held
     * @return bool whether the target's lock is held
     */
    function mb_kingdom_lock_target($db, $target, $self)
    {
        if ((int) $target === (int) $self) {
            return true;
        }
        return @mb_kingdom_lock($db, $target, 5);
    }
}
