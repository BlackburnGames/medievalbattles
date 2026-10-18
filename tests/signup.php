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
} elseif (strpos($client->get('/preferences.php'), 'value="o&#039;brien@example.com"') === false) {
    echo "FAIL: $obrien is not shown escaped on the preferences form\n";
    $failures++;
} else {
    echo "ok: $obrien signs up, logs in and is shown escaped\n";
}

loadFixture($db);

if ($failures) {
    echo "FAIL: $failures signup grant(s) wrong.\n";
    exit(1);
}
echo "PASS: every race and class starts with what it is granted.\n";
