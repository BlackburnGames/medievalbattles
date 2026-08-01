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

if (!function_exists('mb_rich')) {
    /**
     * A forum post or message body, rendered.
     *
     * The bodies are stored as **exactly what the player typed** -- no tags, no
     * entities, no <br>. Everything below happens at render time, which is the
     * whole point: the database holds text, and only this function decides what
     * that text looks like as markup.
     *
     * It did not use to. common.php and commong.php ran the body through
     * nl2br(strip_tags($message, "<i>,<b>")) on the way in, so the stored value
     * was HTML -- which is why the pages rendering it could not simply escape
     * it, and why an allowed tag kept its attributes and <i onmouseover=...>
     * passed through whole.
     *
     * The forum has always allowed two tags and still does, written [b] and
     * [i]. Brackets rather than angle brackets for one reason that matters:
     * mb_h() runs first, so a player who types <b> gets &lt;b&gt; and sees it
     * as text, while [ and ] are not HTML metacharacters and survive escaping
     * untouched. The markup cannot be forged, because forging it would require
     * emitting a character the escaper has already dealt with.
     *
     * Order is escape, then break, then tags. nl2br() after mb_h() is safe --
     * it only inserts <br />, it does not interpret anything -- and running the
     * tag pass last means it can only ever match the literal bracket sequences.
     *
     * @param mixed $value
     * @return string
     */
    function mb_rich($value)
    {
        $out = nl2br(mb_h($value), false);
        return preg_replace('~\[(/?)(i|b)\]~i', '<$1$2>', $out);
    }
}

if (!function_exists('mb_safe_url')) {
    /**
     * A player-supplied URL, or '' if it is not one we will link to.
     *
     * Two of these exist -- the settlement picture and the guild flag -- and
     * both are echoed into an href as well as an img src. mb_attr() stops the
     * value breaking out of the attribute, but it does not stop the attribute
     * meaning something dangerous: href="javascript:alert(1)" is a perfectly
     * well-formed attribute and it runs.
     *
     * So the scheme is checked, which is the part that decides. An empty value
     * is legal -- both fields are optional and the pages test for "" already.
     *
     * S_SINFOS.php used to guard these with ninety str_replace() calls
     * stripping ".exe", ".php", "onmouseover" and so on out of the middle of
     * the string. It could not have worked: the XSS clauses ran *after*
     * htmlspecialchars(), so they were looking for "<script>" in a string where
     * it had already become "&lt;script&gt;". What they did do was mangle
     * legitimate input -- a settlement named "Wheightsville" came out
     * "Wsville", because "height" is on the list.
     *
     * @param mixed $value
     * @return string the URL, or ''
     */
    function mb_safe_url($value)
    {
        $value = trim((string) $value);
        return preg_match('~^https?://~i', $value) === 1 ? $value : '';
    }
}

if (!function_exists('mb_sl_badge')) {
    /**
     * The "(SL)" a settlement leader's name carries on the forums.
     *
     * It used to be stored. input.posts.php and sl-input.posts.php appended
     * "<font class=red>(SL)</font>" to the poster's name before the INSERT, so
     * the decoration lived in the `name` column and in `lastposter` beside it.
     *
     * That is markup in the database, and it froze the badge at post time -- a
     * leader who was voted out kept it on every old post forever. The forum
     * index was reaching for the render-time version already: sforum.php and
     * sl-forum.php both echo "{$r['name']}$S_L", and $S_L is assigned nowhere
     * in the codebase, so the badge never appeared there at all. Both entries
     * are on tests/register-globals.txt as dead reads.
     *
     * So this is $S_L, finally written. The caller looks up who leads the
     * settlement once, before its loop, and asks per row.
     *
     * @param mixed $name    the poster's name, as stored
     * @param mixed $leader  ename of the settlement's current leader, or ''
     * @return string markup, or '' -- already safe, it is a constant
     */
    function mb_sl_badge($name, $leader)
    {
        if ((string) $leader === '' || (string) $name !== (string) $leader) {
            return '';
        }
        return '<font class=red>(SL)</font>';
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
