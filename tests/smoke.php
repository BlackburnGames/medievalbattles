<?php
/**
 * Authenticated smoke crawl.
 *
 * Logs in as a fixture user, walks every reachable in-game page, and fails on
 * any PHP diagnostic in the response. This is the cheapest regression net
 * available for the port: app/.htaccess already turns display_errors on, so
 * PHP failures land in the response body where they can be asserted on.
 *
 * Usage (from the repo root):
 *   docker compose exec -T web php /repo/tests/smoke.php
 */

require __DIR__ . '/lib/client.php';

$BASE  = getenv('MB_BASE_URL') ? getenv('MB_BASE_URL') : 'http://localhost';
$EMAIL = 'tester@example.com';
$PASS  = 'test1234';
$MAX_PAGES = 600;

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

$client = new MbClient($BASE);

// Prime the computer_id cookie the login flow expects, then authenticate.
$client->get('/index.php');
$client->post('/checklogin.php', array('email' => $EMAIL, 'pw' => $PASS, 'login' => 'Login'));

if ($client->lastStatus !== 302) {
    fwrite(STDERR, "FATAL: login did not redirect (got HTTP {$client->lastStatus}).\n");
    fwrite(STDERR, "Is the database seeded? Try: docker compose exec -T db mysql -umb -pmb mbv6 < db/seed.sql\n");
    exit(2);
}

$baseline = loadBaseline($BASELINE_FILE);

$queue    = array('main.php?pageid=news');
$seen     = array();
$skipped  = array();
$failures = array();
$observed = array();
$scriptsHit = array();
$noticeCount = 0;
$visited = 0;

while ($queue && $visited < $MAX_PAGES) {
    $path = array_shift($queue);
    if (isset($seen[$path])) {
        continue;
    }
    $seen[$path] = true;

    $body = $client->get('/' . $path);
    $visited++;
    $script = strtok($path, '?');
    $scriptsHit[$script] = true;

    $status = $client->lastStatus;
    if ($status !== 200 && $status !== 302) {
        $failures[] = array($path, "HTTP $status");
    }

    // Diagnostics must be matched against the stripped text, not the raw body:
    // with html_errors on, PHP emits "<b>Notice</b>:  ..." and any pattern
    // expecting "Notice:" silently matches nothing. That produces a green run
    // that proves absolutely nothing, which is worse than a red one.
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
            $observed[$signature] = true;
            if (!isset($baseline[$signature])) {
                $failures[$signature] = $path;
            }
        }
    }

    $noticeCount += preg_match_all($NOTICE_PATTERN, $text, $ignored);

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

if ($visited >= $MAX_PAGES && $queue) {
    // The URL space never closes -- forum and message listings keep generating
    // fresh query strings -- so the cap always trips. Distinct scripts reached
    // is the coverage number that actually means something.
    echo "NOTE: hit the $MAX_PAGES page cap with " . count($queue) . " URL(s) still queued"
        . " (paginated listings generate URLs without bound).\n";
}

$allScripts = array();
foreach (glob(__DIR__ . '/../app/*.php') as $file) {
    $allScripts[basename($file)] = true;
}
$unreached = array_diff_key($allScripts, $scriptsHit);

echo "Scripts reached: " . count($scriptsHit) . " of " . count($allScripts) . " in app/\n";
if ($unreached) {
    echo "Not reached (no link from the crawl root, or skipped): "
        . implode(', ', array_keys($unreached)) . "\n";
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
