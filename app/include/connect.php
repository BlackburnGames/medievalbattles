<?php
/**
 * Database connection.
 *
 * Provides four handles because the codebase is mid-migration and different
 * files expect different ones:
 *   $dbnam - database name, consumed by the ~184 mysql_db_query() calls
 *   $db    - mysqli handle, used by the ~9 files converted to mysqli
 *   (default mysql link) - relied on by the ~89 files still on mysql_*
 *   $var   - explicit mysql link, passed to mysql_query() in ~10 files
 *
 * Pages include this with a relative path and often more than once per
 * request, so connecting is guarded.
 */

$dbnam      = getenv('MB_DB_NAME') ? getenv('MB_DB_NAME') : 'mbv6';
$mb_db_host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : '127.0.0.1';
$mb_db_user = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
$mb_db_pass = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';

if (!isset($db) || !($db instanceof mysqli)) {
    $db = @mysqli_connect($mb_db_host, $mb_db_user, $mb_db_pass, $dbnam);

    if (!$db) {
        if (!headers_sent()) {
            header('Content-Type: text/plain', true, 500);
        }
        echo "Error: Unable to connect to database.\n";
        echo mysqli_connect_error() . "\n";
        exit;
    }

    mysqli_set_charset($db, 'utf8mb4');
}

// Legacy mysql_* extension. Most of the app is still on it and calls
// mysql_query() without a link, which requires a default link to exist.
if (function_exists('mysql_connect') && (!isset($var) || !is_resource($var))) {
    $var = @mysql_connect($mb_db_host, $mb_db_user, $mb_db_pass);

    if ($var) {
        @mysql_select_db($dbnam, $var);
        @mysql_set_charset('utf8mb4', $var);
    }
}
