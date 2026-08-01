<?php
/**
 * Explicit request input, replacing register_globals.
 *
 * The compat layer injects every $_GET/$_POST/$_COOKIE key into the global
 * scope, which is the vulnerability the setting was removed for: any global
 * the code has not assigned yet can be set by the caller. mb_input() reads the
 * same three arrays in the same order, but only for a name the page asks for
 * by hand, so nothing else in scope can be overwritten from outside.
 *
 * Two details make it a drop-in replacement:
 *
 * 1. GPC precedence. The shim loops $_GET, then $_POST, then $_COOKIE,
 *    assigning as it goes, so a later source wins -- cookie beats post beats
 *    get, matching PHP 4's variables_order default. Reproduced here.
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
        if (array_key_exists($name, $_COOKIE)) {
            $default = $_COOKIE[$name];
        }
        return $default;
    }
}
