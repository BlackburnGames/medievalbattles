# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Medieval Battles v6 — a PHP/MySQL browser strategy game written in 2003 for PHP 4, released publicly for historical purposes (see README.md). It is fully procedural: 133 PHP files containing 15 function declarations between them, no classes, no framework, no Composer.

The work in progress is a staged modernization. Phase 1 (a running, test-covered baseline on PHP 5.6 in Docker) is done. Phase 2 is the mechanical port to PHP 8. Phase 3 is the structural and security rewrite.

**Do not expose this app to a network.** All ~1300 queries are built by string interpolation with no escaping, passwords are unsalted MD5, and output is unescaped. Both container ports bind to 127.0.0.1 deliberately — keep it that way.

## Commands

```bash
docker compose up -d --build     # start the stack; app on http://127.0.0.1:8080
docker compose logs -f web       # PHP errors also go to stderr
bash tests/run-all.sh            # full suite (tick golden master + smoke crawl)
bash tests/reset-db.sh           # restore the fixture world
```

Individual tests:

```bash
bash tests/tick-golden.sh                 # tick regression test
bash tests/tick-golden.sh --accept        # re-accept after an intended change
docker compose exec -T web php /repo/tests/smoke.php        # page crawl only
docker compose exec -T web php /repo/tests/dump-state.php   # dump DB as text
MB_WRITE_BASELINE=1 docker compose exec -T web php /repo/tests/smoke.php  # regen baseline
```

Run the tick manually (it must have the repo root as CWD, unlike every page):

```bash
docker compose exec -w /repo web php update.php
```

Fixture logins are `tester@example.com` / `test1234` (plus `idle@` / `pass2`, `poor@` / `pass3`).

**On Windows, prefix `docker compose exec` with `MSYS_NO_PATHCONV=1`** when passing container paths, or Git Bash rewrites `/repo/...` into a Windows path and the command fails. The scripts in `tests/` already set it.

## Testing strategy

Characterization tests, not unit tests — there is almost nothing unit-testable, and the failure mode of this port is *silent behaviour change*, which golden masters catch and unit tests would not.

- **`tests/tick-golden.sh`** pins `update.php`, the best test target in the codebase: pure database-in/database-out, no HTML, session or auth, and the densest game logic. It resets the database, runs a tick, dumps all tables via `tests/dump-state.php`, and diffs against `tests/golden/tick.txt`. Treat re-accepting a golden as a review step: read the diff and confirm every changed number was intended.
- **`tests/smoke.php`** logs in and crawls every reachable page, failing on any PHP fatal, parse error, or warning in the response body. Notices are counted but not failed — this is PHP 4 code that reads uninitialised variables everywhere, and failing on them would mean a permanently red suite.
- **`tests/known-issues.txt`** baselines warnings that predate the port, keyed by source location. New warnings fail; entries that stop firing are reported so they can be deleted. **The list should only ever shrink.**

Constraints worth knowing before changing the harness:

- MyISAM has no transactions, so there is no rollback isolation — `reset-db.sh` drops and reloads the whole database. It must drop *all* tables, not just the 22 in `db.sql`, because the app creates per-settlement tables (`setmain<id>`, `setmsgs<id>`) at runtime.
- Fixture user ids are deliberately 1–3: the tick loops from 0 to `max(userid)` rather than over existing rows, so high ids make every run crawl.
- Do not seed users by driving the signup form. Signup derives starting resources from `rand()`, and PHP 7.1 rewired `rand()` to `mt_rand` and fixed its modulo bias, so the same seed yields different numbers on 5.6 than on 8.x and cross-version goldens would never match.
- Diagnostics must be matched against `strip_tags()`ed output. With `html_errors` on, PHP emits `<b>Notice</b>:`, and a pattern expecting `Notice:` silently matches nothing — that produced a false PASS during development.
- Known coverage gap: the crawl reaches 38 of 68 top-level scripts. The forum pages (`gl-*`, `sl-*`, `topic*`) need seeded threads and the runtime per-settlement tables before they are reachable; the rest are includes or auth-flow pages. `smoke.php` prints the unreached list on every run.

## The `mysql_result` two-argument trap

The single most important thing to know before touching data access here, and the cause of two separate silent breakages already found.

`mysql_result($result, $row, $field)` takes the **row** second. The 2003 code almost always called the two-argument form with a *name*:

```php
$max_UID = mysql_result($max_userid, "max_UID");   // "max_UID" is the ROW
```

PHP cast `"max_UID"` to int `0`, so this returned row 0, column 0 — and worked. Most of those names are fiction: the query behind it is `SELECT max(userid) FROM user`, whose column is actually named `max(userid)`. Queries behind `noplayers`, `least_set`, `R_Mem`, `sel_mem` and `mgid` have no such column either.

The abandoned PHP 7 port "fixed" these by resolving the name, which returns null and silently breaks the caller. Both instances are now repaired:

- `mysqli_field_seek(...)` (which returns a bool, not a value) was substituted for `mysql_result` in the converted files. Now `mb_db_result()`, defined in the compat layer.
- `update.php` was rewritten to the 3-arg form asking for non-existent columns, which disabled **the entire game tick** — the user loop exited immediately. Now `mysql_result($r, 0, 0)`.

When porting a call site: reproduce *column 0 of row 0*, not the name, unless you have confirmed the query has that alias.

## Architecture

- `app/` is the web root; **all includes are CWD-relative**, so pages only work served with `app/` as the working directory. `update.php` sits at the repo root and includes `app/include/...`, so it needs the root as CWD instead.
- **Page flow**: `index.php` (login) → `checklogin.php` → `main.php?pageid=...`. Game pages include `include/igtop.php` (session check and header, which also pulls in `functions.php`) and `include/ignavbar.php`. Screens live in `app/include/S_*.php`.
- **`functions.php` declares no functions.** It is a procedural include that loads the logged-in user's entire state into globals (`$gp`, `$civ`, `$land`, `$exp`, `$safemode`, …) which the page templates then echo.
- **Auth**: `$_SESSION['email']` plus `$_SESSION['pw']` (the MD5 hash) act as a bearer credential, and nearly every query filters `WHERE email='$email' AND pw='$pw'`. The email/hash pair is **denormalized into `buildings`, `military`, `research` and `explore`**, so changing the auth model means touching the schema and almost every query.
- **Game tick**: `update.php` sets `game_info.tick='yes'` (every page then renders "Tick in progress" and dies), loops userids applying economy growth with hardcoded modifiers, mails inactive players, recomputes guild and settlement strength, and resets `tick='no'` at line 492. It is HTTP-invokable with no authentication.
- **Schema** (`db.sql`): 22 MyISAM tables, almost no indexes, no charset declared, types nearly all `bigint(255)`/`varchar(255)`. It is not schema-only — it seeds the `game_info` row and ten empty settlements. `db/seed.sql` adds the deterministic test world on top.

## The compatibility layer

`docker/php/compat.php` is loaded via `auto_prepend_file` so the 2003 sources can stay untouched. It supplies `mysql_db_query()`, the `session_register()` family, `ereg_replace()`, `mb_db_result()`, `mb_client_hostname()`, and a `register_globals` emulation gated behind `MB_REGISTER_GLOBALS=1`.

**Treat it as a debt checklist, not infrastructure.** Each section has a stated exit condition, and Phase 2 is complete when the file is empty. The `register_globals` section in particular *is* the vulnerability the setting was removed for, and is the main reason the stack is localhost-only; roughly 35 files read bare `$pageid` / `$gid` / `$umessage` and every form is inert without it.

It deliberately does not paper over unquoted array keys (`$row[email]`, ~1221 of them, fatal from PHP 8) — those need real source fixes.

## Porting notes

- PHP 5.6 is the baseline because the ~89 files still on `mysql_*` are fatal from PHP 7.0. The images are archived and their Debian jessie apt repos are dead, so the Dockerfile installs nothing via apt.
- `output_buffering` is on because several logic-only includes emit stray whitespace after their closing tag, which breaks `header()` calls. It masks the underlying problem; those files still need cleaning.
- `sendmail_path=/bin/true` — `update.php` mails a hardcoded Gmail address every tick. Never let local runs deliver mail.
- A literal `?>` inside a `//` comment terminates PHP mode and dumps the rest of the file to the page. Avoid writing it in comments.
- Fixed in Phase 1 and worth not regressing: the `gethostname()` redeclarations in `common.php`/`commong.php` (a built-in since 5.3, so an instant fatal), the one-argument `mysqli_query()` calls in `index.php`, and the unfiltered `include($_GET['page'] . ".php")` in `index.php`.
