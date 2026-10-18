<?php
/**
 * mb_empire_remove(), on an empire with everything attached to it.
 *
 * Both ways an empire ends -- deleting the account and losing its last land --
 * go through app/include/empire.php now, and neither is a path the crawl can
 * take whole. The crawl deletes one account (see accountDeletion() in
 * smoke.php), but that account leads no guild, and destroying an empire in
 * battle needs one with less than ten land, which the fixture does not have
 * and the tick golden master forbids adding. So the removal is driven directly
 * here, on rows built for it.
 *
 * The empire under test leads a guild with a member, votes for one empire and
 * is voted for by another, has a join request filed with someone else's guild,
 * and has a row in every per-empire table. What this asserts:
 *
 *   1. Every per-empire row is gone -- including `military`, which attack.php's
 *      `DELETE FROM militaryWHERE` never removed.
 *   2. The vote it cast is withdrawn -- which attack.php's `ename='$V_check'`
 *      never did -- and votes cast for it are reset.
 *   3. The guild it led is gone and its member is back to None -- which
 *      preferences.php's `owner='$ename'` lookup never found.
 *   4. Its settlement has one member fewer, which preferences.php never did.
 *   5. Removing an empire that does not exist touches nothing.
 *
 *   docker compose exec -T web php /repo/tests/empire-remove.php
 *
 * It writes on ids far outside the fixture's and deletes what it wrote, so it
 * is order-independent and safe against a live world.
 */

$host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : 'db';
$name = getenv('MB_DB_NAME') ? getenv('MB_DB_NAME') : 'mbv6';
$user = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
$pass = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';

$db = @mysqli_connect($host, $user, $pass, $name);
if (!$db) {
    fwrite(STDERR, "Cannot connect: " . mysqli_connect_error() . "\n");
    exit(2);
}
mysqli_set_charset($db, 'utf8mb4');

require_once __DIR__ . '/../app/include/db.php';
require_once __DIR__ . '/../app/include/empire.php';

$GONE    = 999201;   // the empire being removed
$MEMBER  = 999202;   // in its guild
$VOTEE   = 999203;   // it votes for this one
$VOTER   = 999204;   // this one votes for it
$OTHERGL = 999205;   // leads another guild, which it asked to join
$SETID   = 999201;
$GNAME   = 'mb-empire-remove-test';
$TABLES  = array('user', 'military', 'buildings', 'research', 'returntbl', 'explore');

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
function q($db, $sql)
{
    if (!mysqli_query($db, $sql)) {
        fwrite(STDERR, "setup failed: " . mysqli_error($db) . "\n  $sql\n");
        exit(2);
    }
}
function cleanup($db)
{
    global $GONE, $MEMBER, $VOTEE, $VOTER, $OTHERGL, $SETID, $GNAME, $TABLES;
    $ids = "$GONE, $MEMBER, $VOTEE, $VOTER, $OTHERGL";
    foreach ($TABLES as $t) {
        mysqli_query($db, "DELETE FROM $t WHERE userid IN ($ids)");
    }
    mysqli_query($db, "DELETE FROM guild WHERE gname IN ('$GNAME', '$GNAME-other')");
    mysqli_query($db, "DELETE FROM guildrequests WHERE applicant IN ('$GONE', '$MEMBER') OR gl_userid IN ('$GONE', '$OTHERGL')");
    mysqli_query($db, "DELETE FROM settlement WHERE setid=$SETID");
}

cleanup($db);

// --- The world around it. ---
foreach (array(
    // Votes are honest: Gone holds 2 (Member, Voter) and leads on the tie with
    // Votee, which holds 2 (Gone, Other). Once Gone is removed Votee has 1 and
    // nobody else has any, so the recount must crown Votee.
    $GONE    => array('Gone Empire',   $GNAME,  'Votee Empire', 2),
    $MEMBER  => array('Member Empire', $GNAME,  'Gone Empire',  0),
    $VOTEE   => array('Votee Empire',  'None',  'None',         2),
    $VOTER   => array('Voter Empire',  'None',  'Gone Empire',  0),
    $OTHERGL => array('Other Leader',  "$GNAME-other", 'Votee Empire', 0),
) as $id => $r) {
    q($db, "INSERT INTO user (ename, userid, setid, guild, votefor, vote)
            VALUES ('$r[0]', $id, $SETID, '$r[1]', '$r[2]', $r[3])");
}
foreach (array('military', 'buildings', 'research', 'returntbl', 'explore') as $t) {
    q($db, "INSERT INTO $t (userid) VALUES ($GONE)");
}
q($db, "INSERT INTO guild (gname, owner, mem) VALUES ('$GNAME', '$GONE', 2), ('$GNAME-other', '$OTHERGL', 1)");
q($db, "INSERT INTO guildrequests (applicant, gl_userid) VALUES ('$GONE', '$OTHERGL'), ('$MEMBER', '$GONE')");
q($db, "INSERT INTO settlement (setid, setname, members) VALUES ($SETID, 'Removal Test', 5)");
q($db, "UPDATE user SET sl = IF(userid=$GONE, 'yes', 'no') WHERE setid=$SETID");

// --- Removing nobody first: it must touch nothing. ---
check('a userid that names nobody is reported as such', mb_empire_remove($db, 999299) === false);
check('...and the settlement count is untouched', (int) one($db, "SELECT members FROM settlement WHERE setid=$SETID") === 5);

// --- The removal. ---
check('the empire is removed', mb_empire_remove($db, $GONE) === true);

foreach ($TABLES as $t) {
    check("its $t row is gone", (int) one($db, "SELECT count(*) FROM $t WHERE userid=$GONE") === 0);
}
check('the vote it cast is withdrawn', (int) one($db, "SELECT vote FROM user WHERE userid=$VOTEE") === 1);
check('a vote cast for it is reset', one($db, "SELECT votefor FROM user WHERE userid=$VOTER") === 'None');
check('the guild it led is gone', (int) one($db, "SELECT count(*) FROM guild WHERE gname='$GNAME'") === 0);
check('that guild\'s member is guildless', one($db, "SELECT guild FROM user WHERE userid=$MEMBER") === 'None');
check('requests to its guild are gone', (int) one($db, "SELECT count(*) FROM guildrequests WHERE gl_userid='$GONE'") === 0);
check('its own request elsewhere is gone', (int) one($db, "SELECT count(*) FROM guildrequests WHERE applicant='$GONE'") === 0);
check('another guild is untouched', (int) one($db, "SELECT count(*) FROM guild WHERE gname='$GNAME-other'") === 1);
check('its settlement has one member fewer', (int) one($db, "SELECT members FROM settlement WHERE setid=$SETID") === 4);
check('the settlement is recounted and the top vote-holder leads',
    one($db, "SELECT sl FROM user WHERE userid=$VOTEE") === 'yes'
    && (int) one($db, "SELECT count(*) FROM user WHERE setid=$SETID AND sl='yes'") === 1);

cleanup($db);

echo "\n";
if ($failed > 0) {
    echo "FAIL: $failed empire removal check(s) failed.\n";
    exit(1);
}
echo "PASS: removing an empire removes everything that depends on it, and nothing else.\n";
exit(0);
