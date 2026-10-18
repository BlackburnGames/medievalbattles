<?php
/**
 * A new game starts the administrators over too.
 *
 * Both world wipes -- admin/newgame.php and gameconfig.php's account wipe --
 * keep administrator accounts, and they used to keep their kingdoms with
 * them: each table was cleared only of rows whose account was gone, so an
 * admin went into the next round with the last one's gold, research, army,
 * returns in flight and barter offers. include/kingdom.php resets them now.
 *
 * "Reset" means "as signup would make it", and this holds it to exactly that
 * rather than to a list of expected values. After each wipe it signs up a twin
 * of every admin -- same race and class, through checksignup.php -- and every
 * column of every kingdom table must match the twin's, except the ones signup
 * rolls dice for, which must fall in signup's ranges. A column added later
 * that the reset forgets fails here without this file being touched.
 *
 * The wipes delete kingdoms, not accounts, which live in their own table
 * and outlast the round. So it also checks that the player whose kingdom went
 * keeps the account, is told at login that it has no kingdom, and gets a new
 * one by signing up again -- with its own password, and not with another.
 *
 * It reloads the schema before each wipe and after, so it destroys whatever
 * world it runs against and leaves the fixture behind.
 *
 *   docker compose exec -T web php /repo/tests/newgame.php
 */

require_once __DIR__ . '/lib/golden.php';

// Each wipe, as the request that runs it.
$WIPES = array(
    'admin/newgame.php'            => array(),
    'admin/gameconfig.php accounts' => array('action' => 'accounts'),
);

// The fixture's admins, given a class signup offers -- tester@'s "Warrior"
// is not one, and a twin has to be signed up as the same class -- and a
// second admin who is a Cleric, whose rolls differ.
$ADMINS = array(
    1 => array('email' => 'tester@example.com', 'race' => 'Human', 'class' => 'Fighter'),
    2 => array('email' => 'idle@example.com',   'race' => 'Demon', 'class' => 'Cleric'),
);
$NOT_ADMIN = 'poor@example.com';

// Who an account is, which a reset keeps; and the columns signup rolls dice
// for, checked against its ranges below instead of against the twin.
$IDENTITY = array('userid', 'ename', 'race', 'class', 'setid', 'csnum', 'aim',
    'msn', 'ip', 'lastlog', 'lastlogin', 'current_comp_id', 'signup_comp_id', 'account_id');
$ROLLED = array(
    'user'     => array('gp', 'iron'),
    'military' => array('civ', 'recruits', 'warriors', 'wizards', 'priests', 'maxciv', 'archers', 'suicide'),
);
// include/kingdom.php's ranges. gp is two rolls concatenated, so its bounds
// are the smallest and largest strings the pair can make.
$RANGES = array(
    'default' => array('gp' => array(2500, 350999), 'iron' => array(4000, 6000),
        'civ' => array(1000, 1250), 'recruits' => array(150, 250), 'maxciv' => array(100, 250)),
    'Cleric'  => array('gp' => array(3500, 450999), 'iron' => array(6000, 7500),
        'civ' => array(1200, 1400), 'recruits' => array(200, 300), 'maxciv' => array(250, 350)),
);
$TABLES = array('user', 'buildings', 'military', 'research', 'explore', 'returntbl');

$failures = 0;
function fail($msg)
{
    global $failures;
    echo "FAIL: $msg\n";
    $failures++;
}

function rowsFor($db, $table, $userid)
{
    $rows = array();
    $r = mysqli_query($db, "SELECT * FROM `$table` WHERE userid = " . (int) $userid);
    while ($row = mysqli_fetch_assoc($r)) {
        $rows[] = $row;
    }
    return $rows;
}

/** How many kingdoms the account with this address has: the round holds no address. */
function kingdomsOf($db, $email)
{
    $row = mysqli_fetch_row(mysqli_query($db, "SELECT account_id FROM account WHERE email = '"
        . mysqli_real_escape_string($db, $email) . "'"));
    return $row ? countWhere($db, "SELECT count(*) FROM user WHERE account_id = " . (int) $row[0]) : 0;
}

function countWhere($db, $sql)
{
    $row = mysqli_fetch_row(mysqli_query($db, $sql));
    return (int) $row[0];
}

foreach ($WIPES as $wipe => $form) {
    loadFixture($db);

    $ids = implode(',', array_keys($ADMINS));
    // The flag is the account's, not the kingdom's.
    foreach ($ADMINS as $a) {
        mysqli_query($db, "UPDATE account SET admin = 1 WHERE email = '" . mysqli_real_escape_string($db, $a['email']) . "'");
    }
    foreach ($ADMINS as $id => $a) {
        mysqli_query($db, "UPDATE user SET class = '" . mysqli_real_escape_string($db, $a['class'])
            . "', guild = 'Old Guild', vote = 3, sl = 'yes' WHERE userid = $id");
        mysqli_query($db, "INSERT INTO empnews (yourid, round_id) VALUES ($id, 1)");
    }

    // The state a reset has to clear, present to begin with -- or a pass
    // proves nothing.
    if (countWhere($db, "SELECT count(*) FROM returntbl WHERE userid = 1 AND war1 > 0") === 0
        || countWhere($db, "SELECT count(*) FROM barter WHERE userid = 1") === 0) {
        fail("$wipe: the fixture no longer gives tester@ returns in flight and a barter offer; this test needs both");
    }

    $admin = login($BASE, 'tester@example.com', 'test1234');
    $path = strtok($wipe, ' ');
    $body = $admin->post('/' . $path, $form);
    if ($admin->lastStatus !== 200) {
        fail("$wipe: HTTP {$admin->lastStatus}");
        continue;
    }
    foreach (diagnostics($body) as $d) {
        fail("$wipe: $d");
    }

    if (kingdomsOf($db, $NOT_ADMIN) !== 0) {
        fail("$wipe: $NOT_ADMIN, not an admin, kept its kingdom");
    }
    // The kingdom, not the account: accounts outlast a round.
    if (countWhere($db, "SELECT count(*) FROM account WHERE email = '$NOT_ADMIN'") !== 1) {
        fail("$wipe: $NOT_ADMIN's account was deleted with its kingdom");
    }
    if (countWhere($db, "SELECT count(*) FROM barter WHERE userid IN ($ids)") !== 0) {
        fail("$wipe: an admin's barter offers survived");
    }
    if (countWhere($db, "SELECT count(*) FROM empnews WHERE yourid IN ($ids)") !== 0) {
        fail("$wipe: an admin's empire news survived");
    }
    $members = countWhere($db, "SELECT sum(members) FROM settlement");
    if ($members !== count($ADMINS)) {
        fail("$wipe: the settlements count $members members, should be " . count($ADMINS));
    }

    // Twins. Signup refuses a second account from an address already on a
    // row, and tester@ just logged in from this one.
    mysqli_query($db, "UPDATE user SET ip = ''");
    foreach ($ADMINS as $id => $a) {
        $twinEmail = "twin$id@example.com";
        $client = new MbClient($BASE);
        $client->get('/index.php');
        $client->post('/checksignup.php', array(
            'signup' => 1, 'email' => $twinEmail, 'cemail' => $twinEmail, 'pw' => 'pass1234', 'cpw' => 'pass1234',
            'handle' => "Twin Player $id", 'ename' => "Twin $id", 'race' => $a['race'], 'class' => $a['class'], 'msn' => '', 'aim' => '',
        ));
        $twinRow = mysqli_fetch_row(mysqli_query($db, "SELECT userid FROM user WHERE ename = 'Twin $id'"));
        if (!$twinRow) {
            fail("$wipe: could not sign up a twin for {$a['email']}");
            continue;
        }
        $twin = (int) $twinRow[0];
        $label = "$wipe: {$a['email']}";
        $ranges = isset($RANGES[$a['class']]) ? $RANGES[$a['class']] : $RANGES['default'];

        foreach ($TABLES as $table) {
            $mine = rowsFor($db, $table, $id);
            $theirs = rowsFor($db, $table, $twin);
            if (count($mine) !== 1) {
                fail("$label: " . count($mine) . " $table rows, should be 1");
                continue;
            }
            $mine = $mine[0];
            $theirs = $theirs[0];
            $rolled = isset($ROLLED[$table]) ? $ROLLED[$table] : array();
            $wrong = 0;
            foreach ($mine as $column => $value) {
                if (in_array($column, $IDENTITY, true)) {
                    continue;
                }
                if (in_array($column, $rolled, true)) {
                    if (isset($ranges[$column])
                        && ($value < $ranges[$column][0] || $value > $ranges[$column][1])) {
                        fail("$label: $table.$column is $value, outside signup's {$ranges[$column][0]}-{$ranges[$column][1]}");
                        $wrong++;
                    }
                    continue;
                }
                if ((string) $value !== (string) $theirs[$column]) {
                    fail("$label: $table.$column is $value, signup gives {$theirs[$column]}");
                    $wrong++;
                }
            }
            if (!$wrong) {
                echo "ok: $label: $table is as signup makes it\n";
            }
        }
    }

    // The account is kept, so its password still works.
    $again = new MbClient($BASE);
    $again->get('/index.php');
    $again->post('/checklogin.php', array('email' => 'tester@example.com', 'pw' => 'test1234', 'login' => 'Login'));
    if ($again->lastStatus !== 302) {
        fail("$wipe: tester@ cannot log in afterwards (HTTP {$again->lastStatus})");
    }

    // And the player whose kingdom went has a way back: signing up again with
    // the account's own password makes it a new kingdom, and any other
    // password finds the address taken.
    $back = new MbClient($BASE);
    $back->get('/index.php');
    $body = $back->post('/checklogin.php', array('email' => $NOT_ADMIN, 'pw' => 'pass3', 'login' => 'Login'));
    if ($back->lastStatus === 302 || stripos($body, 'no kingdom in this round') === false) {
        fail("$wipe: $NOT_ADMIN, with no kingdom, was not told so at login");
    }
    mysqli_query($db, "UPDATE user SET ip = ''");
    $rejoin = array('signup' => 1, 'email' => $NOT_ADMIN, 'cemail' => $NOT_ADMIN, 'pw' => 'wrong', 'cpw' => 'wrong',
        'handle' => 'Ignored On Rejoin', 'ename' => 'Returned Serf', 'race' => 'Giant', 'class' => 'Ranger', 'msn' => '', 'aim' => '');
    $back->post('/checksignup.php', $rejoin);
    if (kingdomsOf($db, $NOT_ADMIN) !== 0) {
        fail("$wipe: signup gave $NOT_ADMIN's address a kingdom without its password");
    }
    $rejoin['pw'] = $rejoin['cpw'] = 'pass3';
    $back->post('/checksignup.php', $rejoin);
    if (kingdomsOf($db, $NOT_ADMIN) !== 1) {
        fail("$wipe: $NOT_ADMIN could not sign up again with its own password");
    }
    if (countWhere($db, "SELECT count(*) FROM account WHERE email = '$NOT_ADMIN'") !== 1) {
        fail("$wipe: signing up again made $NOT_ADMIN a second account");
    }
    $back = new MbClient($BASE);
    $back->get('/index.php');
    $back->post('/checklogin.php', array('email' => $NOT_ADMIN, 'pw' => 'pass3', 'login' => 'Login'));
    if ($back->lastStatus !== 302) {
        fail("$wipe: $NOT_ADMIN cannot log into its new kingdom (HTTP {$back->lastStatus})");
    } elseif (stripos($back->get('/main.php?pageid=news'), 'Returned Serf') === false) {
        fail("$wipe: $NOT_ADMIN logged in, but not to its new kingdom");
    }
}

loadFixture($db);

if ($failures) {
    echo "FAIL: $failures problem(s) with the administrators' reset.\n";
    exit(1);
}
echo "PASS: both world wipes start the administrators over as signup would.\n";
