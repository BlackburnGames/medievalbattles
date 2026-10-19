<?php
/**
 * Grant, revoke or list the admin flag on accounts.
 *
 *   docker compose exec web php /repo/bin/admin.php list
 *   docker compose exec web php /repo/bin/admin.php grant someone@example.com
 *   docker compose exec web php /repo/bin/admin.php revoke someone@example.com
 *   docker compose exec web php /repo/bin/admin.php verify someone@example.com
 *   docker compose exec web php /repo/bin/admin.php password someone@example.com
 *
 * An administrator is an account with `account.admin` set, in the accounts
 * database; there is no admin password. This is the command line rather than
 * a page because the first admin has to come from somewhere other than a page
 * only admins see. Revoking takes effect on the admin's next request --
 * app/admin/include/auth.php reads the flag every time.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/include/connect.php';

$usage = "usage: php bin/admin.php list | grant <email> | revoke <email> | verify <email> | password <email>\n";
$command = isset($argv[1]) ? $argv[1] : '';

/** The empire this account plays in the current round, for the listing, or "-". */
function mb_admin_cli_ename($db, $account_id)
{
    $r = mb_query($db, "SELECT ename FROM user WHERE round_id=? AND account_id=?", array((int) mb_round_current($db), (int) $account_id));
    $row = $r ? mysqli_fetch_row($r) : null;
    return $row ? $row[0] : '-';
}

if ($command === 'list') {
    $r = mysqli_query(mb_db(), "SELECT account_id, email FROM account WHERE admin = 1 ORDER BY account_id");
    $n = 0;
    while ($row = mysqli_fetch_assoc($r)) {
        echo "{$row['account_id']}\t{$row['email']}\t" . mb_admin_cli_ename($db, $row['account_id']) . "\n";
        $n++;
    }
    if ($n === 0) {
        echo "No admin accounts.\n";
    }
    exit(0);
}

if (!in_array($command, array('grant', 'revoke', 'verify', 'password'), true) || !isset($argv[2])) {
    fwrite(STDERR, $usage);
    exit(2);
}

$account = mb_account_find($argv[2]);
if (!$account) {
    fwrite(STDERR, "No account with the address {$argv[2]}.\n");
    exit(1);
}

// A forgotten password, until the game has a reset of its own: a random one,
// printed once, for the player to change in Preferences. Storing it changes
// the account's fingerprint, which ends every session it has open.
if ($command === 'password') {
    $temporary = bin2hex(random_bytes(6));
    mb_password_store($account['account_id'], mb_password_hash($temporary));
    echo "Temporary password for {$account['email']}: $temporary\n";
    echo "Log in with it and change it in Preferences.\n";
    exit(0);
}

// Confirm an address by hand: local mail goes nowhere, so a dev signup's link
// never arrives.
if ($command === 'verify') {
    mb_account_mark_verified($account['account_id']);
    echo "Verified: {$account['email']}.\n";
    exit(0);
}

$flag = $command === 'grant';
mb_account_set_admin($account['account_id'], $flag);
echo ($flag ? "Granted" : "Revoked") . " admin: {$account['email']} ("
    . mb_admin_cli_ename($db, $account['account_id']) . ").\n";
