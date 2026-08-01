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
 * mb_db_result() rides along with the connection.
 *
 * Every one of its ~47 call sites runs a query, so every one of them reaches
 * this file -- several only transitively, through functions.php or igtop.php,
 * which is why the helper is required here rather than included at each site.
 * __DIR__ because includes in this codebase are otherwise CWD-relative and the
 * CWD differs between pages (app/), update.php (repo root) and the admin area
 * (app/admin/).
 */
require_once __DIR__ . '/db.php';

/**
 * And html.php rides along for the same reason, at the other end of the request.
 *
 * It has nothing to do with the connection. It is here because this is the one
 * file every rendering path already reaches, and a page that echoes a value it
 * read out of the database is a page that ran a query.
 */
require_once __DIR__ . '/html.php';

/**
 * And the news writers, which need both -- mb_sql_str() to file a row and
 * mb_rich() to render one. Every page that files news runs a query, so every
 * one of them is here already.
 */
require_once __DIR__ . '/news.php';

/**
 * Query errors are reported again, as of Phase 3.
 *
 * PHP 8.1 made mysqli throw on error instead of returning false. Phase 2 turned
 * that off wholesale with MYSQLI_REPORT_OFF, because this code fires ~1300
 * unchecked queries and several had never succeeded -- under exceptions each
 * one becomes a fatal, which is a behaviour change rather than a port.
 *
 * Those failures are now fixed (research and returning armies were both dead
 * because db.sql was missing columns; see docs/modernization.md), and
 * tests/query-audit.sh reports none left on the surface the tests reach. So
 * reporting goes back on -- but as MYSQLI_REPORT_ERROR *without*
 * MYSQLI_REPORT_STRICT. Failures are raised as warnings, not exceptions:
 *
 *   - The smoke crawl fails on any warning, so a query that breaks on a covered
 *     page now fails the suite instead of being swallowed. That is the point.
 *   - A query that breaks on a page the crawl cannot reach still only warns, so
 *     restoring reporting cannot turn a live page into a blank one. Going
 *     further, to STRICT and exceptions, needs the remaining ~1300 call sites to
 *     actually check their return values first.
 *
 * MB_REPORT_QUERIES=0 turns it back off, as a bisection aid.
 */
mysqli_report(getenv('MB_REPORT_QUERIES') === '0' ? MYSQLI_REPORT_OFF : MYSQLI_REPORT_ERROR);

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
