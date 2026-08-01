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
