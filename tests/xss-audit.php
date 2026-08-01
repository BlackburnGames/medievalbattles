<?php
/**
 * Unescaped output audit -- the checklist for the second security item of
 * Phase 3.
 *
 * Every template in this codebase echoes its values raw. There is no escaping
 * helper anywhere in the 2003 sources, and the pages are one long
 * `echo "<html>...$var..."` per branch, so a value that reaches output reaches
 * it as markup. This script finds the reachable half of that: request input
 * that survives into an `echo` or a `print`.
 *
 * Method and limits are the SQL audit's, and the taint tracker is literally the
 * same one -- see tests/taint-lib.php. Sources are mb_input() and the request
 * superglobals; taint spreads through assignment to a fixpoint; a value stops
 * being tainted when it passes through a sanitizer or an arithmetic operator.
 *
 * Each finding records the HTML context the value lands in, because as with the
 * quoting context in a query, it decides the fix:
 *
 *   text         ...>$x<...              htmlspecialchars()
 *   attr-quoted  <a href="$x">           htmlspecialchars() with ENT_QUOTES --
 *                                          the default leaves single quotes
 *   attr-bare    <font class=$x>         htmlspecialchars() is NOT enough. A
 *                                          bare attribute ends at the first
 *                                          space, so `x onerror=alert(1)` needs
 *                                          no metacharacter at all. The
 *                                          attribute has to be quoted first,
 *                                          and this codebase writes almost
 *                                          every attribute bare.
 *
 * Three limits, the first two shared with the SQL audit:
 *
 *   - First-order only. The stored XSS -- a player's empire name, a forum post,
 *     a message -- is read back out of the database and this audit cannot see
 *     it. It is the larger half of the problem and the more dangerous one, so
 *     do not read a zero here as "output is safe".
 *   - Per-file. A parameter read in one file and echoed in another is invisible.
 *   - Sink-only. It reports the variable and the worst context it appears in,
 *     not each site, so that fixing one call site of two does not read as
 *     progress.
 *
 * Baselined in tests/xss.txt, and the baseline may only ever SHRINK.
 *
 *   php tests/xss-audit.php             # check against baseline
 *   php tests/xss-audit.php --accept    # rewrite the baseline
 */

$repoRoot = dirname(__DIR__);
$appRoot  = $repoRoot . '/app';
$accept   = in_array('--accept', $argv, true);
$stored   = in_array('--stored', $argv, true);
$baseline = __DIR__ . ($stored ? '/xss-stored.txt' : '/xss.txt');

require_once __DIR__ . '/taint-lib.php';

/**
 * --stored takes a row read back out of the database as a source too.
 *
 * Everything in this database was typed by a player at some point, so a value
 * fetched and echoed is request input that has been round-tripped -- and that
 * is the larger half of the problem, because the default list cannot see any
 * of it. The two are separate baselines rather than one, so that the
 * first-order list keeps meaning what docs/testing.md says it means: request
 * input, at zero, and staying there.
 */
$MB_XSS_CALLS = $stored ? $MB_STORED_CALLS : $MB_SOURCE_CALLS;

/**
 * Everything that is safe to interpolate into a query is safe in HTML too --
 * a number or a hex digest cannot carry markup -- so this extends that list
 * rather than replacing it.
 *
 * strip_tags() is deliberately NOT here. common.php runs the forum message
 * through it, but it is called as strip_tags($message, "<i>,<b>"), and an
 * allowed tag keeps its attributes: <i onmouseover=...> survives intact.
 * nl2br() is not here either, for the obvious reason.
 *
 * mb_news_html() is, because it is mb_rich() on the text and a whitelist on the
 * category -- an unknown class falls back rather than reaching the attribute --
 * so neither of its two arguments can leave it as markup. It is the only
 * renderer in the app with that property, which is the point of it existing.
 */
$MB_HTML_SANITIZERS = array_merge($MB_SANITIZERS, array(
    'htmlspecialchars', 'htmlentities', 'urlencode', 'rawurlencode',
    'number_format', 'date', 'mb_h', 'mb_attr', 'mb_rich', 'mb_news_html',
));

/** How bad each context is, worst last. */
$MB_HTML_CONTEXTS = array('text' => 0, 'attr-quoted' => 1, 'attr-bare' => 2);

/**
 * Which HTML context the literal text emitted so far leaves us in.
 *
 * Crude on purpose, and in the safe direction. It looks at the last unclosed
 * `<`: if there is none we are in element text, and if there is one we are
 * inside a tag, where an odd number of quotes since that `<` means the value
 * lands inside an attribute value and anything else means it does not.
 */
function mb_html_context($text)
{
    $lt = strrpos($text, '<');
    $gt = strrpos($text, '>');
    if ($lt === false || ($gt !== false && $gt > $lt)) {
        return 'text';
    }
    $tag = substr($text, $lt);
    if (substr_count($tag, '"') % 2 === 1 || substr_count($tag, "'") % 2 === 1) {
        return 'attr-quoted';
    }
    // Inside a tag but not inside quotes: a bare attribute value, an attribute
    // name, or the element name itself. All three are unquoted markup.
    return 'attr-bare';
}

/**
 * Tainted variables that reach an echo or a print, and the worst HTML context
 * each lands in.
 *
 * @return array<string,string>
 */
function mb_scan_output($src, array $tainted, array $sourceGlobals, array $sanitizers, array $rank)
{
    $t = token_get_all($src);
    $n = count($t);
    $found = array();

    // Literal markup emitted so far, accumulated across the whole file rather
    // than reset per statement, because the tag a value lands in is routinely
    // opened somewhere else. topic.php writes an `<input ... value="` in inline
    // HTML, then a short-tag echo of $topic, then closes the quote and the tag
    // -- so the echo statement carries no literal text of its own. Scored per
    // statement that reads as element text, which is the mild answer and the
    // wrong one.
    //
    // Only the tail matters -- mb_html_context() looks at the last unclosed
    // `<` -- so this cannot drift far, and where control flow makes it wrong it
    // is wrong about which flavour of markup, never about whether the value is
    // escaped.
    $doc   = '';
    $start = -1;   // token index of the enclosing echo/print, or -1 outside one
    $end   = -1;

    for ($i = 0; $i < $n; $i++) {
        $x = $t[$i];
        if (!is_array($x)) {
            continue;
        }

        if ($x[0] === T_INLINE_HTML) {
            $doc .= $x[1];
            continue;
        }
        if (in_array($x[0], array(T_ECHO, T_PRINT, T_OPEN_TAG_WITH_ECHO), true)) {
            $start = $i;
            $end   = mb_stmt_end($t, $i);
            continue;
        }
        if ($x[0] === T_ENCAPSED_AND_WHITESPACE) {
            $doc .= $x[1];
            continue;
        }
        if ($x[0] === T_CONSTANT_ENCAPSED_STRING) {
            $doc .= substr($x[1], 1, -1);
            continue;
        }

        // Only a variable inside an echo/print statement is output.
        if ($x[0] !== T_VARIABLE || $i > $end) {
            continue;
        }

        $name = substr($x[1], 1);
        if (!isset($tainted[$name]) && !in_array($name, $sourceGlobals, true)) {
            continue;
        }
        if (mb_is_arithmetic($t, $i) || mb_is_sanitized($t, $i, $start, $sanitizers)) {
            continue;
        }

        $context = mb_html_context($doc);
        if (!isset($found[$name]) || $rank[$context] > $rank[$found[$name]]) {
            $found[$name] = $context;
        }
    }

    return $found;
}

$current = array();
foreach (mb_app_files($appRoot) as $path) {
    $rel = ltrim(str_replace(str_replace('\\', '/', $repoRoot), '', $path), '/');
    $src = file_get_contents($path);
    $tainted = mb_tainted_names($src, $MB_SOURCE_GLOBALS, $MB_HTML_SANITIZERS, $MB_XSS_CALLS);
    $found   = mb_scan_output($src, $tainted, $MB_SOURCE_GLOBALS, $MB_HTML_SANITIZERS, $MB_HTML_CONTEXTS);
    ksort($found);
    foreach ($found as $name => $context) {
        $current[] = "$rel\t$name\t$context";
    }
}

exit(mb_ratchet(array(
    'baseline' => $baseline,
    'current'  => $current,
    'accept'   => $accept,
    'header'   => "# Unescaped output audit baseline -- see tests/xss-audit.php\n"
                . "# " . ($stored ? 'Player data, request or stored,' : 'Request input')
                . " that reaches an echo unescaped, as\n"
                . "# file, variable and the worst HTML context it lands in. This list may only\n"
                . "# ever SHRINK. Regenerate with: php tests/xss-audit.php"
                . ($stored ? ' --stored' : '') . " --accept\n",
    'label'    => 'unescaped ' . ($stored ? 'player data' : 'request input') . ' in output',
    'pass'     => 'no new unescaped ' . ($stored ? 'player data' : 'request input') . ' in output.',
    'fail'     => 'new unescaped echo(es)',
    'advice'   => "Wrap it with mb_h(). If the context is attr-bare, quote the attribute\n"
                . "first -- escaping a bare attribute value does not close it. See\n"
                . "app/include/html.php.\n",
    'format'   => 'mb_xss_entry',
)));

/** "app/x.php\tname\tcontext" as a display line. */
function mb_xss_entry($entry)
{
    $p = explode("\t", $entry);
    return "$p[0]  \$$p[1]  ($p[2])";
}
