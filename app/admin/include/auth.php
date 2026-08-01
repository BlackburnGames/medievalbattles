<?php
/**
 * The admin area's authentication, which until now did not exist.
 *
 * What was here: adminlogin.php compared the posted name and password against
 * a hardcoded `mako` / `quickshot` and, when they matched, printed a link to
 * main.php. That was the whole mechanism. No session was written, and no page
 * in the area asked whether one existed -- so every screen served to anyone
 * who typed its URL, including gameconfig.php (DELETE FROM user) and
 * newgame.php, which wipes the world on a plain GET with no parameters at all.
 * gameconfig.php's own check had been commented out since 2003.
 *
 * What replaces it is deliberately small. This is a single-operator admin
 * screen for a 2003 hobby game, not an account system: one credential, held in
 * the environment, checked once and remembered in the session.
 *
 *   MB_ADMIN_USER      defaults to "mako"
 *   MB_ADMIN_PASSWORD  defaults to "quickshot"
 *
 * The defaults are the historical pair, so nothing that worked before stops
 * working -- and they are published in this repository's history, which is the
 * reason they are only defaults. Set both in the environment for any instance
 * that is not a throwaway. docker-compose.yml passes them through.
 *
 * This does not make the admin area safe to expose. The pages behind it still
 * build queries by interpolation, still echo user data unescaped, and still
 * delete the entire game with one link. The gate stops a stranger reaching
 * them; it does not make what is behind it sound. Both container ports stay
 * bound to 127.0.0.1.
 */

require_once __DIR__ . '/../../include/session.php';

if (!function_exists('mb_admin_credentials')) {
    /**
     * @return array{0: string, 1: string} configured user and password
     */
    function mb_admin_credentials()
    {
        $user = getenv('MB_ADMIN_USER');
        $pass = getenv('MB_ADMIN_PASSWORD');

        return array(
            $user === false || $user === '' ? 'mako'      : $user,
            $pass === false || $pass === '' ? 'quickshot' : $pass,
        );
    }
}

if (!function_exists('mb_admin_logged_in')) {
    /**
     * @return bool
     */
    function mb_admin_logged_in()
    {
        return isset($_SESSION['mb_admin']) && $_SESSION['mb_admin'] === true;
    }
}

if (!function_exists('mb_admin_login')) {
    /**
     * Check a submitted credential and, if it holds, mark the session.
     *
     * hash_equals() rather than == for two reasons. The obvious one is timing,
     * which barely matters on a loopback-only app. The one that does matter is
     * that PHP's == on two strings that both look numeric compares them as
     * numbers, so a password of "0e12345" and one of "0" have compared equal
     * in this codebase's idiom before now.
     *
     * @param  string $user
     * @param  string $pass
     * @return bool
     */
    function mb_admin_login($user, $pass)
    {
        list($expectUser, $expectPass) = mb_admin_credentials();

        // Both compared before either is acted on, rather than && -- which
        // short-circuits, and so answers "was the username right?" on its own.
        $userOk = hash_equals($expectUser, (string) $user);
        $passOk = hash_equals($expectPass, (string) $pass);

        if (!$userOk || !$passOk) {
            return false;
        }

        // A fresh id, so a session handed to the browser before the login
        // cannot be used to ride in on the privilege granted after it.
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['mb_admin'] = true;

        return true;
    }
}

if (!function_exists('mb_admin_logout')) {
    function mb_admin_logout()
    {
        unset($_SESSION['mb_admin']);
    }
}

if (!function_exists('mb_admin_require')) {
    /**
     * The gate. Every page in the area calls this before doing anything.
     *
     * 403 rather than a redirect, because several of these pages act on a GET
     * with no confirmation step: a redirect would let a refused request look
     * like a successful one that simply bounced somewhere else, and the smoke
     * crawl asserts on the status code.
     */
    function mb_admin_require()
    {
        if (mb_admin_logged_in()) {
            return;
        }

        if (!headers_sent()) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: text/html; charset=utf-8');
        }
        echo "<html><body bgcolor=white text=black><center><br><br>"
            . "<b>Not logged in.</b><br><br>"
            . "<a href=\"index.php\">Administration login</a>"
            . "</center></body></html>";
        exit;
    }
}
