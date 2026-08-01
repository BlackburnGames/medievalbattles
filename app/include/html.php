<?php
/**
 * Output escaping helpers.
 *
 * The counterpart to db.php. Nothing in the 2003 sources escapes anything on
 * the way out -- there is no helper to call, and the pages are one long
 * `echo "<html> ... $var ... "` per branch, so a value that reaches output
 * reaches it as markup.
 *
 * Pulled in by connect.php for the same reason db.php is: every page that
 * renders anything reaches it, directly or through igtop.php, and adding an
 * include line at several hundred call sites would be its own source of bugs.
 */

if (!function_exists('mb_h')) {
    /**
     * A value, escaped for HTML element text or a quoted attribute.
     *
     * ENT_QUOTES rather than the default, which leaves `'` alone. This codebase
     * writes single-quoted attributes as readily as double-quoted ones, and a
     * helper whose safety depends on which quote the caller happened to use is
     * a helper that will eventually be used with the other one.
     *
     * The explicit charset matters on 5.6, where the default is ISO-8859-1 and
     * multibyte input is silently dropped rather than escaped. The reference
     * image still builds, so it still has to be right there.
     *
     * @param mixed $value cast to string, so null escapes to '' rather than
     *                     tripping PHP 8.1's deprecation
     * @return string
     */
    function mb_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mb_post_html')) {
    /**
     * A forum post or message body, safe to store and echo as markup.
     *
     * The forum has always allowed two tags. common.php and commong.php ran the
     * body through
     *
     *     strip_tags($message, "<i>,<b>")
     *
     * on the way in, which is why the stored value is HTML and why the pages
     * that render it cannot simply escape it -- doing so would show every post's
     * line breaks as a literal <br>.
     *
     * strip_tags() is the wrong tool for the allow-list, though: an allowed tag
     * keeps its attributes, so <i onmouseover=alert(1)> passes through whole.
     * Escaping everything and then restoring exactly the two bare tags gets the
     * same feature with no hole, because the restore matches the escaped form
     * and an attribute makes it not match.
     *
     * Two visible differences, both in the safe direction. A disallowed tag used
     * to vanish and is now shown as text, and <i class=x> used to be italic and
     * is now shown as text. Neither can happen to a post that was not either an
     * attack or malformed.
     *
     * @param mixed $value
     * @return string
     */
    function mb_post_html($value)
    {
        return preg_replace('~&lt;(/?)(i|b)&gt;~i', '<$1$2>', mb_h($value));
    }
}

if (!function_exists('mb_attr')) {
    /**
     * An attribute value, escaped AND quoted, ready to interpolate.
     *
     * The quotes are part of what this returns, exactly as with mb_sql_str(),
     * and for the same reason: an unquoted attribute is the one case where
     * escaping alone does not close the hole. This codebase writes almost every
     * attribute bare --
     *
     *     <font class=$color>   <a href=topic.php?tid=$tid>
     *
     * -- and a bare attribute value ends at the first space, so
     * `x onerror=alert(1)` breaks out carrying no character htmlspecialchars()
     * would touch. The value has to be quoted before escaping it means
     * anything, so the natural conversion lifts the whole attribute value out:
     *
     *     "<font class=" . mb_attr($color) . ">"
     *
     * @param mixed $value
     * @return string including the surrounding double quotes
     */
    function mb_attr($value)
    {
        return '"' . mb_h($value) . '"';
    }
}
