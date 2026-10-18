<?php
/**
 * Who may use the admin area: a logged-in player whose account is flagged
 * `account.admin`, in the accounts database (include/accounts.php).
 *
 * Until Phase 3 nothing gated the area at all -- adminlogin.php compared a
 * hardcoded `mako` / `quickshot` and printed a link, and no page checked
 * anything. The first gate was one shared credential from the environment. A
 * shared password cannot be taken back from one person, says nothing about who
 * did what, and is one more secret to leak, so there is no admin password now:
 * an admin logs into the game like any player, through the same login, hashing,
 * session rotation and revocation, and the account carries the privilege.
 *
 * The flag is read from the database on every request rather than remembered
 * in the session, so revoking it takes effect on the admin's next click, and
 * a session that include/session.php logs out -- expired, password changed,
 * account deleted -- takes the privilege with it.
 *
 * Granting and revoking is bin/admin.php, from the command line, because the
 * first admin has to come from somewhere other than a page only admins see.
 *
 * This does not make the admin area safe to expose. The pages behind it still
 * build queries by interpolation and still delete the entire game with one
 * link. The gate stops a stranger reaching them; it does not make what is
 * behind it sound.
 */

require_once __DIR__ . '/../../include/session.php';

if (!function_exists('mb_admin_logged_in')) {
    /**
     * @return bool
     */
    function mb_admin_logged_in()
    {
        global $userid, $mb_account_id;

        // session.php has already checked these against the account and the
        // kingdom, and blanked them if either failed.
        if ($userid === '' || !$mb_account_id) {
            return false;
        }

        // The flag is the account's, in the accounts database, not the
        // kingdom's -- an administrator stays one from round to round.
        $account = mb_account_get($mb_account_id);

        return $account && (int) $account['admin'] === 1;
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
            . "<b>Administrators only.</b><br><br>"
            . "Log into the game with an administrator's account first.<br><br>"
            . "<a href=\"../index.php\">Game login</a>"
            . "</center></body></html>";
        exit;
    }
}
