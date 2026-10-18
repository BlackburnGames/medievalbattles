<?php
/**
 * Run a Phinx command against both databases: the accounts, then the round.
 *
 *   docker compose exec web php /repo/bin/migrate.php status
 *   docker compose exec web php /repo/bin/migrate.php migrate
 *   docker compose exec web php /repo/bin/migrate.php create AddRoundId round
 *
 * Any other arguments go to Phinx as they are. `create` makes a migration in
 * the one database named after it, and the class name comes before it.
 *
 * The databases are the MB_DB_NAME and MB_ACCOUNTS_DB_NAME the app uses
 * (db/phinx/config.php). A new database starts from the baseline migration;
 * the fixture is built from db.sql instead (tests/reset-db.sh) and has no
 * phinxlog, so this is for real databases, and for tests/migrations.sh. A
 * schema change is a migration *and* the same change to db.sql or
 * db/accounts.sql, and that test fails until the two agree.
 *
 * Exits non-zero, and stops before the round, if the accounts' run fails.
 * `status` reports both, and exits non-zero if either has anything pending.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root  = dirname(__DIR__);
$phinx = $root . '/vendor/bin/phinx';
if (!is_file($phinx)) {
    fwrite(STDERR, "vendor/ is missing: run composer install first.\n");
    exit(2);
}

$args = array_slice($argv, 1);
if (!$args) {
    fwrite(STDERR, "usage: php bin/migrate.php status | migrate [phinx options] | create <Name> round|accounts\n");
    exit(2);
}

$targets = array('accounts', 'round');
if ($args[0] === 'create') {
    if (count($args) < 3 || !in_array($args[2], $targets, true)) {
        fwrite(STDERR, "usage: php bin/migrate.php create <Name> round|accounts\n");
        exit(2);
    }
    $targets = array($args[2]);
    array_splice($args, 2, 1);
}

// status exits non-zero while anything is pending, which is an answer rather
// than a failure: it reports both databases and exits with the worse.
$worst = 0;
foreach ($targets as $which) {
    echo "== $which\n";
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phinx);
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg($a);
    }
    $cmd .= ' -c ' . escapeshellarg("$root/db/phinx/$which.php");
    passthru($cmd, $status);
    if ($status !== 0 && $args[0] !== 'status') {
        exit($status);
    }
    $worst = max($worst, $status);
}
exit($worst);
