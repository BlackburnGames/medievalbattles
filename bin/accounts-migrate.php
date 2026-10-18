<?php
/**
 * Move a round's credentials out onto accounts.
 *
 *   docker compose exec web php /repo/bin/accounts-migrate.php
 *
 * For a world that predates accounts: every `user` row holding its address in
 * `email` and its password hash in `pw`, both copied into five more tables,
 * and the admin flag in `user.admin`. For each kingdom this makes an account
 * with the address, the hash and the flag -- creating the `account` table
 * first, as db.sql declares it, if the world has none -- and records it in
 * `user.account_id`. Then it drops `email` and `pw` from all six tables and
 * `admin` from `user`, carries each kingdom's activation over to its account
 * and drops `emailvalidate`, and indexes the five child tables on userid, as
 * db.sql declares them. Each account's public name is its empire's.
 *
 * Re-runnable. A world without `user.email` has been moved, and nothing is
 * done. An address that already has an account keeps that account, and its
 * hash is not overwritten by the round's.
 *
 * The fixture never needs this: db/seed.sql is already split. It is for the
 * one corpus that is not in this repo.
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

/**
 * A public name for an account made from an old kingdom: its empire name,
 * which signup held to the same rule and kept unique in the round -- never
 * anything from the address, which is private. A clash with an account that
 * already exists gets a number.
 */
function mb_migrate_handle(array $row)
{
    $base = mb_valid_handle($row['ename']) ? $row['ename'] : 'Player ' . (int) $row['userid'];
    $handle = $base;
    for ($n = 2; mb_account_handle_taken($handle); $n++) {
        $handle = substr($base, 0, 25 - strlen(" $n")) . " $n";
    }
    return $handle;
}

if (!mb_migrate_has_column($db, 'user', 'email')) {
    echo "Already moved: user has no email column.\n";
    exit(0);
}

if (!mysqli_fetch_row(mysqli_query($db, "SHOW TABLES LIKE 'account'"))) {
    require_once __DIR__ . '/../db/sql-statements.php';
    foreach (mb_sql_statements(__DIR__ . '/../db.sql') as $stmt) {
        if (strpos($stmt, 'CREATE TABLE `account`') === 0) {
            mysqli_query($db, $stmt); // mb-unbound-ok: db.sql's own statement
        }
    }
}

if (!mb_migrate_has_column($db, 'user', 'account_id')) {
    mysqli_query($db, "ALTER TABLE user ADD COLUMN account_id int unsigned NOT NULL default '0', ADD KEY account_id (account_id)");
}

$hasAdmin = mb_migrate_has_column($db, 'user', 'admin');
$hasValidate = (bool) mysqli_fetch_row(mysqli_query($db, "SHOW TABLES LIKE 'emailvalidate'"));
// A world from before accounts is a single round, every kingdom of it read.
$select = $hasAdmin
    ? "SELECT userid, email, pw, signup_comp_id, ename, admin FROM user ORDER BY userid" // mb-round-ok: a world from before accounts
    : "SELECT userid, email, pw, signup_comp_id, ename, 0 AS admin FROM user ORDER BY userid"; // mb-round-ok: likewise
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
        mb_account_create($row['email'], mb_migrate_handle($row), $row['pw'], $row['signup_comp_id']);
        $account = mb_account_find($row['email']);
        $made++;
    }
    if ((int) $row['admin'] === 1) {
        mb_account_set_admin($account['account_id'], true);
    }
    mb_query($db, "UPDATE user SET account_id=? WHERE userid=?", array((int) $account['account_id'], (int) $row['userid']));

    // An account is made unverified. One whose kingdom the old world had
    // already activated -- emailvalidate.check 2 -- stays activated, or every
    // existing player would be held at the gate on their next login.
    if ($hasValidate) {
        $v = mb_query($db, "SELECT `check` FROM emailvalidate WHERE userid=?", array((int) $row['userid']));
        $vrow = $v ? mysqli_fetch_row($v) : null;
        if ($vrow && (int) $vrow[0] === 2) {
            mb_account_mark_verified($account['account_id']);
        }
    }
}

if ($hasValidate) {
    mysqli_query($db, "DROP TABLE emailvalidate");
}

// Only after every kingdom is linked: the copies are what the links were made from.
foreach (array('buildings', 'explore', 'military', 'research', 'returntbl') as $table) {
    mysqli_query($db, "ALTER TABLE `$table` DROP COLUMN email, DROP COLUMN pw, ADD KEY userid (userid)"); // mb-unbound-ok: table names from a literal list
}
mysqli_query($db, "ALTER TABLE user DROP COLUMN email, DROP COLUMN pw" . ($hasAdmin ? ", DROP COLUMN admin" : ""));

echo "Kingdoms linked: " . count($rows) . " ($made new accounts, $kept already had one). "
    . "Dropped email and pw from six tables" . ($hasAdmin ? ", and user.admin" : "") . ".\n";
