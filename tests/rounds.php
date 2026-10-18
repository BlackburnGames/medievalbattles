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
 *      and lands on the round list: no round open, its own result, and the
 *      ended round's leading kingdoms and guilds. Joining is refused, and
 *      the tick does nothing.
 *   4. Opening round 2 gives it settlements numbered on from round 1's. The
 *      account joins it from the round list -- under the same kingdom name
 *      it had, which round 2 has never seen -- and is logged into the new
 *      kingdom, as a later login is.
 *   5. The tick leaves an open round alone, and advances it once started,
 *      while round 1's rows stay as they ended.
 *   6. The admin area's round page ends round 2, but only with its box
 *      ticked; an administrator whose kingdom ended with it can still reach
 *      the page and open round 3, and a player cannot.
 *   7. Deleting an account keeps its round results, anonymized.
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
$back->post('/checklogin.php', array('email' => 'tester@example.com', 'pw' => 'test1234', 'login' => 'Login'));
check('the account logs in, to the round list', $back->lastStatus === 302 && $back->lastLocation === 'rounds.php');
$list = $back->get('/rounds.php');
check('...which says no round is open', $back->lastStatus === 200 && stripos($list, 'No round is open') !== false);
check('...and shows the account its result: TestLord, 1st of 3',
    preg_match('#<td>Round 1</td><td>TestLord</td><td>Human Warrior</td><td>1 of 3</td>#', $list) === 1);
check('...and the round\'s leading kingdoms and guilds',
    strpos($list, '<td>1</td><td>TestLord</td><td>1,000</td>') !== false
    && strpos($list, '<td>Testguild</td><td>2 members</td><td>1,400</td>') !== false);
$body = $back->post('/join.php', array('join' => 1, 'ename' => 'TestLord', 'race' => 'Human', 'class' => 'Fighter', 'msn' => '', 'aim' => ''));
check('joining says no round is open', stripos($body, 'No round is open') !== false);

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

list($back, $joined) = joinRound($BASE, 'tester@example.com', 'test1234', 'TestLord', 'Human', 'Fighter');
check('joining logs into the new kingdom', $back->lastStatus === 302 && $back->lastLocation === 'main.php?pageid=news',
    trim(substr(strip_tags($joined), 0, 200)));
$k = row($db, "SELECT userid, setid, round_id, ename FROM user WHERE account_id = 1 AND round_id = 2");
check('the account is in round 2, under its old kingdom name', $k && $k['ename'] === 'TestLord');
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

// 6. The same steps from the admin area. tester@ is the fixture's
// administrator, playing in round 2.
$admin = login($BASE, 'tester@example.com', 'test1234');
$admin->post('/admin/rounds.php', array('step' => 'end'));
check('the admin page will not end a round without the box ticked',
    row($db, "SELECT status FROM `round` WHERE round_id = 2")['status'] === 'running');
$body = $admin->post('/admin/rounds.php', array('step' => 'end', 'sure' => 'yes'));
check('the admin page ends round 2', row($db, "SELECT status FROM `round` WHERE round_id = 2")['status'] === 'ended'
    && (int) row($db, "SELECT count(*) AS n FROM round_result WHERE round_id = 2")['n'] === 1, trim(substr(strip_tags($body), 0, 200)));

// The administrator's kingdom ended with it; the account still administers.
$admin = new MbClient($BASE);
$admin->get('/index.php');
$admin->post('/checklogin.php', array('email' => 'tester@example.com', 'pw' => 'test1234', 'login' => 'Login'));
$admin->get('/admin/rounds.php');
check('an administrator with no kingdom reaches the admin area', $admin->lastStatus === 200);
$admin->post('/admin/rounds.php', array('step' => 'open', 'name' => 'Round 3'));
check('...and opens round 3 from it', row($db, "SELECT status FROM `round` WHERE round_id = 3")['status'] === 'open');
$poorer = new MbClient($BASE);
$poorer->get('/index.php');
$poorer->post('/checklogin.php', array('email' => 'poor@example.com', 'pw' => 'pass3', 'login' => 'Login'));
$poorer->get('/admin/rounds.php');
check('a player who is not one does not', $poorer->lastStatus === 403);

// 7. Deleting an account keeps its results, without its name on them.
require_once __DIR__ . '/../app/include/accounts.php';
mb_account_delete(3);
check('a deleted account\'s results stay, no longer its',
    row($db, "SELECT account_id FROM round_result WHERE round_id = 1 AND ename = 'PoorSerf'")['account_id'] === '0'
    && (int) row($db, "SELECT count(*) AS n FROM round_result WHERE round_id = 1")['n'] === 3);

loadFixture($db);

echo "\n";
if ($failures) {
    echo "FAIL: $failures check(s) failed.\n";
    exit(1);
}
echo "PASS: a round ends into its results, and the next opens, starts and ticks alone.\n";
