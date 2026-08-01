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
    'guildbarter.php'      => 'dead: barter.php dies at line 17, the barter system was switched off in 2003',
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
crawl($member, array('main.php?pageid=news'), $MAX_MEMBER_PAGES);

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
function crawl(MbClient $client, array $seeds, $maxPages)
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
        $failures[] = array($path, "HTTP $status");
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

echo "Scripts reached: " . count($scriptsHit) . " of " . count($allScripts) . " in app/\n";

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
