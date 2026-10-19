<?php
/**
 * Forgotten passwords: a link, mailed to the account's address, that sets a
 * new one.
 *
 * The game never had a way back from a forgotten password; an administrator
 * set a temporary one from the command line (bin/admin.php password). Now
 * forgot.php mails a link to reset.php, both shown inside index.php.
 *
 * The link carries a row's id and a random token. The row holds only the
 * token's SHA-256, so the table is not a list of working links; the token is
 * 128 bits, so a fast unsalted digest is enough. A link is good for
 * MB_RESET_TTL minutes and once, and only while the account's address and
 * password are the ones it was issued against -- the row keeps the account's
 * fingerprint (include/accounts.php), so changing either, or using any of the
 * account's links, voids every other one.
 *
 * Requests are limited per account and per IP, so the page cannot be used to
 * flood someone's mailbox. forgot.php says the same thing whether the address
 * has an account, was refused, or was mailed, so it tells nobody which
 * addresses play.
 *
 * The link is built from mb_site_url(). Without MB_SITE_URL that is the
 * request's Host header, which the requester chooses -- set it on any install
 * that sends real mail (docs/deployment.md).
 */

require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/password.php';

if (!defined('MB_RESET_TTL')) {
    /** Minutes a reset link works for. */
    define('MB_RESET_TTL', 60);
    /** Links one account may be sent per hour. */
    define('MB_RESET_MAX_ACCOUNT', 3);
    /** Links one IP may have sent per hour, across accounts. */
    define('MB_RESET_MAX_IP', 10);
}

if (!function_exists('mb_password_reset_issue')) {
    /**
     * Make a reset link for this account, unless it or this IP has asked for
     * too many lately.
     *
     * @param  array  $account  a row from mb_account_find()
     * @return string|null  the link to mail, or null if refused
     */
    function mb_password_reset_issue(array $account, $ip)
    {
        $db = mb_db();
        mb_query($db, "DELETE FROM password_reset WHERE created < NOW() - INTERVAL 1 DAY");

        $by_account = mysqli_fetch_row(mb_query($db,
            "SELECT count(*) FROM password_reset WHERE account_id=? AND created > NOW() - INTERVAL 1 HOUR",
            array((int) $account['account_id'])));
        $by_ip = mysqli_fetch_row(mb_query($db,
            "SELECT count(*) FROM password_reset WHERE ip=? AND created > NOW() - INTERVAL 1 HOUR",
            array((string) $ip)));
        if ((int) $by_account[0] >= MB_RESET_MAX_ACCOUNT || (int) $by_ip[0] >= MB_RESET_MAX_IP) {
            return null;
        }

        $token = bin2hex(random_bytes(16));
        mb_query($db, "INSERT INTO password_reset (account_id, token_hash, fp, ip, created, expires)
            VALUES (?, ?, ?, ?, NOW(), NOW() + INTERVAL ? MINUTE)",
            array((int) $account['account_id'], hash('sha256', $token), mb_account_fingerprint($account),
                substr((string) $ip, 0, 45), (int) MB_RESET_TTL));
        $reset_id = (int) mysqli_insert_id($db);

        require_once __DIR__ . '/mail.php';
        return mb_site_url() . '/index.php?page=reset&id=' . $reset_id . '&token=' . $token;
    }
}

if (!function_exists('mb_password_reset_find')) {
    /**
     * The account a link resets, if it is one that still works.
     *
     * @return array|null  the account row
     */
    function mb_password_reset_find($reset_id, $token)
    {
        if (!is_string($token) || !preg_match('/^[0-9a-f]{32}$/', $token)) {
            return null;
        }
        $r = mb_query(mb_db(), "SELECT account_id, token_hash, fp FROM password_reset WHERE reset_id=? AND expires > NOW()",
            array((int) $reset_id));
        $row = $r ? mysqli_fetch_assoc($r) : null;
        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $token))) {
            return null;
        }
        $account = mb_account_get($row['account_id']);
        if (!$account || !hash_equals(mb_account_fingerprint($account), $row['fp'])) {
            return null;
        }
        return $account;
    }
}

if (!function_exists('mb_password_reset_complete')) {
    /**
     * Set the account's new password, and void every link it has.
     *
     * The new hash changes the account's fingerprint, so include/session.php
     * ends every session it had -- whoever knew the old password is logged
     * out. Following the link proved the address, so an unverified account is
     * verified, and the address's failed logins no longer count against it.
     */
    function mb_password_reset_complete(array $account, $plain)
    {
        $db = mb_db();
        mb_password_store($account['account_id'], mb_password_hash($plain));
        mb_query($db, "DELETE FROM password_reset WHERE account_id=?", array((int) $account['account_id']));
        mb_account_mark_verified($account['account_id']);
        require_once __DIR__ . '/throttle.php';
        mb_login_succeeded($db, $account['email']);
    }
}
