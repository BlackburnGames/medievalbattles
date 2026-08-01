<?php
/**
 * Legacy compatibility layer for Medieval Battles v6 (written for PHP 4).
 *
 * Loaded via prepend.php so the 2003 sources could stay untouched while a
 * working baseline was established. Every section was migration debt with a
 * defined exit condition, and the file shrinking to nothing is the signal that
 * Phase 2 is done.
 *
 * Retired: mysql_db_query() and mysql_result() (the whole mysql_* extension is
 * gone from the sources), ereg_replace(), the session_register() family (call
 * sites use app/include/session.php and $_SESSION directly), and
 * mb_db_result() / mb_client_hostname(), which were never shims for removed
 * functions and now live in app/include/db.php and app/include/hostname.php.
 *
 * What is left is one disabled block, kept as a bisection aid. Nothing in this
 * file runs on a normal request.
 *
 * Deliberately does NOT paper over unquoted array keys ($row[email]), which
 * are fatal from PHP 8; those were fixed at the source in Phase 2.
 */

// ---------------------------------------------------------------------------
// register_globals -- switched off in PHP 5.4, and now OFF here too.
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
//    regression?" in one request. Delete this file, the env var and the
//    require in prepend.php once those paths are crawled.
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
