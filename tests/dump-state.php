<?php
/**
 * Dump the whole database as stable, line-oriented text for golden-master
 * comparison.
 *
 * The database is the real output of this game: the tick's job is to move
 * numbers, so a state diff localises a regression to an exact table, row and
 * column in a way an HTML diff never could.
 *
 * Usage (from the repo root):
 *   docker compose exec -T web php /repo/tests/dump-state.php
 */

$host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : 'db';
$name = getenv('MB_DB_NAME') ? getenv('MB_DB_NAME') : 'mbv6';
$user = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
$pass = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';

$db = @mysqli_connect($host, $user, $pass, $name);
if (!$db) {
    fwrite(STDERR, "Cannot connect: " . mysqli_connect_error() . "\n");
    exit(2);
}
mysqli_set_charset($db, 'utf8mb4');

/*
 * Columns whose value is wall-clock or environment dependent. They are
 * replaced rather than dropped so that a column changing from "set" to
 * "empty" is still visible in the diff.
 */
$VOLATILE_COLUMNS = array('ip', 'lastlogin', 'lastlog', 'date', 'datestamp');

$tables = array();
$result = mysqli_query($db, 'SHOW TABLES');
while ($row = mysqli_fetch_row($result)) {
    $tables[] = $row[0];
}
sort($tables);

foreach ($tables as $table) {
    $columns = array();
    $meta = mysqli_query($db, "SHOW COLUMNS FROM `$table`");
    while ($row = mysqli_fetch_assoc($meta)) {
        $columns[] = $row['Field'];
    }

    // Order by every column: most of these tables have no primary key, so
    // storage order is not guaranteed and would make diffs spuriously noisy.
    $orderBy = range(1, count($columns));

    $rows = mysqli_query($db, "SELECT * FROM `$table` ORDER BY " . implode(',', $orderBy));
    $count = $rows ? mysqli_num_rows($rows) : 0;

    echo "== $table ($count row" . ($count === 1 ? '' : 's') . ") ==\n";

    if (!$rows) {
        echo "  !! query failed: " . mysqli_error($db) . "\n";
        continue;
    }

    while ($row = mysqli_fetch_assoc($rows)) {
        $parts = array();
        foreach ($row as $column => $value) {
            $parts[] = $column . '=' . normalise($column, $value, $VOLATILE_COLUMNS);
        }
        echo '  ' . implode(' | ', $parts) . "\n";
    }
}

function normalise($column, $value, array $volatileColumns)
{
    if ($value === null) {
        return '<NULL>';
    }

    if (in_array(strtolower($column), $volatileColumns, true) && $value !== '') {
        return '<VOLATILE>';
    }

    // clock.php format, e.g. "8/1/26, 2:56pm"
    if (preg_match('#^\d{1,2}/\d{1,2}/\d{2}, \d{1,2}:\d{2}[ap]m$#', $value)) {
        return '<CLOCK>';
    }
    // commong.php datestamp format, e.g. "01 August 14:56"
    if (preg_match('/^\d{2} [A-Za-z]+ \d{2}:\d{2}$/', $value)) {
        return '<DATE>';
    }

    // Collapse newlines so every row stays on one line.
    return str_replace(array("\r", "\n"), array('', '\\n'), $value);
}
