<?php
/**
 * Smoke crawl.
 *
 * Walks every reachable page and fails on any PHP diagnostic in the response.
 * This is the cheapest regression net available for the port: PHP failures
 * land in the response body where they can be asserted on.
 *
 * Two phases, each with its own cookie jar. The first crawls the logged-out
 * surface -- index.php and the fragments it includes -- and the second logs in
 * as a fixture user and crawls the game. Coverage is reported for both
 * together, and any app/ script that is neither reached nor listed in
 * $UNREACHABLE with a reason fails the run.
 *
 * REQUIRES A FRESH DATABASE. Many actions in this app are plain GET links, so
 * crawling is itself destructive -- buildings get bought, explorations get
 * launched -- and a second run over the same fixture reaches fewer pages than
 * the first. run-all.sh calls reset-db.sh immediately before this.
 *
 * Usage (from the repo root):
 *   bash tests/reset-db.sh
 *   docker compose exec -T web php /repo/tests/smoke.php
 */

require __DIR__ . '/lib/client.php';

$BASE  = getenv('MB_BASE_URL') ? getenv('MB_BASE_URL') : 'http://localhost';
$EMAIL = 'tester@example.com';
$PASS  = 'test1234';

// A rank-and-file guild member, for the other arm of every role check.
$MEMBER_EMAIL = 'idle@example.com';
$MEMBER_PASS  = 'pass2';
// The third fixture empire, who leads nothing, is in no guild and -- the reason
// they are logged in at all -- is the only member of settlement 2. A raid files
// two rows against the defender's settlement, and nobody else can read them.
$TARGET_EMAIL = 'poor@example.com';
$TARGET_PASS  = 'pass3';
// Per-phase page caps. The logged-in URL space never closes -- paginated
// listings keep generating fresh query strings -- so its cap always trips and
// which scripts get reached depends on queue order. That makes the budget
// load-bearing: sharing one pool between the phases let the handful of
// logged-out pages starve the settlement-leader pages off the end of the run.
$MAX_ANON_PAGES   = 25;
$MAX_PAGES        = 600;
// The member pass re-treads most of the same pages; it is here for the branches
// the leader never takes, not for breadth, so it gets a fraction of the budget.
$MAX_MEMBER_PAGES = 120;

/*
 * The admin area, whose URL space is small and closed -- unlike the game's, a
 * modest cap here is a real ceiling rather than a budget that always trips.
 *
 * The credential matches app/admin/include/auth.php's defaults, which
 * docker-compose.yml passes through unchanged unless MB_ADMIN_USER and
 * MB_ADMIN_PASSWORD are set in the environment.
 */
$MAX_ADMIN_PAGES = 40;
$ADMIN_USER = getenv('MB_ADMIN_USER') ? getenv('MB_ADMIN_USER') : 'mako';
$ADMIN_PASS = getenv('MB_ADMIN_PASSWORD') ? getenv('MB_ADMIN_PASSWORD') : 'quickshot';

// Every entry point, including the two that nothing links to. A page added to
// app/admin/ and left off this list is a page whose gate is never checked, so
// the coverage report at the end of the run flags one that is missing.
$ADMIN_PAGES = array(
    'admin/main.php',
    'admin/gameconfig.php',
    'admin/newgame.php',
    'admin/dempire.php',
    'admin/get_user_info.php',
    'admin/dusers.php',
    'admin/dusers_email.php',
    'admin/dusers_ip.php',
    'admin/dusers_suid.php',
    'admin/dusers_curid.php',
);

// gameconfig.php's bulk actions, by the key its form submits.
$MB_ADMIN_ACTIONS = array('messages', 'news', 'forums', 'guilds', 'settlements', 'accounts');

/*
 * Baseline of warnings that already existed before the port began.
 *
 * The app cannot be made warning-clean in one step, but a permanently red
 * suite is a suite nobody reads. So known warnings are recorded by source
 * location and suppressed, and anything NOT in the baseline fails the run.
 * The list is only ever allowed to shrink -- entries that stop firing are
 * reported so they can be deleted.
 */
$BASELINE_FILE = __DIR__ . '/known-issues.txt';

/*
 * Endpoints the crawler must not follow.
 *
 * logout.php is the only one that would break the crawl outright, by ending
 * the session. The rest are destructive actions reachable by plain GET; the
 * fixture database is reset per run so the mutation itself is harmless, but
 * letting the crawler delete forum posts and disband armies makes every later
 * page depend on crawl order, which would turn this into a flaky test.
 */
$SKIP = array(
    'logout.php',
    'gl-delposts.php',
    'sl-delposts.php',
    'disband.php',
    'gkick.php',
    'gdel.php',
    // Matched as prefixes, so only the destructive variant is skipped and the
    // page itself is still crawled. gmembers.php?leave=true drops the fixture
    // user out of the test guild, which would make every guild page after it
    // unreachable depending on crawl order.
    'gmembers.php?leave',
    'guildconfig.php?accept',
    'guildconfig.php?reject',
    // Buying and cancelling are plain GET links off the barter listings, and
    // both consume the fixture rows. Driven explicitly below instead, so which
    // of them runs does not depend on where the crawler happened to run out of
    // budget.
    'barter.php?barter',
    'barter.php?end',
    'guildbarter.php?barter',
    'guildbarter.php?end',
);

/*
 * Severity policy.
 *
 * Fatals, parse errors and warnings are real breakage and fail the run.
 * Notices are only counted: this is 2003 PHP 4 code that reads uninitialised
 * variables everywhere by design, so failing on them would mean a permanently
 * red suite that nobody reads. Track the count instead -- it should trend
 * down during the port, never up.
 *
 * PHP 8 promoted reading an undefined variable, array key or string offset
 * from E_NOTICE to E_WARNING. Those are the exact diagnostics the paragraph
 * above is about, so they are demoted back to notice class here: on 8.3 they
 * number in the hundreds and are the same lines that were notices on the 5.6
 * baseline, not anything the port introduced. Everything else PHP calls a
 * warning still fails, including the mysqli ones that matter.
 */
$SEVERITY_DEMOTED = '/(Undefined (variable|array key|index|offset|property)|Trying to access array offset on)/i';

$FAIL_PATTERN  = '/\b(Fatal error|Parse error|Catchable fatal error|Recoverable fatal error|Warning)\s*:\s*(.{0,200})/i';
$NOTICE_PATTERN = '/\b(Notice|Deprecated)\s*:\s*(.{0,200})/i';

/*
 * Query-audit mode: MB_QUERY_AUDIT=1, driven by tests/query-audit.sh.
 *
 * Collects the mysqli failures the crawl walks into and writes them to
 * tests/broken-queries.txt instead of failing on them. They surface only when
 * the stack runs with MB_REPORT_QUERIES=1 (see app/include/connect.php), which
 * the normal one does not, so all of this is inert in an ordinary run.
 *
 * The point is an inventory, not a green light: roughly 1300 queries here are
 * fired unchecked, and some have never succeeded in the game's life. Knowing
 * which ones is the prerequisite for turning error reporting on for good.
 */
$QUERY_AUDIT = getenv('MB_QUERY_AUDIT') === '1';
$QUERY_ERROR_PATTERN = '/^mysqli_(query|real_query|multi_query|prepare)\(\)/i';
$queryErrors = array();

/*
 * Scripts the crawl cannot reach, and why.
 *
 * Without this the unreached list is just a number that nobody can act on --
 * "24 not reached" reads as 24 holes in the net when most of them are files
 * that are not pages at all. Each entry here is a claim that the gap is
 * understood; anything unreached and NOT listed fails the run, so a page
 * dropping out of coverage is a regression rather than a silent loss.
 *
 * Remove an entry when the gap is closed. Like the other baselines in this
 * suite, the list should only ever shrink -- except for the three includes,
 * which are structural and will not.
 */
$UNREACHABLE = array(
    // Not pages. Included by other scripts; serving them directly is meaningless.
    'common.php'           => 'include: settlement helpers, pulled in by the s* pages',
    'commong.php'          => 'include: guild helpers, pulled in by the g* pages',
    'functions.php'        => 'include: loads the logged-in user state into globals',

    // Reached only by submitting a form. The four thread handlers are driven
    // by postForm() now; these two are not.
    'checklogin.php'       => 'POST handler: exercised by the login that starts this run',
    'checksignup.php'      => 'POST handler: creates a user from rand(), see docs/testing.md',
    'gl-topic.php'         => 'POST handler: guild-leader thread moderation view',

    // Deliberately skipped: destructive by plain GET. See $SKIP.
    'disband.php'          => 'skipped: destroys the fixture army',
    'logout.php'           => 'skipped: ends the session the crawl depends on',

    // Dead in the shipped game, not merely uncovered.
    'scoreboard.php'       => 'dead: orphaned, nothing links to it and index.php has its Scores link commented out',
    // Auth flow outside both crawl roots.
    'activate_account.php' => 'auth flow: needs a live activation code emailed by activate_code.php',
    'activate_code.php'    => 'auth flow: sends the activation mail, requires an unactivated session',
);

$baseline = loadBaseline($BASELINE_FILE);

$skipped     = array();
$failures    = array();
$observed    = array();
$scriptsHit  = array();
$noticeCount = 0;
$visited     = 0;
$unfinished  = 0;

/*
 * The logged-out surface, crawled first and with its own cookie jar.
 *
 * index.php and the four $data fragments it includes are the only pages a
 * visitor sees before authenticating, and they sat outside the net entirely --
 * a parse error in the login page would have passed this suite. about_us.php
 * is seeded explicitly because nothing links to it; game_scores.php is linked
 * from index.php but only inside an HTML comment.
 */
$anon = new MbClient($BASE);
crawl($anon, array('index.php', 'index.php?page=about_us'), $MAX_ANON_PAGES);

/*
 * The primary tester: leads Testguild and settlement 1, so both leader-only
 * forum surfaces are theirs.
 *
 * The forms go first, before the crawl rather than after it, so the threads
 * they create are on the board when the crawl reaches the listings -- the reply
 * handlers then get exercised against a thread this run wrote, not only against
 * the seeded ones. Apostrophes in every field on purpose: an unescaped
 * interpolation loses the row silently, which is exactly the failure this
 * suite exists to catch, and none of these handlers checks a return value.
 */
$tester = login($BASE, $EMAIL, $PASS);
foreach (array('gl-inputposts.php', 'sl-input.posts.php', 'input.posts.php') as $handler) {
    // Both arms of every handler. addtopic and addreply are separate branches
    // running separate queries against separate tables, and gl-inputposts.php's
    // reply branch was broken in a way its topic branch was not.
    postForm($tester, $handler, array(
        'addtopic' => 'Post this message',
        'topic'    => "A leader's topic ($handler)",
        'message'  => "Muster at O'Brien's ford.",
    ));
    postForm($tester, $handler, array(
        'addreply' => 'Reply with this message',
        'topicid'  => 1,
        'topic'    => "A leader's reply ($handler)",
        'message'  => "O'Brien is holding the ford.",
    ));
}
/*
 * The two moderation handlers, driven here rather than at the end of the run.
 *
 * They destroy forum content, so $SKIP keeps the crawler from following a
 * Delete link mid-crawl -- but they have to be called BEFORE the crawl anyway,
 * because both now check that the caller leads the guild or the settlement and
 * the crawl takes that away. govt.php:20 clears sl for every member of the
 * settlement on each visit and then re-elects whoever holds the most votes;
 * the fixture has nobody voting for anybody, so simply opening the page in
 * passing deposes the tester and every later request is refused.
 *
 * Seeded ids rather than the ones posted above, so this does not depend on how
 * many threads the run happened to create.
 */
foreach (array(
    'sl-delposts.php?delpost=true&mid=1&tid=1',
    'sl-delposts.php?delete=true&tid=2',
    'gl-delposts.php?delpost=true&mid=1&tid=1',
    'gl-delposts.php?delete=true&tid=2',
) as $path) {
    $body = $tester->get('/' . $path);
    $visited++;
    $scriptsHit[strtok($path, '?')] = true;
    inspect($path, $body, $tester->lastStatus);
}

/*
 * The two barter boards, which were switched off in 2003 and are back.
 *
 * Every path is driven by hand rather than followed: listing is a form, and
 * buying and cancelling are GET links that consume the row they point at, so
 * leaving them to the crawler would make the coverage depend on queue order.
 * $SKIP keeps it off them.
 *
 * Before the crawl for the same reason the moderation handlers are -- buying
 * needs gold and iron, and the crawl spends both on buildings and troops.
 *
 * Both arms of both boards: the seeded rows 1 and 2 are the buy path (you
 * cannot buy your own, so they belong to other empires, and row 2's seller is
 * a guild-mate because guildbarter.php refuses anyone else), row 3 is the
 * tester's own and is the cancel path.
 */
// Sage on the guild board specifically: it is the type whose name the game
// changed, and the only way to know the handler now writes military.sages
// rather than the abandoned military.scientists is to sell one.
foreach (array('barter.php' => 'Warrior', 'guildbarter.php' => 'Sage') as $board => $unit) {
    postForm($tester, $board, array(
        'add'    => '1',
        'amount' => 1,
        'type'   => $unit,
        'cost'   => 100,
        'method' => 'gp',
    ));
}
foreach (array(
    'barter.php?barter=1&bid=1',
    'guildbarter.php?barter=1&bid=2',
    'barter.php?end=true&bid=3',
) as $path) {
    $body = $tester->get('/' . $path);
    $visited++;
    $scriptsHit[strtok($path, '?')] = true;
    inspect($path, $body, $tester->lastStatus);
}

/*
 * Combat, which is the only thing in the game that files a news row.
 *
 * Thirty-two of the fifty-one news sites are in the three attack pages, and
 * none of them had ever run: the crawler reaches attack.php but only its
 * landing form, because launching an attack means naming a target and a number
 * of units. So the whole conversion of the news column from stored markup to
 * stored text -- see app/include/news.php -- was covered by nothing that ran
 * here. What the sentences say is checked below; that they can be rendered
 * safely from hostile input is tests/news-render.php.
 *
 * Target is PoorSerf (userid 3), who is in no guild, is not in safe mode and
 * holds a settlement of their own, so neither the guild check nor the
 * own-empire check refuses. The unit counts are chosen to clear their defence:
 * they field five archers at power 2, so the attacker's offence has to beat 10
 * and three warriors alone (power 2) would not.
 *
 * Before the crawl, like the barter calls above, because an attack needs a
 * general and troops in the barracks, and the crawl spends both.
 *
 * Two attacks and no more: user.fleets is 2 in the fixture -- four generals
 * less the two armies already in the field -- and each attack consumes one.
 * That is also why attackm.php is not driven. It is the same file shape as
 * attackr.php, and a third attack would find no general; giving the fixture
 * one more would hand calculations.php a fifth army for four return slots,
 * which is a fixture change with an engine bug attached rather than coverage.
 */
foreach (array(
    // The land attack, won: three warriors at power 2 and three wizards at
    // power 3 make 15 against their 10. This is the branch with the most news
    // sites in it, and the one whose neighbour writes the [i]Medieval style[/i]
    // line.
    'attack.php?attack=1&empvalue=3&uwarrior=3&uwizard=3',
    // The resource attack, lost, which is the other half of the shape: a raid
    // files four rows either way and the two outcomes use different categories.
    // Priests, because the warriors and wizards above are in the field now and
    // attackr.php refuses to send what you do not have -- and three of them at
    // power 2 make 6, which is what makes this the losing branch.
    'attackr.php?attack=1&empvalue=3&upriest=3',
) as $path) {
    $body = $tester->get('/' . $path);
    $visited++;
    $scriptsHit[strtok($path, '?')] = true;
    inspect($path, $body, $tester->lastStatus);
}

/*
 * The rendered result, asserted rather than merely fetched.
 *
 * inspect() only knows about PHP diagnostics, and every one of these writers
 * runs unchecked -- a news INSERT that fails leaves no trace in the response
 * at all, which is exactly how the guildnews column named `guildid` went
 * unnoticed for twenty years. So the two pages that read the rows back are
 * asserted on directly.
 *
 * The class matters as much as the sentence. It is a category with a legend on
 * snews.php, and it lives in its own column now, so a row rendering with the
 * fallback colour would mean the writer never passed one.
 */
newsShows($tester, 'snews.php',
    'TestLord (1) successfully attacked PoorSerf (2) and gained 25 land', '<font class=yellow>');
newsShows($tester, 'snews.php',
    'TestLord (1) failed to attack PoorSerf (2) for resources', '<font class=yellow>');
// Empire news reaches the tester through the barter buys above; an attack files
// its empire line against the defender, not the attacker.
newsShows($tester, 'main.php?pageid=news', 'You have bought', '<font class=yellow>');

/*
 * The defender's half, which needs the defender's own eyes: snews.php shows the
 * settlement you are in and nothing else, and the two empires are in different
 * ones.
 *
 * Worth a third login for one page, because the two rows this asserts were the
 * bug this phase found. attackr.php and attackm.php filed the defender's
 * settlement news against $tsetid -- a name nothing assigns in either file, and
 * on tests/register-globals.txt as a dead read since the audit was written -- so
 * both rows went to setid 0 and no settlement has ever been told one of its
 * members was raided. attack.php, which does the same thing correctly, is what
 * says what the value should have been.
 */
$target = login($BASE, $TARGET_EMAIL, $TARGET_PASS);
newsShows($target, 'snews.php',
    'PoorSerf (2) lost 25 land to TestLord (1)', '<font class=lg>');
newsShows($target, 'snews.php',
    'PoorSerf (2) successfully defended their resources against TestLord (1)', '<font class=orange>');

/*
 * The cross-tenant arm, which needs an outsider and PoorSerf is the only one:
 * settlement 2, no guild, and no relationship to any row the fixture seeds.
 *
 * Both forums are one shared table apiece, and a reply names its thread by an
 * id off the request. topicid 1 is a Testguild thread and a settlement-1
 * thread, so each of these is a write into a board this account cannot read.
 * The guild half really landed -- guildmsgs has no guild column of its own, and
 * topicg.php renders it by topicid unscoped, so the post appeared on the
 * victim's thread.
 */
forumRefuses($target, 'inputpostsg.php', array(
    'addreply' => 'Reply with this message',
    'topicid'  => 1,
    'topic'    => 'Not in this guild',
    'message'  => 'Posted from outside the guild.',
), 'have to be in a guild', 'app/inputpostsg.php: no membership check before the INSERT');
forumRefuses($target, 'input.posts.php', array(
    'addreply' => 'Reply with this message',
    'topicid'  => 1,
    'topic'    => 'Not in this settlement',
    'message'  => 'Posted from outside the settlement.',
), 'not in your settlement', 'app/input.posts.php: the reply is not scoped to the caller\'s own board');

/*
 * Joining a guild, end to end, and the arm where nobody asked.
 *
 * guildconfig.php's accept branch wrote the membership from the auserid in the
 * URL without looking for a request row, so a leader could conscript any
 * guildless empire in the game. The refusal is driven FIRST, while PoorSerf has
 * genuinely not applied -- the assertion is only worth anything before the row
 * exists.
 *
 * Then the request is made through gc.php rather than seeded into the fixture,
 * which is what puts that branch on the crawl: the accept is only known to work
 * against a row the game itself wrote.
 */
$body = $tester->get('/guildconfig.php?accept=true&auserid=3');
$visited++;
inspect('guildconfig.php?accept (no request)', $body, $tester->lastStatus);
if (stripos($body, 'has not asked to join') === false) {
    $failures['guildconfig.php | accepted an empire that never applied'] =
        'app/guildconfig.php: the accept branch does not check guildrequests';
}

$body = $target->get('/gc.php?request=yes&req_guild=Testguild');
$visited++;
$scriptsHit['gc.php'] = true;
inspect('gc.php?request=yes', $body, $target->lastStatus);
if (stripos($body, 'has been sent') === false) {
    $failures['gc.php | the join request was not filed'] = 'app/gc.php: the request=yes branch no longer inserts';
}

$body = $tester->get('/guildconfig.php?accept=true&auserid=3');
$visited++;
inspect('guildconfig.php?accept', $body, $tester->lastStatus);
if (stripos($body, 'has been accepted into the guild') === false) {
    $failures['guildconfig.php | rejected a genuine join request'] =
        'app/guildconfig.php: the new guildrequests check is refusing a real applicant';
}

crawl($tester, array('main.php?pageid=news'), $MAX_PAGES);

/*
 * A second identity, run last and on a smaller budget.
 *
 * Every role check in this app has two arms, and the primary tester only ever
 * exercises one: they lead Testguild and settlement 1, so the crawl never sees
 * the branch an ordinary member gets. idle@ is in the guild and leads nothing,
 * which is what makes gforums.php -- the non-leader alternate the navbar offers
 * in place of gl-forum.php -- reachable at all.
 *
 * Last because the crawl mutates game state through plain GET links, and the
 * primary pass should see the fixture world rather than whatever this one left.
 */
$member = login($BASE, $MEMBER_EMAIL, $MEMBER_PASS);
// inputpostsg.php is the rank-and-file guild post handler; gl-inputposts.php
// above is the leader's. They are separate files with separate queries.
postForm($member, 'inputpostsg.php', array(
    'addtopic' => 'Post this message',
    'topic'    => "A member's topic",
    'message'  => "Where is O'Brien?",
));
postForm($member, 'inputpostsg.php', array(
    'addreply' => 'Reply with this message',
    'topicid'  => 1,
    'topic'    => "A member's reply",
    'message'  => "O'Brien is at the ford.",
));
/*
 * The leader-only halves of both forums, posted to by a member of both.
 *
 * This is the arm the primary tester can never drive: they lead Testguild and
 * settlement 1, so every gl-* and sl-* request they make is allowed. idle@ is
 * inside the guild and inside the settlement and leads neither, which is the
 * exact case each guard exists to refuse -- and gl-inputposts.php was running
 * the leader query and discarding the answer.
 */
forumRefuses($member, 'gl-inputposts.php', array(
    'addtopic' => 'Post this message',
    'topic'    => 'Not a leader',
    'message'  => 'Posted to the leader board by a member.',
), 'not a Guild Leader', 'app/gl-inputposts.php: the guild-leader check is not gating the INSERT');
forumRefuses($member, 'sl-input.posts.php', array(
    'addtopic' => 'Post this message',
    'topic'    => 'Not a leader',
    'message'  => 'Posted to the leader board by a member.',
), 'not Settlement Leader', 'app/sl-input.posts.php: no settlement-leader check before the INSERT');

crawl($member, array('main.php?pageid=news'), $MAX_MEMBER_PAGES);

/*
 * Changing the account's email address, on the member rather than the primary
 * tester -- it rewrites six tables and the session, and every fixture the
 * primary pass depends on is keyed on that address.
 *
 * This is the last entry that was on tests/sql-injection.txt. A player-chosen
 * address is written here and then interpolated into every "WHERE
 * email='$email'" in the game, so include/credentials.php now says what an
 * address may be and signup, this page and include/session.php all enforce it.
 * The refusal is the assertion that matters; the accepted case is here so the
 * six UPDATEs and the session rewrite are still known to work.
 */
prefsEmailChange($member);

/*
 * Passwords: the legacy hash, the rehash, and a change.
 *
 * The fixture carries both formats on purpose -- idle@ is bcrypt, tester@ and
 * poor@ are the unsalted MD5 the game stored until this change -- so the
 * logins above have already driven both arms of mb_password_verify(). What
 * they cannot show is what happened afterwards: an upgraded row is rewritten
 * across six tables, and if one of them is missed the session's token stops
 * matching there and only that table's pages go quietly empty.
 *
 * So the credential is used again, after the upgrade, on a fresh session. A
 * second login proves `user` was rewritten consistently with what the session
 * carries, and a page that reads a different table proves the rest were.
 *
 * On idle2@ rather than the primary tester: the crawl leaves the tester's
 * fixture spent, and this is the account the email change above just moved.
 */
passwordChange($BASE, 'idle2@example.com', $MEMBER_PASS, 'newpass4');

/**
 * Change a password through preferences.php, then log in with it.
 *
 * Three assertions, and the middle one is the reason this is not just a form
 * submission: the wrong current password must be refused, the change must
 * report success, and -- the part no HTTP status reveals -- the new credential
 * must actually authenticate on a session that knows nothing about this one.
 */
function passwordChange($base, $email, $oldPass, $newPass)
{
    global $failures;

    $client = login($base, $email, $oldPass);

    $body = postForm($client, 'preferences.php', array(
        'changepw'  => 'Change',
        'currentpw' => $oldPass . 'wrong',
        'newpw'     => $newPass,
        'cnewpw'    => $newPass,
    ));
    if (stripos($body, 'current password is not correct') === false) {
        $failures['preferences.php | accepted the wrong current password'] =
            'app/include/password.php: mb_password_verify() is not gating the change';
    }

    $body = postForm($client, 'preferences.php', array(
        'changepw'  => 'Change',
        'currentpw' => $oldPass,
        'newpw'     => $newPass,
        'cnewpw'    => $newPass,
    ));
    if (stripos($body, 'password has been changed') === false) {
        $failures['preferences.php | rejected a valid password change'] = 'the change no longer completes';
    }

    // A new client, so nothing but the database decides this.
    $after = new MbClient($base);
    $after->get('/index.php');
    $after->post('/checklogin.php', array('email' => $email, 'pw' => $newPass, 'login' => 'Login'));
    if ($after->lastStatus !== 302) {
        $failures['checklogin.php | the changed password does not authenticate'] =
            'mb_password_store() and mb_password_verify() disagree, or a table was missed';
        return;
    }

    // And a page whose query filters on a table other than `user`, which is the
    // half a login cannot check: military.pw holding a stale hash renders an
    // empty barracks rather than an error.
    $body = $after->get('/main.php?pageid=news');
    $visitedNote = stripos($body, 'IdleBaron');
    if ($visitedNote === false) {
        $failures['main.php | logged in but the account reads as empty after a password change'] =
            'one of the six pw columns still holds the old hash';
    }

    // The old password must stop working. Same client, so this is purely the
    // stored hash talking.
    $stale = new MbClient($base);
    $stale->get('/index.php');
    $stale->post('/checklogin.php', array('email' => $email, 'pw' => $oldPass, 'login' => 'Login'));
    if ($stale->lastStatus === 302) {
        $failures['checklogin.php | the old password still authenticates'] =
            'the change did not replace the stored hash';
    }
}

/**
 * Both arms of preferences.php's email change.
 */
function prefsEmailChange(MbClient $client)
{
    global $failures;

    $hostile = "a'b@example.com";
    $body = postForm($client, 'preferences.php', array(
        'update'   => 'Update',
        'newemail' => $hostile,
        'newaim'   => 'aim',
        'newmsn'   => 'msn',
    ));
    if (stripos($body, 'not a valid email address') === false) {
        $failures['preferences.php | accepted an address containing a quote'] =
            'app/include/credentials.php: mb_valid_email() is not being applied before the six UPDATEs';
    }

    $body = postForm($client, 'preferences.php', array(
        'update'   => 'Update',
        'newemail' => 'idle2@example.com',
        'newaim'   => 'aim',
        'newmsn'   => 'msn',
    ));
    if (stripos($body, 'settings have been updated') === false) {
        $failures['preferences.php | rejected a valid address'] =
            'the email change no longer completes';
    }

    // The session was rewritten to the new address by the handler. If
    // include/session.php's validation disagreed with what preferences.php
    // just wrote, the very next request would come back logged out -- which is
    // the failure mode of putting a rule at a boundary and not at the writes.
    $body = $client->get('/preferences.php');
    if (stripos($body, 'idle2@example.com') === false) {
        $failures['preferences.php | logged out after its own email change'] =
            'include/session.php rejects what preferences.php stores';
    }
}

/*
 * The admin area, which no test has ever touched.
 *
 * It sat under app/css/admin/ with no authentication of any kind: adminlogin.php
 * compared against a hardcoded pair and, on a match, printed a link. No session
 * was written and nothing checked for one, so every page in it -- including the
 * one that deletes every account -- served to anyone who typed the URL. It has
 * a session gate now, and this is what says so.
 *
 * Two passes. The first asserts that a client with no admin session is refused
 * by every entry point; without that arm the second pass would pass just as
 * happily against no gate at all. The second logs in and crawls.
 *
 * DESTRUCTIVE, and last in the file for that reason: adminCrawl() drives
 * gameconfig.php's bulk actions, which is the only way to find out whether
 * statements naming thirty tables that do not exist actually run. The fixture
 * is spent afterwards. Nothing below this point may depend on game state.
 */
adminGate($BASE, $ADMIN_PAGES);
$admin = adminLogin($BASE, $ADMIN_USER, $ADMIN_PASS);
// Confined to admin/. The navbar's links are relative -- href="main.php" from
// a page served out of /admin/ -- and extractLinks() normalises every href to
// a path from the docroot, so without this the admin pass walks straight out
// into the game as a client with no player session.
crawl($admin, array('admin/main.php'), $MAX_ADMIN_PAGES, 'admin/');
adminActions($admin, $MB_ADMIN_ACTIONS);

/**
 * Every admin entry point must refuse a client that has not logged in.
 *
 * Checked before the login pass, and on a client that HAS a game session --
 * being a logged-in player must not be enough to reach the admin area, and a
 * gate keyed on the wrong session slot would look fine without this.
 */
function adminGate($base, array $pages)
{
    global $failures, $visited;

    $stranger = new MbClient($base);
    $player   = login($base, 'tester@example.com', 'test1234');

    foreach (array('anonymous' => $stranger, 'logged-in player' => $player) as $who => $client) {
        foreach ($pages as $path) {
            $body = $client->get('/' . $path);
            $visited++;

            // 403 from the gate, or 302 from the two pages that redirect
            // (index.php sends an authenticated caller on to main.php, and
            // adminlogin.php redirects either way). Anything that renders is
            // a page that served itself to someone who is not an admin.
            if ($client->lastStatus !== 403 && $client->lastStatus !== 302) {
                $failures["$path | reachable by $who (HTTP {$client->lastStatus})"] =
                    'app/admin/include/auth.php: mb_admin_require() is not gating this page';
                continue;
            }
            if (stripos($body, 'MB Administration') !== false) {
                $failures["$path | rendered the admin header for $who"] =
                    'the gate ran too late -- call mb_admin_require() before any output';
            }
        }
    }
}

/**
 * Log into the admin area, or fail the run.
 */
function adminLogin($base, $user, $pass)
{
    global $visited;

    $client = new MbClient($base);
    $client->post('/admin/adminlogin.php', array('username' => $user, 'pw' => $pass));
    $visited++;

    if ($client->lastStatus !== 302) {
        fwrite(STDERR, "FATAL: admin login did not redirect (got HTTP {$client->lastStatus}).\n");
        fwrite(STDERR, "MB_ADMIN_USER/MB_ADMIN_PASSWORD must match app/admin/include/auth.php's defaults.\n");
        exit(2);
    }

    return $client;
}

/**
 * Drive gameconfig.php's bulk actions, each of which empties a table.
 *
 * The point is the queries, not the buttons. Thirty of this page's statements
 * named tables no other part of the schema has -- `setnews1`..`setnews10`,
 * `setmain*`, `setmsgs*`, `return` -- so "delete all news" and "delete all
 * forums" had never once done anything. mysqli reports a bad table as a
 * warning now and inspect() fails on warnings, so running them is what says
 * the spellings are right.
 *
 * Ordered so the account wipe is last: deleting one empire by name needs an
 * empire to still be there.
 */
function adminActions(MbClient $admin, array $actions)
{
    postForm($admin, 'admin/gameconfig.php', array('daccount' => 'Delete', 'empre' => 'PoorSerf'));
    postForm($admin, 'admin/gameconfig.php', array('daccount' => 'Delete', 'empre' => "No O'Brien here"));

    foreach ($actions as $action) {
        postForm($admin, 'admin/gameconfig.php', array('action' => $action));
    }

    // newgame.php takes no parameters and wipes the world on a bare GET, so it
    // is left until everything else has run.
    postForm($admin, 'admin/newgame.php', array());
}

/**
 * Authenticate a fresh client, or abort the run.
 */
function login($base, $email, $pass)
{
    // The GET primes the computer_id cookie the login flow expects; without it
    // checklogin.php renders its "new computer" challenge instead of redirecting.
    $client = new MbClient($base);
    $client->get('/index.php');
    $client->post('/checklogin.php', array('email' => $email, 'pw' => $pass, 'login' => 'Login'));

    if ($client->lastStatus !== 302) {
        fwrite(STDERR, "FATAL: login as $email did not redirect (got HTTP {$client->lastStatus}).\n");
        fwrite(STDERR, "Is the database seeded? Try: bash tests/reset-db.sh\n");
        exit(2);
    }

    return $client;
}

/**
 * Walk every page reachable from $seeds, asserting on the diagnostics in each.
 *
 * Shares one budget and one result set across both phases: the page cap, the
 * scripts-hit map and the failure list are global state on purpose, so the
 * report describes the whole run rather than one half of it.
 */
function crawl(MbClient $client, array $seeds, $maxPages, $confineTo = '')
{
    global $SKIP, $FAIL_PATTERN, $NOTICE_PATTERN, $SEVERITY_DEMOTED;
    global $QUERY_AUDIT, $QUERY_ERROR_PATTERN, $queryErrors;
    global $baseline, $skipped, $failures, $observed, $scriptsHit, $noticeCount, $visited, $unfinished;

    // Seen is per-phase: the same URL renders differently logged in and out,
    // and both renderings are worth asserting on.
    $queue   = $seeds;
    $seen    = array();
    $budget  = $maxPages;

    while ($queue && $budget > 0) {
        $path = array_shift($queue);
        if (isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;

        // Charged per fetch, not per dequeue. The queue holds duplicates -- the
        // navbar alone puts thirty links on every page -- so decrementing on
        // dequeue spent three quarters of the budget on URLs already visited.
        $budget--;
        $body = $client->get('/' . $path);
        $visited++;
        $scriptsHit[strtok($path, '?')] = true;
        creditFragment($path, $scriptsHit);

        inspect($path, $body, $client->lastStatus);

        foreach (extractLinks($body) as $link) {
            if ($confineTo !== '' && strpos($link, $confineTo) !== 0) {
                continue;
            }
            if (isSkipped($link, $SKIP)) {
                $skipped[$link] = true;
                continue;
            }
            if (!isset($seen[$link])) {
                $queue[] = $link;
            }
        }
    }

    // Reported rather than silent: a phase that ran out of budget with work
    // left did not prove the pages it never opened were fine.
    $unfinished += count($queue);
}

/**
 * Fetch a news page and assert that a line is on it, in its category's colour.
 *
 * The rest of this file asserts absence -- no fatal, no warning, no unexpected
 * status -- which is the right shape for a crawl and the wrong shape for a
 * write that produces no diagnostic when it fails. Every news INSERT in the
 * game is fired unchecked, so "the page rendered" and "the row was written"
 * are independent facts and only the first one is otherwise tested.
 *
 * $class is asserted separately from the sentence rather than as one string,
 * because the two now come from different columns: matching the whole
 * "<font class=yellow>TestLord ..." would pass a row that stored the colour
 * back inside the sentence, which is the thing being kept out.
 */
function newsShows(MbClient $client, $path, $needle, $class)
{
    global $scriptsHit, $visited, $failures;

    $body = $client->get('/' . $path);
    $visited++;
    $scriptsHit[strtok($path, '?')] = true;
    inspect($path, $body, $client->lastStatus);

    if (strpos($body, $needle) === false) {
        $failures["$path | news line missing: $needle"] = $path;
    } elseif (strpos($body, $class) === false) {
        $failures["$path | news line not in category $class"] = $path;
    }

    return $body;
}

/**
 * Assert on one response, whatever fetched it.
 *
 * Lifted out of crawl() so a form submission gets exactly the same treatment as
 * a followed link. A POST handler that emits a warning has to fail the run for
 * the same reason a page does, and there is no version of that which should be
 * decided by how the request was made.
 */
function inspect($path, $body, $status)
{
    global $FAIL_PATTERN, $NOTICE_PATTERN, $SEVERITY_DEMOTED;
    global $QUERY_AUDIT, $QUERY_ERROR_PATTERN, $queryErrors;
    global $baseline, $failures, $observed, $noticeCount;

    if ($status !== 200 && $status !== 302) {
        // Keyed like every other failure. It used to append array($path,
        // "HTTP $status"), which the report at the bottom then tried to print
        // as "$signature ... first seen at: $path" -- so the first time this
        // ever fired it printed a row of "0 / first seen at: Array".
        $failures["$path | HTTP $status"] = $path;
    }

    // Diagnostics must be matched against the stripped text, not the raw
    // body: with html_errors on, PHP emits "<b>Notice</b>:  ..." and any
    // pattern expecting "Notice:" silently matches nothing. That produces a
    // green run that proves absolutely nothing, which is worse than a red one.
    $text = html_entity_decode(strip_tags($body), ENT_QUOTES);

    if (preg_match_all($FAIL_PATTERN, $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            // PHP 8 raises the old uninitialised-read notices to warnings;
            // count them as notices so the severity policy means the same
            // thing on both versions. See $SEVERITY_DEMOTED.
            if (preg_match($SEVERITY_DEMOTED, $m[2])) {
                $noticeCount++;
                continue;
            }
            $signature = signature($m[1], $m[2]);

            // Query-audit mode: collect the mysqli failures instead of
            // failing on them. They only appear at all when connect.php is
            // running with MB_REPORT_QUERIES=1, which the normal stack is
            // not, so this branch is inert in an ordinary run.
            if ($QUERY_AUDIT && preg_match($QUERY_ERROR_PATTERN, $m[2])) {
                $queryErrors[$signature][$path] = true;
                continue;
            }

            $observed[$signature] = true;
            if (!isset($baseline[$signature])) {
                $failures[$signature] = $path;
            }
        }
    }

    $noticeCount += preg_match_all($NOTICE_PATTERN, $text, $ignored);
}

/**
 * Submit one form, and count the handler as reached.
 *
 * The crawler follows links, so every POST handler in the game sat outside the
 * net -- including the four that write forum threads, which between them hold
 * most of what is left on the SQL injection worklist. Rewriting a page the
 * suite does not visit is a guess, so they are driven here instead.
 *
 * Deliberately not seeded through the DB: the point is to exercise the handler,
 * and an INSERT written by hand into the fixture proves nothing about the code
 * that was supposed to write it.
 */
function postForm(MbClient $client, $path, array $fields)
{
    global $scriptsHit, $visited;

    $body = $client->post('/' . $path, $fields);
    $visited++;
    $scriptsHit[strtok($path, '?')] = true;
    inspect($path, $body, $client->lastStatus);

    return $body;
}

/**
 * Credit index.php?page=<name> to <name>.php as well as to index.php.
 *
 * Those four files are not pages -- they assign $data and index.php echoes it --
 * but a parse error in one still breaks the request, so the coverage number
 * should say they were exercised. Counting the URL alone would report them as
 * permanently unreached while they were in fact being rendered on every run.
 */
/**
 * Submit a form that must be refused, and say so if it was not.
 *
 * The other arm of every guard. A gate is only known to work if something drives
 * the request it is supposed to stop -- gl-inputposts.php ran the guild-leader
 * query and then never compared it, which passed every test in this suite,
 * because the only account that had ever posted there was the leader.
 */
function forumRefuses(MbClient $client, $path, array $fields, $needle, $why)
{
    global $failures;

    $body = postForm($client, $path, $fields);
    if (stripos($body, $needle) === false) {
        $failures[$path . ' | accepted a write it should have refused'] = $why;
    }
}

function creditFragment($path, array &$scriptsHit)
{
    if (strtok($path, '?') !== 'index.php') {
        return;
    }
    $query = parse_url($path, PHP_URL_QUERY);
    if ($query === null || $query === false) {
        // No ?page= at all: index.php falls back to main-site.php.
        $scriptsHit['main-site.php'] = true;
        return;
    }
    parse_str($query, $params);
    $page = isset($params['page']) ? basename($params['page']) : '';
    if ($page !== '' && is_file(__DIR__ . '/../app/' . $page . '.php')) {
        $scriptsHit[$page . '.php'] = true;
    } else {
        $scriptsHit['main-site.php'] = true;
    }
}

/**
 * Same-origin, non-anchor links, normalised to a relative path.
 */
function extractLinks($html)
{
    $links = array();
    if (!preg_match_all('/<a\s[^>]*href\s*=\s*["\']?([^"\'>\s]+)/i', $html, $matches)) {
        return $links;
    }

    foreach ($matches[1] as $href) {
        $href = html_entity_decode($href);

        // External, mail, anchors and JS pseudo-links.
        if (preg_match('#^(https?:|mailto:|javascript:|\#)#i', $href)) {
            continue;
        }
        // Only PHP endpoints are interesting; static assets cannot fail.
        if (!preg_match('/\.php(\?|$)/i', $href)) {
            continue;
        }
        $links[ltrim($href, '/')] = true;
    }

    return array_keys($links);
}

function isSkipped($link, array $skip)
{
    foreach ($skip as $needle) {
        if (stripos($link, $needle) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Collapse a diagnostic to a stable identity: source location plus message.
 *
 * Keyed on where the problem lives rather than which URL exposed it, so the
 * same broken include reached through ten settlement ids is one entry, and
 * the signature survives changes to navigation.
 */
function signature($type, $message)
{
    $message = trim($message);
    $where = 'unknown';

    if (preg_match('#\bin (/\S+?) on line (\d+)#', $message, $m)) {
        $where = basename($m[1]) . ':' . $m[2];
        $message = trim(substr($message, 0, strpos($message, ' in ' . $m[1])));
    }

    // Row/index numbers vary with fixture size; keep signatures stable.
    $message = preg_replace('/\bindex \d+\b/', 'index N', $message);

    return $where . ' | ' . ucfirst(strtolower($type)) . ': ' . $message;
}

function loadBaseline($file)
{
    if (!is_file($file)) {
        return array();
    }
    $entries = array();
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $entries[$line] = true;
    }
    return $entries;
}

// --- report ----------------------------------------------------------------

echo "Crawled $visited page(s).\n";

if ($skipped) {
    // Reported rather than silent: an unexplained gap in coverage reads as
    // "everything passed" when it actually means "we never looked".
    echo "Skipped " . count($skipped) . " destructive/session-ending link(s): "
        . implode(', ', array_keys($skipped)) . "\n";
}

if ($unfinished) {
    // The URL space never closes -- forum and message listings keep generating
    // fresh query strings -- so the cap always trips. Distinct scripts reached
    // is the coverage number that actually means something.
    echo "NOTE: hit a page cap with $unfinished URL(s) still queued"
        . " (paginated listings generate URLs without bound).\n";
}

$allScripts = array();
foreach (glob(__DIR__ . '/../app/*.php') as $file) {
    $allScripts[basename($file)] = true;
}
$unreached = array_diff_key($allScripts, $scriptsHit);

// Intersected, not counted raw: $scriptsHit also holds the admin/ paths, which
// are not in app/*.php and were making this report "70 of 69".
echo "Scripts reached: " . count(array_intersect_key($scriptsHit, $allScripts)) . " of " . count($allScripts) . " in app/\n";

// Split the gap into "known and explained" and "new", because only the second
// kind is actionable. An unreached script with no entry in $UNREACHABLE means
// either a page just fell out of the net or someone added one without
// covering it; both are regressions and both fail below.
$explained = array_intersect_key($UNREACHABLE, $unreached);
$newGaps   = array_diff_key($unreached, $UNREACHABLE);
$stalePlea = array_diff_key($UNREACHABLE, $unreached);

if ($explained) {
    echo "Not reached, explained (" . count($explained) . "):\n";
    foreach ($explained as $script => $reason) {
        echo "  $script -- $reason\n";
    }
}

if ($stalePlea) {
    // Reaching a page that $UNREACHABLE says is unreachable is good news, but
    // the entry has to go or the list stops meaning anything.
    echo "\n" . count($stalePlea) . " entry(s) in \$UNREACHABLE are now reachable -- delete them from "
        . basename(__FILE__) . ":\n";
    foreach (array_keys($stalePlea) as $script) {
        echo "  $script\n";
    }
}

foreach (array_keys($newGaps) as $script) {
    $failures["$script | unreached with no entry in \$UNREACHABLE"] =
        'add it to the crawl, or record why it cannot be reached';
}

/*
 * The admin area is counted separately, against $ADMIN_PAGES rather than
 * against what the crawl happened to reach.
 *
 * $ADMIN_PAGES is the list adminGate() proves is refused to a stranger, so a
 * page missing from it is a page whose gate nothing checks. index.php and
 * adminlogin.php are the login pair and are deliberately outside it -- they
 * are the two that must serve to someone who is not signed in yet.
 */
$adminScripts = array();
foreach (glob(__DIR__ . '/../app/admin/*.php') as $file) {
    $adminScripts['admin/' . basename($file)] = true;
}
unset($adminScripts['admin/index.php'], $adminScripts['admin/adminlogin.php']);

$adminUngated = array_diff_key($adminScripts, array_flip($ADMIN_PAGES));
echo "Admin pages behind a checked gate: " . count($ADMIN_PAGES) . " of " . count($adminScripts) . "\n";
foreach (array_keys($adminUngated) as $script) {
    $failures["$script | not in \$ADMIN_PAGES"] =
        'nothing checks that this page refuses a caller without an admin session';
}

echo "Notices/deprecations (not failing): $noticeCount\n";
echo "Known warnings in baseline: " . count($baseline) . " (" . count(array_intersect_key($baseline, $observed)) . " still firing)\n";

// Emitting the full observed set makes regenerating the baseline a copy-paste
// rather than a transcription exercise.
if (getenv('MB_WRITE_BASELINE') === '1') {
    ksort($observed);
    file_put_contents(
        $BASELINE_FILE,
        "# Warnings present before the port began. Only ever remove lines.\n"
        . "# Regenerate with: MB_WRITE_BASELINE=1 php tests/smoke.php\n"
        . implode("\n", array_keys($observed)) . "\n"
    );
    echo "Wrote " . count($observed) . " signature(s) to $BASELINE_FILE\n";
}

if ($QUERY_AUDIT) {
    // Emitted as marker lines rather than written to the inventory directly:
    // the crawl is only one of the two surfaces audited, and query-audit.sh
    // merges these with the tick's failures so both go through one formatter.
    ksort($queryErrors);
    foreach ($queryErrors as $signature => $paths) {
        $where = array_keys($paths);
        echo "QUERYFAIL\t" . $signature . "\t" . $where[0] . "\n";
    }
    echo "\nQuery audit: " . count($queryErrors)
        . " distinct failing quer(y/ies) from the crawl (see tests/query-audit.sh).\n";
}

$stale = array_diff_key($baseline, $observed);
if ($stale) {
    echo "\n" . count($stale) . " baseline entry(s) no longer firing -- delete them from "
        . basename($BASELINE_FILE) . ":\n";
    foreach (array_keys($stale) as $entry) {
        echo "  $entry\n";
    }
}

if (!$failures) {
    echo "\nPASS: no new fatals, parse errors or warnings.\n";
    exit(0);
}

echo "\nFAIL: " . count($failures) . " new problem(s)\n";
foreach ($failures as $signature => $path) {
    echo "  $signature\n      first seen at: $path\n";
}
exit(1);
