<?php
/**
 * The machinery the golden masters for page actions share: tests/combat-golden.php
 * and tests/actions-golden.php.
 *
 * A scenario starts from the fixture world -- db.sql and db/seed.sql,
 * reloaded, the same world the tick golden master is computed from -- applies
 * its `setup` SQL, logs in as the tester, and makes its requests over HTTP.
 * What is printed is what the page said and every column that changed in
 * every table, as before -> after. The database is the real output, as in the
 * tick golden master, but here only the movement is shown, because the
 * movement is the action.
 *
 * It reloads the schema, so it destroys whatever world it runs against.
 */

require_once __DIR__ . '/client.php';

$BASE = getenv('MB_BASE_URL') ? getenv('MB_BASE_URL') : 'http://localhost';

$host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : 'db';
$name = getenv('MB_DB_NAME') ? getenv('MB_DB_NAME') : 'mbv6';
$user = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
$pass = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';

$db = @mysqli_connect($host, $user, $pass, $name);
if (!$db) {
    fwrite(STDERR, "Cannot connect: " . mysqli_connect_error() . "\n");
    exit(2);
}
require_once __DIR__ . '/../../app/include/db.php';
mb_db_session($db);

/*
 * Columns left out of the comparison, all of them wall-clock. `pw` used to be
 * one too, because the login before each scenario rehashed the tester's MD5
 * into a salted bcrypt in all six tables. The round holds no credential now.
 */
$IGNORED_COLUMNS = array('ip', 'lastlogin', 'lastlog', 'date', 'datestamp');

/** Set columns on one empire's row in one table. Returns SQL for a `setup`. */
function set($table, $userid, array $columns)
{
    $parts = array();
    foreach ($columns as $column => $value) {
        $parts[] = "`$column`=" . (is_int($value) ? $value : "'" . addslashes($value) . "'");
    }
    return "UPDATE `$table` SET " . implode(', ', $parts) . " WHERE userid=$userid";
}

/**
 * Research the projects named, by points: enough to finish any of them
 * (r13 Archery, r16 Advanced Construction, r17 Animation, r18 Advanced Animation).
 */
function researched($userid, array $projects)
{
    $columns = array();
    foreach ($projects as $p) {
        $columns["r{$p}pts"] = 125000;
    }
    return set('research', $userid, $columns);
}

/* ------------------------------------------------------------------ *
 * Run.
 * ------------------------------------------------------------------ */

/**
 * A request that runs the tick rather than a page: array(TICK, array()).
 * Everything the tick touches moves -- every empire's gold and food, every
 * timer -- and that is the tick golden master's to pin, so a scenario that
 * ticks names the columns it is about in `show`, as table => columns.
 */
const TICK = '(tick)';

/**
 * Run every scenario, or only the one named on the command line. $fields, if
 * given, maps a page and its fields to what is actually posted -- the combat
 * pages all want a submit flag the scenarios would otherwise each repeat.
 */
function runScenarios(array $scenarios, $fields = null)
{
    global $argv, $db, $BASE;

    $only = isset($argv[1]) ? $argv[1] : null;

    foreach ($scenarios as $s) {
        if ($only !== null && $s['name'] !== $only) {
            continue;
        }

        loadFixture($db);
        foreach ($s['setup'] as $sql) {
            if (!mysqli_query($db, $sql)) {
                fwrite(STDERR, "FATAL: setup for {$s['name']} failed: " . mysqli_error($db) . "\n  $sql\n");
                exit(2);
            }
        }

        // After the setup, so the login sees the setup, and before the first
        // snapshot, so the login's own writes (the rehash, lastlogin) are not
        // reported as the action.
        // The tester unless the scenario names another fixture login, as
        // array(email, password) -- a guard has to be driven from the arm it
        // refuses, and the tester leads everything.
        list($as, $password) = isset($s['login']) ? $s['login'] : array('tester@example.com', 'test1234');
        $client = login($BASE, $as, $password);

        echo "### {$s['name']}\n";
        echo "# {$s['why']}\n";

        foreach ($s['requests'] as $req) {
            list($page, $posted) = $req;

            $before = snapshot($db);
            if ($page === TICK) {
                $log = runTick();
                $after = snapshot($db);
                echo "\n> tick\n";
                foreach (tickDiagnostics($log) as $line) {
                    echo "  php:  $line\n";
                }
            } else {
                if ($fields !== null) {
                    $posted = $fields($page, $posted);
                }
                $body = $client->post('/' . $page, $posted);
                $after = snapshot($db);

                echo "\n> $page " . http_build_query($posted, '', ' ') . "\n";
                if ($client->lastStatus !== 200) {
                    echo "  HTTP {$client->lastStatus}\n";
                }
                foreach (pageSays($body) as $line) {
                    echo "  says: $line\n";
                }
                foreach (diagnostics($body) as $line) {
                    echo "  php:  $line\n";
                }
            }
            if (isset($s['show'])) {
                $before = only($before, $s['show']);
                $after = only($after, $s['show']);
            }
            $changes = changes($before, $after);
            foreach ($changes as $line) {
                echo "  $line\n";
            }
            if (!$changes) {
                echo "  (no change)\n";
            }
        }
        echo "\n";
    }
}

/* ------------------------------------------------------------------ *
 * The fixture.
 * ------------------------------------------------------------------ */

/**
 * The fixture world, from scratch: every table dropped -- the app creates
 * tables at runtime that db.sql does not know -- and db.sql and db/seed.sql
 * loaded, as tests/reset-db.sh does. That script needs the database's root
 * and runs from the host; this runs per scenario, inside the container.
 */
function loadFixture($db)
{
    // The accounts with it, being in the same database: an account left over
    // from a scenario -- a signup, an address change -- would otherwise log
    // into the next one's kingdoms.
    loadDatabase($db, array(__DIR__ . '/../../db.sql', __DIR__ . '/../../db/seed.sql'));
}

/** Drop every table in a database and load these files into it. */
function loadDatabase($conn, array $files)
{
    $result = mysqli_query($conn, 'SHOW TABLES');
    $tables = array();
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = '`' . $row[0] . '`';
    }
    if ($tables) {
        mysqli_query($conn, 'DROP TABLE ' . implode(', ', $tables));
    }

    foreach ($files as $file) {
        if (!mysqli_multi_query($conn, file_get_contents($file))) {
            fwrite(STDERR, "FATAL: loading $file: " . mysqli_error($conn) . "\n");
            exit(2);
        }
        do {
            if ($r = mysqli_store_result($conn)) {
                mysqli_free_result($r);
            }
            if (mysqli_errno($conn)) {
                fwrite(STDERR, "FATAL: loading $file: " . mysqli_error($conn) . "\n");
                exit(2);
            }
        } while (mysqli_more_results($conn) && mysqli_next_result($conn));
        if (mysqli_errno($conn)) {
            fwrite(STDERR, "FATAL: loading $file: " . mysqli_error($conn) . "\n");
            exit(2);
        }
    }
}

function login($base, $email, $pass)
{
    // The GET primes the computer_id cookie checklogin.php expects; see smoke.php.
    $client = new MbClient($base);
    $client->get('/index.php');
    $client->post('/checklogin.php', array('email' => $email, 'pw' => $pass, 'login' => 'Login'));
    if ($client->lastStatus !== 302) {
        fwrite(STDERR, "FATAL: login as $email did not redirect (got HTTP {$client->lastStatus}).\n");
        exit(2);
    }
    return $client;
}

/* ------------------------------------------------------------------ *
 * Observing a request.
 * ------------------------------------------------------------------ */

/**
 * Every table, as rows. A table with a userid column is keyed on it, so a
 * change reads as columns on an empire; any other table -- the news tables,
 * mostly -- is a bag of rows, and a change reads as rows added or removed.
 */
function snapshot($db)
{
    global $IGNORED_COLUMNS;

    $state = array();
    $result = mysqli_query($db, 'SHOW TABLES');
    $tables = array();
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    sort($tables);

    foreach ($tables as $table) {
        $rows = mysqli_query($db, "SELECT * FROM `$table`");
        $keyed = array();
        $bag = array();
        while ($row = mysqli_fetch_assoc($rows)) {
            foreach ($IGNORED_COLUMNS as $column) {
                unset($row[$column]);
            }
            foreach ($row as $column => $value) {
                $row[$column] = normalise($value);
            }
            if (array_key_exists('userid', $row) && !isset($keyed[$row['userid']])) {
                $keyed[$row['userid']] = $row;
            } else {
                $bag[] = rowText($row);
            }
        }
        sort($bag);
        $state[$table] = array('keyed' => $keyed, 'bag' => $bag);
    }
    return $state;
}

function changes(array $before, array $after)
{
    $lines = array();
    foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
        $b = isset($before[$table]) ? $before[$table] : array('keyed' => array(), 'bag' => array());
        $a = isset($after[$table]) ? $after[$table] : array('keyed' => array(), 'bag' => array());

        $ids = array_unique(array_merge(array_keys($b['keyed']), array_keys($a['keyed'])));
        sort($ids);
        foreach ($ids as $id) {
            if (!isset($a['keyed'][$id])) {
                $lines[] = "- $table [$id]";
                continue;
            }
            if (!isset($b['keyed'][$id])) {
                $lines[] = "+ $table [$id] " . rowText($a['keyed'][$id]);
                continue;
            }
            $moved = array();
            foreach ($a['keyed'][$id] as $column => $value) {
                $old = isset($b['keyed'][$id][$column]) ? $b['keyed'][$id][$column] : '<absent>';
                if ($old !== $value) {
                    $moved[] = "$column $old -> $value";
                }
            }
            if ($moved) {
                $lines[] = "  $table [$id] " . implode(', ', $moved);
            }
        }

        foreach (bagDiff($b['bag'], $a['bag']) as $row) {
            $lines[] = "- $table $row";
        }
        foreach (bagDiff($a['bag'], $b['bag']) as $row) {
            $lines[] = "+ $table $row";
        }
    }
    return $lines;
}

/** The rows of $x that are not in $y, counting duplicates. */
function bagDiff(array $x, array $y)
{
    $out = array();
    foreach ($x as $row) {
        $i = array_search($row, $y, true);
        if ($i === false) {
            $out[] = $row;
        } else {
            unset($y[$i]);
        }
    }
    return $out;
}

function rowText(array $row)
{
    $parts = array();
    foreach ($row as $column => $value) {
        $parts[] = "$column=$value";
    }
    return implode(' | ', $parts);
}

/** As tests/dump-state.php: wall-clock values masked, rows kept on one line. */
function normalise($value)
{
    if ($value === null) {
        return '<NULL>';
    }
    if (preg_match('#^\d{1,2}/\d{1,2}/\d{2}, \d{1,2}:\d{2}[ap]m$#', $value)) {
        return '<CLOCK>';
    }
    return str_replace(array("\r", "\n"), array('', '\\n'), $value);
}

/**
 * What the page told the player: the centred messages after the site-wide
 * banner, as text. The forms that follow -- the combat drop-down and unit
 * table, the construction table -- are the same on every response and say
 * nothing about the action.
 */
function pageSays($body)
{
    $marker = "Don't get your hopes up, however.";
    $at = strpos($body, $marker);
    if ($at === false) {
        // PHP's own messages are reported by diagnostics(), and quoting them
        // here would churn the golden on every edit that moves a line number.
        $text = preg_replace('/(Warning|Notice|Deprecated|Fatal error):.*?on line \d+/s', '', strip_tags($body));
        $text = trim(preg_replace('/\s+/', ' ', $text));
        return array($text === '' ? '<no game page>' : '<no game page: ' . substr($text, 0, 120) . '>');
    }
    $body = substr($body, $at + strlen($marker));
    // The diagnostics are reported on their own.
    $body = preg_replace('#<br />\s*<b>[A-Za-z ]+</b>:.*?<br />#s', '', $body);

    // A win's message div is never closed: the drop-down runs straight on.
    preg_match_all('#<div align=center>(.*?)(?=</div>|<select|<form|<font class=inner2>You are viewing)#s', $body, $m);
    $says = array();
    foreach ($m[1] as $html) {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<br>', ' ', $html)))));
        if ($text !== '') {
            $says[] = $text;
        }
    }
    return $says;
}

/**
 * PHP's diagnostics, where they mean something to an action: errors, and the
 * warnings that are a failed query. Not reads of unassigned names -- PHP 8
 * calls those warnings too, but what one does to the game is already in the
 * changes printed below it, and listing them here would churn this file on
 * every commit that starts a tally from zero. They are the crawl's business,
 * as are notices and deprecations. Line numbers are dropped for the same
 * reason.
 */
function diagnostics($body)
{
    preg_match_all('#<b>(Warning|Fatal error|Parse error)</b>:\s*(.*?) in <b>(?:/var/www/html/|/repo/app/)?([^<]*)</b> on line#s',
        $body, $m, PREG_SET_ORDER);
    $out = array();
    foreach ($m as $hit) {
        $message = trim(strip_tags($hit[2]));
        if (preg_match('/^(Undefined (variable|array key|global variable)|Trying to access array offset)/', $message)) {
            continue;
        }
        // An uncaught exception carries its own location and a stack trace.
        $message = preg_replace('#\s+in /\S+:\d+\s+Stack trace:.*$#s', '', $message);
        // A failed query quotes itself, credential and all, and the login has
        // just rehashed that into a salted bcrypt.
        $message = preg_replace('#\$2y\$[^\' ]*#', '<HASH>', $message);
        $line = "{$hit[1]}: $message ({$hit[3]})";
        if (!in_array($line, $out, true)) {
            $out[] = $line;
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ *
 * The tick.
 * ------------------------------------------------------------------ */

/** Run update.php as the cron would -- from the repo root, which it needs. */
function runTick()
{
    $root = realpath(__DIR__ . '/../..');
    return (string) shell_exec('cd ' . escapeshellarg($root) . ' && php update.php 2>&1');
}

/**
 * The tick's diagnostics, on the same terms as a page's: errors and failed
 * queries, not reads of unassigned names, deduplicated and without line
 * numbers. The CLI may print each twice, once to the log and once inline.
 */
function tickDiagnostics($log)
{
    $text = strip_tags($log);
    preg_match_all('#(?:PHP )?(Warning|Fatal error|Parse error):\s*(.*?) in (?:/repo/)?(\S+?)(?::\d+| on line \d+)#', $text, $m, PREG_SET_ORDER);
    $out = array();
    foreach ($m as $hit) {
        $message = trim($hit[2]);
        if ($hit[1] === 'Warning' && strpos($message, 'mysqli_') === false) {
            continue;
        }
        $message = preg_replace('#\s+in /\S+:\d+\s+Stack trace:.*$#s', '', $message);
        $line = "{$hit[1]}: $message ({$hit[3]})";
        if (!in_array($line, $out, true)) {
            $out[] = $line;
        }
    }
    return $out;
}

/** A snapshot cut down to the tables and columns named, as table => columns. */
function only(array $state, array $show)
{
    $out = array();
    foreach ($show as $table => $columns) {
        if (!isset($state[$table])) {
            continue;
        }
        $keep = array_flip($columns);
        $keyed = array();
        foreach ($state[$table]['keyed'] as $id => $row) {
            $keyed[$id] = array_intersect_key($row, $keep);
        }
        $out[$table] = array('keyed' => $keyed, 'bag' => array());
    }
    return $out;
}
