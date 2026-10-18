<?php
/**
 * What a new empire starts with, by race and class.
 *
 * The crawl cannot sign up -- checksignup.php makes a user out of rand(), so
 * there is no fixture to compare against -- and so nothing checked the race
 * and class grants. This signs one empire up per combination that grants
 * something and asserts the grant, as ranges where the page rolls dice.
 *
 * It reloads the schema before and after, so it destroys whatever world it
 * runs against and leaves the fixture behind.
 *
 *   docker compose exec -T web php /repo/tests/signup.php
 */

require_once __DIR__ . '/lib/golden.php';
// activation_hours, for the tick's half below.
require_once __DIR__ . '/../app/include/gamedata.php';

// race, class, then what the research and military rows must hold:
// column => exact value, or array(min, max).
$CASES = array(
    array('Night Elf', 'Fighter', array('r13pts' => 125000, 'archers' => array(4, 10))),
    array('Night Elf', 'Ranger',  array('r13pts' => 125000, 'archers' => array(4, 10), 'wizards' => 0)),
    array('Human',     'Ranger',  array('r13pts' => 125000, 'archers' => array(4, 10), 'wizards' => 0)),
    array('Human',     'Fighter', array('r13pts' => 0, 'archers' => 0, 'r1pts' => 0, 'r14pts' => 0, 'suicide' => 0)),
    array('Human',     'Warlock', array('r1pts' => 50000, 'r13pts' => 0)),
    array('Human',     'Insurrectionist', array('r14pts' => 125000, 'suicide' => array(10, 20), 'wizards' => 0)),
    array('Giant',     'Fighter', array('wizards' => 0, 'priests' => 0)),
    array('Demon',     'Fighter', array('priests' => 0)),
);

loadFixture($db);

$failures = 0;
foreach ($CASES as $i => $case) {
    list($race, $class, $expect) = $case;
    $email = "signup$i@example.com";

    $client = new MbClient($BASE);
    $client->get('/index.php');
    $body = $client->post('/checksignup.php', array(
        'signup' => 1, 'email' => $email, 'cemail' => $email, 'pw' => 'pass1234', 'cpw' => 'pass1234',
        'ename' => "Signup $i", 'race' => $race, 'class' => $class, 'msn' => '', 'aim' => '',
    ));

    $label = "$race $class";
    // By empire name: the round holds no address.
    $row = mysqli_fetch_assoc(mysqli_query($db,
        "SELECT r.r1pts, r.r13pts, r.r14pts, m.archers, m.wizards, m.priests, m.suicide
         FROM user u JOIN research r ON r.userid = u.userid JOIN military m ON m.userid = u.userid
         WHERE u.ename = 'Signup $i'"));
    if (!$row) {
        echo "FAIL: $label: no account was made. The page said: "
            . trim(substr(strip_tags($body), 0, 200)) . "\n";
        $failures++;
        continue;
    }

    foreach ($expect as $column => $want) {
        $got = $row[$column];
        $ok = is_array($want) ? ($got >= $want[0] && $got <= $want[1]) : ((string) $got === (string) $want);
        $wantText = is_array($want) ? "{$want[0]}-{$want[1]}" : $want;
        if ($ok) {
            echo "ok: $label: $column $got\n";
        } else {
            echo "FAIL: $label: $column is $got, should be $wantText\n";
            $failures++;
        }
    }
}

// An address with an apostrophe, which is legal and which the game refused
// while addresses were interpolated into its queries. It signs up, logs in,
// and comes back escaped in the preferences form's attribute.
$obrien = "o'brien@example.com";
mysqli_query($db, "UPDATE user SET ip = ''");
$client = new MbClient($BASE);
$client->get('/index.php');
$body = $client->post('/checksignup.php', array(
    'signup' => 1, 'email' => $obrien, 'cemail' => $obrien, 'pw' => 'pass1234', 'cpw' => 'pass1234',
    'ename' => 'Obrien', 'race' => 'Human', 'class' => 'Fighter', 'msn' => '', 'aim' => '',
));
$client = new MbClient($BASE);
$client->get('/index.php');
$client->post('/checklogin.php', array('email' => $obrien, 'pw' => 'pass1234', 'login' => 'Login'));
if ($client->lastStatus !== 302) {
    echo "FAIL: $obrien could not sign up and log in. Signup said: " . trim(substr(strip_tags($body), 0, 200)) . "\n";
    $failures++;
} else {
    // A new account is unverified, and the game's pages say so until the
    // mailed link is followed. Mail goes nowhere here, so the code is read
    // back from the account -- which is also the only coverage
    // activate_account.php and activate_code.php have.
    $checks = array();
    $checks['an unverified account is held at the activation gate'] =
        stripos($client->get('/main.php?pageid=news'), 'You must activate your account') !== false;
    $checks['the resend page answers'] =
        stripos($client->get('/activate_code.php'), 'activation code has been resent') !== false;
    $acct = mysqli_fetch_assoc(mysqli_query($adb, "SELECT account_id, verify_code FROM account WHERE email = '"
        . mysqli_real_escape_string($adb, $obrien) . "'"));
    $link = '/activate_account.php?activate=true&act_account=' . (int) $acct['account_id'] . '&act_code=';
    $checks['a wrong code is refused'] =
        stripos((new MbClient($BASE))->get($link . str_repeat('0', 32)), 'Validation code is incorrect') !== false;
    $checks['the mailed link activates it'] =
        stripos((new MbClient($BASE))->get($link . $acct['verify_code']), 'has been activated') !== false;
    $checks['after which the game lets it in, the address shown escaped'] =
        strpos($client->get('/preferences.php'), 'value="o&#039;brien@example.com"') !== false;
    foreach ($checks as $what => $ok) {
        echo ($ok ? 'ok' : 'FAIL') . ": $obrien: $what\n";
        $failures += $ok ? 0 : 1;
    }
}

// And the tick's side. An account that has not confirmed its address within
// activation_hours loses its kingdom -- not the account, which is not the
// round's to delete. One made just now is left alone, as is o'brien@, which
// confirmed above.
$lapsed = 'lapsed@example.com';
$fresh  = 'fresh@example.com';
foreach (array($lapsed => 'Lapsed', $fresh => 'Fresh') as $addr => $name) {
    mysqli_query($db, "UPDATE user SET ip = ''");
    $c = new MbClient($BASE);
    $c->get('/index.php');
    $c->post('/checksignup.php', array(
        'signup' => 1, 'email' => $addr, 'cemail' => $addr, 'pw' => 'pass1234', 'cpw' => 'pass1234',
        'ename' => $name, 'race' => 'Human', 'class' => 'Fighter', 'msn' => '', 'aim' => '',
    ));
}
$hours = $GAMEDATA['timers']['activation_hours'] + 1;
mysqli_query($adb, "UPDATE account SET created = NOW() - INTERVAL $hours HOUR WHERE email = '$lapsed'");
$tickLog = runTick();
$kingdom = function ($ename) use ($db) {
    return (int) mysqli_fetch_row(mysqli_query($db, "SELECT count(*) FROM user WHERE ename = '$ename'"))[0];
};
$accountCount = function ($addr) use ($adb) {
    return (int) mysqli_fetch_row(mysqli_query($adb, "SELECT count(*) FROM account WHERE email = '"
        . mysqli_real_escape_string($adb, $addr) . "'"))[0];
};
$checks = array(
    'the tick deletes the kingdom of an account unconfirmed past the window' => $kingdom('Lapsed') === 0,
    '...and keeps the account' => $accountCount($lapsed) === 1,
    'it leaves an account still inside the window alone' => $kingdom('Fresh') === 1,
    'and a confirmed one' => $kingdom('Obrien') === 1,
    'and reports no query errors' => strpos($tickLog, 'mysqli_') === false,
);
foreach ($checks as $what => $ok) {
    echo ($ok ? 'ok' : 'FAIL') . ": activation: $what\n";
    $failures += $ok ? 0 : 1;
}

loadFixture($db);

if ($failures) {
    echo "FAIL: $failures signup grant(s) wrong.\n";
    exit(1);
}
echo "PASS: every race and class starts with what it is granted.\n";
