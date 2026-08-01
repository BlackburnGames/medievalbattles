<?php
/**
 * Legacy compatibility layer for Medieval Battles v6 (written for PHP 4).
 *
 * Loaded via prepend.php so the 2003 sources can stay untouched while a
 * working baseline is established. Every section below is migration debt with
 * a defined exit condition -- delete each one as the code that needs it is
 * ported, and the file shrinking to nothing is the signal that Phase 2 is done.
 *
 * Retired so far: mysql_db_query() and mysql_result() (the whole mysql_*
 * extension is gone from the sources), ereg_replace(), and the
 * session_register() family (call sites now use app/include/session.php and
 * $_SESSION directly). Left: register_globals, the last item of Phase 2.
 *
 * Deliberately does NOT paper over unquoted array keys ($row[email]), which
 * are fatal from PHP 8; those were fixed at the source in Phase 2.
 */

// ---------------------------------------------------------------------------
// 1. mb_db_result() -- the replacement for mysql_result(), removed in PHP 7.0,
//    and for the ~15 broken mysqli_field_seek() call sites that needed a value
//    and got a bool. Unlike the rest of this file it is not a shim for a
//    removed function, so it is the one section that outlives Phase 2; it
//    should move into the app once there is somewhere sensible to put it.
// ---------------------------------------------------------------------------
if (!function_exists('mb_db_result')) {
    /**
     * Fetch a single field from a mysqli result, mirroring mysql_result().
     *
     * The column-name fallback below is load-bearing, not defensive. Every
     * original call was the two-argument form, e.g.
     *
     *     mysql_result($r, "noplayers")
     *
     * where mysql_result()'s second parameter is the ROW, not the field. PHP
     * cast "noplayers" to int 0, so it returned row 0 / column 0 and the name
     * was never looked up. Most of those names are fiction: the query behind
     * "noplayers" is SELECT count(userid) FROM user, whose column is actually
     * named "count(userid)". Resolving these names strictly would return null
     * at nearly every call site and break signup, login and the guild code.
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

// ---------------------------------------------------------------------------
// 2. mb_client_hostname() -- extracted from common.php / commong.php.
//    Both declared their own gethostname(), which became a PHP built-in in
//    5.3, so loading either was an instant fatal (and loading both, a second
//    one). Hosted here so there is a single definition.
// ---------------------------------------------------------------------------
if (!function_exists('mb_client_hostname')) {
    function mb_client_hostname()
    {
        $address = getenv('REMOTE_ADDR');
        if (!$address) {
            $address = getenv('REMOTE_HOST');
        }
        if (!$address) {
            return '';
        }
        return @gethostbyaddr($address);
    }
}

// ---------------------------------------------------------------------------
// 3. register_globals -- switched off in PHP 5.4, and now OFF here too.
//
//    This was the single most invasive shim and the reason the stack is bound
//    to localhost: injecting request data into the global scope IS the
//    vulnerability register_globals was removed for. All 315 reads that
//    depended on it now call mb_input() (app/include/request.php) instead, so
//    MB_REGISTER_GLOBALS is "0" in docker-compose.yml and this block does
//    nothing. tests/register-globals-audit.php holds the line.
//
//    Kept, disabled, for one reason: the smoke crawl reaches 39 of 69 scripts
//    and only issues GETs, so the forum, signup and guild-admin handlers were
//    verified statically rather than by exercise. If one of them misbehaves,
//    flipping the flag back to "1" answers "is this a register_globals
//    regression?" in one request. Delete the block, the env var and this
//    comment once those paths are crawled.
// ---------------------------------------------------------------------------
if (getenv('MB_REGISTER_GLOBALS') === '1') {
    $mb_protected = array(
        'GLOBALS', '_SERVER', '_GET', '_POST', '_COOKIE', '_FILES',
        '_ENV', '_REQUEST', '_SESSION', 'mb_protected', 'mb_source', 'mb_key', 'mb_value',
    );

    // GPC order, matching the PHP 4 variables_order default.
    foreach (array($_GET, $_POST, $_COOKIE) as $mb_source) {
        foreach ($mb_source as $mb_key => $mb_value) {
            if (in_array($mb_key, $mb_protected, true)) {
                continue;
            }
            // Skip keys that are not valid identifiers; they could not have
            // been read as variables anyway, and would corrupt $GLOBALS.
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $mb_key)) {
                continue;
            }
            $GLOBALS[$mb_key] = $mb_value;
        }
    }

    unset($mb_protected, $mb_source, $mb_key, $mb_value);
}
