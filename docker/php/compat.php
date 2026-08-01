<?php
/**
 * Legacy compatibility layer for Medieval Battles v6 (written for PHP 4).
 *
 * Loaded via auto_prepend_file so the 2003 sources can stay untouched while a
 * working baseline is established. Every section below is migration debt with
 * a defined exit condition -- delete each one as the code that needs it is
 * ported, and the file shrinking to nothing is the signal that Phase 2 is done.
 *
 * Deliberately does NOT paper over:
 *   - unquoted array keys ($row[email]), which are fatal from PHP 8
 *   - the mysqli_field_seek mis-conversion, which is a real bug, not an API gap
 * Both need real source fixes and are tracked in CLAUDE.md.
 */

// ---------------------------------------------------------------------------
// 1. mysql_db_query() -- removed in PHP 5.3, still called ~184 times.
//    Exit condition: those call sites move to mysqli/PDO.
// ---------------------------------------------------------------------------
if (!function_exists('mysql_db_query') && function_exists('mysql_query')) {
    function mysql_db_query($database, $query, $link = null)
    {
        if ($link === null) {
            @mysql_select_db($database);
            return @mysql_query($query);
        }
        @mysql_select_db($database, $link);
        return @mysql_query($query, $link);
    }
}

// ---------------------------------------------------------------------------
// 2. session_register() family -- removed in PHP 5.4, still called ~51 times.
//
//    In PHP 4 this aliased a global to a session slot in both directions: the
//    value survived the request, and came back as a global on the next one.
//    Callers (common.php, act_igtop.php) rely on the read direction to restore
//    $login/$email/$pw, so emulating only the write direction would silently
//    log everyone out. Exit condition: call sites read $_SESSION directly.
// ---------------------------------------------------------------------------
if (!function_exists('session_register')) {
    function session_register($name)
    {
        if (session_id() === '' && !headers_sent()) {
            session_start();
        }
        foreach (func_get_args() as $key) {
            if (isset($_SESSION[$key])) {
                $GLOBALS[$key] = $_SESSION[$key];          // session -> global
            } elseif (isset($GLOBALS[$key])) {
                $_SESSION[$key] = $GLOBALS[$key];          // global -> session
            }
        }
        return true;
    }

    function session_unregister($name)
    {
        unset($_SESSION[$name], $GLOBALS[$name]);
        return true;
    }

    function session_is_registered($name)
    {
        return isset($_SESSION[$name]);
    }
}

// ---------------------------------------------------------------------------
// 3. ereg_replace() -- removed in PHP 7.0, used 14 times.
//    Present natively on the 5.6 baseline, so this only arms itself on newer
//    PHP. The POSIX-to-PCRE translation is naive, which is adequate only
//    because every current call is the no-op ereg_replace("nothing", ...).
//    Exit condition: call sites move to preg_replace.
// ---------------------------------------------------------------------------
if (!function_exists('ereg_replace')) {
    function ereg_replace($pattern, $replacement, $string)
    {
        return preg_replace('/' . str_replace('/', '\\/', $pattern) . '/', $replacement, $string);
    }
}

// ---------------------------------------------------------------------------
// 4. mysql_result() -- removed in PHP 7.0.
//    Also the correct replacement for the ~15 broken mysqli_field_seek() call
//    sites, which need a value and currently get a bool. Source fixes call
//    mb_db_result() instead; it is defined here so both paths share semantics.
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
// 5. mb_client_hostname() -- extracted from common.php / commong.php.
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
// 6. register_globals -- switched off in PHP 5.4.
//
//    This is the single most invasive shim and the reason the stack is bound
//    to localhost. Injecting request data into the global scope IS the
//    vulnerability register_globals was removed for; it is enabled here only
//    because ~35 files read bare $pageid / $gid / $umessage and every form in
//    the game is inert without it.
//
//    Gated on an explicit env var so it can never switch itself on outside the
//    dev container. Exit condition: handlers read $_GET/$_POST explicitly, at
//    which point MB_REGISTER_GLOBALS is removed from docker-compose.yml.
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
