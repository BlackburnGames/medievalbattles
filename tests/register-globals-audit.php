<?php
/**
 * register_globals audit -- the checklist for the last item of Phase 2.
 *
 * The compat layer injects $_GET/$_POST/$_COOKIE into the global scope because
 * roughly 55 files read bare $pageid / $topicid / $update3 and every form in
 * the game is inert without it. That injection *is* the vulnerability
 * register_globals was removed for, and it is the reason the stack is bound to
 * localhost. Removing it means finding every one of those reads, which is what
 * this script does.
 *
 * Method: tokenize each source and report variables whose FIRST appearance in
 * global scope is a read, and that none of the shared includes provide.
 * Function bodies are skipped -- register_globals only ever populated global
 * scope, so a function-local read of an unset name is a different bug.
 *
 * First-appearance rather than never-assigned, because the most load-bearing
 * uses transform the injected value in place and would otherwise look like
 * ordinary assignments: common.php opens with
 *
 *     $message = nl2br(strip_tags($message, "<i>,<b>"));
 *
 * where the $message being stripped is the one register_globals put there.
 *
 * The result is a superset of the register_globals call sites: it also catches
 * plain uninitialised reads, of which this codebase has plenty and some of
 * which are real bugs (app/gl-delposts.php reads $setgid where it means
 * $guild_id; app/include/S_WEP.php echoes $shortsword_button and friends that
 * are never set anywhere, so the weapon buy buttons do not render). Both kinds
 * belong on the list: the first is Phase 2 work, the second is Phase 3 work,
 * and neither should grow.
 *
 * Baselined in tests/register-globals.txt, and the baseline may only ever
 * SHRINK -- same rule as tests/known-issues.txt. Converting a file to read
 * $_GET/$_POST explicitly turns the read into an assignment, so entries drop
 * off this list on their own as the port proceeds.
 *
 *   php tests/register-globals-audit.php             # check against baseline
 *   php tests/register-globals-audit.php --accept    # rewrite the baseline
 */

$repoRoot  = dirname(__DIR__);
$appRoot   = $repoRoot . '/app';
$baseline  = __DIR__ . '/register-globals.txt';
$accept    = in_array('--accept', $argv, true);

/**
 * Classify a source file's global-scope variable occurrences.
 *
 * @return array{0: array<string,true> assigned anywhere,
 *               1: array<string,true> whose first occurrence is a read,
 *               2: array<string,int>  line of that first read}
 */
function mb_scan_globals($src)
{
    $t = token_get_all($src);
    $n = count($t);
    $written  = array();
    $readonly = array();
    $line     = array();
    $seen     = array();
    $skipTo   = -1;

    for ($i = 0; $i < $n; $i++) {
        if ($i < $skipTo) {
            continue;
        }
        $tok = $t[$i];

        // Skip a whole function declaration: parameters and body. Anything in
        // there is function-local and was never reachable by register_globals.
        if (is_array($tok) && $tok[0] === T_FUNCTION) {
            $depth = 0;
            $open  = false;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($t[$j] === '{') {
                    $depth++;
                    $open = true;
                } elseif ($t[$j] === '}') {
                    $depth--;
                    if ($open && $depth === 0) {
                        break;
                    }
                } elseif (!$open && $t[$j] === ';') {
                    break;   // declaration without a body
                }
            }
            $skipTo = $j + 1;
            continue;
        }

        if (!is_array($tok) || $tok[0] !== T_VARIABLE) {
            continue;
        }
        // Superglobals only. This used to skip every name starting with `_`,
        // which is a wider net than it needs and it caught a real one:
        // wconstruct.php read $_mace, injected by a button that posts `_mace`,
        // and the Phase 2 port never saw it because the audit never listed it.
        // The Mace was the first priest weapon and the prerequisite for the
        // six above it, so the whole branch of the tree went quiet.
        $name = substr($tok[1], 1);
        $superglobals = array(
            'this', 'GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST',
            '_SERVER', '_SESSION', '_FILES', '_ENV',
        );
        if (in_array($name, $superglobals, true)) {
            continue;
        }

        // Previous significant token.
        $p = $i - 1;
        while ($p >= 0 && is_array($t[$p]) && ($t[$p][0] === T_WHITESPACE || $t[$p][0] === T_COMMENT)) {
            $p--;
        }
        $prev = $p >= 0 ? $t[$p] : null;

        // Next significant token, stepping over any [...] subscripts so that
        // $row['x'] = 1 still counts as a write to $row.
        $j = $i + 1;
        $depth = 0;
        while ($j < $n) {
            $x = $t[$j];
            if (is_array($x) && ($x[0] === T_WHITESPACE || $x[0] === T_COMMENT)) { $j++; continue; }
            if ($x === '[') { $depth++; $j++; continue; }
            if ($x === ']') { $depth--; $j++; continue; }
            if ($depth > 0) { $j++; continue; }
            break;
        }
        $next = $j < $n ? $t[$j] : null;

        $isWrite = ($next === '=');
        $assignOps = array(
            T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL,
            T_MOD_EQUAL, T_INC, T_DEC, T_POW_EQUAL, T_SL_EQUAL, T_SR_EQUAL,
            T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL,
        );
        if (defined('T_COALESCE_EQUAL')) {
            $assignOps[] = T_COALESCE_EQUAL;
        }
        if (is_array($next) && in_array($next[0], $assignOps, true)) {
            $isWrite = true;
        }
        // foreach (... as $v) / foreach (... as $k => $v) / global $v / ++$v
        if (is_array($prev) && in_array($prev[0], array(T_AS, T_DOUBLE_ARROW, T_GLOBAL, T_INC, T_DEC), true)) {
            $isWrite = true;
        }
        if ($prev === '&') {
            $isWrite = true;
        }
        // list($a, $b) = ...
        for ($k = $i; $k >= 0 && $k > $i - 60; $k--) {
            if (is_array($t[$k]) && $t[$k][0] === T_LIST) { $isWrite = true; break; }
            if ($t[$k] === ';' || $t[$k] === '{' || $t[$k] === '}') { break; }
        }

        // A self-referential first assignment reads before it writes, however
        // the tokens are ordered: in "$message = strip_tags($message)" the
        // left-hand $message comes first textually but the value being
        // stripped is whatever was already in scope. Look ahead to the end of
        // the statement for the name appearing again.
        if ($isWrite && !isset($seen[$name])) {
            $d = 0;
            for ($k = $i + 1; $k < $n; $k++) {
                $x = $t[$k];
                if ($x === '(' || $x === '[') { $d++; continue; }
                if ($x === ')' || $x === ']') { $d--; continue; }
                if ($d <= 0 && ($x === ';' || $x === '{' || $x === '}')) { break; }
                if (is_array($x) && $x[0] === T_VARIABLE && substr($x[1], 1) === $name) {
                    $isWrite = false;   // read on its own right-hand side
                    break;
                }
            }
        }

        if ($isWrite) {
            $written[$name] = true;
        } elseif (!isset($seen[$name])) {
            // First mention of this name in global scope is a read, so the
            // value can only have come from outside the file.
            $readonly[$name] = true;
            $line[$name] = $tok[2];
        }
        $seen[$name] = true;
    }

    return array($written, $readonly, $line);
}

// Variables the shared includes drop into scope. A page reading $gp or $email
// is reading state that functions.php loaded, not a request parameter.
$sharedIncludes = array(
    'functions.php', 'common.php', 'commong.php',
    'include/connect.php', 'include/clock.php', 'include/session.php',
    'include/igtop.php', 'include/act_igtop.php', 'include/ignavbar.php',
    'include/gamedata.php',
);
$provided = array();
foreach ($sharedIncludes as $inc) {
    $path = "$appRoot/$inc";
    if (!file_exists($path)) {
        continue;
    }
    list($w, , ) = mb_scan_globals(file_get_contents($path));
    foreach ($w as $name => $_) {
        // Remember which include supplies it, so an include is not credited
        // with providing a name to itself.
        $provided[$name][$inc] = true;
    }
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
    $rel  = ltrim(str_replace(str_replace('\\', '/', $repoRoot), '', $path), '/');
    $self = substr($rel, 4);   // path relative to app/, to match $sharedIncludes
    list(, $r, ) = mb_scan_globals(file_get_contents($path));
    $names = array();
    foreach ($r as $name => $_) {
        if (isset($provided[$name])) {
            // Provided by a shared include -- unless this IS that include, in
            // which case the value still has to come from the request.
            $sources = $provided[$name];
            unset($sources[$self]);
            if ($sources) {
                continue;
            }
        }
        $names[] = $name;
    }
    sort($names);
    foreach ($names as $name) {
        $current[] = "$rel\t$name";
    }
}

if ($accept) {
    $header = "# register_globals audit baseline -- see tests/register-globals-audit.php\n"
            . "# Global-scope variables each file reads but never assigns. This list may\n"
            . "# only ever SHRINK. Regenerate with: php tests/register-globals-audit.php --accept\n";
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

echo "register_globals reads: " . count($current) . " (baseline " . count($expected) . ")\n";

if ($removed) {
    echo "\n" . count($removed) . " entr" . (count($removed) === 1 ? 'y' : 'ies')
       . " no longer firing -- delete from the baseline:\n";
    foreach ($removed as $e) {
        echo "  - " . str_replace("\t", "  \$", $e) . "\n";
    }
}

if ($added) {
    echo "\nFAIL: " . count($added) . " new register_globals-dependent read(s):\n";
    foreach ($added as $e) {
        echo "  + " . str_replace("\t", "  \$", $e) . "\n";
    }
    echo "\nRead the request parameter explicitly (see app/include/request.php)\n";
    echo "rather than relying on the compat layer to inject it.\n";
    exit(1);
}

echo "PASS: no new register_globals-dependent reads.\n";
exit(0);
