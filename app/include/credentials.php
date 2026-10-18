<?php
/**
 * What an email address, an empire name and a guild name are allowed to be.
 *
 * This began with one entry on tests/sql-injection.txt that could not be fixed
 * where it was reported. preferences.php let a player choose a new address,
 * wrote it to six tables and assigned it to $email -- and $email, with $pw
 * beside it, was the credential some 400 queries filtered on, interpolated:
 *
 *     ... WHERE email='$email' AND pw='$pw'
 *
 * Escaping 400 sites in every file was not the move, so the boundary moved
 * instead: if the value could only ever be something with no SQL meaning,
 * every one of those interpolations was safe by construction. checksignup.php,
 * preferences.php and include/session.php all held addresses to this rule.
 *
 * None of that is the reason any more. Every query binds its values through
 * mb_query(), and the round no longer holds an address at all: a kingdom is
 * found by its userid, and the address lives once, on the account
 * (include/accounts.php). The rule is now about what an address must be to be
 * stored and mailed, and it still has a cost worth stating -- see
 * mb_valid_email().
 *
 * The empire and guild names below are still interpolation-era rules too, and
 * still worth having: both are read back and echoed on every page.
 */

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
