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

Logs in and crawls every reachable page, failing on any PHP fatal, parse error
or warning in the response body. Notices are counted but not failed — this is
PHP 4 code that reads uninitialised variables everywhere, and failing on them
would mean a permanently red suite.

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

`smoke.php` prints the unreached script list on every run. The forum pages
(`gl-*`, `sl-*`, `topic*`) need seeded threads and the runtime per-settlement
tables before they are reachable; the rest are includes or auth-flow pages
reached only by POSTing credentials.
