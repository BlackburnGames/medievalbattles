<?php
/**
 * Session bootstrap: start the session and load the bearer credential.
 *
 * Replaces the PHP 4 session_register('login'|'email'|'pw') calls that used to
 * open every page. session_register() aliased a global to a session slot in
 * both directions, so the same three lines both saved a fresh login and
 * restored an existing one; it was removed in PHP 5.4. Only the restore
 * direction is wanted here -- checklogin.php writes the session explicitly and
 * is the only thing that should.
 *
 * Include this from the app root (pages, and includes reached from them, all
 * run with app/ as the CWD). It is idempotent: including it twice re-reads the
 * same values, which is why the files that include both this and functions.php
 * are unaffected by the double load.
 *
 * $login/$email/$pw are the credential nearly every query filters on --
 * $_SESSION['pw'] is the MD5 hash, used directly as a bearer token. Replacing
 * that model is Phase 3 and means touching the schema, since email and hash
 * are denormalized into buildings, military, research and explore.
 */

// Several includes emit stray whitespace, so guard on headers_sent() as the old
// shim did rather than warning; a page past that point cannot get a session
// cookie out anyway and falls through as logged out.
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

$login = isset($_SESSION['login']) ? $_SESSION['login'] : '';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$pw    = isset($_SESSION['pw'])    ? $_SESSION['pw']    : '';

/*
 * The credential is checked on the way out, not just on the way in.
 *
 * $email and $pw are what ~1300 queries filter on, interpolated straight into
 * "WHERE email='$email' AND pw='$pw'" with no escaping. Fixing that at the call
 * sites is not on the table -- there are 1300 of them, and most are in files
 * that never see where the value came from. Constraining what the two names can
 * hold makes every one of those interpolations safe by construction, and this
 * is the one place every request passes through.
 *
 * A value that fails is not repaired, it is discarded: an address or a digest
 * that is not one of those things is not a credential, and the page falls
 * through to its logged-out branch exactly as it does for an expired session.
 * checksignup.php and preferences.php apply the same rule at the point of
 * writing, so this only ever fires for a row that predates them.
 *
 * See include/credentials.php for the rule and what it costs.
 */
require_once __DIR__ . '/credentials.php';

if (!mb_valid_email($email) || !mb_valid_pw_hash($pw)) {
    $login = $email = $pw = '';
}
