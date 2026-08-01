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

Treat re-accepting a golden as a review step: read the diff and confirm every
changed number was intended. Re-accept with `--accept`.

### `tests/smoke.php`

Crawls every reachable page, failing on any PHP fatal, parse error or warning
in the response body. Notices are counted but not failed — this is PHP 4 code
that reads uninitialised variables everywhere, and failing on them would mean a
permanently red suite.

Two phases, each with its own cookie jar and its own page budget:

1. **Logged out**, from `index.php`. This is the login page plus the four
   fragments it `include`s and echoes as `$data` (`main-site`, `about_us`,
   `game_scores`, `signup`). They are credited to their own filenames rather
   than to `index.php`, because a parse error in one still breaks the request.
2. **Logged in**, from `main.php?pageid=news`.

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
first appearance in global scope is a read. That was the checklist for removing
the `register_globals` shim, and entries remain in
`tests/register-globals.txt` — all of them now plain uninitialised reads rather
than request inputs, several of them real bugs:

- `app/gl-delposts.php` passes `$setgid` to `mb_db_result()` where it means
  `$guild_id`.
- `app/include/S_WEP.php` echoes `$shortsword_button` and 23 siblings that are
  never assigned, so the weapon buy buttons render as nothing.

**The list should only ever shrink**, and it doubles as a Phase 3 bug list.
Re-accept with `--accept`.

### `tests/query-audit.sh`

Not a test — an inventory generator, and the only thing that runs the stack
with `MB_REPORT_QUERIES=1`.

`connect.php` normally sets `MYSQLI_REPORT_OFF`, which is correct (see
[porting-notes.md](porting-notes.md)) and is also a blindfold: the app fires
~1300 unchecked queries and swallows every failure, so nobody knows which have
been broken or since when. This script selects `MYSQLI_REPORT_ERROR` *without*
`MYSQLI_REPORT_STRICT` for one run — failures become warnings rather than
exceptions, so each names itself and its line and the request still completes —
and writes what it finds to `tests/broken-queries.txt`.

Two passes, because neither covers the other: the smoke crawl in audit mode
(`MB_QUERY_AUDIT=1`, which collects the mysqli warnings instead of failing on
them), and `update.php`, which the crawl never touches and which is the densest
query surface in the codebase.

It is not in `run-all.sh` — it restarts the container twice, and with reporting
on the crawl is red by construction. The output is a floor on the number of
broken queries, not a total, and **should only ever shrink**.

The first run of it found that research has never worked and that troops sent
out are lost permanently; see `$GAMEDATA['quirks']` and
[modernization.md](modernization.md).

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

The crawl reaches **50 of the 69 scripts in `app/`**, and the other 19 are
listed in `$UNREACHABLE` in `smoke.php` with a reason each. That list is the
third ratchet: an `app/` script that is neither reached nor explained **fails
the run**, so a page falling out of the net is a regression rather than a
silently smaller number. Entries that become reachable are reported so they can
be deleted.

The 19 fall into five groups:

- **Includes** (3) — `common.php`, `commong.php`, `functions.php`. Not pages;
  this group is structural and will not shrink.
- **POST handlers** (7) — form targets. Covering them means driving the forms,
  not seeding more data.
- **Destructive, deliberately skipped** (4) — see `$SKIP`. The fixture is reset
  per run so the mutation is harmless, but letting the crawler delete forum
  posts makes every later page depend on crawl order.
- **Dead in the shipped game** (3) — `guildbarter.php` (`barter.php` dies at
  line 17; the barter system was switched off in 2003), `scoreboard.php`
  (orphaned — nothing links to it and `index.php` has its Scores link
  commented out), and `gforums.php` (the non-leader alternate to `gl-forum.php`,
  which the crawl user no longer sees because they lead the test guild).
- **Auth flow** (2) — the activation pages, which need a live emailed code.

`gforums.php` is the only one of the 19 that a second crawl identity would
close.
