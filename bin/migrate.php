<?php
/**
 * Run a Phinx command against the game's database.
 *
 *   docker compose exec web php /repo/bin/migrate.php status
 *   docker compose exec web php /repo/bin/migrate.php migrate
 *   docker compose exec web php /repo/bin/migrate.php create AddRoundId
 *
 * Shorthand for vendor/bin/phinx with -c db/phinx.php, which reads the same
 * MB_DB_* settings as the app. Any arguments go to Phinx as they are.
 *
 * A new database starts from the baseline migration. The fixture is built
 * from db.sql instead (tests/reset-db.sh) and has no phinxlog, so this is for
 * real databases, and for tests/migrations.sh. A schema change is a migration
 * *and* the same change to db.sql, and that test fails until the two agree.
 * `status` creates phinxlog if it is missing, so it is not read-only.
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
    fwrite(STDERR, "usage: php bin/migrate.php status | migrate | create <Name> [phinx options]\n");
    exit(2);
}

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phinx);
foreach ($args as $a) {
    $cmd .= ' ' . escapeshellarg($a);
}
$cmd .= ' -c ' . escapeshellarg("$root/db/phinx.php");
passthru($cmd, $status);
exit($status);
