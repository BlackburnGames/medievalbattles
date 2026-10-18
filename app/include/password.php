<?php
/**
 * Password hashing.
 *
 * The 2003 code stored md5($pw) -- unsalted, unstretched, and denormalized into
 * six tables beside the email address that identifies the row -- and the
 * digest did two jobs. It protected the account, and it was the bearer token:
 * the session carried it and roughly 1300 queries filtered on
 *
 *     ... WHERE email=? AND pw=?
 *
 * Both jobs have moved. The hash protects the account, and lives in one row of
 * the accounts database (include/accounts.php). The token is a kingdom key the
 * round holds instead (include/kingdom.php), so those 1300 queries are
 * untouched and none of them ever sees a password hash.
 *
 * password_hash() salts per row and costs enough to make a stolen table
 * expensive rather than free. PASSWORD_BCRYPT rather than PASSWORD_DEFAULT:
 * the pin was load-bearing while the hash was interpolated into queries and
 * include/credentials.php vouched for its alphabet. It is only ever bound now,
 * so moving to a newer algorithm is a decision about cost and compatibility,
 * not about injection -- but it is a decision, and PASSWORD_DEFAULT would make
 * it silently on a PHP upgrade.
 *
 * Legacy rows still verify. There is exactly one corpus of this game's data and
 * it is not this repo's fixture, so a login that stopped working for every
 * existing account would be a behaviour change nothing here could catch. An md5
 * account is accepted once and rehashed in place on the way through.
 */

// mb_accounts_db(), for mb_password_store().
require_once __DIR__ . '/accounts.php';

if (!function_exists('mb_password_hash')) {
    /**
     * @param  mixed  $plain the password as typed
     * @return string a 60-character bcrypt hash
     */
    function mb_password_hash($plain)
    {
        return password_hash((string) $plain, PASSWORD_BCRYPT);
    }
}

if (!function_exists('mb_password_is_legacy')) {
    /**
     * Is this one of the unsalted md5 digests the game used to store?
     *
     * @param  mixed $stored
     * @return bool
     */
    function mb_password_is_legacy($stored)
    {
        return is_string($stored) && preg_match('/^[0-9a-f]{32}$/i', $stored) === 1;
    }
}

if (!function_exists('mb_password_verify')) {
    /**
     * Does $plain match the hash stored for this account?
     *
     * hash_equals() on the legacy arm rather than ==, for two reasons. The
     * obvious one is timing. The less obvious one is that PHP's == compares two
     * numeric-looking strings as numbers, and a 32-character hex digest is
     * numeric-looking whenever it happens to contain no letters -- "0e" followed
     * by 30 digits is 0 in float, and so is every other digest of that shape.
     * The == in checklogin.php was that comparison.
     *
     * @param  mixed $plain
     * @param  mixed $stored the hash as it is in the database
     * @return bool
     */
    function mb_password_verify($plain, $stored)
    {
        if (!is_string($stored) || $stored === '') {
            return false;
        }
        if (mb_password_is_legacy($stored)) {
            return hash_equals(strtolower($stored), md5((string) $plain));
        }
        return password_verify((string) $plain, $stored);
    }
}

if (!function_exists('mb_password_needs_upgrade')) {
    /**
     * Should this account's stored hash be replaced after a successful login?
     *
     * True for the md5 rows, and true again if the bcrypt cost is ever raised.
     *
     * @param  mixed $stored
     * @return bool
     */
    function mb_password_needs_upgrade($stored)
    {
        if (!is_string($stored) || $stored === '') {
            return false;
        }
        if (mb_password_is_legacy($stored)) {
            return true;
        }
        return password_needs_rehash($stored, PASSWORD_BCRYPT);
    }
}

if (!function_exists('mb_password_store')) {
    /**
     * Write a new hash to the account.
     *
     * One UPDATE. It used to be six -- `pw` was denormalized across user,
     * buildings, military, research, returntbl and explore, and a table left
     * holding the old hash was a page that silently stopped matching -- and
     * the list lived here so that preferences.php and the login upgrade could
     * not disagree about it. The round's six columns hold the kingdom key now,
     * which a password change does not touch.
     *
     * The session that made the change has to carry the new hash's
     * fingerprint, or include/session.php ends it with the others:
     * mb_session_rotate().
     *
     * @param  int   $account_id
     * @param  mixed $newHash
     * @return void
     */
    function mb_password_store($account_id, $newHash)
    {
        mb_query(mb_accounts_db(), "UPDATE account SET pw=? WHERE account_id=?",
            array((string) $newHash, (int) $account_id));
    }
}
