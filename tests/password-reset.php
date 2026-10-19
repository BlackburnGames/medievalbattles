<?php
/**
 * Forgotten passwords (app/include/password_reset.php): forgot.php mails a
 * link, reset.php follows it.
 *
 *   1. Asking for a link says the same thing whether the address has an
 *      account or not, and only the account gets a row.
 *   2. A link sets a new password: the old one stops working, the new one
 *      logs in, and the account's existing sessions are ended.
 *   3. A link works once.
 *   4. A link does not work past its expiry, with the wrong token, or once
 *      the password has changed since it was issued.
 *   5. An account may be sent only so many links an hour.
 *
 * Links are made here, in-process, rather than read out of a mail: local mail
 * goes nowhere, and the table holds only the token's hash. Reloads the schema
 * before and after.
 *
 *   docker compose exec -T web php /repo/tests/password-reset.php
 */

require_once __DIR__ . '/lib/golden.php';
require_once __DIR__ . '/../app/include/password_reset.php';

$failures = 0;
function check($label, $ok)
{
    global $failures;
    echo ($ok ? 'ok' : 'FAIL') . ": $label\n";
    if (!$ok) {
        $failures++;
    }
}

/** A client logged in with this password, or null. */
function signIn($base, $email, $pass)
{
    $client = new MbClient($base);
    $client->get('/index.php');
    $client->post('/checklogin.php', array('email' => $email, 'pw' => $pass, 'login' => 'Login'));
    return $client->lastStatus === 302 ? $client : null;
}

function stillIn(MbClient $client)
{
    $client->get('/main.php?pageid=news');
    return $client->lastStatus === 200 && strpos($client->lastLocation, 'logout.php') === false;
}

/** A fresh link for the address, as the path and query reset.php is reached by. */
function link_for($email)
{
    $link = mb_password_reset_issue(mb_account_find($email), '10.0.0.1');
    return $link === null ? null : substr($link, strpos($link, '/index.php'));
}

function resets($db, $email)
{
    $r = mysqli_fetch_row(mysqli_query($db, "SELECT count(*) FROM password_reset p JOIN account a ON a.account_id = p.account_id
        WHERE a.email = '" . mysqli_real_escape_string($db, $email) . "'"));
    return (int) $r[0];
}

loadFixture($db);
$EMAIL = 'poor@example.com';

// 1.
$c = new MbClient($BASE);
$known = strip_tags($c->post('/index.php?page=forgot', array('email' => $EMAIL)));
$unknown = strip_tags($c->post('/index.php?page=forgot', array('email' => 'nobody@example.com')));
check('asking for a link for an account makes one', resets($db, $EMAIL) === 1);
check('the page says it may have been mailed',
    strpos($known, "If $EMAIL has an account, a link") !== false);
check('an address with no account is answered the same way',
    strpos($unknown, 'If nobody@example.com has an account, a link') !== false);
check('...and gets no row', resets($db, 'nobody@example.com') === 0
    && (int) mysqli_fetch_row(mysqli_query($db, "SELECT count(*) FROM password_reset"))[0] === 1);

// 2.
$before = signIn($BASE, $EMAIL, 'pass3');
check('...a session logged in to begin with', $before !== null && stillIn($before));
$path = link_for($EMAIL);
parse_str(parse_url($path, PHP_URL_QUERY), $q);
$form = $c->get($path);
check('the link shows the form', strpos($form, "name='pw'") !== false);
$body = $c->post('/index.php?page=reset', array('id' => $q['id'], 'token' => $q['token'], 'pw' => 'newpass9', 'cpw' => 'other'));
check('passwords that do not match are refused', strpos($body, 'do not match') !== false);
check('...and change nothing', signIn($BASE, $EMAIL, 'pass3') !== null);
$body = $c->post('/index.php?page=reset', array('id' => $q['id'], 'token' => $q['token'], 'pw' => 'newpass9', 'cpw' => 'newpass9'));
check('a matching pair sets the password', strpos($body, 'has been changed') !== false);
check('the old password no longer logs in', signIn($BASE, $EMAIL, 'pass3') === null);
check('the new one does', signIn($BASE, $EMAIL, 'newpass9') !== null);
check('the session from before is ended', !stillIn($before));
check('every link the account had is gone', resets($db, $EMAIL) === 0);

// 3.
$body = $c->get($path);
check('a used link does not work again', strpos($body, 'has expired or has already been used') !== false);
$body = $c->post('/index.php?page=reset', array('id' => $q['id'], 'token' => $q['token'], 'pw' => 'again1', 'cpw' => 'again1'));
check('...not even by POST', strpos($body, 'has expired') !== false && signIn($BASE, $EMAIL, 'newpass9') !== null);

// 4.
$path = link_for($EMAIL);
parse_str(parse_url($path, PHP_URL_QUERY), $q);
$body = $c->get('/index.php?page=reset&id=' . $q['id'] . '&token=' . str_repeat('0', 32));
check('the wrong token is refused', strpos($body, 'has expired') !== false);
mysqli_query($db, "UPDATE password_reset SET expires = NOW() - INTERVAL 1 MINUTE WHERE reset_id = " . (int) $q['id']);
check('an expired link is refused', strpos($c->get($path), 'has expired') !== false);
$path = link_for($EMAIL);
mb_password_store(mb_account_find($EMAIL)['account_id'], mb_password_hash('changed1'));
check('a link issued before a password change is refused', strpos($c->get($path), 'has expired') !== false);

// 5.
mysqli_query($db, "DELETE FROM password_reset");
for ($i = 0; $i < MB_RESET_MAX_ACCOUNT; $i++) {
    link_for($EMAIL);
}
check('an account may be sent only ' . MB_RESET_MAX_ACCOUNT . ' links an hour', link_for($EMAIL) === null);
mysqli_query($db, "UPDATE password_reset SET created = created - INTERVAL 61 MINUTE");
check('...and another once they are an hour old', link_for($EMAIL) !== null);

loadFixture($db);

echo "\n";
if ($failures) {
    echo "FAIL: $failures check(s) failed.\n";
    exit(1);
}
echo "PASS: a forgotten password can be reset, once, by the address's owner.\n";
