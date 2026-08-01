<?php
/**
 * Dev-container prepend hook.
 *
 * php.ini can only auto_prepend one file, and two unrelated things need to run
 * before every request. Keeping them in separate files matters: compat.php is
 * migration debt that shrinks to nothing when Phase 2 completes, whereas this
 * file is harness support and stays.
 */

require_once __DIR__ . '/compat.php';

/**
 * Error display is opt-in per request.
 *
 * This is PHP 4 code that reads uninitialised variables constantly -- a single
 * logged-in page emits hundreds of notices. Rendering those into the response
 * body makes the game unusable in a browser, but the smoke test detects
 * regressions by scanning exactly that body, so they cannot simply be off.
 *
 * So: off for humans, on for the harness, which sends X-MB-Show-Errors. CLI
 * always shows them, because update.php and `php -l` have no response body to
 * pollute. Logging to stderr is unconditional either way -- see
 * `docker compose logs -f web`. Exit condition: none; this outlives Phase 2.
 */
$mb_show_errors = (PHP_SAPI === 'cli')
    || (isset($_SERVER['HTTP_X_MB_SHOW_ERRORS']) && $_SERVER['HTTP_X_MB_SHOW_ERRORS'] === '1');

ini_set('display_errors', $mb_show_errors ? '1' : '0');

unset($mb_show_errors);
