<?php
/**
 * A database as text: every table's CREATE TABLE and every row, in an order
 * that depends on nothing but the contents. tests/migrations.sh dumps a
 * database built from db.sql and one built by the migrations, and diffs them.
 *
 *   php tests/schema-dump.php <database>
 *
 * phinxlog is left out -- it is the one table only the migrated side has --
 * and so are AUTO_INCREMENT counters, which say how many rows were ever
 * inserted, not what the schema is.
 */

if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    fwrite(STDERR, "usage: php tests/schema-dump.php <database>\n");
    exit(2);
}

$host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : 'db';
$user = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
$pass = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';

$db = @mysqli_connect($host, $user, $pass, $argv[1]);
if (!$db) {
    fwrite(STDERR, "Cannot connect: " . mysqli_connect_error() . "\n");
    exit(2);
}
require_once __DIR__ . '/../app/include/db.php';
mb_db_session($db);

$tables = array();
$r = mysqli_query($db, "SHOW TABLES");
while ($row = mysqli_fetch_row($r)) {
    if ($row[0] !== 'phinxlog') {
        $tables[] = $row[0];
    }
}
sort($tables);

foreach ($tables as $t) {
    $create = mysqli_fetch_row(mysqli_query($db, "SHOW CREATE TABLE `$t`"))[1];
    echo preg_replace('/ AUTO_INCREMENT=\d+/', '', $create), ";\n";

    $n = mysqli_num_fields(mysqli_query($db, "SELECT * FROM `$t` LIMIT 0"));
    $r = mysqli_query($db, "SELECT * FROM `$t` ORDER BY " . implode(', ', range(1, $n)));
    while ($row = mysqli_fetch_row($r)) {
        echo "  ", implode("\t", array_map(function ($v) {
            return $v === null ? 'NULL' : addcslashes($v, "\\t\n\r");
        }, $row)), "\n";
    }
    echo "\n";
}
