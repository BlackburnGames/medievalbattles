# Testing strategy

Characterization tests, not unit tests — there is almost nothing unit-testable,
and the failure mode of this port is *silent behaviour change*, which golden
masters catch and unit tests would not.

## The suite

`bash tests/run-all.sh` runs the tick golden master, the smoke crawl and the
`register_globals` audit.

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

### `tests/sql-injection-audit.php`

Static, like the one above. It marks every variable derived from `mb_input()`
or a request superglobal, spreads the taint through assignment to a fixpoint,
and reports what survives into the second argument of `mysqli_query()` — the
codebase's only query entry point. Baselined in `tests/sql-injection.txt`.

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
too.

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

One value is deliberately still echoed as markup. The forum post body is stored
as markup, because the forum has always allowed `<i>` and `<b>`; it is closed
by `mb_post_html()` at the point it is stored instead. See
[modernization.md](modernization.md).

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

- **MyISAM has no transactions**, so there is no rollback isolation —
  `reset-db.sh` drops and reloads the whole database. It must drop *all*
  tables, not just the 22 in `db.sql`, because the app creates per-settlement
  tables (`setmain<id>`, `setmsgs<id>`) at runtime.
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
- **Auth flow** (2) — the activation pages, which need a live emailed code.

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

**Destructive requests run before the crawl, not after.** `govt.php:20` clears
`sl` for every member of a settlement on each visit and then re-elects whoever
holds the most votes; nobody in the fixture votes, so the crawler merely
opening that page deposes the tester. Anything afterwards that needs settlement
leadership is refused, silently, because a refusal renders a message rather
than a warning.

The same reasoning covers the barter boards. Listing is a form, and buying and
cancelling are GET links that consume the row they point at, so `$SKIP` keeps
the crawler off them and all three are driven by hand — before the crawl,
because buying needs gold and iron and the crawl spends both on buildings. The
seeded listings are numbered 1–3 so the URLs are stable: 1 and 2 belong to
other empires and are the buy path (2's seller is a guild-mate, because
`guildbarter.php` refuses anyone else), 3 is the tester's own and is the cancel
path.

The email change in `preferences.php` is driven on the *member*, not the
primary tester: it rewrites six tables and the session, and every fixture the
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

Two passes, and the first is the one that matters. Before logging in, every
entry point in `$ADMIN_PAGES` is fetched twice: once by a client with no
session and once by a **logged-in player**. Both must be refused. Without that
second arm a gate keyed on the wrong session slot would pass. The list is
checked against `glob(app/admin/*.php)` minus the login pair, so an admin page
added and left ungated fails the run.

The crawl is confined to `admin/`. `extractLinks()` normalises every href to a
path from the docroot, and the admin navbar's links are relative — so without
the confinement the pass followed `href="main.php"` out into the game as a
client with no player session.
