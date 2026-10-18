# Testing strategy

Characterization tests, not unit tests — there is almost nothing unit-testable,
and the failure mode of this port is *silent behaviour change*, which golden
masters catch and unit tests would not.

## The suite

`bash tests/run-all.sh` runs the tick, combat and actions golden masters, the
smoke crawl and the `register_globals` audit.

### `tests/tick-golden.sh`

Pins `update.php`, the best test target in the codebase: pure
database-in/database-out, no HTML, session or auth, and the densest game logic.
It resets the database, runs a tick, dumps all tables via
`tests/dump-state.php`, and diffs against `tests/golden/tick.txt`.

It also **fails if the tick reports any query error**. The tick is outside the
smoke crawl, so a failing query there would otherwise be reported by nothing at
all — which is exactly how the dead-research and lost-army bugs survived the
whole port.

Treat re-accepting a golden as a review step: read the diff and confirm every
changed number was intended. Re-accept with `--accept`.

### `tests/combat-golden.sh`

Pins combat — `attack.php`, `attackr.php`, `attackm.php`, `scattack.php` and
the two shared includes — which the tick never runs and the crawl drives only
far enough to prove it renders. Thirty-nine scenarios, each from a fresh
fixture: `tests/combat-golden.php` drops every table and reloads `db.sql` and
`db/seed.sql` itself, applies a few `UPDATE`s to set up the two armies, logs in
as `tester@` and posts the attack. It prints what the page said and **every
column that changed in every table**, as before → after, and diffs that against
`tests/golden/combat.txt`. About four seconds for the lot.

The golden file is meant to be read. Each scenario prints its own one-line
reason, and a reviewer can check a battle by hand from the numbers in it.
`php tests/combat-golden.php <name>` runs one scenario.

**The scenarios are chosen by branch, and then by scale.** Every outcome of
every page, the land-gain brackets, the order land is taken in, and the
refusals that run after the target is fetched are covered. So are the quirks:
a combat bug gets a `quirk-` scenario that pins it as it is, and the fix
renames it and keeps it as the regression test, with a diff that touches only
the scenarios the bug reached. Every combat quirk recorded so far has gone
that way, and none is open. But small armies hide small changes in
the rounding: three hundred warriors at 100 or 101 hit points both come home
297 strong. So `land-win-large` fields every unit type on both sides in the
hundreds of thousands, and the `modifier-` sweep attacks once as each class
and each race that has a modifier. Each of these was checked by moving one
constant by one point (unit power and hit points, every modifier, the gain
rates) and watching the test fail. Sixteen were tried and all sixteen failed
the test.

**Only failed queries and errors are reported from PHP's diagnostics.** PHP 8
reports a read of an unassigned name as a Warning, but the database changes
already show what that read did to the battle, and listing those warnings would
churn the golden on every commit that starts a tally from zero. They are the
crawl's business. Line numbers and the bcrypt hash a failed query quotes are
masked for the same reason.

**Wait two seconds after an edit before trusting a result.** The image runs
opcache with `revalidate_freq=2`, so Apache keeps serving the file it cached
for up to two seconds after a change. An edit followed at once by a test run
can pass against the old code. This matters for scripted mutation checks, not
for an ordinary edit-then-test cycle.

### `tests/actions-golden.sh`

The combat golden master's harness pointed at the orders an empire gives
itself: construction so far, plus every order form submitted once. The
machinery the two share is `tests/lib/golden.php` and `tests/lib/golden.sh` —
fixture reload, login, snapshot and diff, and the compare-or-accept wrapper —
so a third would be a scenario list and nothing else. Golden file
`tests/golden/actions.txt`; `php tests/actions-golden.php <name>` runs one
scenario, and the same review rule and `quirk-` convention apply.

**A scenario can run the tick** as one of its requests, `array(TICK, array())`,
which runs `update.php` from the repo root as the cron does. Everything the
tick touches moves, and that is the tick golden master's to pin, so a scenario
that ticks names the columns it is about in `show` and only those are printed.
The `explore-` scenarios use it: the tick golden master's fixture has no
explorers in the field, so nothing else exercises the exploring rates.

**The `blank-boxes-` scenarios submit each form as a browser does**, one box
filled and every other box sent as `""`. That is not the same request as
leaving the other fields out, and it is the one that matters: PHP 8 does not
read `""` as 0, so `"" < 0` is true and `"" + ""` throws. When this file was
written, every one of those forms refused the order as negative or died, and
the build page died even with the other fields left out — nobody on 8.3 could
build anything without filling in all seven boxes. The crawl could not see it
because it follows links and never fills a form in. `mb_count()` is the fix;
see `app/include/request.php`.

### `tests/unbound-queries-audit.php`

How many queries in each file still build their SQL out of values instead of
binding them with `mb_query()`: a variable interpolated or concatenated into
the SQL argument, or a variable holding the whole statement. A query with no
values in it is not counted, and neither is a value in `mb_query()`'s params
array. The baseline, `tests/unbound-queries.txt`, holds a count per file rather
than a list of entries, so the comparison is its own: a file may have fewer
than its baseline, never more, and a file the baseline does not list may have
none. `--list <file>` prints each call; `--accept` lowers the baseline after a
conversion. It scans `update.php` as well as `app/`. It is at **zero**, so any
query built from values fails it. A deliberate exception — SQL that needs a
table or column name, from a literal list — carries
`// mb-unbound-ok: <why>` on its line or the line above, and is not counted.

`tools/bind-queries.php` does most conversions. It rewrites a call only when
every value in it is unambiguously a value, and reports the rest with the
reason:

- `'$x'` or `\"$x\"` quoted in the SQL becomes a bound value; so do
  `mb_sql_str($db, X)` and `mb_sql_int(X)`, and a `$q_x` variable holding one
  of those, when it is assigned once and nothing in `X` is reassigned after.
  The assignment goes when nothing reads it any more.
- A bare variable is bound only as the whole right-hand side of a `WHERE`
  comparison or after `LIMIT` / `OFFSET`. In a `SET` or in arithmetic it is
  left alone: MySQL reads a bare number as an exact decimal but a quoted one —
  which is how `mb_query()` writes anything but an int — as a double, and
  `ROUND()` of the two can differ on a .5. Combat and the tick round
  everywhere.
- Anything where a name may go, a value inside quotes with other text, a
  variable concatenated in, or SQL that is itself a variable, is left.

`--bare` lists every unquoted value it would bind, with the SQL either side,
for review.

### `tests/signup.php`

What a new empire starts with. The crawl cannot sign up — `checksignup.php`
makes an empire out of `rand()`, so there is nothing to compare a golden
against — and nothing else checked the race and class grants. This signs up
one empire per combination that grants something and asserts each grant, as a
range where signup rolls dice. It found the Night Elf's Archery being taken
back by the Ranger check two lines below it. Reloads the schema before and
after.

### `tests/newgame.php`

Both world wipes, `admin/newgame.php` and gameconfig's account wipe, keep
administrator accounts, and this checks that they start the administrators'
kingdoms over. It makes tester@ and idle@ admins, runs each wipe as tester@,
then signs up a twin of each admin (same race and class) through
`checksignup.php`. Every column of `user`, `buildings`, `military`,
`research`, `explore` and `returntbl` must match the twin's, except who the
account is and the columns signup rolls dice for, which are held to signup's
ranges instead. Comparing against a real signup rather than a list of
expected values means a column added later that the reset forgets fails here
untouched. It also checks that the non-admin is gone, the admins' barter
offers and empire news are gone, the settlements are recounted, and tester@
can still log in. With the reset disabled it fails on lumber, research,
returns in flight, safemode and barter, which is the bug it was written for.
Reloads the schema before each wipe and after.

### `tests/smoke.php`

Crawls every reachable page, failing on any PHP fatal, parse error or warning
in the response body. Notices are counted but not failed — this is PHP 4 code
that reads uninitialised variables everywhere, and failing on them would mean a
permanently red suite.

Three phases, each with its own cookie jar and its own page budget:

1. **Logged out**, from `index.php`. This is the login page plus the four
   fragments it `include`s and echoes as `$data` (`main-site`, `about_us`,
   `game_scores`, `signup`). They are credited to their own filenames rather
   than to `index.php`, because a parse error in one still breaks the request.
2. **Logged in as `tester@`**, from `main.php?pageid=news`. This user leads
   both Testguild and settlement 1.
3. **Logged in as `idle@`**, a rank-and-file guild member, on a fraction of the
   budget. Every role check in this app has two arms and the primary tester
   only ever takes one; this pass is here for the other arm, not for breadth.
   It runs last because crawling mutates game state through plain GET links,
   and the primary pass should see the fixture world rather than its leavings.

The budgets are separate on purpose. The logged-in URL space never closes —
paginated listings keep generating fresh query strings — so its cap always
trips, and which scripts get reached depends on queue order. A shared pool let
the handful of logged-out pages starve the settlement-leader pages off the end
of the run.

**It requires a fresh database.** Many actions in this app are plain GET links,
so crawling is itself destructive; a second run over the same fixture reaches
fewer pages than the first. `run-all.sh` calls `reset-db.sh` immediately before
it.

### `tests/known-issues.txt`

Baselines warnings that predate the port, keyed by source location. New
warnings fail; entries that stop firing are reported so they can be deleted.

**The list should only ever shrink.** It is currently empty — it held 21
entries at the end of Phase 1 — so any warning at all is now a regression.

### `tests/register-globals-audit.php`

Static, not behavioural: it tokenizes every source and reports variables whose
first appearance in global scope is a read, and that none of the shared
includes **that file pulls in** provide. That was the checklist for removing
the `register_globals` shim, and entries remain in
`tests/register-globals.txt` — all of them now plain uninitialised reads rather
than request inputs.

**The list should only ever shrink**, and it doubles as a Phase 3 bug list.
Re-accept with `--accept`. It has grown exactly once, by three entries, on the
commit that made the include resolution per file; the reason is in the script's
header.

Include resolution is worth knowing about before you change the script. It is
transitive, and a file that is only ever included sees its **includer's** scope
— half this codebase is a panel template that renders `$race` and `$gp` while
including nothing, and scoring those against their own closure reports 345
entries of pure noise. Paths resolve the way PHP resolves them here: CWD first,
where the CWD is the requested page's directory, then the including file's
directory. `include($mb_panel)` is followed via the literal `.php` strings
assigned to that name in the same file.

**Read it as a list of suspects, not defects.** The audit is still
flow-insensitive, so a variable assigned inside one branch and read in another
looks unassigned. Both readings have to be checked before acting:

- `app/gl-delposts.php` passing `$setgid` to `mb_db_result()` where it means
  `$guild_id` was **real** — `$setgid` appeared exactly once in the whole
  codebase, as that argument.
- `app/include/S_WEP.php` echoing `$shortsword_button` and 23 siblings was
  **not** — all 24 are assigned in `include/buttons.php`, which `S_WEP.php`
  includes on its first line. The weapon buy buttons render fine; this entry
  and its siblings are an artefact of the per-file analysis.
- `app/attackr.php` and `app/attackm.php` reading `$tsetid` were **real**, and
  took driving an attack to prove: both filed the defender's settlement news
  against it, so four news rows went to setid 0 rather than to the settlement
  being raided. Fixed, which is what took the list from 161 to 159.

**A page now provides names to its own includes**, not only the shared ones —
and the reason is a bug the old rule caused rather than a false positive it
reported. `attack.php` reads and checks `$uwarrior` and five siblings, then
includes `include/attack/defANDoff.php`, which reads them. The audit only
credited `$sharedIncludes`, so those reads looked like request parameters, and
the Phase 2 port gave both combat includes their own `mb_input()` copies. Those
replaced every count the pages had validated — which is how a resource raid
could field 100,000 golems it did not own. The rule is quantified over
**entries**, files nothing includes: a name read in F is provided if, for every
entry whose closure reaches F, some other file in that closure assigns it. One
page that forgets the name keeps the read reported. It took the list from 157
to 91; every entry it removed is an include whose includers all assign the
name, `S_WEP.php`'s 24 buttons among them.

### `tests/sql-injection-audit.php`

Static, like the one above. It marks every variable derived from `mb_input()`
or a request superglobal, spreads the taint through assignment to a fixpoint,
and reports what survives into a query call — `mysqli_query()` and its
siblings, or `->query()` on a connection. It matched only the first form until
the zero it reported turned out to be silent about the sixty object-form calls
on the signup, login and activation pages. Baselined in
`tests/sql-injection.txt`.

**`--stored` is the second order**, baselined separately in
`tests/sql-injection-stored.txt` as the XSS audit does it: a value fetched out
of the database counts as a source too, because a player typed it. It opened at
36 and the one it existed for was real — a guild named `a' OR 'a'='a` emptied
every guild in the game when deleted, because the name is copied into each
member's row and interpolated bare. It is at zero now. It is per-file like the
first-order list, so `$empireguild`, which `functions.php` loads, was invisible
to it in every page that reads it; those eighteen sites were found by grep and
escaped by hand, and `mb_valid_gname()` holds the name to a safe charset at
creation.

**The list should only ever shrink**, and it is the Phase 3 SQL worklist.
Re-accept with `--accept`. Entries carry no line numbers on purpose: a fix
elsewhere in the file would otherwise read as one entry removed and one added.

**It is at zero, and zero is not the same as clean.** Two limits are in the
script's own header and both still hold: it is first-order, so a tainted value
written to the database and read back into a later query is invisible to it,
and it is per-file, so a parameter read in one file and interpolated in another
is too. That second one is a live hazard now rather than a theoretical one —
**moving code into an include is a way to launder a page off this list without
fixing anything.** `app/include/barter_handler.php` keeps its `mb_input()`
reads beside its queries for exactly that reason; if it had taken them as
arguments from the two pages that include it, the audit would have found no
taint and reported nothing.

Each entry records the quoting context, because it decides the fix.
`mb_sql_str()` for a value between quotes; `mb_sql_int()` for one interpolated
bare, where escaping is useless because there is no quote to break out of.

Three things worth knowing before acting on it:

- **Arithmetic launders taint, and the audit knows.** Most of this game is
  `$gp = $gp - $cost` chains seeded by a request parameter, and a subtraction
  cannot carry an injection out of its operands. Teaching it that rule dropped
  the initial list from 332 entries to 161. But the rule is about *PHP's*
  arithmetic — `"SET thieves = round($thieves - ($send * .1))"` is MySQL's, so
  `$send` is still statement text and is still reported. Correctly.
- **It is flow-insensitive, so it over-reports one variable used two ways.**
  `wconstruct.php` read `mb_input('longsword')` only to test `IsSet()` and then
  assigned the same name the build time in ticks; what reached the query was
  always a literal. Twenty-two entries, none of them real. Both cases found so
  far were fixed by renaming rather than suppressed — if the audit cannot tell
  the two meanings apart, neither can a reader.
- **It is first-order and per-file.** A tainted value written to the database
  and read back into a later query is a real stored injection this will not
  see, and `extract()` on a row hides the flow entirely — which is how a
  genuine one in `S_VMESS.php` stayed invisible. Every string reaching a query
  still has to be escaped, not just the ones listed.

### `tests/xss-audit.php`

The same audit at the other end of the request: it reports tainted values that
survive into an `echo` or a `print`. Baselined in `tests/xss.txt`.

The taint tracker is not a second copy — both audits use `tests/taint-lib.php`,
because a source or a sanitizer that one knows about and the other does not is
a false clean bill of health. Everything above about arithmetic,
flow-insensitivity and the first-order/per-file limits applies here unchanged.

**The list should only ever shrink**, and **it is at zero, which is not the
same as clean.** The first-order limit bites harder here than it does for SQL:
the stored XSS — an empire name, a guild name, a forum post, a message — is
read back out of the database and is invisible to this. That is the larger half
of the problem and the more dangerous one, so it has its own list:

```bash
php tests/xss-audit.php --stored             # check tests/xss-stored.txt
php tests/xss-audit.php --stored --accept    # re-accept it
```

`--stored` counts a `mysqli_fetch_*` or `mb_db_result()` as a source as well as
`mb_input()`, on the reasoning that everything in this database was typed by a
player at some point. Two baselines rather than one, so the default list keeps
meaning exactly what this section says it means. `xss-stored.txt` opened at 116
and is the superset — a fix that clears an entry there usually clears one here
too. **It is also at zero now**, which moves what this whole audit is guarding:
the job is no longer working through a list, it is that a new echo of a stored
value has to go through `mb_h()` or `mb_attr()` or the run goes red.

Each entry records the HTML context, because it decides the fix:

| Context | Example | Fix |
| --- | --- | --- |
| `text` | `...>$x<...` | `mb_h()` |
| `attr-quoted` | `<a href="$x">` | `mb_h()` — it uses `ENT_QUOTES`, the default does not |
| `attr-bare` | `<font class=$x>` | `mb_attr()`, which quotes as well as escapes |

The third is the one that matters, and it is how this codebase writes almost
every attribute. A bare attribute value ends at the first space, so
`x onerror=alert(1)` breaks out carrying no character `htmlspecialchars()`
would touch — escaping alone does not close it, exactly as escaping alone does
not close `WHERE topicid=$topicid`.

Context is accumulated across the whole file rather than per statement, because
the tag a value lands in is routinely opened somewhere else: `topic.php` emits
an `<input ... value="` as inline HTML and the short-tag echo that follows
carries no literal text of its own.

Nothing is echoed as stored markup any more. The forum body and the news lines
used to be, and both are stored as plain text now — `mb_rich()` and
`mb_news_html()` in `app/include/` do the markup at render time. See
[modernization.md](modernization.md).

**The 51 news writers are not covered.** The crawl produces no rows in
`empnews`, `setnews` or `guildnews` at all, so a broken news INSERT would not
fail this suite — which is how `guildconfig.php` came to be writing into a
`guildid` column the schema has never had.

### `tests/news-render.php`

The only test in the suite that drives app functions directly rather than
through a page, and the reason is a limit of the crawl rather than a preference.
The three news writers and `mb_news_html()` (see
[`app/include/news.php`](../app/include/news.php)) turn a stored sentence and a
category back into markup, and the value that reaches every one of those
sentences is an empire name — which a player chooses. The crawl files real news
rows now, but it cannot make one of those names an XSS payload: the fixture's
names are what the tick golden master is computed from.

So the round trip runs here, with the hostile values a real player could pick.
It asserts that what goes in comes back byte for byte (escaping on the way in
was the defect being removed), that the category is in its own column rather
than wrapped around the sentence, that the payload renders inert, that `[i]`
works while a typed `<i>` does not, and that an unknown category falls back
rather than landing in the unquoted `class=` attribute.

It writes to the three news tables on ids far outside the fixture's and deletes
what it wrote, so it is order-independent and safe against a live world.

### `tests/session.php`

What a session id is worth, over HTTP with curl, because the crawl's client
cannot see any of it: it sends no `Sec-Fetch-Site`, never reads a cookie's
attributes, and never goes back to a session it has finished with. Each check
is one rule in `app/include/session.php` — the cookie's `HttpOnly` and
`SameSite=Strict`; that a planted id is not adopted and a login replaces the
id it arrived with; that logout ends the session; that a cross-site or
same-site request gets no session to read, act, log in or log out with; that
a password change ends the account's other sessions; and that the idle and
absolute limits hold.

The expiry checks do not wait hours. They wind the clock back in the session
file itself — PHP's default `key|value;` serialization, which the CLI can read
because it shares `session.save_path` — and `mb_session_start()` declines to
start a session in the CLI at all, so the test cannot create one of its own.
It uses poor@ and restores its password. Against the old session code it
fails 17 of its 23 checks.

### `tests/barter-race.php`

The one test that makes requests overlap. The crawl is strictly sequential, so
it cannot see a handler that is correct one request at a time and wrong two at
a time — which is what both barter handlers were: they checked the listing and
the balances at page load, then wrote absolute figures from them, and never
asked whether the `DELETE` that ended the listing had removed anything.

It logs the tester in twelve times and fires one buy, then one cancel, from
all twelve sessions at once with `curl_multi`. Separate sessions matter: PHP's
file session handler holds a lock for the whole request, so requests sharing a
cookie queue and never race. It asserts that exactly one succeeds and that
every balance moved by exactly one sale's worth. Against the old handler it
failed five runs in five.

It needs the fixture's listings, which the crawl consumes, so `run-all.sh`
resets the database before it.

### `tests/migrations.sh`

The fixture is built from `db.sql` and `db/accounts.sql`; a real database is
built by the migrations in `db/migrations/` (`bin/migrate.php`). This builds
each of the two databases both ways, in scratch databases beside the fixture
(`mbmig_*`, dropped afterwards), dumps them with `tests/schema-dump.php` —
every `CREATE TABLE` without its `AUTO_INCREMENT` counter, and every row,
sorted — and fails on any difference. Then it runs the migrations again,
which must change nothing. `phinxlog` is the one table it ignores.

So a schema change is two edits, a migration and the schema file, and this
fails until they agree. Changing a `varchar(255)` to `varchar(254)` in
`db.sql` alone fails it, on both runs.

### `tests/query-audit.sh`

Not a test — an inventory generator, and the only thing that runs the stack
with `MB_REPORT_QUERIES=1`.

Query reporting is on by default now (see
[porting-notes.md](porting-notes.md)), and both the crawl and the tick fail on a
query error, so this is no longer the only way to see one. What it still does
that they cannot is produce the **combined inventory** in one pass, across both
surfaces at once, without stopping at the first failure — which is what you want
when you are working through a batch rather than guarding against a regression.

Two passes: the smoke crawl in audit mode (`MB_QUERY_AUDIT=1`, which collects
the mysqli warnings instead of failing on them), and `update.php`. It is not in
`run-all.sh` because it restarts the container twice.

`tests/broken-queries.txt` is **currently empty**, and its output is a floor on
the number of broken queries rather than a total — it covers only what the two
passes reach.

Its first run found that research had never worked and that troops sent out were
lost permanently. Both are fixed; see [modernization.md](modernization.md).

### `tests/register-globals-diff.sh`

Being flow-insensitive, the audit has one blind spot worth knowing: a variable
assigned in one branch and read in another looks assigned. That is exactly how
`gc.php` slipped through.

This script is what caught it: it renders 31 parameterised URLs with the shim
on and again with it off and diffs the bodies, so any page still depending on
injection shows up as a difference. It is not in `run-all.sh` because it
restarts the container twice; run it by hand after touching request handling.

## Constraints worth knowing before changing the harness

- **There is no rollback isolation** — the tables are InnoDB now, but the
  app and the tick commit as they go, so `reset-db.sh` drops and reloads the
  whole database. It must drop *all*
  tables, not just the 22 in `db.sql`, because the app creates per-settlement
  tables (`setmain<id>`, `setmsgs<id>`) at runtime.
- **There are two databases, and both are reset.** `reset-db.sh` and the
  golden harness's `loadFixture()` reload the accounts database alongside the
  round. An account left over from an earlier run — a signup, a moved address
  — would otherwise log into the next run's kingdoms.
- **Fixture user ids are deliberately 1–3**: the tick loops from 0 to
  `max(userid)` rather than over existing rows, so high ids make every run
  crawl.
- **Do not seed users by driving the signup form.** Signup derives starting
  resources from `rand()`, and PHP 7.1 rewired `rand()` to `mt_rand` and fixed
  its modulo bias, so the same seed yields different numbers on 5.6 than on
  8.x and cross-version goldens would never match.
- **Diagnostics must be matched against `strip_tags()`ed output.** With
  `html_errors` on, PHP emits `<b>Notice</b>:`, and a pattern expecting
  `Notice:` silently matches nothing — that produced a false PASS during
  development.

## Coverage

The crawl reaches **58 of the 69 scripts in `app/`**, and the other 11 are
listed in `$UNREACHABLE` in `smoke.php` with a reason each. That list is the
third ratchet: an `app/` script that is neither reached nor explained **fails
the run**, so a page falling out of the net is a regression rather than a
silently smaller number. Entries that become reachable are reported so they can
be deleted.

The denominator is `glob(app/*.php)`, so it counts neither the ten admin pages
nor the includes. Those have their own accounting — see **The admin phase**
below.

The 11 fall into five groups:

- **Includes** (3) — `common.php`, `commong.php`, `functions.php`. Not pages;
  this group is structural and will not shrink.
- **POST handlers** (3) — `checklogin.php` is exercised by the login that
  starts each run, and is listed because the coverage map credits URLs rather
  than requests. `checksignup.php` derives starting resources from `rand()` and
  cannot be driven without breaking the cross-version golden (see above).
  `gl-topic.php` has nothing linking to it.
- **Destructive, deliberately skipped** (2) — `disband.php` destroys the
  fixture army, `logout.php` ends the session the crawl depends on. See `$SKIP`.
- **Dead in the shipped game** (1) — `scoreboard.php`, orphaned; nothing links
  to it and `index.php` has its Scores link commented out. `guildbarter.php`
  was the other one and came off the list when the barter system was switched
  back on.
- **Auth flow** (2) — the activation pages, which need an unverified account;
  the crawl's are all verified. `tests/signup.php` signs one up and drives both,
  reading the code back from the account since mail goes nowhere.

### Driving forms

The crawler follows links, so every POST handler in the game sat outside the
net. `postForm()` submits one and routes the response through `inspect()`, the
same assertions a crawled page gets — a handler that emits a warning fails the
run for the same reason a page does.

Both arms of all four forum thread handlers are driven (`addtopic` and
`addreply` are separate branches writing separate tables), and the two
moderation handlers are called directly. Every field carries an apostrophe on
purpose: an unescaped `INSERT` loses the row silently, and none of these
handlers checks a return value.

Deliberately not seeded through the database instead. An `INSERT` written by
hand into the fixture proves nothing about the code that was supposed to write
it — the first run of this found a double-executed query, a `VALUES` clause
missing a quote since 2003, and a debug `echo` of raw SQL.

**Destructive requests run before the crawl, not after.** This was learned on
`govt.php`, which used to re-run the settlement election on every view and so
deposed the tester — whom nobody in the fixture votes for — the moment the
crawler passed through; anything afterwards that needed settlement leadership
was refused, silently, because a refusal renders a message rather than a
warning. The election only runs when a vote changes now (see
`app/include/election.php`), and `accountDeletion()` asserts the tester still
leads after the crawl, but the rule stands for every request that consumes
fixture state.

The same reasoning covers the barter boards. Listing is a form, and buying and
cancelling are GET links that consume the row they point at, so `$SKIP` keeps
the crawler off them and all three are driven by hand — before the crawl,
because buying needs gold and iron and the crawl spends both on buildings. The
seeded listings are numbered 1–3 so the URLs are stable: 1 and 2 belong to
other empires and are the buy path (2's seller is a guild-mate, because
`guildbarter.php` refuses anyone else), 3 is the tester's own and is the cancel
path.

**Combat is driven by hand too, and it is the only thing that files news.**
Thirty-two of the fifty-one news sites are in the three attack pages, and none
had ever run: the crawler reaches `attack.php` but only its landing form,
because launching an attack means naming a target and a number of units. Two
attacks on `poor@` — a land raid that wins and a resource raid that loses, so
both outcome branches are covered — and then the rendered lines are asserted on
`snews.php` and `main.php?pageid=news`.

The assertion is the point. Every news `INSERT` in the game is fired unchecked,
so a row that is never written produces no diagnostic and the crawl's usual
"no warning in the body" test cannot see it. `newsShows()` fetches the page,
runs the same `inspect()` as everything else, and then requires the sentence
*and* its category colour — separately, because they come from different
columns now and matching them as one string would pass a row that stored the
`<font>` tag back inside the text.

It found one on its first run. `attackr.php` and `attackm.php` filed the
defender's settlement news against `$tsetid`, which nothing in either file
assigns — both were on `register-globals.txt` as dead reads — so the rows went
to setid 0 and no settlement has ever been told that one of its members was
raided. `attack.php` does the same thing correctly and is what says what the
value should be. Checking that half needs a third login: `snews.php` shows the
settlement you are in, and attacker and defender are in different ones.

Two attacks and no more, because `user.fleets` is 2 in the fixture — four
generals less the two armies already in the field — and each attack consumes
one. That is also why `attackm.php` is not driven: it is the same file shape as
`attackr.php`, and giving the fixture a third general would hand
`calculations.php` a fifth army for four return slots.

The refusals go first, and they are the only requests that get `attackm.php`,
`scattack.php` and `intel.php` past their landing forms. All five combat pages are sent at userid
999, which names nobody, plus an empty army on `attack.php` and the tester at
their own empire on `scattack.php`. None of them may consume anything, and the
fixture makes that checkable for free: a refusal that leaked a general leaves
the resource raid behind it with none, and its news assertion fails.

**The fixture carries two password formats, and that is coverage rather than
untidiness.** `idle@` is bcrypt — what `app/include/password.php` writes now —
while `tester@` and `poor@` keep the unsalted MD5 the game stored until Phase 3,
so every run drives both arms of `mb_password_verify()` *and* the rehash that
follows a legacy login. The hashes are in `db/accounts-seed.sql`, because the
password is the account's; the round holds no credential at all, so the
rehash moves no round row and the tick golden master is the same on every
run. Deleting the MD5 rows would delete the only coverage that path
has.

`pw` used to be on the golden harness's ignore list, because every login
rehashed it in six tables. The column is gone from the round.

`passwordChange()` closes the other half, on `idle2@` after the email change.
It refuses a wrong current password, changes the password, and then logs in on
a **new client** — nothing but the database decides that one — checks a page
that reads past `user`, and confirms the old password has stopped working.

`accountDeletion()` then spends `idle2@` for good: it votes for TestLord through
`govt.php`, deletes the account through `preferences.php`, and reads TestLord's
vote count back off `govt.php` as the tester. It is the last step before the
admin phase because the account does not exist afterwards — nothing after it
may log in as `idle@` or `idle2@`.

The email change in `preferences.php` is driven on the *member*, not the
primary tester: it rewrites the account, six tables and the session, and every fixture the
primary pass depends on is keyed on that address. Both arms are asserted — an
address containing a quote must be refused, a valid one must go through — and
then the next request is checked for still being logged in, which is what
catches `include/session.php` and `preferences.php` disagreeing about what an
address may be.

### The admin phase

Runs **last**, and is the one exception to the rule above. It logs into
`app/admin/` and drives `gameconfig.php`'s bulk actions, which is the only way
to find out whether they work — thirty of that page's statements named tables
this schema has never had, so "delete all news" and "delete all forums" had
never once done anything. Running them leaves the fixture spent, so nothing may
be added after it.

Two passes, and the first is the one that matters. Before the admin pass, every
entry point in `$ADMIN_PAGES` is fetched twice: once by a client with no
session and once by **`poor@`, a logged-in player who is not an admin**. Both
must get a 403. Without that second arm a gate that checked only for a session
would pass. The list is checked against `glob(app/admin/*.php)`, so an admin
page added and left ungated fails the run. There is no login page to exempt:
the admin is `tester@`, logged in through the game, because an administrator is
a flagged player account.

After the bulk actions the phase asserts two things about that: deleting an
admin account by name is refused, and the admin is still admitted after both
world wipes — "delete every account" and `newgame.php` keep administrators
rather than deleting the account running them.

The crawl is confined to `admin/`. `extractLinks()` normalises every href to a
path from the docroot, and the admin navbar's links are relative — so without
the confinement the pass followed `href="main.php"` out into the game as a
client with no player session.
