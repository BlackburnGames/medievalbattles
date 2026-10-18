<?php
/**
 * Move a round's credentials out into the accounts database.
 *
 *   docker compose exec web php /repo/bin/accounts-migrate.php
 *
 * For a world that predates the accounts database -- every `user` row holding
 * its password hash in `pw`, copied into five more tables, and the admin flag
 * in `user.admin`, and no `user.account_id`. It adds that column, then for
 * each kingdom makes an account with the hash and the flag, links the kingdom
 * to it, and writes a fresh kingdom key over the hash in all six tables. Last,
 * it drops `user.admin`, which db.sql no longer has.
 *
 * Re-runnable. A kingdom whose `pw` is already a key has been moved and is
 * skipped, and an address that already has an account keeps that account --
 * its hash is not overwritten by the round's.
 *
 * The fixture never needs this: db/seed.sql and db/accounts-seed.sql are
 * already split. It is for the one corpus that is not in this repo.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/include/connect.php';
require_once __DIR__ . '/../app/include/credentials.php';
require_once __DIR__ . '/../app/include/kingdom.php';

$cols = mysqli_query($db, "SHOW COLUMNS FROM user LIKE 'account_id'");
if (!$cols || !mysqli_fetch_row($cols)) {
    // As db.sql declares it.
    mysqli_query($db, "ALTER TABLE user ADD COLUMN account_id int unsigned NOT NULL default '0', ADD KEY account_id (account_id)");
}

$hasAdmin = false;
$cols = mysqli_query($db, "SHOW COLUMNS FROM user LIKE 'admin'");
if ($cols && mysqli_fetch_row($cols)) {
    $hasAdmin = true;
}

$select = $hasAdmin
    ? "SELECT userid, email, pw, signup_comp_id, admin FROM user ORDER BY userid"
    : "SELECT userid, email, pw, signup_comp_id, 0 AS admin FROM user ORDER BY userid";
$rows = array();
$r = mysqli_query($db, $select); // mb-unbound-ok: one of two literal statements
while ($row = mysqli_fetch_assoc($r)) {
    $rows[] = $row;
}

$made = $kept = $skipped = 0;
foreach ($rows as $row) {
    if (mb_valid_kingdom_key($row['pw'])) {
        $skipped++;
        continue;
    }

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

    $key = mb_kingdom_key();
    foreach (array('user', 'buildings', 'military', 'research', 'returntbl', 'explore') as $table) {
        mb_query($db, "UPDATE $table SET pw=? WHERE email=? AND pw=?", array($key, $row['email'], $row['pw'])); // mb-unbound-ok: table names from a literal list
    }
}

if ($hasAdmin) {
    mysqli_query($db, "ALTER TABLE user DROP COLUMN admin");
}

echo "Kingdoms keyed: " . ($made + $kept) . " ($made new accounts, $kept already had one); "
    . "$skipped already moved." . ($hasAdmin ? " Dropped user.admin." : "") . "\n";
