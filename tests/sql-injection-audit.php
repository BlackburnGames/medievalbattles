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

/** Superglobals that carry request input. $_SERVER is in for the Host/UA headers. */
$MB_SOURCE_GLOBALS = array('_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_FILES');

/**
 * Calls and casts that make a value safe to interpolate.
 *
 * The first three are the intended fix. The rest are there because they return
 * a number or a hex digest whatever they are handed, and this codebase leans on
 * them constantly -- most of the game's writes are "$gp = $gp - $cost" chains
 * seeded by a request parameter. Without these the audit would report several
 * hundred arithmetic results as injectable and be useless as a worklist.
 */
$MB_SANITIZERS = array(
    'mb_sql_str', 'mb_sql_int', 'mysqli_real_escape_string',
    'intval', 'floatval', 'round', 'floor', 'ceil', 'abs',
    'count', 'sizeof', 'strlen', 'rand', 'mt_rand', 'md5', 'sha1',
);

/**
 * Significant token index at or after $i, skipping whitespace and comments.
 */
function mb_sig(array $t, $i, $dir = 1)
{
    $n = count($t);
    while ($i >= 0 && $i < $n && is_array($t[$i])
        && ($t[$i][0] === T_WHITESPACE || $t[$i][0] === T_COMMENT || $t[$i][0] === T_DOC_COMMENT)) {
        $i += $dir;
    }
    return ($i >= 0 && $i < $n) ? $i : -1;
}

/**
 * End of the statement starting at $i: the index of the `;` that closes it at
 * bracket depth zero, or the last token.
 */
function mb_stmt_end(array $t, $i)
{
    $n = count($t);
    $d = 0;
    for ($k = $i; $k < $n; $k++) {
        $x = $t[$k];
        if ($x === '(' || $x === '[' || $x === '{') { $d++; continue; }
        if ($x === ')' || $x === ']' || $x === '}') { $d--; continue; }
        if ($d <= 0 && $x === ';') { return $k; }
    }
    return $n - 1;
}

/**
 * Is the variable at $i an operand of an arithmetic operator?
 *
 * PHP's arithmetic operators always produce a number, so "$gp - $cost" cannot
 * carry an injection out of $cost however hostile $cost is -- and this codebase
 * spends most of its lines doing exactly that before writing the result back.
 *
 * Only the adjacent operator is checked, which is what makes this safe to
 * assume: inside a double-quoted string a `-` is part of the surrounding
 * T_ENCAPSED_AND_WHITESPACE run rather than a token of its own, so "$a-$b"
 * does not read as subtraction here. Concatenation is a `.` and is not on the
 * list, so string building never launders.
 */
function mb_is_arithmetic(array $t, $i)
{
    $ops = array('+', '-', '*', '/', '%');
    foreach (array(-1, 1) as $dir) {
        $k = mb_sig($t, $i + $dir, $dir);
        if ($k < 0) {
            continue;
        }
        if (in_array($t[$k], $ops, true)) {
            return true;
        }
        if (is_array($t[$k]) && $t[$k][0] === T_POW) {
            return true;
        }
    }
    return false;
}

/**
 * Is the token at $i enclosed by one of $names -- a call to it, or an (int)
 * cast in front of it?
 *
 * Deliberately crude: it looks for the nearest enclosing call, so
 * mb_sql_str($db, trim($x)) counts and so does (int) $x, but
 * "prefix" . $x . mb_sql_str($y) does not launder $x. Over-reporting is the
 * safe direction for an audit whose job is to be shrunk to zero.
 */
function mb_is_sanitized(array $t, $i, $start, array $names)
{
    // (int) $x / (integer) $x, possibly with the cast a few tokens back.
    $p = mb_sig($t, $i - 1, -1);
    while ($p >= $start && is_array($t[$p]) && $t[$p][0] === T_INT_CAST) {
        return true;
    }

    // Walk left counting brackets; at each unmatched '(' check the name before it.
    $d = 0;
    for ($k = $i - 1; $k >= $start; $k--) {
        $x = $t[$k];
        if ($x === ')') { $d++; continue; }
        if ($x !== '(') { continue; }
        if ($d > 0) { $d--; continue; }
        $f = mb_sig($t, $k - 1, -1);
        if ($f >= 0 && is_array($t[$f]) && $t[$f][0] === T_STRING
            && in_array(strtolower($t[$f][1]), $names, true)) {
            return true;
        }
        // Not a sanitizer -- keep walking outwards, the caller may be one.
        $d--;
    }
    return false;
}

/**
 * Names of the variables in $src that carry request input.
 *
 * @return array<string,true>
 */
function mb_tainted_names($src, array $sourceGlobals, array $sanitizers)
{
    $t = token_get_all($src);
    $n = count($t);
    $tainted = array();

    // Fixpoint, because taint can flow backwards through the file: a page may
    // build a query from $x on line 40 and assign $x = $y on line 12 where $y
    // came from mb_input() on line 8. One pass in source order gets that, but
    // loops and includes make the general case unordered, so iterate.
    for ($pass = 0; $pass < 8; $pass++) {
        $before = count($tainted);

        for ($i = 0; $i < $n; $i++) {
            if (!is_array($t[$i]) || $t[$i][0] !== T_VARIABLE) {
                continue;
            }
            $name = substr($t[$i][1], 1);

            // Is this an assignment target? Step over any [...] subscripts.
            $j = $i + 1;
            $d = 0;
            while ($j < $n) {
                $x = $t[$j];
                if (is_array($x) && ($x[0] === T_WHITESPACE || $x[0] === T_COMMENT)) { $j++; continue; }
                if ($x === '[') { $d++; $j++; continue; }
                if ($x === ']') { $d--; $j++; continue; }
                if ($d > 0) { $j++; continue; }
                break;
            }
            $isAssign = ($j < $n) && ($t[$j] === '='
                || (is_array($t[$j]) && $t[$j][0] === T_CONCAT_EQUAL));
            if (!$isAssign || isset($tainted[$name])) {
                continue;
            }

            // Scan the right-hand side for a source or an already-tainted name.
            $end = mb_stmt_end($t, $j);
            for ($k = $j + 1; $k <= $end; $k++) {
                $x = $t[$k];
                $hit = false;
                if (is_array($x) && $x[0] === T_STRING && strtolower($x[1]) === 'mb_input') {
                    $hit = true;
                } elseif (is_array($x) && $x[0] === T_VARIABLE) {
                    $v = substr($x[1], 1);
                    $hit = in_array($v, $sourceGlobals, true) || isset($tainted[$v]);
                }
                if ($hit && !mb_is_arithmetic($t, $k) && !mb_is_sanitized($t, $k, $j, $sanitizers)) {
                    $tainted[$name] = true;
                    break;
                }
            }
        }

        if (count($tainted) === $before) {
            break;
        }
    }

    return $tainted;
}

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

// Walk the app in a stable order so the baseline diffs cleanly.
$files = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot));
foreach ($rii as $f) {
    if ($f->isDir() || substr($f->getFilename(), -4) !== '.php') {
        continue;
    }
    $files[] = str_replace('\\', '/', $f->getPathname());
}
sort($files);

$current = array();
foreach ($files as $path) {
    $rel = ltrim(str_replace(str_replace('\\', '/', $repoRoot), '', $path), '/');
    $src = file_get_contents($path);
    if (strpos($src, 'mysqli_query') === false) {
        continue;
    }
    $tainted = mb_tainted_names($src, $MB_SOURCE_GLOBALS, $MB_SANITIZERS);
    $found   = mb_scan_queries($src, $tainted, $MB_SOURCE_GLOBALS, $MB_SANITIZERS);
    ksort($found);
    foreach ($found as $name => $context) {
        $current[] = "$rel\t$name\t$context";
    }
}

if ($accept) {
    $header = "# SQL injection audit baseline -- see tests/sql-injection-audit.php\n"
            . "# Request input that reaches a mysqli_query() unescaped, as file, variable\n"
            . "# and the quoting context it lands in. This list may only ever SHRINK.\n"
            . "# Regenerate with: php tests/sql-injection-audit.php --accept\n";
    file_put_contents($baseline, $header . implode("\n", $current) . "\n");
    echo "Wrote " . count($current) . " entries to " . basename($baseline) . "\n";
    exit(0);
}

if (!file_exists($baseline)) {
    fwrite(STDERR, "FAIL: missing $baseline -- run with --accept to create it.\n");
    exit(1);
}

$expected = array();
foreach (file($baseline, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if ($l === '' || $l[0] === '#') {
        continue;
    }
    $expected[] = $l;
}

$added   = array_diff($current, $expected);
$removed = array_diff($expected, $current);

echo "unescaped request input in queries: " . count($current)
   . " (baseline " . count($expected) . ")\n";

if ($removed) {
    echo "\n" . count($removed) . " entr" . (count($removed) === 1 ? 'y' : 'ies')
       . " no longer firing -- delete from the baseline:\n";
    foreach ($removed as $e) {
        $p = explode("\t", $e);
        echo "  - $p[0]  \$$p[1]\n";
    }
}

if ($added) {
    echo "\nFAIL: " . count($added) . " new unescaped interpolation(s):\n";
    foreach ($added as $e) {
        $p = explode("\t", $e);
        echo "  + $p[0]  \$$p[1]  ($p[2])\n";
    }
    echo "\nQuote it with mb_sql_str(), or cast it with mb_sql_int() when the\n";
    echo "query interpolates it outside quotes. See app/include/db.php.\n";
    exit(1);
}

echo "PASS: no new unescaped request input in queries.\n";
exit(0);
