<?php
/**
 * What an email address, a kingdom key and an empire name are allowed to be.
 *
 * This exists because of one entry on tests/sql-injection.txt that could not be
 * fixed where it was reported. preferences.php lets a player choose a new
 * address, writes it to six tables and assigns it to $email -- and $email, with
 * $pw beside it, is the credential that roughly 1300 queries in this codebase
 * filter on:
 *
 *     ... WHERE email='$email' AND pw='$pw'
 *
 * Escaping the call sites is the fix everywhere else in Phase 3 and is not
 * available here: there are 1300 of them, in every file, and the address gets
 * there by way of the session rather than by way of the page that reads it, so
 * the per-file audit cannot even see most of them. checksignup.php makes it
 * worse -- its address validation has been commented out since 2003 (line 52),
 * so any string at all could be stored and then interpolated on every page the
 * account ever loads.
 *
 * So the boundary moves instead of the call sites. If the two values can only
 * ever be things that carry no SQL meaning, then all 1300 interpolations are
 * safe by construction and stay safe as they are rewritten. Three places agree
 * on the rule:
 *
 *   - checksignup.php, so a hostile address is never stored;
 *   - preferences.php, so it cannot be changed into one later;
 *   - include/session.php, so a row that predates this file cannot log in and
 *     put one back into scope.
 *
 * The other half of the credential is not the password hash any more. The hash
 * is the account's, in the accounts database (include/accounts.php), and is
 * only ever bound. What the session carries as $pw, and what the round's six
 * `pw` columns hold, is a kingdom key: 64 lowercase hex characters from
 * include/kingdom.php's mb_kingdom_key(). That is a stricter rule than the one
 * it replaces, which had to admit both bcrypt's alphabet and the legacy md5.
 *
 * The address half does cost something, and it is worth being explicit about
 * it. An apostrophe is legal in the local part of a real address, and
 * o'brien@example.com is rejected here. That is a genuine loss. It is the
 * cheaper side of the trade against a value that reaches every query in the
 * game unescaped, and it stops being necessary the day those queries bind
 * their parameters instead of interpolating them -- at which point this file
 * can relax to FILTER_VALIDATE_EMAIL alone.
 */

if (!function_exists('mb_valid_kingdom_key')) {
    /**
     * @param  mixed $value
     * @return bool  true for a key as mb_kingdom_key() makes one
     */
    function mb_valid_kingdom_key($value)
    {
        return is_string($value) && preg_match('/^[0-9a-f]{64}$/', $value) === 1;
    }
}

if (!function_exists('mb_valid_email')) {
    /**
     * @param  mixed $value
     * @return bool
     */
    function mb_valid_email($value)
    {
        // 50, not the 254 an address may run to: every table that holds one
        // is varchar(50), and with this server's non-strict sql_mode a longer
        // address was stored cut short without a word -- so the account it
        // made could never log in, because nothing matched what it typed.
        if (!is_string($value) || $value === '' || strlen($value) > 50) {
            return false;
        }

        // A positive charset, not a blacklist of the characters that happen to
        // matter to MySQL today. Everything outside it -- quote, backslash,
        // backtick, NUL, newline, anything above ASCII -- is refused, so this
        // does not have to be revisited when the next sink is HTML or a header
        // rather than a query.
        if (preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $value) !== 1) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('mb_valid_gname')) {
    /**
     * A guild name travels like an empire name, for the same reason: it is
     * copied into every member's `user.guild`, read back as $empireguild on
     * every page, and interpolated bare -- "WHERE guild='$empireguild'",
     * "WHERE gname='$GN'" -- at eighteen sites, three of them writes. gc.php
     * accepted any fifteen characters, so a guild called `a' OR 'a'='a` was
     * one leader away from guildconfig.php's "UPDATE user SET guild='None'
     * WHERE guild='$GN'" emptying every guild in the game. That was driven by
     * hand, and it did.
     *
     * gc.php is the only writer -- no page renames a guild -- so one check
     * holds it. The sites are escaped as well, so nothing leans on this alone.
     *
     * @param  mixed $value
     * @return bool
     */
    function mb_valid_gname($value)
    {
        return is_string($value)
            && preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9 ._\-]{0,13}[A-Za-z0-9._\-])?$/', $value) === 1;
    }
}

if (!function_exists('mb_valid_ename')) {
    /**
     * An empire name is not a credential, but it travels like one. It is read
     * back out of `user` on every page and interpolated bare -- forty-odd
     * "WHERE ename='$ename'" sites, the votes in govt.php and attack.php, the
     * login bookkeeping in checklogin.php -- and nothing escapes it there.
     *
     * Until checksignup.php escaped its INSERT, a name containing a quote or a
     * backslash broke that statement, so no such name could ever be stored and
     * those sites were safe by accident. Escaping the INSERT removes the
     * accident, so the rule has to be stated instead. Signup is the only writer:
     * nothing in the game renames an empire.
     *
     * A positive charset for the same reason as the address above, and 25 to
     * match the form's maxlength.
     *
     * @param  mixed $value
     * @return bool
     */
    function mb_valid_ename($value)
    {
        return is_string($value)
            && preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9 ._\-]{0,23}[A-Za-z0-9._\-])?$/', $value) === 1;
    }
}
