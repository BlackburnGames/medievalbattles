<?php
/**
 * Shared taint tracking for the Phase 3 audits.
 *
 * tests/sql-injection-audit.php and tests/xss-audit.php ask the same question
 * of the same sources -- which variables in this file carry request input --
 * and differ only in the sink they check. The tracker lives here so the two
 * cannot drift, because a source or a sanitizer that one of them knows about
 * and the other does not is a false clean bill of health.
 *
 * The tracking is flow-insensitive and per-file by choice. See the header of
 * either audit for what that costs.
 */

/** Superglobals that carry request input. $_SERVER is in for the Host/UA headers. */
$MB_SOURCE_GLOBALS = array('_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_FILES');

/**
 * Functions whose return value is player-controlled.
 *
 * mb_input() is the request. The fetch calls are the second order: everything
 * in this database was typed by a player at some point, so a row read back is
 * request input that has been round-tripped. The SQL audit uses only the first
 * -- see its header for why -- and the XSS audit takes both in --stored mode.
 */
$MB_SOURCE_CALLS = array('mb_input');
$MB_STORED_CALLS = array(
    'mb_input', 'mysqli_fetch_array', 'mysqli_fetch_row', 'mysqli_fetch_assoc',
    'mysqli_fetch_object', 'mysqli_fetch_all', 'mb_db_result',
);

/**
 * Calls and casts that make a value safe to interpolate into a query.
 *
 * The first three are the intended fix. The rest are there because they return
 * a number or a hex digest whatever they are handed, and this codebase leans on
 * them constantly -- most of the game's writes are "$gp = $gp - $cost" chains
 * seeded by a request parameter. Without these the audit would report several
 * hundred arithmetic results as injectable and be useless as a worklist.
 *
 * Every one of them is safe for HTML output too, which is why the XSS audit
 * takes this list and adds to it rather than keeping its own.
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
function mb_tainted_names($src, array $sourceGlobals, array $sanitizers, array $sourceCalls = null)
{
    if ($sourceCalls === null) {
        $sourceCalls = array('mb_input');
    }
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
                if (is_array($x) && $x[0] === T_STRING
                    && in_array(strtolower($x[1]), $sourceCalls, true)) {
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
 * Every .php file under $appRoot, in a stable order so a baseline diffs cleanly.
 *
 * @return array<int,string> absolute paths with forward slashes
 */
function mb_app_files($appRoot)
{
    $files = array();
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot));
    foreach ($rii as $f) {
        if ($f->isDir() || substr($f->getFilename(), -4) !== '.php') {
            continue;
        }
        $files[] = str_replace('\\', '/', $f->getPathname());
    }
    sort($files);
    return $files;
}

/**
 * Compare a freshly computed list against a baseline file and report.
 *
 * Both ratchets behave the same way: a removed entry is a nudge to update the
 * baseline, an added one is a failure. Returns the process exit code -- only
 * that is load-bearing, tests/run-all.sh reads nothing else.
 *
 * The wording stays with each audit rather than being generated here, because
 * the FAIL text is where a reader is told what the fix is.
 *
 * @param array $opt  baseline, current, accept, header, label, pass, fail,
 *                    advice, format (entry line => display string)
 */
function mb_ratchet(array $opt)
{
    $baseline = $opt['baseline'];
    $current  = $opt['current'];

    if ($opt['accept']) {
        file_put_contents($baseline, $opt['header'] . implode("\n", $current) . "\n");
        echo "Wrote " . count($current) . " entries to " . basename($baseline) . "\n";
        return 0;
    }

    if (!file_exists($baseline)) {
        fwrite(STDERR, "FAIL: missing $baseline -- run with --accept to create it.\n");
        return 1;
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

    echo $opt['label'] . ": " . count($current) . " (baseline " . count($expected) . ")\n";

    if ($removed) {
        echo "\n" . count($removed) . " entr" . (count($removed) === 1 ? 'y' : 'ies')
           . " no longer firing -- delete from the baseline:\n";
        foreach ($removed as $e) {
            echo "  - " . call_user_func($opt['format'], $e) . "\n";
        }
    }

    if ($added) {
        echo "\nFAIL: " . count($added) . " " . $opt['fail'] . ":\n";
        foreach ($added as $e) {
            echo "  + " . call_user_func($opt['format'], $e) . "\n";
        }
        echo "\n" . $opt['advice'];
        return 1;
    }

    echo "PASS: " . $opt['pass'] . "\n";
    return 0;
}
