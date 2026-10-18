<?php
/**
 * Grant, revoke or list the admin flag on accounts.
 *
 *   docker compose exec web php /repo/bin/admin.php list
 *   docker compose exec web php /repo/bin/admin.php grant someone@example.com
 *   docker compose exec web php /repo/bin/admin.php revoke someone@example.com
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

$usage = "usage: php bin/admin.php list | grant <email> | revoke <email>\n";
$command = isset($argv[1]) ? $argv[1] : '';

/** The empire this account plays in the round, for the listing, or "-". */
function mb_admin_cli_ename($db, $account_id)
{
    $r = mb_query($db, "SELECT ename FROM user WHERE account_id=?", array((int) $account_id));
    $row = $r ? mysqli_fetch_row($r) : null;
    return $row ? $row[0] : '-';
}

if ($command === 'list') {
    $r = mysqli_query(mb_accounts_db(), "SELECT account_id, email FROM account WHERE admin = 1 ORDER BY account_id");
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

if (($command !== 'grant' && $command !== 'revoke') || !isset($argv[2])) {
    fwrite(STDERR, $usage);
    exit(2);
}

$account = mb_account_find($argv[2]);
if (!$account) {
    fwrite(STDERR, "No account with the address {$argv[2]}.\n");
    exit(1);
}

$flag = $command === 'grant';
mb_account_set_admin($account['account_id'], $flag);
echo ($flag ? "Granted" : "Revoked") . " admin: {$account['email']} ("
    . mb_admin_cli_ename($db, $account['account_id']) . ").\n";
