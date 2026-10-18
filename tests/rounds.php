<?php
/**
 * A round's life, end to end: ending one, and opening and starting the next.
 *
 * From the fixture, whose world is round 1, running:
 *
 *   1. bin/round.php refuses what it should: opening a round while one is in
 *      play, starting one when none is open.
 *   2. Ending round 1 writes a result for each kingdom, placed by exp, with
 *      its settlement's name, and marks the round ended.
 *   3. A session in round 1 is ended with it. Its account can still log in,
 *      and is told it has no kingdom; signup says no round is open; the tick
 *      does nothing.
 *   4. Opening round 2 gives it settlements numbered on from round 1's. The
 *      account signs up again, with its own password, into round 2 -- under
 *      the same kingdom name it had, which round 2 has never seen -- and
 *      logging in reaches the new kingdom.
 *   5. The tick leaves an open round alone, and advances it once started,
 *      while round 1's rows stay as they ended.
 *
 * It reloads the schema, so it destroys whatever world it runs against.
 *
 *   docker compose exec -T web php /repo/tests/rounds.php
 */

require_once __DIR__ . '/lib/golden.php';

$failures = 0;
function check($label, $ok, $detail = '')
{
    global $failures;
    echo ($ok ? 'ok' : 'FAIL') . ": $label" . (!$ok && $detail !== '' ? "\n      $detail" : '') . "\n";
    if (!$ok) {
        $failures++;
    }
}

function row($db, $sql)
{
    $r = mysqli_query($db, $sql);
    return $r ? mysqli_fetch_assoc($r) : null;
}

/** bin/round.php, as an administrator runs it. */
function round_cli($args)
{
    exec('php ' . escapeshellarg(dirname(__DIR__) . '/bin/round.php') . ' ' . $args . ' 2>&1', $out, $rc);
    return array($rc, implode("\n", $out));
}

function tick()
{
    $cwd = getcwd();
    chdir(dirname(__DIR__));
    exec('php update.php 2>&1', $out, $rc);
    chdir($cwd);
    return implode("\n", $out);
}

/** Every row a round has in its kingdom tables, for comparing before and after. */
function round_rows($db, $round_id)
{
    $out = array();
    foreach (array('user', 'military', 'research', 'buildings', 'explore', 'returntbl', 'settlement') as $t) {
        $r = mysqli_query($db, "SELECT * FROM `$t` WHERE round_id = " . (int) $round_id . " ORDER BY 1, 2, 3");
        while ($row = mysqli_fetch_row($r)) {
            $out[] = "$t\t" . implode("\t", $row);
        }
    }
    return $out;
}

loadFixture($db);

// 1. What bin/round.php refuses while round 1 runs.
list($rc, $out) = round_cli('open ' . escapeshellarg('Too Soon'));
check('opening a round while one is in play is refused', $rc === 1 && strpos($out, 'has not ended') !== false, $out);
list($rc, $out) = round_cli('start');
check('starting a round when none is open is refused', $rc === 1, $out);

// A player in round 1, logged in before it ends.
$poor = login($BASE, 'poor@example.com', 'pass3');
$poor->get('/main.php?pageid=news');
check('a round 1 session plays', $poor->lastStatus === 200);

// 2. Ending it.
list($rc, $out) = round_cli('end');
check('ending round 1 succeeds', $rc === 0, $out);
$round1 = row($db, "SELECT status, ended_at FROM `round` WHERE round_id = 1");
check('round 1 is ended, with a time', $round1['status'] === 'ended' && $round1['ended_at'] !== null);

$results = array();
$r = mysqli_query($db, "SELECT place, ename, account_id, exp, land, setname, guild FROM round_result WHERE round_id = 1 ORDER BY place");
while ($row = mysqli_fetch_assoc($r)) {
    $results[] = $row;
}
// The fixture's exp: TestLord 1000, IdleBaron 400, PoorSerf 0.
check('a result for each kingdom, placed by exp',
    count($results) === 3 && $results[0]['ename'] === 'TestLord' && $results[1]['ename'] === 'IdleBaron'
    && $results[2]['ename'] === 'PoorSerf', json_encode($results));
check('each result keeps its account, land, settlement name and guild',
    $results && $results[0]['account_id'] === '1' && $results[0]['land'] === '250'
    && $results[0]['setname'] === 'Testhold' && $results[0]['guild'] === 'Testguild', json_encode($results));
$frozen = round_rows($db, 1);

list($rc, $out) = round_cli('end');
check('ending it twice is refused', $rc === 1, $out);

// 3. The round 1 session is over; the account is not.
$poor->get('/main.php?pageid=news');
check('the round 1 session is ended with its round', $poor->lastStatus !== 200);

$back = new MbClient($BASE);
$back->get('/index.php');
$body = $back->post('/checklogin.php', array('email' => 'tester@example.com', 'pw' => 'test1234', 'login' => 'Login'));
check('the account logs in and is told it has no kingdom', $back->lastStatus !== 302 && stripos($body, 'no kingdom in this round') !== false);

$signup = array('signup' => 1, 'email' => 'tester@example.com', 'cemail' => 'tester@example.com',
    'pw' => 'test1234', 'cpw' => 'test1234', 'handle' => 'Ignored On Rejoin', 'ename' => 'TestLord',
    'race' => 'Human', 'class' => 'Fighter', 'msn' => '', 'aim' => '');
$body = $back->post('/checksignup.php', $signup);
check('signup says no round is open', stripos($body, 'No round is open') !== false);

$out = tick();
check('the tick runs nothing', strpos($out, 'No round is running') !== false, substr($out, 0, 200));
check('...and round 1 is as it ended', round_rows($db, 1) === $frozen);

// 4. Round 2.
list($rc, $out) = round_cli('open ' . escapeshellarg('Round 2'));
check('opening round 2 succeeds', $rc === 0, $out);
$r2 = row($db, "SELECT round_id, status, min(s.setid) AS first, max(s.setid) AS last, count(*) AS n
    FROM `round` r JOIN settlement s USING (round_id) WHERE r.round_id = 2 GROUP BY r.round_id, r.status");
check('round 2 is open, with ten settlements numbered on from round 1\'s',
    $r2 && $r2['status'] === 'open' && $r2['first'] === '11' && $r2['last'] === '20' && $r2['n'] === '10', json_encode($r2));

$back->get('/index.php');
$back->post('/checksignup.php', $signup);
$k = row($db, "SELECT userid, setid, round_id, ename FROM user WHERE account_id = 1 AND round_id = 2");
check('the account signs up into round 2, under its old kingdom name', $k && $k['ename'] === 'TestLord');
check('...in one of round 2\'s settlements', $k && $k['setid'] >= 11 && $k['setid'] <= 20, json_encode($k));
check('...and round 1 keeps its kingdom', row($db, "SELECT userid FROM user WHERE account_id = 1 AND round_id = 1") !== null);

$tester = login($BASE, 'tester@example.com', 'test1234');
$page = $tester->get('/main.php?pageid=news');
check('logging in reaches the round 2 kingdom', $tester->lastStatus === 200
    && row($db, "SELECT online FROM user WHERE userid = " . (int) $k['userid'])['online'] === '1');

// 5. The tick, before and after the start.
$safemode = row($db, "SELECT safemode FROM user WHERE userid = " . (int) $k['userid'])['safemode'];
$out = tick();
check('the tick leaves an open round alone', strpos($out, 'No round is running') !== false
    && row($db, "SELECT safemode FROM user WHERE userid = " . (int) $k['userid'])['safemode'] === $safemode);

list($rc, $out) = round_cli('start');
check('starting round 2 succeeds', $rc === 0, $out);
check('round 2 is running, with a start time', row($db, "SELECT status FROM `round` WHERE round_id = 2 AND started_at IS NOT NULL")['status'] === 'running');
tick();
check('the tick advances round 2', (int) row($db, "SELECT safemode FROM user WHERE userid = " . (int) $k['userid'])['safemode'] === $safemode - 1);
check('...and leaves round 1 as it ended', round_rows($db, 1) === $frozen);

list($rc, $out) = round_cli('list');
check('the list shows both', $rc === 0 && preg_match('/^1\tended\t3 kingdoms/m', $out) && preg_match('/^2\trunning\t1 kingdoms/m', $out), $out);

loadFixture($db);

echo "\n";
if ($failures) {
    echo "FAIL: $failures check(s) failed.\n";
    exit(1);
}
echo "PASS: a round ends into its results, and the next opens, starts and ticks alone.\n";
