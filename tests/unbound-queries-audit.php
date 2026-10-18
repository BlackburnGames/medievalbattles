<?php
/**
 * How many queries in each file still build their SQL out of values.
 *
 * mb_query($db, $sql, $params) binds values instead of pasting them into the
 * SQL text (app/include/db.php). This counts, per file, every query call whose
 * SQL argument is anything other than a fixed string: a variable interpolated
 * into it, a value concatenated onto it, or a variable holding the whole thing.
 * A query with no values in it at all is not counted -- there is nothing to
 * bind -- and neither is a value in mb_query()'s params array, which is bound.
 *
 * mb_query() itself is counted when its SQL is built from variables, because
 * that is the hole the helper exists to close. The two that do it on purpose,
 * interpolating a table name from a fixed list, are in the baseline.
 *
 * Unlike the other audits this compares counts, not entries: a file is allowed
 * fewer unbound queries than the baseline says, never more, and a file the
 * baseline does not list is allowed none. When a file's count falls, lower it
 * with --accept.
 *
 *   php tests/unbound-queries-audit.php            # check against baseline
 *   php tests/unbound-queries-audit.php --accept   # rewrite the baseline
 *   php tests/unbound-queries-audit.php --list app/foo.php   # show each call
 */

$repoRoot = dirname(__DIR__);
$appRoot  = $repoRoot . '/app';
$baseline = __DIR__ . '/unbound-queries.txt';
$accept   = in_array('--accept', $argv, true);
$listAt   = array_search('--list', $argv, true);
$listFile = $listAt !== false && isset($argv[$listAt + 1]) ? $argv[$listAt + 1] : null;

require_once __DIR__ . '/taint-lib.php';

$SINK_FUNCTIONS = array('mysqli_query', 'mysqli_real_query', 'mysqli_multi_query', 'mysqli_prepare', 'mb_query');
$SINK_METHODS   = array('query', 'real_query', 'multi_query', 'prepare');

/**
 * Every query call in a file whose SQL argument carries a value, as
 * array(line, first line of the SQL argument's source).
 */
function mb_unbound_calls($src)
{
    global $SINK_FUNCTIONS, $SINK_METHODS;

    $t = token_get_all($src);
    $n = count($t);
    $calls = array();

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_STRING) {
            continue;
        }
        $name = strtolower($t[$i][1]);
        $prev = $i - 1;
        while ($prev >= 0 && is_array($t[$prev]) && $t[$prev][0] === T_WHITESPACE) {
            $prev--;
        }
        $isMethod = $prev >= 0 && is_array($t[$prev]) && $t[$prev][0] === T_OBJECT_OPERATOR;
        if ($isMethod ? !in_array($name, $SINK_METHODS, true) : !in_array($name, $SINK_FUNCTIONS, true)) {
            continue;
        }
        // A declaration of the function, not a call to it.
        if ($prev >= 0 && is_array($t[$prev]) && $t[$prev][0] === T_FUNCTION) {
            continue;
        }

        $open = $i + 1;
        while ($open < $n && is_array($t[$open]) && $t[$open][0] === T_WHITESPACE) {
            $open++;
        }
        if ($open >= $n || $t[$open] !== '(') {
            continue;
        }

        // The SQL argument: the second for a procedural call, the first for a
        // method, running to the next top-level comma or the closing paren.
        $want = $isMethod ? 0 : 1;
        $arg = 0;
        $depth = 0;
        $start = $open + 1;
        $sql = array();
        for ($k = $open; $k < $n; $k++) {
            $x = $t[$k];
            if ($x === '(' || $x === '[' || $x === '{' || (is_array($x) && in_array($x[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif ($x === ')' || $x === ']' || $x === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } elseif ($x === ',' && $depth === 1) {
                $arg++;
                continue;
            }
            if ($depth >= 1 && $arg === $want) {
                $sql[] = $x;
            }
        }

        $hasValue = false;
        foreach ($sql as $x) {
            if (is_array($x) && $x[0] === T_VARIABLE) {
                $hasValue = true;
                break;
            }
        }
        if ($hasValue) {
            $text = '';
            foreach ($sql as $x) {
                $text .= is_array($x) ? $x[1] : $x;
            }
            $calls[] = array($t[$i][2], trim(strtok(trim($text), "\n")));
        }
    }

    return $calls;
}

// The app, and the tick, which lives at the repo root.
$counts = array();
foreach (array_merge(mb_app_files($appRoot), array($repoRoot . '/update.php')) as $path) {
    $rel = ltrim(str_replace(str_replace('\\', '/', $repoRoot), '', $path), '/');
    $calls = mb_unbound_calls(file_get_contents($path));
    if ($listFile !== null && $rel === $listFile) {
        foreach ($calls as $c) {
            echo "  line {$c[0]}: {$c[1]}\n";
        }
        exit(0);
    }
    if ($calls) {
        $counts[$rel] = count($calls);
    }
}
ksort($counts);
$total = array_sum($counts);

if ($accept) {
    $out = "# Unbound queries per file -- see tests/unbound-queries-audit.php.\n"
         . "# A file's count may only FALL. Regenerate with --accept after converting\n"
         . "# queries to mb_query().\n";
    foreach ($counts as $file => $count) {
        $out .= "$file\t$count\n";
    }
    file_put_contents($baseline, $out);
    echo "Wrote " . count($counts) . " files, $total unbound queries, to " . basename($baseline) . "\n";
    exit(0);
}

if (!file_exists($baseline)) {
    fwrite(STDERR, "FAIL: missing $baseline -- run with --accept to create it.\n");
    exit(1);
}

$expected = array();
foreach (file($baseline, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#') {
        continue;
    }
    list($file, $count) = explode("\t", $line);
    $expected[$file] = (int) $count;
}

echo "Unbound queries: $total in " . count($counts) . " files (baseline " . array_sum($expected) . ")\n";

$grew = array();
$fell = array();
foreach ($counts as $file => $count) {
    $was = isset($expected[$file]) ? $expected[$file] : 0;
    if ($count > $was) {
        $grew[] = "$file: $was -> $count";
    } elseif ($count < $was) {
        $fell[] = "$file: $was -> $count";
    }
}
foreach ($expected as $file => $was) {
    if (!isset($counts[$file])) {
        $fell[] = "$file: $was -> 0";
    }
}

if ($fell) {
    echo "\n" . count($fell) . " file(s) have fewer than the baseline -- lower it with --accept:\n";
    foreach ($fell as $f) {
        echo "  - $f\n";
    }
}
if ($grew) {
    echo "\nFAIL: " . count($grew) . " file(s) have more unbound queries than the baseline:\n";
    foreach ($grew as $f) {
        echo "  + $f\n";
    }
    echo "\nBind the values: mb_query(\$db, \"... WHERE x=?\", array(\$x)). See app/include/db.php.\n"
       . "php tests/unbound-queries-audit.php --list <file> shows each call.\n";
    exit(1);
}

echo "PASS: no file has more unbound queries than the baseline.\n";
exit(0);
