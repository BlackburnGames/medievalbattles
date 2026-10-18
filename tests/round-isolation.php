<?php
/**
 * An ended round is history: nothing done to the running one reaches it.
 *
 * Every round shares one database (docs/rounds.md), so a statement that
 * forgets its round does not fail -- it quietly rewrites a finished one.
 * tests/round-audit.php reads the code for that; this runs it.
 *
 * It loads the fixture, whose world is round 1, running, and adds round 2,
 * ended, with a row in every table a round owns. Round 2 is built to be hit:
 * its kingdoms, guild and accounts have round 1's names and owners, so a
 * lookup by name finds both; and its values are ones each of the tick's
 * whole-table statements would move -- timers about to run out, a return
 * landing, research over its cap, messages about to expire, a kingdom one
 * tick from deletion for inactivity.
 *
 * Then, against the running round: the tick, a guild leader deleting their
 * guild, and every administrator action -- each of gameconfig.php's and its
 * delete-one-kingdom. After each, round 2's rows must be
 * exactly as they were, and the action must have done something to round 1,
 * or the pass proves nothing.
 *
 * It reloads the schema, so it destroys whatever world it runs against.
 *
 *   docker compose exec -T web php /repo/tests/round-isolation.php
 */

require_once __DIR__ . '/lib/golden.php';

/** The tables a round owns, as tests/round-audit.php lists them. */
$ROUND_TABLES = array(
    'user', 'military', 'research', 'buildings', 'explore', 'returntbl',
    'barter', 'empnews', 'messages',
    'settlement', 'setnews', 'setforums', 'setforumsmsgs',
    'guild', 'guildmsgs', 'guildnews', 'guildrequests', 'guildthreads',
);

$failures = 0;
function check($label, $ok)
{
    global $failures;
    echo ($ok ? 'ok' : 'FAIL') . ": $label\n";
    if (!$ok) {
        $failures++;
    }
}

function q($db, $sql)
{
    if (!mysqli_query($db, $sql)) {
        fwrite(STDERR, "FATAL: $sql\n  " . mysqli_error($db) . "\n");
        exit(2);
    }
}

function count_of($db, $sql)
{
    $row = mysqli_fetch_row(mysqli_query($db, $sql));
    return (int) $row[0];
}

/** Every row round 2 has, table by table, in a fixed order. */
function ended_round($db)
{
    global $ROUND_TABLES;
    $out = array();
    foreach (array_merge($ROUND_TABLES, array('round')) as $t) {
        $n = mysqli_num_fields(mysqli_query($db, "SELECT * FROM `$t` LIMIT 0"));
        $r = mysqli_query($db, "SELECT * FROM `$t` WHERE round_id = 2 ORDER BY " . implode(', ', range(1, $n)));
        while ($row = mysqli_fetch_row($r)) {
            $out[] = "$t\t" . implode("\t", $row);
        }
    }
    return $out;
}

/**
 * The fixture, with round 2 beside it. Reloaded before each action, so that
 * each runs on a whole world rather than on what the last one left.
 *
 * Round 2's ids are its own -- ids are issued once across rounds -- and its
 * names are round 1's.
 */
function load_world($db)
{
    loadFixture($db);

    q($db, "INSERT INTO `round` (round_id, name, status, started_at, ended_at)
            VALUES (2, 'Ended Round', 'ended', '2003-01-01 00:00:00', '2003-02-01 00:00:00')");
    q($db, "INSERT INTO settlement (setid, setname, members, setstrength, round_id) VALUES
            (101, 'Testhold', 2, 5000, 2), (102, 'Oldhaven', 0, 0, 2)");
    // Settlement ids carry on from the last round's, as a real one's would.
    q($db, "ALTER TABLE settlement AUTO_INCREMENT = 1");
    q($db, "INSERT INTO user (ename, race, class, gp, exp, land, mts, setid, csnum, userid, online,
            safemode, countdown, barterclock, guild, votefor, vote, sl, account_id, round_id) VALUES
            ('TestLord', 'Human', 'Warrior', 999, 0, 900, 90, 101, 101, 101, 1, 5, 1, 5, 'Testguild', 'PoorSerf', 0, 'yes', 1, 2),
            ('PoorSerf', 'Giant', 'Ranger', 111, 0, 300, 30, 101, 101, 102, 1, 5, 1, 5, 'Testguild', 'None', 1, 'no', 3, 2)");
    foreach (array(101, 102) as $id) {
        q($db, "INSERT INTO military (userid, warriors, dbwar, dbwar2, dbgolem, shortsword, cs, round_id)
                VALUES ($id, 50, 7, 3, 2, 5, 5, 2)");
        q($db, "INSERT INTO research (userid, r1pts, r16pts, round_id) VALUES ($id, 90000, 200000, 2)");
        q($db, "INSERT INTO buildings (userid, home, dhome, Hhrs, round_id) VALUES ($id, 10, 6, 2, 2)");
        q($db, "INSERT INTO explore (userid, expland, landhrs, round_id) VALUES ($id, 20, 3, 2)");
        q($db, "INSERT INTO returntbl (userid, war1, time1, war2, time2, round_id) VALUES ($id, 9, 1, 4, 3, 2)");
        q($db, "INSERT INTO empnews (date, news, class, yourid, round_id) VALUES ('1/1/03', 'old news', 'yellow', $id, 2)");
    }
    q($db, "INSERT INTO messages (origin, datesent, message, yourid, mid, age, round_id)
            VALUES ('PoorSerf', '1/1/03', 'from the last round', 101, 101, 191, 2)");
    q($db, "INSERT INTO barter (seller, type, amount, cost, barterid, userid, method, page, guild, round_id)
            VALUES ('TestLord', 'Warrior', 1, 500, 101, 101, 'gp', 'guild', 'Testguild', 2)");
    q($db, "INSERT INTO setnews (date, news, class, setid, sid, age, round_id)
            VALUES ('1/1/03', 'old settlement news', 'red', 101, 101, 191, 2)");
    q($db, "INSERT INTO setforums (setid, topicid, name, topic, message, datestamp, round_id)
            VALUES (101, 101, 'TestLord', 'Old muster', 'Hold', '1/1/03', 2)");
    q($db, "INSERT INTO setforumsmsgs (setid, messageid, name, topic, topicid, message, datestamp, round_id)
            VALUES (101, 101, 'PoorSerf', 'Old muster', 101, 'Held', '1/1/03', 2)");
    q($db, "INSERT INTO guild (gname, cpw, strength, gid, datemade, info, owner, mem, notice, round_id)
            VALUES ('Testguild', '', 7000, 101, '1/1/03', 'The last round''s', '101', 2, 'None', 2)");
    q($db, "INSERT INTO guildthreads (topicid, name, topic, message, datestamp, guildname, round_id)
            VALUES (101, 'TestLord', 'Old orders', 'March', '1/1/03', 'Testguild', 2)");
    q($db, "INSERT INTO guildmsgs (messageid, name, topic, topicid, message, datestamp, round_id)
            VALUES (101, 'PoorSerf', 'Old orders', 101, 'Marched', '1/1/03', 2)");
    q($db, "INSERT INTO guildnews (date, news, class, gnid, age, gid, round_id)
            VALUES ('1/1/03', 'old guild news', 'blue', 101, 191, '101', 2)");
    q($db, "INSERT INTO guildrequests (applicant, gl_userid, round_id) VALUES ('102', '101', 2)");

    // And a line of settlement news in round 1, which the fixture has none
    // of, for the news action to clear.
    q($db, "INSERT INTO setnews (date, news, class, setid, sid, round_id) VALUES ('1/1/03', 'news', 'red', 1, 1, 1)");
}

load_world($db);
$ENDED = ended_round($db);

$missing = array();
foreach ($ROUND_TABLES as $t) {
    if (count_of($db, "SELECT count(*) FROM `$t` WHERE round_id = 2") === 0) {
        $missing[] = $t;
    }
}
check('the ended round has a row in every round table' . ($missing ? ' (none in ' . implode(', ', $missing) . ')' : ''), !$missing);

/** After an action: round 2 as it was, and round 1 moved as the action says. */
function after($label, $round1Moved)
{
    global $db, $ENDED;
    $now = ended_round($db);
    $changed = array_merge(array_diff($ENDED, $now), array_diff($now, $ENDED));
    check("$label leaves the ended round alone"
        . ($changed ? ":\n      " . implode("\n      ", array_slice($changed, 0, 6)) : ''), !$changed);
    check("$label did something to the running round", $round1Moved);
}

// The tick, from the repo root as it must run. Round 1's army in slot 1
// lands, as tests/golden/tick.txt says.
chdir(dirname(__DIR__));
exec('php update.php 2>&1', $out, $rc);
check('the tick runs', $rc === 0);
after('the tick', count_of($db, "SELECT count(*) FROM returntbl WHERE userid = 1 AND time1 = 0 AND war1 = 0") === 1);

// A guild leader deleting their guild: its members are found by guild name,
// and round 2 has a guild of that name with members of the same names.
load_world($db);
$leader = login($BASE, 'tester@example.com', 'test1234');
$leader->post('/guildconfig.php', array('deleteg' => 'Delete', 'cpw' => ''));
after('deleting a guild', count_of($db, "SELECT count(*) FROM user WHERE round_id = 1 AND guild = 'Testguild'") === 0);

// Each of gameconfig.php's actions. tester@ is the fixture's administrator.
$ACTIONS = array(
    'messages'    => "SELECT count(*) FROM messages WHERE round_id = 1",
    'news'        => "SELECT count(*) FROM setnews WHERE round_id = 1",
    'forums'      => "SELECT count(*) FROM setforums WHERE round_id = 1",
    'guilds'      => "SELECT count(*) FROM guildthreads WHERE round_id = 1",
    'settlements' => "SELECT count(*) FROM settlement WHERE round_id = 1 AND setname <> 'None'",
);
foreach ($ACTIONS as $action => $left) {
    load_world($db);
    $had = count_of($db, $left);
    $admin = login($BASE, 'tester@example.com', 'test1234');
    $admin->post('/admin/gameconfig.php', array('action' => $action));
    after("admin action '$action'", $had > 0 && count_of($db, $left) === 0);
}

// Deleting one kingdom by name: there is a PoorSerf in each round.
load_world($db);
$admin = login($BASE, 'tester@example.com', 'test1234');
$admin->post('/admin/gameconfig.php', array('empre' => 'PoorSerf', 'daccount' => 'Delete'));
after('deleting PoorSerf', count_of($db, "SELECT count(*) FROM user WHERE round_id = 1 AND ename = 'PoorSerf'") === 0);

// dempire.php deletes by name too, through the same function.
load_world($db);
$admin = login($BASE, 'tester@example.com', 'test1234');
$admin->post('/admin/dempire.php', array('empre' => 'PoorSerf', 'daccount' => 'Delete'));
after('dempire.php deleting PoorSerf', count_of($db, "SELECT count(*) FROM user WHERE round_id = 1 AND ename = 'PoorSerf'") === 0);
$body = $admin->post('/admin/dempire.php', array('empre' => 'TestLord', 'daccount' => 'Delete'));
check('dempire.php refuses an administrator\'s kingdom', stripos($body, 'is an administrator') !== false
    && count_of($db, "SELECT count(*) FROM user WHERE round_id = 1 AND ename = 'TestLord'") === 1);

// Every admin action writes, and none of them answers a link: the same
// requests as GETs leave the world as it was.
load_world($db);
$admin = login($BASE, 'tester@example.com', 'test1234');
$messages = count_of($db, "SELECT count(*) FROM messages");
$admin->get('/admin/gameconfig.php?action=messages');
$admin->get('/admin/gameconfig.php?empre=PoorSerf&daccount=Delete');
$admin->get('/admin/dempire.php?empre=PoorSerf&daccount=Delete');
$admin->get('/admin/rounds.php?step=end&sure=yes');
check('a GET to an admin action does nothing',
    count_of($db, "SELECT count(*) FROM messages") === $messages
    && count_of($db, "SELECT count(*) FROM user WHERE round_id = 1 AND ename = 'PoorSerf'") === 1
    && count_of($db, "SELECT count(*) FROM `round` WHERE round_id = 1 AND status = 'running'") === 1);

// A player aiming at the ended round. Every id in round 2 is a real row --
// the audit passes a query that filters on one -- so it is the page that has
// to see the target is not in the player's round. Round 2's kingdom 101 has
// no safe mode, its guild has the tester's guild's name, and its guild
// listing is on a board the tester can trade on by that name.
load_world($db);
q($db, "UPDATE user SET safemode = 0 WHERE userid = 101");
$ENDED = ended_round($db);
$player = login($BASE, 'tester@example.com', 'test1234');
$AIMED = array(
    array('post', 'attack.php',   array('attack' => 1, 'empvalue' => 101, 'uwarrior' => 1), 'That empire does not exist'),
    array('post', 'attackr.php',  array('attack' => 1, 'empvalue' => 101, 'uwarrior' => 1), 'That empire does not exist'),
    array('post', 'attackm.php',  array('attack' => 1, 'empvalue' => 101, 'uwarrior' => 1), 'That empire does not exist'),
    array('post', 'scattack.php', array('suicideatk' => 1, 'empvalue' => 101, 'send' => 1, 'toatk' => 'civ'), 'That empire does not exist'),
    array('post', 'intel.php',    array('gather' => 1, 'empvalue' => 101, 'send' => 1), 'That empire does not exist'),
    array('post', 'messaging.php', array('sendmessage' => 1, 'empvalue' => 101, 'umessage' => 'hello'), 'That empire does not exist'),
    array('get',  'guildbarter.php?barter=1&bid=101', null, 'There is no such barter'),
    array('get',  'topicg.php?topicid=101', null, 'You cannot view forum posts in other guilds'),
    array('get',  'gl-topic.php?topicid=101', null, 'That thread is not in your guild'),
    array('post', 'gl-inputposts.php', array('addreply' => 1, 'topicid' => 101, 'topic' => 'x', 'message' => 'y'), 'That thread is not in your guild'),
);
foreach ($AIMED as $a) {
    list($how, $page, $fields, $refusal) = $a;
    $body = $how === 'get' ? $player->get('/' . $page) : $player->post('/' . $page, $fields);
    check("$page refuses a target in the ended round", strpos($body, $refusal) !== false);
}
$now = ended_round($db);
$changed = array_merge(array_diff($ENDED, $now), array_diff($now, $ENDED));
check('aiming at it leaves the ended round alone'
    . ($changed ? ":\n      " . implode("\n      ", array_slice($changed, 0, 6)) : ''), !$changed);

loadFixture($db);

echo "\n";
if ($failures) {
    echo "FAIL: $failures check(s) failed.\n";
    exit(1);
}
echo "PASS: an ended round is left exactly as it was.\n";
