<?php
/**
 * Explicit request input, replacing register_globals.
 *
 * The compat layer injects every $_GET/$_POST/$_COOKIE key into the global
 * scope, which is the vulnerability the setting was removed for: any global
 * the code has not assigned yet can be set by the caller. mb_input() reads the
 * first two of them in the same order, but only for a name the page asks for
 * by hand, so nothing else in scope can be overwritten from outside.
 *
 * Two details make it a drop-in replacement:
 *
 * 1. GP precedence. The shim loops $_GET, then $_POST, assigning as it
 *    goes, so a later source wins -- post beats get, as PHP 4's
 *    variables_order did. Cookies came last and beat both, and no longer
 *    count: no page wants a parameter from a cookie, and a cookie is one
 *    thing a sibling site on a shared domain (*.sg-host.com) can set. A
 *    cookie named update and one named newemail would have changed the
 *    next player to open preferences.php.
 *
 * 2. The default is null, NOT "". Under register_globals a parameter that was
 *    not sent left the variable *unset*, and this codebase branches on
 *    IsSet($update) / IsSet($delete) in dozens of places. isset() is false for
 *    a null just as it is for an unset name, so null keeps every one of those
 *    branches behaving exactly as it did. Defaulting to "" would make every
 *    IsSet() true and fire every handler on every request.
 *
 * Note most of the game's forms are <form type=post>, and `type` is not a form
 * attribute -- they submit as GET. So do not assume a handler's input arrives
 * in $_POST just because it came from a form; read it with mb_input() unless
 * you have checked (index.php's login form is a genuine method=post).
 *
 * Nothing here escapes or validates. Queries are still built by interpolation
 * and output is still unescaped; fixing that is Phase 3.
 */

if (!function_exists('mb_input')) {
    /**
     * @param  string $name    request parameter name
     * @param  mixed  $default returned when the parameter was not sent
     * @return mixed  the raw value, or $default (null) when absent
     */
    function mb_input($name, $default = null)
    {
        if (array_key_exists($name, $_GET)) {
            $default = $_GET[$name];
        }
        if (array_key_exists($name, $_POST)) {
            $default = $_POST[$name];
        }
        return $default;
    }
}

if (!function_exists('mb_count')) {
    /**
     * A number of things typed into an order form, as a whole number: the
     * thousands separators stripped, and a box left blank counting as none.
     *
     * PHP 5 read a blank box as 0 wherever it met a number. PHP 8 does not:
     * `"" + ""` throws, and `"" < 0` is true -- it compares the strings "" and
     * "0" -- so every "you can't send negative or 0" check refused any order
     * that left a box empty, which is nearly every order a form sends.
     *
     * @param  mixed $value the raw request value, null when it was not sent
     * @return int
     */
    function mb_count($value)
    {
        return (int) str_replace(',', '', (string) $value);
    }
}
