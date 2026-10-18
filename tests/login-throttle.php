<?php
/**
 * Login throttling (app/include/throttle.php): an address, or an IP, that
 * has failed too often lately is refused before its password is looked at.
 *
 *   1. Five wrong passwords for an address, and the right one is refused too.
 *   2. Another address from the same machine still logs in.
 *   3. Once the failures age out of the window, the right password works, and
 *      clears the address's count.
 *   4. An address with no account is refused the same way, in the same words.
 *   5. Twenty failures from one IP, across addresses, and every address from
 *      it is refused.
 *
 * Every request in the suite comes from one address, so this reloads the
 * schema before and after: what it leaves behind would refuse the next test.
 *
 *   docker compose exec -T web php /repo/tests/login-throttle.php
 */

require_once __DIR__ . '/lib/golden.php';

$failures = 0;
function check($label, $ok)
{
    global $failures;
    echo ($ok ? 'ok' : 'FAIL') . ": $label\n";
    if (!$ok) {
        $failures++;
    }
}

/** One login attempt: whether it logged in, and what the page said. */
function attempt($base, $email, $pass)
{
    $client = new MbClient($base);
    $client->get('/index.php');
    $body = $client->post('/checklogin.php', array('email' => $email, 'pw' => $pass, 'login' => 'Login'));
    return array($client->lastStatus === 302, $body);
}

loadFixture($db);

// 1.
for ($i = 0; $i < 5; $i++) {
    attempt($BASE, 'poor@example.com', 'wrong');
}
list($in, $body) = attempt($BASE, 'poor@example.com', 'pass3');
check('after five wrong passwords, the right one is refused', !$in && stripos($body, 'Too many failed logins') !== false);

// 2.
list($in) = attempt($BASE, 'idle@example.com', 'pass2');
check('another address from the same machine still logs in', $in);

// 3.
mysqli_query($db, "UPDATE login_failure SET at = at - INTERVAL 16 MINUTE");
list($in) = attempt($BASE, 'poor@example.com', 'pass3');
check('once they age out, the right password works', $in);
$left = mysqli_fetch_row(mysqli_query($db, "SELECT count(*) FROM login_failure WHERE email = 'poor@example.com'"));
check('...and clears the address\'s failures', (int) $left[0] === 0);

// 4.
for ($i = 0; $i < 5; $i++) {
    attempt($BASE, 'nobody@example.com', 'wrong');
}
list($in, $body) = attempt($BASE, 'nobody@example.com', 'wrong');
check('an address with no account is refused in the same words', !$in && stripos($body, 'Too many failed logins') !== false);

// 5.
mysqli_query($db, "DELETE FROM login_failure");
for ($i = 0; $i < 20; $i++) {
    attempt($BASE, "guess$i@example.com", 'wrong');
}
list($in, $body) = attempt($BASE, 'tester@example.com', 'test1234');
check('twenty failures from one IP, and every address from it is refused', !$in && stripos($body, 'Too many failed logins') !== false);

loadFixture($db);

echo "\n";
if ($failures) {
    echo "FAIL: $failures check(s) failed.\n";
    exit(1);
}
echo "PASS: a password cannot be guessed at for long.\n";
