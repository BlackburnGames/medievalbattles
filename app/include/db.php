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

if (!function_exists('mb_sql_str')) {
    /**
     * A string literal, escaped and quoted, ready to interpolate.
     *
     * The quotes are part of what this returns, deliberately. Every one of the
     * ~1300 queries in this codebase is a double-quoted string with variables
     * dropped into it, so the natural conversion of
     *
     *     "... WHERE ename='$name'"
     *
     * is to lift the value out of the string rather than leave the quotes
     * behind for the next reader to get wrong:
     *
     *     "... WHERE ename=" . mb_sql_str($db, $name)
     *
     * Prepared statements are better, and mb_query() below is how new and
     * converted queries bind their values. This closed the hole at every site
     * of the ~1300 without moving any behaviour, which is the property the
     * characterization suite can check; the sites move to mb_query() a page at
     * a time.
     *
     * @param mysqli $db
     * @param mixed  $value cast to string, so null becomes '' as interpolation did
     * @return string including the surrounding single quotes
     */
    function mb_sql_str($db, $value)
    {
        return "'" . mysqli_real_escape_string($db, (string) $value) . "'";
    }
}

if (!function_exists('mb_sql_int')) {
    /**
     * An integer literal, ready to interpolate outside quotes.
     *
     * Escaping does nothing for "... WHERE topicid=$topicid": the payload never
     * needs a quote to break out, because there is no quote to break out of.
     * Numeric parameters have to be cast instead, which is what this is for.
     *
     * PHP's own cast is the whole implementation -- (int)"5 OR 1=1" is 5 and
     * (int)"" is 0. The value of having a function is that the audit can see
     * it: tests/sql-injection-audit.php treats a call to this as the fix, so
     * every converted site drops off the worklist.
     *
     * Note 0 for a parameter that was not sent, where interpolating null used
     * to emit nothing and leave MySQL with a syntax error. A query that matches
     * no rows is the better of the two, and neither ever matched anything.
     *
     * @param mixed $value
     * @return string
     */
    function mb_sql_int($value)
    {
        return (string) (int) $value;
    }
}

if (!function_exists('mb_query')) {
    /**
     * Run a query with its values bound rather than interpolated.
     *
     *     mb_query($db, "SELECT userid FROM user WHERE email=? AND pw=?", array($email, $pw))
     *
     * This is what mb_sql_str() and mb_sql_int() were standing in for. They
     * close the hole at a call site by escaping or casting the value into the
     * SQL text; here the value never enters the text at all, so there is
     * nothing to get wrong -- no quoting context to read, no escape to forget
     * on a value read back from the database. The SQL argument should be a
     * literal: tests/sql-injection-audit.php reads it as a query sink like
     * mysqli_query(), and the params array as safe.
     *
     * Each value is bound by its PHP type: int as an integer, float as a
     * double, anything else as a string -- null included, as '', which is
     * what interpolating it produced and what mb_sql_str() produces.
     *
     * Failures report the way every other query in the app does: connect.php's
     * MYSQLI_REPORT_ERROR turns a failed prepare or execute into a warning,
     * which the crawl and the tick fail on, and the return value is false.
     *
     * @param mysqli $db
     * @param string $sql    with a ? for each value
     * @param array  $params the values, in order
     * @return mysqli_result|bool a result for a statement that returns rows,
     *                            true for one that does not, false on failure
     */
    function mb_query($db, $sql, array $params = array())
    {
        $stmt = mysqli_prepare($db, $sql);
        if (!$stmt) {
            return false;
        }

        if ($params) {
            $types = '';
            $values = array();
            foreach ($params as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                    $value = (string) $value;
                }
                $values[] = $value;
            }
            mysqli_stmt_bind_param($stmt, $types, ...$values);
        }

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);
        if ($result === false) {
            // No result set: an INSERT, UPDATE or DELETE that succeeded.
            $ok = mysqli_stmt_errno($stmt) === 0;
            mysqli_stmt_close($stmt);
            return $ok;
        }
        mysqli_stmt_close($stmt);
        return $result;
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
