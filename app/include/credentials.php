<?php
/**
 * What an email address and a password hash are allowed to be.
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
 * The password half costs nothing, and the reason is worth stating rather than
 * assuming. The app stores what include/password.php produces, which is bcrypt
 * -- pinned to PASSWORD_BCRYPT rather than PASSWORD_DEFAULT precisely so that
 * its alphabet is known here. Bcrypt emits `$2y$`, a two-digit cost and 53
 * characters of [./A-Za-z0-9], and none of that carries meaning inside a SQL
 * string literal. The 32-character hex digest is the legacy md5 form, still
 * accepted because a row that predates the change has to be able to log in
 * once so that checklogin.php can rehash it.
 *
 * The address half does cost something, and it is worth being explicit about
 * it. An apostrophe is legal in the local part of a real address, and
 * o'brien@example.com is rejected here. That is a genuine loss. It is the
 * cheaper side of the trade against a value that reaches every query in the
 * game unescaped, and it stops being necessary the day those queries bind
 * their parameters instead of interpolating them -- at which point this file
 * can relax to FILTER_VALIDATE_EMAIL alone.
 */

if (!function_exists('mb_valid_pw_hash')) {
    /**
     * @param  mixed $value
     * @return bool  true for a bcrypt hash, or for the legacy md5 digest
     */
    function mb_valid_pw_hash($value)
    {
        if (!is_string($value)) {
            return false;
        }
        // Bcrypt, as include/password.php produces it: $2y$ (or the $2a$/$2b$
        // an older PHP wrote), a two-digit cost, and 53 characters of salt and
        // digest from bcrypt's own alphabet. Nothing outside [./A-Za-z0-9$],
        // which is the property the 1300 interpolations depend on.
        if (preg_match('~^\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}$~', $value) === 1) {
            return true;
        }
        return preg_match('/^[0-9a-f]{32}$/i', $value) === 1;
    }
}

if (!function_exists('mb_valid_email')) {
    /**
     * @param  mixed $value
     * @return bool
     */
    function mb_valid_email($value)
    {
        if (!is_string($value) || $value === '' || strlen($value) > 255) {
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
