<?php
/**
 * Rewrite interpolated queries into mb_query() calls with bound values.
 *
 *   php tools/bind-queries.php app/foo.php ...           # report, change nothing
 *   php tools/bind-queries.php --write app/foo.php ...   # rewrite in place
 *   php tools/bind-queries.php --bare app/foo.php ...    # list unquoted values
 *
 * For each mysqli_query($db, ...) or $db->query(...) whose SQL is built from
 * string pieces, interpolated variables and mb_sql_str() / mb_sql_int() calls,
 * every value becomes a ? and moves into the params array:
 *
 *   mysqli_query($db, "UPDATE user SET gp='$gp' WHERE userid=" . mb_sql_int($id))
 *   mb_query($db, "UPDATE user SET gp=? WHERE userid=?", array($gp, (int) $id))
 *
 * It is conservative on purpose: a call is converted only when every value in
 * it is unambiguously a value. It is left alone, and reported with the reason,
 * when a variable stands where a table or column name goes, sits inside quotes
 * with other text ('%$x%'), is concatenated in any other form, when the SQL is
 * a variable built elsewhere, or when the SQL already contains a ?. Those are
 * for converting by hand.
 *
 * What the rules mean for a value:
 *
 *   '$x'  or \"$x\"     quoted in the SQL: bound as it is
 *   =$x, ($x, $x + 1 ... a bare number: bound as it is (as int after LIMIT)
 *   mb_sql_str($db, X)  bound as X
 *   mb_sql_int(X)       bound as (int) X
 *
 * A bare value is only bound in a WHERE comparison or after LIMIT. In a SET or
 * in arithmetic it is left, because mb_query() writes a non-integer value as a
 * quoted string, and MySQL computes with a quoted '2.5' as a double where it
 * computed with a bare 2.5 as an exact decimal -- ROUND() of the two can differ.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$write = false;
$bare = false;
$files = array();
$MB_BARE = array();
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--bare') {
        // List every unquoted value it binds, with the SQL either side, so a
        // reviewer can confirm none of them is a name.
        $bare = true;
    } elseif ($a === '--write') {
        $write = true;
    } else {
        $files[] = $a;
    }
}
if (!$files) {
    fwrite(STDERR, "usage: php tools/bind-queries.php [--write] file...\n");
    exit(2);
}

$totalConverted = 0;
$totalSkipped = 0;
foreach ($files as $file) {
    $src = file_get_contents($file);
    list($out, $converted, $skipped) = mb_bind_file($src);
    $totalConverted += count($converted);
    $totalSkipped += count($skipped);

    echo "== $file: " . count($converted) . " converted, " . count($skipped) . " left\n";
    foreach ($skipped as $s) {
        echo "   left  line {$s[0]}: {$s[1]}\n";
    }
    if ($write && $converted) {
        file_put_contents($file, $out);
    }
}
if ($bare) {
    echo "\nUnquoted values bound:\n";
    foreach ($MB_BARE as $b) {
        echo "   $b\n";
    }
}
echo "\n$totalConverted converted, $totalSkipped left" . ($write ? '' : ' (dry run: nothing written)') . "\n";

/* ------------------------------------------------------------------ */

function mb_tok_text($x)
{
    return is_array($x) ? $x[1] : $x;
}

function mb_skip_ws(array $t, $i, $dir = 1)
{
    $n = count($t);
    while ($i >= 0 && $i < $n && is_array($t[$i]) && in_array($t[$i][0], array(T_WHITESPACE, T_COMMENT), true)) {
        $i += $dir;
    }
    return $i;
}

/** Index of the token that closes the bracket opened at $i. */
function mb_match(array $t, $i)
{
    $depth = 0;
    $n = count($t);
    for ($k = $i; $k < $n; $k++) {
        $x = $t[$k];
        if ($x === '(' || $x === '[' || $x === '{'
            || (is_array($x) && in_array($x[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
            $depth++;
        } elseif ($x === ')' || $x === ']' || $x === '}') {
            $depth--;
            if ($depth === 0) {
                return $k;
            }
        }
    }
    return -1;
}

/** Split the tokens between $a and $b (exclusive) on top-level commas. */
function mb_args(array $t, $a, $b)
{
    $args = array(array());
    $depth = 0;
    for ($k = $a; $k < $b; $k++) {
        $x = $t[$k];
        if ($x === '(' || $x === '[' || $x === '{'
            || (is_array($x) && in_array($x[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
            $depth++;
        } elseif ($x === ')' || $x === ']' || $x === '}') {
            $depth--;
        } elseif ($x === ',' && $depth === 0) {
            $args[] = array();
            continue;
        }
        $args[count($args) - 1][] = $x;
    }
    return $args;
}

function mb_trim_tokens(array $toks)
{
    while ($toks && is_array($toks[0]) && $toks[0][0] === T_WHITESPACE) {
        array_shift($toks);
    }
    while ($toks && is_array(end($toks)) && end($toks)[0] === T_WHITESPACE) {
        array_pop($toks);
    }
    return $toks;
}

function mb_text(array $toks)
{
    $s = '';
    foreach ($toks as $x) {
        $s .= mb_tok_text($x);
    }
    return trim($s);
}

/** A single-quoted PHP literal's content, re-escaped for a double-quoted one. */
function mb_single_to_double($lit)
{
    $inner = substr($lit, 1, -1);
    $inner = str_replace(array("\\'", "\\\\"), array("'", "\\"), $inner);
    return addcslashes($inner, "\\\"\$");
}

/**
 * The SQL argument as a list of pieces: array('text', raw double-quoted text)
 * or array('value', php expression, kind) with kind 'interp', 'str' or 'int'.
 * Returns a string -- the reason -- when it cannot be read that way.
 */
function mb_pieces(array $toks)
{
    $pieces = array();
    $n = count($toks);
    $i = 0;
    while ($i < $n) {
        $x = $toks[$i];
        if (is_array($x) && in_array($x[0], array(T_WHITESPACE, T_COMMENT), true)) {
            $i++;
            continue;
        }
        if ($x === '.') {
            $i++;
            continue;
        }
        if (is_array($x) && $x[0] === T_CONSTANT_ENCAPSED_STRING) {
            $raw = $x[1];
            $pieces[] = array('text', $raw[0] === '"' ? substr($raw, 1, -1) : mb_single_to_double($raw));
            $i++;
            continue;
        }
        if ($x === '"') {
            $i++;
            while ($i < $n && $toks[$i] !== '"') {
                $y = $toks[$i];
                if (is_array($y) && $y[0] === T_ENCAPSED_AND_WHITESPACE) {
                    $pieces[] = array('text', $y[1]);
                    $i++;
                } elseif (is_array($y) && $y[0] === T_VARIABLE) {
                    $expr = $y[1];
                    $i++;
                    if ($i < $n && $toks[$i] === '[') {
                        $key = $toks[$i + 1];
                        if (is_array($key) && $key[0] === T_STRING) {
                            $expr .= "['" . $key[1] . "']";
                        } elseif (is_array($key) && in_array($key[0], array(T_NUM_STRING, T_VARIABLE), true)) {
                            $expr .= '[' . $key[1] . ']';
                        } else {
                            return 'an array index it cannot read';
                        }
                        $i += 3;
                    } elseif ($i + 1 < $n && is_array($toks[$i]) && $toks[$i][0] === T_OBJECT_OPERATOR) {
                        $expr .= '->' . $toks[$i + 1][1];
                        $i += 2;
                    }
                    $pieces[] = array('value', $expr, 'interp');
                } elseif (is_array($y) && $y[0] === T_CURLY_OPEN) {
                    $close = mb_match($toks, $i);
                    if ($close < 0) {
                        return 'an unbalanced {$...}';
                    }
                    $pieces[] = array('value', mb_text(array_slice($toks, $i + 1, $close - $i - 1)), 'interp');
                    $i = $close + 1;
                } else {
                    return 'interpolation it cannot read (' . mb_tok_text($y) . ')';
                }
            }
            $i++; // closing quote
            continue;
        }
        if (is_array($x) && $x[0] === T_STRING && in_array(strtolower($x[1]), array('mb_sql_str', 'mb_sql_int'), true)) {
            $open = mb_skip_ws($toks, $i + 1);
            if ($open >= $n || $toks[$open] !== '(') {
                return 'a bare ' . $x[1];
            }
            $close = mb_match($toks, $open);
            $args = mb_args($toks, $open + 1, $close);
            if (strtolower($x[1]) === 'mb_sql_str') {
                if (count($args) !== 2) {
                    return 'an mb_sql_str() it cannot read';
                }
                $pieces[] = array('value', mb_text($args[1]), 'str');
            } else {
                $pieces[] = array('value', mb_text($args[0]), 'int');
            }
            $i = $close + 1;
            continue;
        }
        if (is_array($x) && $x[0] === T_VARIABLE) {
            return 'a variable concatenated in (' . $x[1] . ')';
        }
        return 'an expression it cannot read (' . mb_tok_text($x) . ')';
    }
    return $pieces;
}

/** The SQL text and params for a piece list, or a reason string. */
function mb_build(array $pieces)
{
    $sql = '';
    $params = array();
    $count = count($pieces);

    for ($p = 0; $p < $count; $p++) {
        $piece = $pieces[$p];
        if ($piece[0] === 'text') {
            $sql .= $piece[1];
            continue;
        }
        list(, $expr, $kind) = $piece;
        $next = ($p + 1 < $count && $pieces[$p + 1][0] === 'text') ? $pieces[$p + 1][1] : '';

        // A variable that holds an mb_sql_str() / mb_sql_int() result is an
        // escaped literal, quotes and all; binding it would store the quotes.
        // Bind what it was made from instead -- mb_escaped_vars() says when
        // that is certain to be the same value.
        global $MB_ESCAPED;
        if ($kind === 'interp' && preg_match('/^\$(\w+)$/', $expr, $m) && array_key_exists($m[1], $MB_ESCAPED)) {
            if ($MB_ESCAPED[$m[1]] === null) {
                return "an escaped variable whose source it cannot pin down ($expr)";
            }
            list($kind, $expr) = $MB_ESCAPED[$m[1]];
            $GLOBALS['MB_ESCAPED_USED'][$m[1]] = true;
        }
        if ($p + 1 < $count && $pieces[$p + 1][0] === 'value') {
            return 'two values side by side';
        }

        if ($kind === 'str' || $kind === 'int') {
            if (preg_match('/[\'"]$/', $sql)) {
                return 'an escaped value inside quotes';
            }
            $sql .= '?';
            $params[] = $kind === 'int' ? "(int) $expr" : $expr;
            continue;
        }

        // Interpolated. Quoted: the text either side is exactly the quote.
        foreach (array("'", '\\"') as $q) {
            if (substr($sql, -strlen($q)) === $q && substr($next, 0, strlen($q)) === $q) {
                $sql = substr($sql, 0, -strlen($q)) . '?';
                $pieces[$p + 1][1] = substr($next, strlen($q));
                $params[] = $expr;
                continue 2;
            }
        }
        // Inside quotes alongside other text, or touching a quote on one side.
        $quotesBefore = substr_count(str_replace('\\"', '"', $sql), "'");
        if ($quotesBefore % 2 === 1 || preg_match('/[\'"]$/', $sql) || preg_match('/^[\'"\\\\]/', $next)) {
            return "a value inside quotes with other text ($expr)";
        }
        // Bare: only as the whole right-hand side of a comparison in a WHERE
        // clause, or after LIMIT / OFFSET. Never where a name may go, and never
        // in a SET or in arithmetic -- MySQL reads a bare number as an exact
        // decimal and a quoted one, which is what mb_query() writes for anything
        // but an int, as a double, and ROUND() of the two can differ. Those are
        // left for a person to look at.
        $before = rtrim($sql);
        $after = ltrim($next);
        if (preg_match('/^[A-Za-z0-9_.]/', $next)) {
            return "a variable where a name may go ($expr)";
        }
        $limit = preg_match('/\b(LIMIT|OFFSET)$/i', $before);
        $clause = preg_match_all('/\b(SET|WHERE|VALUES|LIMIT)\b/i', $before, $kw) ? strtoupper(end($kw[1])) : '';
        $comparison = preg_match('/(=|<|>)$/', $before)
            && !preg_match('/[-+*\/]\s*[\w.\'"]*\s*(=|<|>)?$/', substr($before, 0, -1))
            && preg_match('/^(\)\s*)?($|AND\b|OR\b|ORDER\b|LIMIT\b|GROUP\b|\))/i', $after);
        if (!$limit && !($clause === 'WHERE' && $comparison)) {
            return preg_match('/(=|<|>|\(|,|\+|-|\*|\/)$/', $before)
                ? "a bare number in a SET or in arithmetic ($expr)"
                : "a variable where a name may go ($expr)";
        }
        global $MB_BARE;
        $MB_BARE[] = substr($before, -40) . ' [' . $expr . '] ' . substr($after, 0, 30);
        $sql .= '?';
        $params[] = preg_match('/\b(LIMIT|OFFSET)$/i', $before) ? "(int) $expr" : $expr;
    }

    return array($sql, $params);
}

/**
 * Variables assigned an mb_sql_str() or mb_sql_int() result, as
 * name => array(kind, source expression), or name => null when binding the
 * source instead would not be certain to bind the same value: the variable is
 * assigned more than once, or a variable in its source is assigned again
 * anywhere after it.
 *
 * Also returns, per name, the token range of its assignment statement, so an
 * assignment nothing reads any more can be removed.
 */
function mb_escaped_vars(array $t)
{
    $n = count($t);
    $map = array();
    $stmts = array();
    $assignedAt = array();   // every assignment position of every variable

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_VARIABLE) {
            continue;
        }
        $op = mb_skip_ws($t, $i + 1);
        if ($op >= $n) {
            continue;
        }
        $isAssign = $t[$op] === '=' || (is_array($t[$op]) && in_array($t[$op][0], array(
            T_CONCAT_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_INC, T_DEC), true));
        $prev = mb_skip_ws($t, $i - 1, -1);
        if ($prev >= 0 && is_array($t[$prev]) && in_array($t[$prev][0], array(T_INC, T_DEC), true)) {
            $isAssign = true;
        }
        if (!$isAssign) {
            continue;
        }
        $name = substr($t[$i][1], 1);
        $assignedAt[$name][] = $i;

        if ($t[$op] !== '=') {
            continue;
        }
        $fn = mb_skip_ws($t, $op + 1);
        if ($fn >= $n || !is_array($t[$fn]) || $t[$fn][0] !== T_STRING
            || !in_array(strtolower($t[$fn][1]), array('mb_sql_str', 'mb_sql_int'), true)) {
            continue;
        }
        $open = mb_skip_ws($t, $fn + 1);
        if ($open >= $n || $t[$open] !== '(') {
            continue;
        }
        $close = mb_match($t, $open);
        $semi = mb_skip_ws($t, $close + 1);
        if ($semi >= $n || $t[$semi] !== ';') {
            continue;
        }
        $args = mb_args($t, $open + 1, $close);
        $isStr = strtolower($t[$fn][1]) === 'mb_sql_str';
        if ($isStr && count($args) !== 2) {
            continue;
        }
        $src = $isStr ? mb_trim_tokens($args[1]) : mb_trim_tokens($args[0]);
        $vars = array();
        foreach ($src as $x) {
            if (is_array($x) && $x[0] === T_VARIABLE) {
                $vars[] = substr($x[1], 1);
            }
        }
        $map[$name] = array($isStr ? 'str' : 'int', mb_text($src), $i, $vars);
        $stmts[$name] = array($i, $semi);
    }

    $out = array();
    foreach ($map as $name => $info) {
        list($kind, $expr, $at, $vars) = $info;
        $certain = count($assignedAt[$name]) === 1;
        foreach ($vars as $v) {
            foreach (isset($assignedAt[$v]) ? $assignedAt[$v] : array() as $pos) {
                if ($pos > $at) {
                    $certain = false;
                }
            }
        }
        $out[$name] = $certain ? array($kind, $expr) : null;
    }
    return array($out, $stmts);
}

/**
 * Rewrite every convertible query call in a file.
 *
 * @return array(new source, converted lines, array(line, reason) skipped)
 */
function mb_bind_file($src)
{
    $t = token_get_all($src);
    $n = count($t);
    list($GLOBALS['MB_ESCAPED'], $escapedStmts) = mb_escaped_vars($t);
    $GLOBALS['MB_ESCAPED_USED'] = array();
    $edits = array();   // [start token, end token, replacement]
    $converted = array();
    $skipped = array();

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_STRING) {
            continue;
        }
        $name = strtolower($t[$i][1]);
        $prev = mb_skip_ws($t, $i - 1, -1);
        $isMethod = $prev >= 0 && is_array($t[$prev]) && $t[$prev][0] === T_OBJECT_OPERATOR;

        if ($isMethod && $name === 'query') {
            $recv = mb_skip_ws($t, $prev - 1, -1);
            if ($recv < 0 || !is_array($t[$recv]) || $t[$recv][0] !== T_VARIABLE) {
                continue;
            }
            $start = $recv;
            $conn = $t[$recv][1];
        } elseif (!$isMethod && $name === 'mysqli_query') {
            if ($prev >= 0 && is_array($t[$prev]) && $t[$prev][0] === T_FUNCTION) {
                continue;
            }
            $start = $i;
            $conn = null;
        } else {
            continue;
        }

        $open = mb_skip_ws($t, $i + 1);
        if ($open >= $n || $t[$open] !== '(') {
            continue;
        }
        $close = mb_match($t, $open);
        $args = mb_args($t, $open + 1, $close);
        $line = $t[$i][2];

        if ($conn === null) {
            if (count($args) !== 2) {
                $skipped[] = array($line, 'mysqli_query() with ' . count($args) . ' arguments');
                continue;
            }
            $conn = mb_text($args[0]);
            $sqlToks = mb_trim_tokens($args[1]);
        } else {
            if (count($args) !== 1) {
                $skipped[] = array($line, '->query() with ' . count($args) . ' arguments');
                continue;
            }
            $sqlToks = mb_trim_tokens($args[0]);
        }

        $hasValue = false;
        foreach ($sqlToks as $x) {
            if (is_array($x) && $x[0] === T_VARIABLE) {
                $hasValue = true;
            }
        }
        if (!$hasValue) {
            continue;
        }

        $pieces = mb_pieces($sqlToks);
        if (is_string($pieces)) {
            $skipped[] = array($line, $pieces);
            continue;
        }
        $raw = '';
        foreach ($pieces as $piece) {
            if ($piece[0] === 'text') {
                $raw .= $piece[1];
            }
        }
        if (strpos($raw, '?') !== false) {
            $skipped[] = array($line, 'the SQL already contains a ?');
            continue;
        }

        $built = mb_build($pieces);
        if (is_string($built)) {
            $skipped[] = array($line, $built);
            continue;
        }
        list($sql, $params) = $built;

        $replacement = 'mb_query(' . $conn . ', "' . $sql . '"'
            . ($params ? ', array(' . implode(', ', $params) . ')' : '') . ')';
        $edits[] = array($start, $close, $replacement);
        $converted[] = $line;
    }

    // Apply from the end so earlier token offsets stay valid.
    $out = $t;
    foreach (array_reverse($edits) as $e) {
        list($a, $b, $text) = $e;
        array_splice($out, $a, $b - $a + 1, array(array(T_INLINE_HTML, $text, 0)));
    }
    $src2 = '';
    foreach ($out as $x) {
        $src2 .= mb_tok_text($x);
    }

    // An escaped variable nothing reads any more: drop its assignment, when
    // that is a statement on a line of its own.
    foreach (array_keys($GLOBALS['MB_ESCAPED_USED']) as $name) {
        if (preg_match_all('/\$' . $name . '\b/', $src2) !== 1) {
            continue;
        }
        $src2 = preg_replace('/^[ \t]*\$' . $name . '\s*=\s*mb_sql_(?:str|int)\([^;\n]*\);[ \t]*(?:\/\/[^\n]*)?\r?\n/m', '', $src2, 1);
    }

    return array($src2, $converted, $skipped);
}
