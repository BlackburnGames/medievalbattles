<?php
/**
 * Accounts: who a player is, in the `account` table beside the round's.
 *
 * One `user` row used to be both who you are -- the address, the password
 * hash, the admin flag -- and what you played this round. So a round could not
 * end without deleting the login along with the kingdom, and that is what both
 * world wipes did. docs/rounds.md splits the two: the account is its own row,
 * which outlasts the round. (It had a database of its own while each round
 * was going to be one; with every round in one database, it shares it.)
 *
 * The round is linked to the account by `user.account_id`, and holds neither
 * the address nor the password: an address can change, and an ended round
 * would keep whichever it had. The game finds a kingdom by its userid, and the
 * address and the password each exist in exactly one row, here.
 *
 * Its queries run on mb_db(), the one connection, which these functions reach
 * from any scope -- the session check calls them before connect.php is loaded.
 */

require_once __DIR__ . '/db.php';

if (!function_exists('mb_account_find')) {
    /**
     * The account with this address, or null.
     *
     * @return array|null  account_id, email, pw, admin
     */
    function mb_account_find($email)
    {
        $r = mb_query(mb_db(),
            "SELECT account_id, email, handle, pw, admin, verified_at, verify_code, created FROM account WHERE email=?", array((string) $email));
        $row = $r ? mysqli_fetch_assoc($r) : null;
        return $row ? $row : null;
    }
}

if (!function_exists('mb_account_get')) {
    /**
     * The account with this id, or null.
     *
     * @return array|null  account_id, email, pw, admin
     */
    function mb_account_get($account_id)
    {
        $r = mb_query(mb_db(),
            "SELECT account_id, email, handle, pw, admin, verified_at, verify_code, created FROM account WHERE account_id=?", array((int) $account_id));
        $row = $r ? mysqli_fetch_assoc($r) : null;
        return $row ? $row : null;
    }
}

if (!function_exists('mb_account_emails')) {
    /**
     * The addresses of these accounts, by id, for pages that list kingdoms
     * with their owners' addresses: the round holds no address of its own.
     *
     * @param  int[] $ids
     * @return array  account_id => email; an id with no account is absent
     */
    function mb_account_emails(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return array();
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $emails = array();
        $r = mb_query(mb_db(), "SELECT account_id, email FROM account WHERE account_id IN ($marks)", $ids); // mb-unbound-ok: one placeholder per id
        while ($r && ($row = mysqli_fetch_row($r))) {
            $emails[(int) $row[0]] = $row[1];
        }
        return $emails;
    }
}

if (!function_exists('mb_account_handle_taken')) {
    /** Whether another account already has this public name, ignoring case. */
    function mb_account_handle_taken($handle)
    {
        $r = mb_query(mb_db(), "SELECT 1 FROM account WHERE handle=?", array((string) $handle));
        return $r && mysqli_fetch_row($r);
    }
}

if (!function_exists('mb_account_create')) {
    /**
     * Make an account, unverified, with the code its confirmation link will
     * carry. The caller has validated the address and hashed the password;
     * signup is the only caller.
     *
     * @return int  the new account's id
     */
    function mb_account_create($email, $handle, $hash, $computer_id)
    {
        $adb = mb_db();
        mb_query($adb, "INSERT INTO account (email, handle, pw, signup_comp_id, created, verify_code) VALUES (?, ?, ?, ?, NOW(), ?)",
            array((string) $email, (string) $handle, (string) $hash, (string) $computer_id, bin2hex(random_bytes(16))));
        return (int) mysqli_insert_id($adb);
    }
}

if (!function_exists('mb_account_verify_link')) {
    /**
     * The link that confirms this account's address, as mailed at signup and
     * by activate_code.php.
     */
    function mb_account_verify_link(array $account)
    {
        require_once __DIR__ . '/mail.php';
        return mb_site_url() . '/activate_account.php?activate=true'
            . '&act_account=' . (int) $account['account_id']
            . '&act_code=' . rawurlencode($account['verify_code']);
    }
}

if (!function_exists('mb_account_verify')) {
    /**
     * Confirm an account's address, if $code is the one its link carried.
     *
     * hash_equals(), not ==: the code is hex, and == compares numeric-looking
     * strings as numbers -- the 0e... collision the old activation page and
     * the old login both had. An account already verified stays verified.
     *
     * @return bool  whether the code was right
     */
    function mb_account_verify($account_id, $code)
    {
        $account = mb_account_get($account_id);
        if (!$account || !is_string($code) || $account['verify_code'] === ''
            || !hash_equals($account['verify_code'], $code)) {
            return false;
        }
        mb_query(mb_db(), "UPDATE account SET verified_at=NOW() WHERE account_id=? AND verified_at IS NULL",
            array((int) $account_id));
        return true;
    }
}

if (!function_exists('mb_account_mark_verified')) {
    /** Verify an account by hand, for bin/admin.php -- local mail goes nowhere. */
    function mb_account_mark_verified($account_id)
    {
        mb_query(mb_db(), "UPDATE account SET verified_at=NOW() WHERE account_id=? AND verified_at IS NULL",
            array((int) $account_id));
    }
}

if (!function_exists('mb_account_set_email')) {
    function mb_account_set_email($account_id, $email)
    {
        mb_query(mb_db(), "UPDATE account SET email=? WHERE account_id=?",
            array((string) $email, (int) $account_id));
    }
}

if (!function_exists('mb_account_set_admin')) {
    function mb_account_set_admin($account_id, $admin)
    {
        mb_query(mb_db(), "UPDATE account SET admin=? WHERE account_id=?",
            array($admin ? 1 : 0, (int) $account_id));
    }
}

if (!function_exists('mb_account_delete')) {
    /**
     * Delete an account. Its kingdom is the round's to remove, and the caller
     * removes it first: include/empire.php's mb_empire_remove().
     *
     * Its round results stay, so an ended round's rankings do not develop
     * gaps, but no longer say whose they were: the kingdom's name is the
     * round's, the link to the account is the player's (docs/rounds.md).
     */
    function mb_account_delete($account_id)
    {
        mb_query(mb_db(), "UPDATE round_result SET account_id=0 WHERE account_id=?", array((int) $account_id));
        mb_query(mb_db(), "DELETE FROM account WHERE account_id=?", array((int) $account_id));
    }
}

if (!function_exists('mb_account_fingerprint')) {
    /**
     * What the session remembers of the account's address and password hash.
     *
     * include/session.php ends a session once either has changed since it
     * logged in -- that is how a password or address change logs out the
     * account's other sessions -- so it has to keep something to compare. A
     * digest rather than the values, so a session file is not a copy of the
     * one value an offline attack wants.
     *
     * @param  array  $account  a row with email and pw
     * @return string
     */
    function mb_account_fingerprint(array $account)
    {
        return hash('sha256', $account['email'] . "\0" . $account['pw']);
    }
}
