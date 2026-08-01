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
 * global scope is a read, and that none of the shared includes THAT FILE PULLS
 * IN provide. Function bodies are skipped -- register_globals only ever
 * populated global scope, so a function-local read of an unset name is a
 * different bug.
 *
 * The include set is resolved per file and transitively, which it was not
 * originally: any name assigned by any of the shared includes was credited to
 * every file in the app, whether or not that file could see it. That hid a read
 * the Phase 2 port then missed. admin/adminlogin.php read $pw, which
 * include/session.php assigns -- but the login included nothing except
 * request.php, so the entry was suppressed and the admin login stayed broken.
 * See mb_resolve_include() for how a path is resolved; the short version is
 * that this codebase's includes are CWD-relative, and the CWD is the directory
 * of whichever page was requested.
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
 * SHRINK -- same rule as tests/known-issues.txt. It has grown exactly once, by
 * three entries, on the commit that made the include resolution per-file: the
 * $gid that attack.php, attackm.php and attackr.php read to file guild news.
 * Only commong.php assigns $gid and none of the three includes it, so the
 * attacker's guild has never been told about an attack -- and the $tgid beside
 * it, the defender's, was already on this list because nothing anywhere assigns
 * it. Both are guarded by `if($x[0] != "")`, so the inserts are skipped rather
 * than run wrong, and repairing them means deciding what the feature should do
 * rather than restoring a read. They stay listed until it is decided.
 *
 * Converting a file to read
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

/**
 * Literal include/require targets in a source, as they are written.
 *
 * Three forms appear in this codebase and all are matched: a plain string,
 * include("include/session.php"); __DIR__ . '/x.php', which the files added
 * since the port use precisely because the plain form is CWD-relative; and
 * include($v), where $v is assigned a literal .php path in the same file.
 *
 * That third form is barter_handler.php's `$mb_panel`, which selects the game
 * board or the guild board. Both arms have to be followed or the two panels
 * look like entry points that read $db and $ename out of nowhere -- they are
 * templates, and the handler that includes them set both.
 *
 * @return array<int, array{0: string path, 1: bool anchored to the includer}>
 */
function mb_scan_includes($src)
{
    $t = token_get_all($src);
    $n = count($t);
    $out = array();

    // $var = 'something.php' anywhere in the file, for the dynamic form below.
    // A name may be assigned more than once; every literal counts, because the
    // point is which files can be reached, not which one a given run picks.
    $literals = array();
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_VARIABLE) {
            continue;
        }
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && $t[$j][0] === T_WHITESPACE) { $j++; }
        if ($j >= $n || $t[$j] !== '=') {
            continue;
        }
        // Collect every .php literal up to the end of the statement, so that a
        // ternary contributes both of its arms.
        for ($k = $j + 1; $k < $n; $k++) {
            $x = $t[$k];
            if ($x === ';' || $x === '{' || $x === '}') { break; }
            if (is_array($x) && $x[0] === T_CONSTANT_ENCAPSED_STRING) {
                $s = substr($x[1], 1, -1);
                if (substr($s, -4) === '.php') {
                    $literals[substr($t[$i][1], 1)][] = $s;
                }
            }
        }
    }

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i])
            || !in_array($t[$i][0], array(T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE), true)) {
            continue;
        }
        // Walk to the end of the include expression, collecting what it is
        // built from. Anything the three forms above do not account for leaves
        // the target unresolved, and an unresolved target is skipped.
        $dir  = false;
        $str  = null;
        $var  = null;
        $bad  = false;
        $depth = 0;
        for ($j = $i + 1; $j < $n; $j++) {
            $x = $t[$j];
            if ($x === '(') { $depth++; continue; }
            if ($x === ')') { if ($depth === 0) { break; } $depth--; continue; }
            if ($x === ';') { break; }
            if ($x === '.') { continue; }
            if (is_array($x)) {
                if ($x[0] === T_WHITESPACE || $x[0] === T_COMMENT) { continue; }
                if ($x[0] === T_DIR) { $dir = true; continue; }
                if ($x[0] === T_CONSTANT_ENCAPSED_STRING && $str === null) {
                    $str = substr($x[1], 1, -1);
                    continue;
                }
                if ($x[0] === T_VARIABLE && $var === null) {
                    $var = substr($x[1], 1);
                    continue;
                }
            }
            $bad = true;
        }
        if (!$bad && $str !== null) {
            $out[] = array($str, $dir);
        } elseif (!$bad && $var !== null && isset($literals[$var])) {
            foreach ($literals[$var] as $s) {
                $out[] = array($s, false);
            }
        }
        $i = $j;
    }

    return $out;
}

/**
 * Resolve an include target to a path relative to app/, or null.
 *
 * PHP looks in the working directory before the including file's own
 * directory, and in this codebase the working directory is the directory of the
 * requested page -- app/ for the game, app/admin/ for the admin area. That is
 * why app/admin/include/igtop.php can say include("connect.php") and reach
 * app/admin/include/connect.php, while app/admin/dusers.php says
 * include("include/igtop.php") and reaches the admin one rather than the game's.
 *
 * @param string $target  path as written
 * @param bool   $anchored  built from __DIR__, so the includer's directory wins
 * @param string $cur     the including file, relative to app/
 * @param string $entry   directory of the requested page, relative to app/
 */
function mb_resolve_include($target, $anchored, $cur, $entry, $appRoot)
{
    $candidates = $anchored
        ? array(dirname($cur) . '/' . $target)
        : array(
            ($entry === '.' ? '' : $entry . '/') . $target,
            (dirname($cur) === '.' ? '' : dirname($cur) . '/') . $target,
            $target,
        );

    foreach ($candidates as $c) {
        // Collapse . and .. so __DIR__ . '/../../include/session.php' matches
        // the same key the shared-include list uses.
        $parts = array();
        foreach (explode('/', $c) as $seg) {
            if ($seg === '' || $seg === '.') { continue; }
            if ($seg === '..') { array_pop($parts); continue; }
            $parts[] = $seg;
        }
        $rel = implode('/', $parts);
        if ($rel !== '' && is_file("$appRoot/$rel")) {
            return $rel;
        }
    }

    return null;
}

/**
 * Every file reachable from $entry by following includes, $entry included.
 *
 * Resolution is anchored at $entry's own directory, because that is the working
 * directory when $entry is the requested page. For a file that is only ever
 * included -- a panel template under include/ -- that anchor is wrong, but the
 * fallback to the app root catches every such path in this codebase.
 *
 * @return array<string,true> paths relative to app/
 */
function mb_include_closure($entry, $appRoot)
{
    $entryDir = dirname($entry);
    $seen     = array($entry => true);
    $queue    = array($entry);

    while ($queue) {
        $cur = array_shift($queue);
        foreach (mb_scan_includes(file_get_contents("$appRoot/$cur")) as $inc) {
            $rel = mb_resolve_include($inc[0], $inc[1], $cur, $entryDir, $appRoot);
            if ($rel !== null && !isset($seen[$rel])) {
                $seen[$rel] = true;
                $queue[] = $rel;
            }
        }
    }

    return $seen;
}

// Variables the shared includes drop into scope. A page reading $gp or $email
// is reading state that functions.php loaded, not a request parameter -- but
// only if the page can actually see it, which is why this is intersected with
// each file's own include closure below rather than applied to everything.
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

// What each file can see. A page sees the shared includes it pulls in; a file
// that is only ever included sees whatever its includers had in scope, because
// half of this codebase is a panel template that renders $race and $gp without
// including anything at all. So: visible(F) is the union of the include
// closures of every file that reaches F, F itself among them.
//
// This is deliberately not the same as crediting every include to every file,
// which is what it used to do. adminlogin.php includes nothing that assigns
// $pw and nothing includes adminlogin.php, so its read is reported -- that is
// the case the old form suppressed, and the admin login stayed broken for it.
$closure = array();
foreach ($files as $path) {
    $rel = substr(ltrim(str_replace(str_replace('\\', '/', $repoRoot), '', $path), '/'), 4);
    $closure[$rel] = mb_include_closure($rel, $appRoot);
}
$visible = array();
foreach ($closure as $from => $reached) {
    foreach ($reached as $to => $_) {
        $visible[$to] = isset($visible[$to]) ? $visible[$to] + $reached : $reached;
    }
}

$current = array();
foreach ($files as $path) {
    $rel  = ltrim(str_replace(str_replace('\\', '/', $repoRoot), '', $path), '/');
    $self = substr($rel, 4);   // path relative to app/, to match $sharedIncludes
    list(, $r, ) = mb_scan_globals(file_get_contents($path));
    $reaches = isset($visible[$self]) ? $visible[$self] : array($self => true);
    $names = array();
    foreach ($r as $name => $_) {
        if (isset($provided[$name])) {
            // Provided by a shared include this file actually pulls in --
            // unless this IS that include, in which case the value still has to
            // come from the request.
            $sources = array_intersect_key($provided[$name], $reaches);
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
