<?php
/**
 * The news pipeline, end to end: three writers, one renderer.
 *
 * The 51 news sites used to build finished markup and store it, with two
 * player-chosen empire names interpolated into it and nothing escaping them.
 * They write plain text and a category now, and mb_news_html() puts the two
 * back together at render time -- see app/include/news.php.
 *
 * That conversion needs a test the suite runs, and the smoke crawl cannot be
 * it. The crawl drives the writers (see the combat phase in smoke.php) but it
 * can only assert that a page came back without a warning; it has no way to
 * put an XSS payload in an empire name, because the fixture's names are what
 * the tick golden master is computed from. So the round trip is driven
 * directly here, with the hostile values a real player could choose.
 *
 * What this asserts, in order:
 *
 *   1. The writers store text. What goes in comes back out byte for byte --
 *      no escaping on the way in, which is the defect the conversion removed.
 *   2. The category lands in its own column, not in the sentence.
 *   3. The renderer escapes. An empire name that is a payload renders inert.
 *   4. [i] works and <i> does not, so the two attack lines that want italics
 *      get them without the markup being forgeable.
 *   5. An unknown category cannot break out of the unquoted class attribute.
 *
 * Usage (from the repo root):
 *   docker compose exec -T web php /repo/tests/news-render.php
 *
 * It writes to the three news tables and deletes what it wrote, keyed on ids
 * far outside the fixture's, so it is safe to run against a live world and in
 * either order relative to the crawl.
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
require_once __DIR__ . '/../app/include/html.php';
require_once __DIR__ . '/../app/include/news.php';

/*
 * Scratch ids. The fixture uses userids 1-3, setids 1-2 and gid 1, and a live
 * game would use small numbers too; nothing reads these rows but this file.
 */
$SCRATCH_USERID = 999001;
$SCRATCH_SETID  = 999001;
$SCRATCH_GID    = 'mb-news-render-test';

/*
 * An empire name is the value that reaches every one of the 51 sentences, and
 * the game lets a player choose one. This is what one could be.
 */
$PAYLOAD = '<script>alert(1)</script> & "O\'Brien"';

$failures = array();

function check($label, $condition, $detail = '')
{
    global $failures;
    if (!$condition) {
        $failures[] = $detail === '' ? $label : "$label\n      $detail";
        echo "FAIL: $label\n";
        if ($detail !== '') {
            echo "      $detail\n";
        }
        return false;
    }
    echo "ok: $label\n";
    return true;
}

function cleanup($db)
{
    global $SCRATCH_USERID, $SCRATCH_SETID, $SCRATCH_GID;
    mysqli_query($db, "DELETE FROM empnews WHERE yourid=" . mb_sql_int($SCRATCH_USERID));
    mysqli_query($db, "DELETE FROM setnews WHERE setid="  . mb_sql_int($SCRATCH_SETID));
    mysqli_query($db, "DELETE FROM guildnews WHERE gid="  . mb_sql_str($db, $SCRATCH_GID));
}

cleanup($db);

/*
 * The three writers, each given the shape of line its real call sites write.
 *
 * The settlement one is attack.php's destroy line verbatim apart from the
 * names: it is the only stored sentence that carries markup of its own, and it
 * is why the renderer runs mb_rich() rather than mb_h().
 */
$empText   = "We have unsuccessfully defended our empire against $PAYLOAD (2) and lost 25 land";
$setText   = "$PAYLOAD (1) gained 25 land and destroyed IdleBaron (2) [i]Medieval style[/i]";
$guildText = "$PAYLOAD (1) has left the guild";

mb_news_emp($db, '1/1/03, 12:00am', 'yellow', $empText, $SCRATCH_USERID);
mb_news_set($db, '1/1/03, 12:00am', 'lg', $setText, $SCRATCH_SETID, 1);
mb_news_guild($db, '1/1/03, 12:00am', 'blue', $guildText, $SCRATCH_GID, 1);

/*
 * 1 and 2: read back what was stored.
 *
 * Both halves matter. The text has to survive unchanged -- htmlspecialchars()
 * on the way in was what locked accounts out and split guild names in two --
 * and the category has to be in `class`, because a sentence with a <font> tag
 * still wrapped around it is the thing being removed.
 */
$rows = array(
    'empnews'   => array("SELECT news, class FROM empnews WHERE yourid=" . mb_sql_int($SCRATCH_USERID), $empText, 'yellow'),
    'setnews'   => array("SELECT news, class FROM setnews WHERE setid=" . mb_sql_int($SCRATCH_SETID), $setText, 'lg'),
    'guildnews' => array("SELECT news, class FROM guildnews WHERE gid=" . mb_sql_str($db, $SCRATCH_GID), $guildText, 'blue'),
);

$stored = array();
foreach ($rows as $table => $spec) {
    list($sql, $expectText, $expectClass) = $spec;

    $result = mysqli_query($db, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    if (!check("$table: the writer filed a row", $row !== null, mysqli_error($db))) {
        continue;
    }
    $stored[$table] = $row;

    check("$table: stores the text as typed", $row['news'] === $expectText,
        "stored: " . var_export($row['news'], true));
    check("$table: stores the category in its own column", $row['class'] === $expectClass,
        "stored: " . var_export($row['class'], true));
    check("$table: stores no markup", strpos($row['news'], '<font') === false,
        "stored: " . var_export($row['news'], true));
}

/*
 * 3, 4 and 5: render the rows that were just read back, not the strings that
 * were written. A round trip that skips the database would not notice a column
 * that mangles what it is given.
 */
if (isset($stored['empnews'])) {
    $html = mb_news_html($stored['empnews']['class'], $stored['empnews']['news']);

    check('renderer: escapes an empire name that is a payload',
        strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, $html);
    check('renderer: escapes the quotes around it',
        strpos($html, '&quot;') !== false && strpos($html, '&#039;') !== false, $html);
    check('renderer: keeps the category as the font class',
        strpos($html, '<font class=yellow>') === 0, $html);
}

if (isset($stored['setnews'])) {
    $html = mb_news_html($stored['setnews']['class'], $stored['setnews']['news']);

    check('renderer: [i] renders as italics', strpos($html, '<i>Medieval style</i>') !== false, $html);
    check('renderer: the class is the stored one', strpos($html, '<font class=lg>') === 0, $html);
}

// The forgery arm of the same rule: the markup is bracket-delimited precisely
// so that a player typing the angle-bracket form gets text. Rendered directly
// rather than stored first, because what is being tested is the renderer.
$typed = mb_news_html('yellow', '<i>Sneaky</i> [b]bold[/b]');
check('renderer: a typed <i> is text, not markup',
    strpos($typed, '<i>Sneaky') === false && strpos($typed, '&lt;i&gt;Sneaky') !== false, $typed);
check('renderer: [b] still renders', strpos($typed, '<b>bold</b>') !== false, $typed);

/*
 * The class lands in an unquoted attribute -- <font class=$class> -- so an
 * unknown value is the one place left where a stored string could reach markup
 * structurally. It falls back rather than being interpolated.
 */
check('renderer: an unknown category falls back to yellow',
    mb_news_html('chartreuse', 'x') === '<font class=yellow>x</font>',
    mb_news_html('chartreuse', 'x'));
check('renderer: an empty category falls back to yellow',
    mb_news_html('', 'x') === '<font class=yellow>x</font>', mb_news_html('', 'x'));
check('renderer: a category that would break out of the attribute cannot',
    strpos(mb_news_html('yellow onmouseover=alert(1)', 'x'), 'onmouseover') === false,
    mb_news_html('yellow onmouseover=alert(1)', 'x'));

cleanup($db);

echo "\n";
if ($failures) {
    echo "FAIL: " . count($failures) . " news assertion(s) failed.\n";
    exit(1);
}
echo "PASS: the news pipeline stores text and renders it safely.\n";
exit(0);
