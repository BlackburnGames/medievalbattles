<?php
/**
 * SQL injection audit -- the checklist for the first item of Phase 3.
 *
 * All ~1300 queries in this codebase are built by interpolating variables into
 * a double-quoted string, and nothing escapes or casts on the way in. That is
 * the reason both container ports bind to 127.0.0.1. Fixing it means finding
 * every place a request parameter reaches a query, which is what this script
 * does.
 *
 * Method: tokenize each source, mark every variable that derives from request
 * input, then report those that survive into the second argument of
 * mysqli_query() -- the codebase's only query entry point, all 1124 of them.
 *
 * Taint sources are mb_input() and the superglobals it reads. Taint spreads
 * through assignment: if the right-hand side mentions a tainted name, the
 * left-hand side is tainted too. The pass repeats to a fixpoint, so the audit
 * does not depend on statement order -- like the register_globals audit it is
 * flow-insensitive by choice, which makes it over-report rather than under-.
 *
 * A value stops being tainted when it passes through one of the sanitizers
 * below -- an (int) cast, or one of the two quoting helpers db.php provides.
 * That is what makes this a ratchet: fixing a call site deletes its entry.
 *
 * Each finding records the context the variable lands in, because it decides
 * the fix:
 *
 *   quoted     "... WHERE name='$x'"   -- escaping is enough; mb_sql_str()
 *   unquoted   "... WHERE id=$x"       -- escaping is USELESS, because the
 *                                          payload needs no quote to break
 *                                          out. Must be cast; mb_sql_int()
 *
 * Two limits, both deliberate:
 *
 *   - First-order only. A tainted value written to the database and read back
 *     into a later query is a real stored injection and this audit will not
 *     see it. Every string that reaches a query still has to be escaped, not
 *     just the ones listed here.
 *   - Per-file. A parameter read in one file and used in a query in another
 *     (the s* and g* pages hand work to common.php and commong.php this way)
 *     is invisible here.
 *
 * So the list is a floor, not a total -- the same caveat tests/broken-queries.txt
 * carries.
 *
 * Baselined in tests/sql-injection.txt, and the baseline may only ever SHRINK.
 * Entries have no line numbers on purpose: a fix elsewhere in the file would
 * otherwise show up as one entry removed and one added.
 *
 *   php tests/sql-injection-audit.php             # check against baseline
 *   php tests/sql-injection-audit.php --accept    # rewrite the baseline
 */

$repoRoot = dirname(__DIR__);
$appRoot  = $repoRoot . '/app';
$baseline = __DIR__ . '/sql-injection.txt';
$accept   = in_array('--accept', $argv, true);

require_once __DIR__ . '/taint-lib.php';

/**
 * Tainted variables interpolated into a mysqli_query() argument.
 *
 * @return array<string,string> variable name => "quoted"|"unquoted"
 */
function mb_scan_queries($src, array $tainted, array $sourceGlobals, array $sanitizers)
{
    $t = token_get_all($src);
    $n = count($t);
    $found = array();

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_STRING
            || strtolower($t[$i][1]) !== 'mysqli_query') {
            continue;
        }
        $open = mb_sig($t, $i + 1);
        if ($open < 0 || $t[$open] !== '(') {
            continue;
        }
        // Argument list runs to the matching ')'.
        $d = 0;
        $close = $open;
        for ($k = $open; $k < $n; $k++) {
            if ($t[$k] === '(') { $d++; }
            elseif ($t[$k] === ')') { $d--; if ($d === 0) { $close = $k; break; } }
        }

        // Quote depth inside the current double-quoted string. PHP tokenizes
        // "a='$x'" as `"`, T_ENCAPSED_AND_WHITESPACE("a='"), T_VARIABLE, ... so
        // an odd number of single quotes in the encapsed text so far means the
        // variable lands between them.
        $inString = false;
        $quotes   = 0;

        for ($k = $open + 1; $k < $close; $k++) {
            $x = $t[$k];

            if ($x === '"') {
                $inString = !$inString;
                $quotes   = 0;
                continue;
            }
            if (is_array($x) && $x[0] === T_ENCAPSED_AND_WHITESPACE) {
                $quotes += substr_count($x[1], "'");
                continue;
            }
            if (is_array($x) && $x[0] === T_CONSTANT_ENCAPSED_STRING) {
                // Concatenated single-quoted fragment: count its double quotes,
                // which is how a concatenated query delimits a string literal.
                $quotes += substr_count($x[1], '"');
                continue;
            }
            if (!is_array($x) || $x[0] !== T_VARIABLE) {
                continue;
            }

            $name = substr($x[1], 1);
            if (!isset($tainted[$name]) && !in_array($name, $sourceGlobals, true)) {
                continue;
            }
            if (mb_is_arithmetic($t, $k) || mb_is_sanitized($t, $k, $open, $sanitizers)) {
                continue;
            }

            $context = ($quotes % 2 === 1) ? 'quoted' : 'unquoted';
            // Worst case wins: one unquoted use makes the parameter unfixable
            // by escaping alone, whatever its other call sites do.
            if (!isset($found[$name]) || $context === 'unquoted') {
                $found[$name] = $context;
            }
        }
    }

    return $found;
}

$current = array();
foreach (mb_app_files($appRoot) as $path) {
    $rel = ltrim(str_replace(str_replace('\\', '/', $repoRoot), '', $path), '/');
    $src = file_get_contents($path);
    if (strpos($src, 'mysqli_query') === false) {
        continue;
    }
    $tainted = mb_tainted_names($src, $MB_SOURCE_GLOBALS, $MB_SANITIZERS);
    $found   = mb_scan_queries($src, $tainted, $MB_SOURCE_GLOBALS, $MB_SANITIZERS);
    ksort($found);
    foreach ($found as $name => $context) {
        $current[] = "$rel	$name	$context";
    }
}

exit(mb_ratchet(array(
    'baseline' => $baseline,
    'current'  => $current,
    'accept'   => $accept,
    'header'   => "# SQL injection audit baseline -- see tests/sql-injection-audit.php
"
                . "# Request input that reaches a mysqli_query() unescaped, as file, variable
"
                . "# and the quoting context it lands in. This list may only ever SHRINK.
"
                . "# Regenerate with: php tests/sql-injection-audit.php --accept
",
    'label'    => 'unescaped request input in queries',
    'pass'     => 'no new unescaped request input in queries.',
    'fail'     => 'new unescaped interpolation(s)',
    'advice'   => "Quote it with mb_sql_str(), or cast it with mb_sql_int() when the
"
                . "query interpolates it outside quotes. See app/include/db.php.
",
    'format'   => 'mb_sql_entry',
)));

/** "app/x.php	name	context" as a display line. */
function mb_sql_entry($entry)
{
    $p = explode("	", $entry);
    return "$p[0]  \$$p[1]  ($p[2])";
}
