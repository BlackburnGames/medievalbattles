<?php
/**
 * Round audit: every query on a round's table is confined to one round.
 *
 * Every round shares one database, and each table a round owns carries a
 * round_id (docs/rounds.md, "Where a round lives"). A query that forgets the
 * round does not fail -- it reads or rewrites every round at once, finished
 * ones included -- so the rule is held here, the way the injection audit held
 * the last one.
 *
 * A query passes if it filters on an id that is issued once across all rounds
 * -- a userid, a setid, a guild's gid or owner, a message or forum id -- or on
 * round_id itself. An INSERT passes if it names round_id. Anything else is an
 * entry, unless the line it is on, or the line above, says why it is meant to
 * span rounds:
 *
 *   // mb-round-ok: ids are issued across rounds
 *
 * account_id is deliberately not a key: an account has a kingdom in every
 * round it played. Nor is a name -- ename, gname, guild, seller -- because a
 * later round can reuse one.
 *
 * The SQL is read from every string literal that starts like a statement, or
 * with the FROM of one built by concatenation, so a query passed through a
 * variable is still seen. It is read loosely: a
 * filter anywhere in the statement counts, a subquery's included. The list
 * is a floor, as the other audits' are.
 *
 * Baselined in tests/round-scope.txt, which may only ever SHRINK. Entries
 * carry no line numbers, so an edit elsewhere in a file does not move them.
 *
 *   php tests/round-audit.php             # check against baseline
 *   php tests/round-audit.php --accept    # rewrite the baseline
 */

$repoRoot = dirname(__DIR__);
$baseline = __DIR__ . '/round-scope.txt';
$accept   = in_array('--accept', $argv, true);

require_once __DIR__ . '/taint-lib.php';

/** The tables a round owns: the ones db.sql gives a round_id. */
$ROUND_TABLES = array(
    'user', 'military', 'research', 'buildings', 'explore', 'returntbl',
    'barter', 'empnews', 'messages',
    'settlement', 'setnews', 'setforums', 'setforumsmsgs',
    'guild', 'guildmsgs', 'guildnews', 'guildrequests', 'guildthreads',
);

/** Ids no two rounds share, and round_id. Filtering on any confines a query. */
$ROUND_KEYS = array(
    'round_id', 'userid', 'yourid', 'applicant', 'gl_userid', 'owner',
    'setid', 'gid', 'barterid', 'mid', 'sid', 'gnid', 'topicid', 'messageid',
);

/**
 * Why a statement is not confined to a round, or null if it is.
 */
function mb_round_problem($sql)
{
    global $ROUND_TABLES, $ROUND_KEYS;

    preg_match_all('/\b(?:FROM|UPDATE|INTO|JOIN)\s+`?(\w+)`?/i', $sql, $m);
    $tables = array_intersect(array_map('strtolower', $m[1]), $ROUND_TABLES);
    if (!$tables) {
        return null;
    }
    if (preg_match('/^\s*(INSERT|REPLACE)\b/i', $sql)) {
        return preg_match('/\bround_id\b/i', $sql) ? null : 'insert names no round';
    }
    $keys = implode('|', $ROUND_KEYS);
    if (preg_match('/\bWHERE\b.*?(?<![\w.])(?:\w+\.)?(?:' . $keys . ')\s*(?:=|IN\b)/is', $sql)) {
        return null;
    }
    return 'filters on no round';
}

$entries = array();
$files = array_merge(
    mb_app_files($repoRoot . '/app'),
    array($repoRoot . '/update.php'),
    glob($repoRoot . '/bin/*.php')
);
foreach ($files as $path) {
    $path = str_replace('\\', '/', $path);
    $rel = ltrim(str_replace(str_replace('\\', '/', $repoRoot), '', $path), '/');
    $src = file_get_contents($path);
    $lines = explode("\n", $src);
    $seen = array();
    $tokens = token_get_all($src);
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        // A double-quoted string with a variable in it -- a column name from a
        // fixed list -- arrives in pieces. Put it back together, the variable
        // as its name, so a WHERE after it is still seen.
        if ($t === '"') {
            $text = '';
            for ($i++; $i < $n && $tokens[$i] !== '"'; $i++) {
                $text .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
            }
            $line = 0;
            for ($k = $i - 1; $k >= 0 && !$line; $k--) {
                if (is_array($tokens[$k])) {
                    $line = $tokens[$k][2];
                }
            }
            $t = array(T_CONSTANT_ENCAPSED_STRING, $text, $line);
        }
        if (!is_array($t) || $t[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $sql = trim(preg_replace('/\s+/', ' ', $t[1]), "\"' ");
        // A statement, or the tail of one built by concatenation.
        if (!preg_match('/^(SELECT|UPDATE|DELETE|INSERT|REPLACE|FROM)\b/i', $sql)) {
            continue;
        }
        $why = mb_round_problem($sql);
        if ($why === null) {
            continue;
        }
        $ln = $t[2];
        $here = isset($lines[$ln - 1]) ? $lines[$ln - 1] : '';
        $above = isset($lines[$ln - 2]) ? $lines[$ln - 2] : '';
        if (preg_match('/mb-round-ok:\s*\S/', $here) || preg_match('/^\s*(\/\/|\*|#)\s*mb-round-ok:\s*\S/', $above)) {
            continue;
        }
        // The same statement twice in a file is two entries.
        $key = "$rel\t$why\t$sql";
        $seen[$key] = isset($seen[$key]) ? $seen[$key] + 1 : 1;
        $entries[] = $seen[$key] > 1 ? "$key\t#{$seen[$key]}" : $key;
    }
}
sort($entries);

exit(mb_ratchet(array(
    'baseline' => $baseline,
    'current'  => $entries,
    'accept'   => $accept,
    'header'   => "# Queries on a round's tables that are not confined to one round --\n"
                . "# see tests/round-audit.php. May only SHRINK; regenerate with --accept.\n",
    'label'    => 'queries not confined to a round',
    'pass'     => 'no new query reaches across rounds.',
    'fail'     => 'new quer' . 'y/ies not confined to a round',
    'advice'   => "Filter on round_id (or a userid, setid or other id issued once across rounds),\n"
                . "name round_id in an INSERT, or say why with // mb-round-ok: <why>.\n",
    'format'   => function ($e) {
        $p = explode("\t", $e);
        return $p[0] . ': ' . $p[1] . ': ' . substr($p[2], 0, 110) . (isset($p[3]) ? ' ' . $p[3] : '');
    },
)));
