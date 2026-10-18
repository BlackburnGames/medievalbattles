<?php
/**
 * Sessions: what a session id is worth, and when it stops being worth it.
 *
 * The 2003 session had no expiry, a cookie script could read, the same id
 * before and after login, a logout that left it standing, no notion that the
 * password it held had changed, and nothing stopping another site from making
 * requests with it. app/include/session.php answers each of those, and none of
 * them is visible to the crawl: its client never sends Sec-Fetch-Site, never
 * looks at a cookie's attributes, and never goes back to a session it has
 * finished with. So each is driven here, over HTTP, with curl.
 *
 * What this asserts:
 *
 *   1. The cookie is HttpOnly and SameSite=Strict.
 *   2. An id the server did not issue is not adopted, and logging in replaces
 *      the id the browser had -- so a planted or observed id is worthless.
 *   3. Logging out ends the session: the old id no longer logs anyone in.
 *   4. A request another site made gets no session -- not to read, not to act
 *      and not to log out -- and the session is intact afterwards.
 *   5. Changing the password ends the account's other sessions.
 *   6. A session idle past MB_SESSION_IDLE, or older than MB_SESSION_MAX, is
 *      ended. Waiting hours is not a test, so the clock is wound back in the
 *      session file itself, from the CLI, which shares the save path.
 *   7. A session ends when its account is gone, or its kingdom key stops
 *      naming a kingdom of that account, and a key that is not one -- the
 *      password hash a session held before accounts moved -- never logs in.
 *      The account's address changing does not end it: the link is the id.
 *
 * Uses poor@ and restores its password and rows, so it is safe after the
 * crawl. The account's hash does change format on the way -- md5 to bcrypt --
 * which is what any login does to a legacy account.
 *
 *   docker compose exec -T web php /repo/tests/session.php
 */

$BASE  = getenv('MB_BASE_URL') ? getenv('MB_BASE_URL') : 'http://localhost';
$EMAIL = 'poor@example.com';
$PASS  = 'pass3';

require_once __DIR__ . '/../app/include/session.php';   // MB_SESSION_IDLE, MB_SESSION_MAX
if (!defined('MB_SESSION_IDLE')) {
    // A session.php that predates the limits; the expiry checks then fail,
    // which is the point, rather than this file failing to load.
    define('MB_SESSION_IDLE', 2 * 3600);
    define('MB_SESSION_MAX', 24 * 3600);
}

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

/**
 * One request. $sid is sent as the session cookie if given; the response's
 * status, Location, the Set-Cookie line for the session and its new id come back.
 */
function req($path, $sid = null, array $post = null, array $headers = array())
{
    global $BASE;
    $ch = curl_init("$BASE/$path");
    $h = $headers;
    if ($sid !== null) {
        $h[] = 'Cookie: PHPSESSID=' . $sid;
    }
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $h,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $out = array(
        'status'   => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'headers'  => substr($raw, 0, $hsize),
        'body'     => substr($raw, $hsize),
        'cookie'   => '',
        'sid'      => null,
        'location' => '',
    );
    curl_close($ch);
    foreach (explode("\r\n", $out['headers']) as $line) {
        if (stripos($line, 'Set-Cookie: PHPSESSID=') === 0) {
            $out['cookie'] = $line;
            if (preg_match('/PHPSESSID=([^;]*)/', $line, $m) && $m[1] !== '' && $m[1] !== 'deleted') {
                $out['sid'] = $m[1];
            }
        }
        if (stripos($line, 'Location:') === 0) {
            $out['location'] = trim(substr($line, 9));
        }
    }
    return $out;
}

function login($sid = null, $pass = null)
{
    global $EMAIL, $PASS;
    $r = req('checklogin.php', $sid, array('email' => $EMAIL, 'pw' => $pass === null ? $PASS : $pass, 'login' => 'Login'));
    return $r['status'] === 302 ? $r['sid'] : null;
}

/** Whether $sid is a logged-in session, judged by main.php not bouncing it to the timeout page. */
function loggedIn($sid, array $headers = array())
{
    $r = req('main.php?pageid=news', $sid, null, $headers);
    return $r['status'] === 200 && strpos($r['location'], 'logout.php') === false;
}

/**
 * Move a session's clock back by $seconds, in the session file itself.
 *
 * The file is PHP's default serialization, "key|value;key|value", and the two
 * clocks are integers, so the edit is a substitution on "mb_seen|i:<n>;".
 */
function windBack($sid, $key, $seconds)
{
    $file = rtrim(ini_get('session.save_path'), '/') . '/sess_' . $sid;
    $data = @file_get_contents($file);
    if ($data === false) {
        return false;
    }
    $data = preg_replace_callback('/(^|;|\})' . preg_quote($key, '/') . '\|i:(\d+);/', function ($m) use ($key, $seconds) {
        return $m[1] . $key . '|i:' . ($m[2] - $seconds) . ';';
    }, $data, 1, $n);
    return $n === 1 && file_put_contents($file, $data) !== false;
}

// --- 1. The cookie. ---
$r = req('checklogin.php', null, array('email' => $EMAIL, 'pw' => $PASS, 'login' => 'Login'));
check('the session cookie is HttpOnly', stripos($r['cookie'], 'httponly') !== false, $r['cookie']);
check('the session cookie is SameSite=Strict', stripos($r['cookie'], 'samesite=strict') !== false, $r['cookie']);
$a = $r['sid'];
check('logging in works', $a !== null && loggedIn($a));

// --- 2. Fixation. ---
// A login carrying an id the attacker chose: strict mode refuses to adopt it,
// and the login replaces it regardless.
$planted = 'plantedbyanattacker0123456789';
$got = login($planted);
check('a login under a planted id does not keep it', $got !== null && $got !== $planted);
check('...and the planted id is not logged in', !loggedIn($planted));
// A real id the server issued before the login -- main.php starts a session
// for a logged-out visitor on its way to the timeout page.
$before = req('main.php?pageid=news')['sid'];
$after  = login($before);
check('logging in replaces the id the browser had', $after !== null && $after !== $before);
check('...and the old id is not logged in', !loggedIn($before));

// --- 3. Logout. ---
$s = login();
req('logout.php', $s);
check('logging out ends the session', !loggedIn($s));

// --- 4. Another site's request. ---
$s = login();
check('a cross-site request gets no session', !loggedIn($s, array('Sec-Fetch-Site: cross-site')));
check('nor does a same-site one from a sibling origin', !loggedIn($s, array('Sec-Fetch-Site: same-site')));
req('logout.php', $s, null, array('Sec-Fetch-Site: cross-site'));
check('a cross-site link to logout.php does not log the player out', loggedIn($s));
$r = req('checklogin.php', null, array('email' => $EMAIL, 'pw' => $PASS, 'login' => 'Login'), array('Sec-Fetch-Site: cross-site'));
check('a login another site submits does not log anyone in', $r['sid'] === null || !loggedIn($r['sid']));
check('a request the player\'s own pages make still works', loggedIn($s, array('Sec-Fetch-Site: same-origin')));
check('so does one the player typed', loggedIn($s, array('Sec-Fetch-Site: none')));

// --- 5. A password change ends the other sessions. ---
$one = login();
$two = login();
$change = function ($sid, $from, $to) {
    return req('preferences.php', $sid, array('changepw' => 'Change', 'currentpw' => $from, 'newpw' => $to, 'cnewpw' => $to));
};
$r = $change($one, $PASS, 'sessiontest9');
check('the password change went through', stripos($r['body'], 'password has been changed') !== false);
check('the session that changed it is still logged in', loggedIn($one));
check('the account\'s other session is ended', !loggedIn($two));
$change($one, 'sessiontest9', $PASS);
check('the password is restored', login() !== null);

// --- 6. Expiry. ---
$s = login();
check('the idle clock can be wound back', windBack($s, 'mb_seen', MB_SESSION_IDLE + 60));
check('a session idle past the limit is ended', !loggedIn($s));
$s = login();
check('the login clock can be wound back', windBack($s, 'mb_auth_at', MB_SESSION_MAX + 60));
check('a session older than the limit is ended, however busy', !loggedIn($s));
$s = login();
windBack($s, 'mb_seen', MB_SESSION_IDLE - 60);
check('a session inside the idle limit carries on', loggedIn($s));

// --- 7. The account and the kingdom, each checked on every request. ---
// The password is the account's, in the accounts database; the session's $pw
// is the kingdom's key. Either one ceasing to match ends the session. Driven
// by editing the rows directly, from the CLI, and putting them back.
$host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : 'db';
$dbu  = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
$dbp  = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';
$round    = mysqli_connect($host, $dbu, $dbp, getenv('MB_DB_NAME') ? getenv('MB_DB_NAME') : 'mbv6');
$accounts = mysqli_connect($host, $dbu, $dbp, getenv('MB_ACCOUNTS_DB_NAME') ? getenv('MB_ACCOUNTS_DB_NAME') : 'mb_accounts');
$qEmail = "'" . mysqli_real_escape_string($round, $EMAIL) . "'";
$key = mysqli_fetch_row(mysqli_query($round, "SELECT pw FROM user WHERE email = $qEmail"))[0];
check('the kingdom holds a key, not a password hash', preg_match('/^[0-9a-f]{64}$/', $key) === 1, $key);

$s = login();
check('...a session logged in to begin with', loggedIn($s));
mysqli_query($round, "UPDATE user SET pw = '" . str_repeat('0', 64) . "' WHERE email = $qEmail");
check('a session whose kingdom key no longer names a kingdom is ended', !loggedIn($s));
mysqli_query($round, "UPDATE user SET pw = '$key' WHERE email = $qEmail");

// The link is user.account_id, not the address, so the account's own address
// changing does not end the session -- only the kingdom ceasing to answer
// does. The round's copy of the address is what the session carries.
$s = login();
check('...a session logged in to begin with', loggedIn($s));
mysqli_query($accounts, "UPDATE account SET email = 'moved@example.com' WHERE email = $qEmail");
check('a session survives its account\'s address changing', loggedIn($s));
mysqli_query($accounts, "UPDATE account SET email = $qEmail WHERE email = 'moved@example.com'");

$s = login();
check('...a session logged in to begin with', loggedIn($s));
$row = mysqli_fetch_assoc(mysqli_query($accounts, "SELECT * FROM account WHERE email = $qEmail"));
mysqli_query($accounts, "DELETE FROM account WHERE email = $qEmail");
check('a session whose account is gone is ended', !loggedIn($s));
mysqli_query($accounts, "INSERT INTO account (account_id, email, pw, admin, signup_comp_id, created) VALUES ("
    . (int) $row['account_id'] . ", $qEmail, '" . mysqli_real_escape_string($accounts, $row['pw']) . "', "
    . (int) $row['admin'] . ", '" . mysqli_real_escape_string($accounts, $row['signup_comp_id']) . "', '{$row['created']}')");

$s = login();
check('...a session logged in to begin with', loggedIn($s));
mysqli_query($round, "UPDATE user SET account_id = 0 WHERE email = $qEmail");
check('a session whose kingdom belongs to another account is ended', !loggedIn($s));
mysqli_query($round, "UPDATE user SET account_id = " . (int) $row['account_id'] . " WHERE email = $qEmail");

/** Replace a string value in a session file, keeping PHP's serialization valid. */
function setInSession($sid, $name, $value)
{
    $file = rtrim(ini_get('session.save_path'), '/') . '/sess_' . $sid;
    $data = @file_get_contents($file);
    if ($data === false) {
        return false;
    }
    $data = preg_replace_callback('/(^|;|\})' . preg_quote($name, '/') . '\|s:\d+:"[^"]*";/', function ($m) use ($name, $value) {
        return $m[1] . $name . '|s:' . strlen($value) . ':"' . $value . '";';
    }, $data, 1, $n);
    return $n === 1 && file_put_contents($file, $data) !== false;
}

// A key that is not one never reaches a query. What a session from before the
// accounts moved held as $pw -- the password hash -- is the realistic case.
$s = login();
check('...a session logged in to begin with', loggedIn($s));
check('a session\'s key can be rewritten', setInSession($s, 'pw', '3afc79b597f88a72528e864cf81856d2'));
check('a session holding a password hash as its key is not logged in', !loggedIn($s));

echo "\n";
if ($failed > 0) {
    echo "FAIL: $failed session check(s) failed.\n";
    exit(1);
}
echo "PASS: a session id is worth what it should be, and only while it should be.\n";
exit(0);
