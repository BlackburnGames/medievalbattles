<?php
/**
 * Move a round's credentials out into the accounts database.
 *
 *   docker compose exec web php /repo/bin/accounts-migrate.php
 *
 * For a world that predates the accounts database: every `user` row holding
 * its address in `email` and its password hash in `pw`, both copied into five
 * more tables, and the admin flag in `user.admin`. For each kingdom this makes
 * an account with the address, the hash and the flag, and records it in
 * `user.account_id`. Then it drops `email` and `pw` from all six tables and
 * `admin` from `user`, and indexes the five child tables on userid, as db.sql
 * declares them.
 *
 * Re-runnable. A world without `user.email` has been moved, and nothing is
 * done. An address that already has an account keeps that account, and its
 * hash is not overwritten by the round's.
 *
 * The fixture never needs this: db/seed.sql and db/accounts-seed.sql are
 * already split. It is for the one corpus that is not in this repo.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/include/connect.php';

function mb_migrate_has_column($db, $table, $column)
{
    $r = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '$column'"); // mb-unbound-ok: literal names from this file
    return $r && mysqli_fetch_row($r);
}

if (!mb_migrate_has_column($db, 'user', 'email')) {
    echo "Already moved: user has no email column.\n";
    exit(0);
}

if (!mb_migrate_has_column($db, 'user', 'account_id')) {
    mysqli_query($db, "ALTER TABLE user ADD COLUMN account_id int unsigned NOT NULL default '0', ADD KEY account_id (account_id)");
}

$hasAdmin = mb_migrate_has_column($db, 'user', 'admin');
$select = $hasAdmin
    ? "SELECT userid, email, pw, signup_comp_id, admin FROM user ORDER BY userid"
    : "SELECT userid, email, pw, signup_comp_id, 0 AS admin FROM user ORDER BY userid";
$rows = array();
$r = mysqli_query($db, $select); // mb-unbound-ok: one of two literal statements
while ($row = mysqli_fetch_assoc($r)) {
    $rows[] = $row;
}

$made = $kept = 0;
foreach ($rows as $row) {
    $account = mb_account_find($row['email']);
    if ($account) {
        $kept++;
    } else {
        mb_account_create($row['email'], $row['pw'], $row['signup_comp_id']);
        $account = mb_account_find($row['email']);
        $made++;
    }
    if ((int) $row['admin'] === 1) {
        mb_account_set_admin($account['account_id'], true);
    }
    mb_query($db, "UPDATE user SET account_id=? WHERE userid=?", array((int) $account['account_id'], (int) $row['userid']));
}

// Only after every kingdom is linked: the copies are what the links were made from.
foreach (array('buildings', 'explore', 'military', 'research', 'returntbl') as $table) {
    mysqli_query($db, "ALTER TABLE `$table` DROP COLUMN email, DROP COLUMN pw, ADD KEY userid (userid)"); // mb-unbound-ok: table names from a literal list
}
mysqli_query($db, "ALTER TABLE user DROP COLUMN email, DROP COLUMN pw" . ($hasAdmin ? ", DROP COLUMN admin" : ""));

echo "Kingdoms linked: " . count($rows) . " ($made new accounts, $kept already had one). "
    . "Dropped email and pw from six tables" . ($hasAdmin ? ", and user.admin" : "") . ".\n";
