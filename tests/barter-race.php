<?php
/**
 * The barter board under concurrency: one listing, many buyers at once.
 *
 * Buying and cancelling used to read the listing and both balances at page
 * load, check them, and then write absolute figures computed from what they
 * read -- with a DELETE at the end that never said whether it had removed
 * anything. Two requests in flight both passed every check. Against the old
 * handler this file failed five runs out of five: of twelve simultaneous buys
 * of one listing, between five and twelve succeeded, and the seller was paid
 * for more than one; of twelve cancels, between five and ten.
 * The handler now claims the row by deleting it and checks that it did, and
 * moves everything relative to what is there -- see app/include/barter_handler.php.
 *
 * The crawl cannot see any of this. It makes one request at a time, and a
 * race needs two that overlap. So this fires them together with curl_multi,
 * from separate sessions: PHP's file session handler locks a session for the
 * whole request, so requests sharing one cookie queue behind each other and
 * never race. A player gets around that by logging in twice, and so does this.
 *
 * What this asserts, for a buy and then a cancel:
 *
 *   1. Exactly one of the simultaneous requests succeeds.
 *   2. Every balance moved by exactly one sale's worth -- the buyer's gold and
 *      land, the seller's gold, the seller's news -- or one refund's.
 *   3. The listing is gone.
 *
 * A correct handler passes every run. A race is a matter of timing, so a broken
 * one is not guaranteed to fail every run, though the old one did: a pass here
 * is strong evidence and a failure is proof.
 *
 * REQUIRES A FRESH DATABASE: it uses fixture listings 1 and 3, which the crawl
 * consumes. tests/run-all.sh resets before running it.
 *
 *   docker compose exec -T web php /repo/tests/barter-race.php
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

$SESSIONS = 12;
$failed   = 0;

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

/** $n logged-in tester sessions, each its own cookie jar. */
function sessions($base, $n)
{
    $jars = array();
    for ($i = 0; $i < $n; $i++) {
        $jar = tempnam(sys_get_temp_dir(), 'mbrace');
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
        $jars[] = $jar;
    }
    return $jars;
}

/** The same GET from every session at once; the bodies that came back. */
function fireTogether($base, $path, array $jars)
{
    $mh = curl_multi_init();
    $handles = array();
    foreach ($jars as $jar) {
        $ch = curl_init("$base/$path");
        curl_setopt_array($ch, array(CURLOPT_COOKIEFILE => $jar, CURLOPT_RETURNTRANSFER => true));
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh);
        }
    } while ($running && $status === CURLM_OK);

    $bodies = array();
    foreach ($handles as $ch) {
        $bodies[] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $bodies;
}

function countContaining(array $bodies, $needle)
{
    $n = 0;
    foreach ($bodies as $b) {
        if (strpos($b, $needle) !== false) {
            $n++;
        }
    }
    return $n;
}

$jars = sessions($BASE, $SESSIONS);

// --- Buy: listing 1, 10 Land for 2,000 gp, sold by PoorSerf (userid 3). ---
$cost = (int) one($db, "SELECT cost FROM barter WHERE barterid=1");
$amt  = (int) one($db, "SELECT amount FROM barter WHERE barterid=1");
check('fixture: listing 1 is there to buy', $cost > 0 && $amt > 0);

$buyer_gp   = (int) one($db, "SELECT gp FROM user WHERE userid=1");
$buyer_land = (int) one($db, "SELECT land FROM user WHERE userid=1");
$buyer_alnd = (int) one($db, "SELECT aland FROM buildings WHERE userid=1");
$seller_gp  = (int) one($db, "SELECT gp FROM user WHERE userid=3");
$seller_nws = (int) one($db, "SELECT count(*) FROM empnews WHERE yourid=3 AND news LIKE '%has bought%'");

$bodies = fireTogether($BASE, 'barter.php?barter=1&bid=1', $jars);

check("buy: exactly one of $SESSIONS simultaneous buys succeeds",
    countContaining($bodies, 'You have bartered') === 1,
    countContaining($bodies, 'You have bartered') . ' succeeded');
check('buy: the buyer paid once',
    (int) one($db, "SELECT gp FROM user WHERE userid=1") === $buyer_gp - $cost);
check('buy: the seller was paid once',
    (int) one($db, "SELECT gp FROM user WHERE userid=3") === $seller_gp + $cost);
check('buy: the land arrived once, in both columns',
    (int) one($db, "SELECT land FROM user WHERE userid=1") === $buyer_land + $amt
    && (int) one($db, "SELECT aland FROM buildings WHERE userid=1") === $buyer_alnd + $amt);
check('buy: the seller was told once',
    (int) one($db, "SELECT count(*) FROM empnews WHERE yourid=3 AND news LIKE '%has bought%'") === $seller_nws + 1);
check('buy: the listing is gone',
    (int) one($db, "SELECT count(*) FROM barter WHERE barterid=1") === 0);

// --- Cancel: listing 3, the tester's own Warrior. ---
$c_amt    = (int) one($db, "SELECT amount FROM barter WHERE barterid=3");
$warriors = (int) one($db, "SELECT warriors FROM military WHERE userid=1");
check('fixture: listing 3 is there to cancel', $c_amt > 0);

$bodies = fireTogether($BASE, 'barter.php?end=true&bid=3', $jars);

check("cancel: exactly one of $SESSIONS simultaneous cancels succeeds",
    countContaining($bodies, 'has been cancelled') === 1,
    countContaining($bodies, 'has been cancelled') . ' succeeded');
check('cancel: the units came back once',
    (int) one($db, "SELECT warriors FROM military WHERE userid=1") === $warriors + $c_amt);
check('cancel: the listing is gone',
    (int) one($db, "SELECT count(*) FROM barter WHERE barterid=3") === 0);

foreach ($jars as $jar) {
    @unlink($jar);
}

echo "\n";
if ($failed > 0) {
    echo "FAIL: $failed barter concurrency check(s) failed.\n";
    exit(1);
}
echo "PASS: one listing is sold or cancelled exactly once, however many ask at once.\n";
exit(0);
