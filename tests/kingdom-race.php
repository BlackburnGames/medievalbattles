<?php
/**
 * One kingdom, two orders at once: the lost update mb_kingdom_lock() closes.
 *
 * Every action page reads the kingdom once, in functions.php, and writes back
 * totals worked out from that read -- `SET gp=?` -- with nothing holding the
 * row in between. A player logged in twice could send a build order and a
 * no-op recruit order together: when the recruit page's write of the gold it
 * had read landed last, the build was free. The exposure review saw it in
 * three runs out of six (docs/modernization.md, "The exposure review").
 *
 * Each round here sends the two together from two sessions -- PHP's session
 * lock would queue them behind each other on one -- and asserts that the gold
 * paid for the homes queued: never homes for nothing. A race is timing, so a
 * pass is evidence and a failure is proof. Without the lock, seven of the
 * ten rounds built for nothing in the run this was written against.
 *
 *   docker compose exec -T web php /repo/tests/kingdom-race.php
 */

$host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : 'db';
$name = getenv('MB_DB_NAME') ? getenv('MB_DB_NAME') : 'mbv6';
$user = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
$pass = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';
$BASE = getenv('MB_BASE_URL') ? getenv('MB_BASE_URL') : 'http://localhost';

$db = @mysqli_connect($host, $user, $pass, $name);
if (!$db) {
    fwrite(STDERR, "Cannot connect: " . mysqli_connect_error() . "\n");
    exit(2);
}
require_once __DIR__ . '/../app/include/db.php';
mb_db_session($db);

$ROUNDS = 10;
$failed = 0;

function check($label, $condition, $detail = '')
{
    global $failed;
    if ($condition) {
        echo "ok: $label\n";
    } else {
        $failed++;
        echo "FAIL: $label" . ($detail !== '' ? " -- $detail" : '') . "\n";
    }
}

function one($db, $sql)
{
    $r = mysqli_query($db, $sql);
    $row = $r ? mysqli_fetch_row($r) : null;
    return $row ? $row[0] : null;
}

/** A logged-in tester session in its own cookie jar. */
function session($base)
{
    $jar = tempnam(sys_get_temp_dir(), 'mbkrace');
    $ch = curl_init("$base/checklogin.php");
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(array('email' => 'tester@example.com', 'pw' => 'test1234')),
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_RETURNTRANSFER => true,
    ));
    curl_exec($ch);
    curl_close($ch);
    return $jar;
}

/** POST each [jar, path, fields] at once. */
function postTogether($base, array $requests)
{
    $mh = curl_multi_init();
    $handles = array();
    foreach ($requests as $r) {
        list($jar, $path, $fields) = $r;
        $ch = curl_init("$base/$path");
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_COOKIEFILE     => $jar,
            CURLOPT_RETURNTRANSFER => true,
        ));
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh);
        }
    } while ($running && $status === CURLM_OK);
    foreach ($handles as $ch) {
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
}

$build   = session($BASE);
$recruit = session($BASE);

$free = 0;
for ($i = 0; $i < $ROUNDS; $i++) {
    $gp    = (int) one($db, "SELECT gp FROM user WHERE userid=1");
    $homes = (int) one($db, "SELECT dhome FROM buildings WHERE userid=1");
    postTogether($BASE, array(
        array($build, 'buildings.php', array('update' => 1, 'uhome' => 1)),
        array($recruit, 'military.php', array('recruited' => 1, 'urecruit' => 0)),
    ));
    $paid   = $gp - (int) one($db, "SELECT gp FROM user WHERE userid=1");
    $queued = (int) one($db, "SELECT dhome FROM buildings WHERE userid=1") - $homes;
    if ($queued > 0 && $paid <= 0) {
        $free++;
    }
}
check('the build order queued homes at all', (int) one($db, "SELECT dhome FROM buildings WHERE userid=1") > 0);
check("no home was built for nothing in $ROUNDS racing rounds", $free === 0, "$free free");

// Six votes for yourself at once, from six logins. The count used to be a
// counter moved by one, and each request saw no earlier vote to take back:
// the exposure review got six votes and the leadership out of it. The count
// is taken from votefor now (mb_settlement_elect()), so it is one.
$voters = array();
for ($i = 0; $i < 6; $i++) {
    $voters[] = array(session($BASE), 'govt.php', array('change' => 1, 'empvalue' => 1));
}
postTogether($BASE, $voters);
check('six simultaneous votes for yourself count once', (int) one($db, "SELECT vote FROM user WHERE userid=1") === 1,
    'vote=' . one($db, "SELECT vote FROM user WHERE userid=1"));

foreach (array_merge(array($build, $recruit), array_column($voters, 0)) as $jar) {
    @unlink($jar);
}

if ($failed) {
    echo "\nFAIL: $failed kingdom race check(s) failed.\n";
    exit(1);
}
echo "\nPASS: two orders at once on one kingdom are applied one after the other.\n";
