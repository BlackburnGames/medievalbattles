<?php
/**
 * Grant, revoke or list the admin flag on player accounts.
 *
 *   docker compose exec web php /repo/bin/admin.php list
 *   docker compose exec web php /repo/bin/admin.php grant someone@example.com
 *   docker compose exec web php /repo/bin/admin.php revoke someone@example.com
 *
 * An administrator is a player account with `user.admin` set; there is no
 * admin password. This is the command line rather than a page because the
 * first admin has to come from somewhere other than a page only admins see.
 * Revoking takes effect on the admin's next request -- app/admin/include/
 * auth.php reads the flag every time.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/include/connect.php';

$usage = "usage: php bin/admin.php list | grant <email> | revoke <email>\n";
$command = isset($argv[1]) ? $argv[1] : '';

if ($command === 'list') {
    $r = mysqli_query($db, "SELECT userid, email, ename FROM user WHERE admin = 1 ORDER BY userid");
    $n = 0;
    while ($row = mysqli_fetch_assoc($r)) {
        echo "{$row['userid']}\t{$row['email']}\t{$row['ename']}\n";
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

$q_email = mb_sql_str($db, $argv[2]);
$found = mysqli_query($db, "SELECT ename FROM user WHERE email=$q_email");
$row = $found ? mysqli_fetch_assoc($found) : null;
if (!$row) {
    fwrite(STDERR, "No account with the address {$argv[2]}.\n");
    exit(1);
}

$flag = $command === 'grant' ? 1 : 0;
mysqli_query($db, "UPDATE user SET admin = $flag WHERE email=$q_email");
echo ($flag ? "Granted" : "Revoked") . " admin: {$argv[2]} ({$row['ename']}).\n";
