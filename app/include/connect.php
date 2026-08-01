<?php
/**
 * Database connection.
 *
 * $db is the mysqli handle, and now the only one: the legacy mysql_* link and
 * the $dbnam-consuming mysql_db_query() call sites were converted in Phase 2.
 *
 * Pages include this with a relative path and often more than once per
 * request, so connecting is guarded.
 */

/**
 * PHP 8.1 made mysqli throw on error instead of returning false. That is the
 * better default, but it is not what this code was written against: it fires
 * ~1300 unchecked queries, several of which have never succeeded (the guild
 * listing selects a column that is not in db.sql, and update.php's first loop
 * iteration interpolates empty values into arithmetic). Under exceptions each
 * one becomes a fatal, which is a behaviour change, not a port.
 *
 * Restoring the silent-false contract keeps Phase 2 mechanical. Turning this
 * back on -- and handling the errors it surfaces -- is Phase 3 work.
 */
mysqli_report(MYSQLI_REPORT_OFF);

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
