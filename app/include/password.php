<?php
/**
 * Password hashing.
 *
 * The 2003 code stored md5($pw) -- unsalted, unstretched, and denormalized into
 * six tables beside the email address that identifies the row. Two properties
 * of that are worth separating, because only one of them is being changed here.
 *
 * **The digest is also a bearer token, and it stays one.** Roughly 1300 queries
 * in this codebase filter on
 *
 *     ... WHERE email='$email' AND pw='$pw'
 *
 * where $pw is the stored hash, carried in the session. That is the app's whole
 * notion of "who is asking", and rewriting it is a different change of a
 * different size -- it touches every file. What matters for this one is that the
 * property those queries rely on is *equality against the stored string*, which
 * a bcrypt hash satisfies exactly as an md5 digest does. Nothing above this file
 * has to know which it is holding.
 *
 * **The digest is what protects the account, and that is what changes.** An
 * unsalted md5 of a human-chosen password is recoverable from a rainbow table in
 * the time it takes to type the query. password_hash() salts per row and costs
 * enough to make a stolen table expensive rather than free.
 *
 * PASSWORD_BCRYPT rather than PASSWORD_DEFAULT, deliberately. The hash is
 * interpolated bare into those 1300 queries and validated on the way out of the
 * session by include/credentials.php, so its *alphabet* is load-bearing: bcrypt
 * emits only [./A-Za-z0-9$], none of which carries meaning inside a SQL string
 * literal. PASSWORD_DEFAULT is bcrypt today and is documented to change, and the
 * argon2 formats it may change to contain `=`, `,` and `+`. Pinning the
 * algorithm keeps the guarantee that credentials.php is asserting rather than
 * making it depend on which PHP the app happens to be running under.
 *
 * Legacy rows still verify. There is exactly one corpus of this game's data and
 * it is not this repo's fixture, so a login that stopped working for every
 * existing account would be a behaviour change nothing here could catch. An md5
 * row is accepted once and rehashed in place on the way through -- see
 * mb_password_store(), which is the same six UPDATEs preferences.php has always
 * run to change a password.
 */

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
     * Write a new hash to all six tables that carry one.
     *
     * `pw` is denormalized across user, buildings, military, research,
     * returntbl and explore, and every page's queries name it beside the email
     * -- so a row left holding the old hash is a page that silently stops
     * matching. preferences.php has always run these six statements; they live
     * here now because the login upgrade path has to run exactly the same set,
     * and two copies of a list like this is how one of them ends up short.
     *
     * The old hash is part of the WHERE for the same reason it is part of every
     * other query in the game: it is the credential. Both values are escaped
     * even so -- the caller has usually validated them, but a helper that is
     * safe only when its caller was careful is not safe.
     *
     * @param  mysqli $db
     * @param  mixed  $email
     * @param  mixed  $oldHash the hash currently stored
     * @param  mixed  $newHash
     * @return void
     */
    function mb_password_store($db, $email, $oldHash, $newHash)
    {
        $where = " WHERE email=" . mb_sql_str($db, $email)
               . " AND pw=" . mb_sql_str($db, $oldHash);
        $set = " SET pw=" . mb_sql_str($db, $newHash);

        foreach (array('user', 'buildings', 'military', 'research', 'returntbl', 'explore') as $table) {
            mysqli_query($db, "UPDATE $table" . $set . $where);
        }
    }
}
