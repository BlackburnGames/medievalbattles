# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Medieval Battles v6 — a PHP/MySQL browser strategy game written in 2003 for PHP 4, released publicly for historical purposes (see README.md). It is fully procedural: 133 PHP files containing 15 function declarations between them, no classes, no framework, no Composer.

The work in progress is a staged modernization. Phase 1 (a running, test-covered baseline in Docker) and Phase 2 (the mechanical port, now **running on PHP 8.3** with the suite green on both 8.3 and 5.6) are done. Phase 3 is the structural and security rewrite. See [docs/modernization.md](docs/modernization.md).

**Do not expose this app to a network.** All ~1300 queries are built by string interpolation with no escaping, passwords are unsalted MD5, and output is unescaped. Both container ports bind to 127.0.0.1 deliberately — keep it that way.

## Documentation

The reference material lives in `docs/`. Read the relevant one before working in that area — each records decisions that look like bugs until you know why.

| Document | Read it before |
| --- | --- |
| [docs/architecture.md](docs/architecture.md) | Touching page flow, auth, request input, the tick or the schema |
| [docs/testing.md](docs/testing.md) | Changing the harness, or re-accepting any baseline |
| [docs/porting-notes.md](docs/porting-notes.md) | Touching data access, error handling or the Docker image |
| [docs/game-manual.md](docs/game-manual.md) | Changing any game value or engine formula |
| [docs/modernization.md](docs/modernization.md) | Deciding what to work on next |
| [docs/v7-design-notes.md](docs/v7-design-notes.md) | Reference only — it describes a **different, unbuilt game**. Never answer a question about game behaviour from it. |

Four things load-bearing enough to repeat here:

- **`mb_db_result()`'s column-0 fallback is deliberate**, not defensive. Resolving field names strictly returns null across signup, login and the guild code, and has already silently disabled the entire game tick once. Read the trap writeup in [docs/porting-notes.md](docs/porting-notes.md) before changing it.
- **`tests/known-issues.txt`, `tests/register-globals.txt`, `tests/broken-queries.txt`, `tests/sql-injection.txt`, `tests/xss.txt` and `tests/xss-stored.txt` may only ever shrink.** They are ratchets, and the last five double as Phase 3 bug lists. Regenerate with `--accept` on the matching script. `sql-injection.txt`, `xss.txt` and `xss-stored.txt` are all **empty**, and they stay that way only if new queries go through `mb_sql_str()` / `mb_sql_int()` and new output goes through `mb_h()` / `mb_attr()`. The first two are per-file and first-order, so moving a query or an echo into an include away from its `mb_input()` clears the entry without fixing anything, and neither sees a value that went through the database on the way — `php tests/xss-audit.php --stored` is the list that does.
- **The credential is validated, not escaped, at `app/include/session.php`.** `$email` and `$pw` are interpolated bare into ~1300 `WHERE` clauses, so `app/include/credentials.php` constrains what they may be instead, at signup, at the preferences change and on the way back out of the session. Loosening that rule re-opens every one of those call sites.
- **`connect.php` reports query errors as warnings, not exceptions**, and the crawl and the tick both fail on one. Going further to `MYSQLI_REPORT_STRICT` needs the ~1300 call sites to check their return values first. `bash tests/query-audit.sh` regenerates the combined inventory.
- **`app/include/gamedata.php` is the only place a game value may be written**, and every value carries a `file:line` citation. Change a cited engine line and the citation must change with it.

## Commands

```bash
docker compose up -d --build     # start the stack on PHP 8.3; app on http://127.0.0.1:8080
docker compose logs -f web       # PHP errors always go to stderr, whatever display_errors says
bash tests/run-all.sh            # full suite (tick golden master + smoke crawl + audit)
bash tests/reset-db.sh           # restore the fixture world
```

To rebuild on the 5.6 reference image — worth doing when a change might have
altered behaviour, since both versions share one golden master:

```bash
docker compose build --build-arg PHP_VERSION=5.6-apache web && docker compose up -d web
```

Individual tests:

```bash
bash tests/tick-golden.sh                 # tick regression test
bash tests/tick-golden.sh --accept        # re-accept after an intended change
docker compose exec -T web php /repo/tests/smoke.php        # page crawl only
docker compose exec -T web php /repo/tests/news-render.php  # news writers + renderer
docker compose exec -T web php /repo/tests/dump-state.php   # dump DB as text
MB_WRITE_BASELINE=1 docker compose exec -T web php /repo/tests/smoke.php  # regen baseline
```

Run the tick manually (it must have the repo root as CWD, unlike every page):

```bash
docker compose exec -w /repo web php update.php
```

Fixture logins are `tester@example.com` / `test1234` (plus `idle@` / `pass2`, `poor@` / `pass3`).

The admin area is `http://127.0.0.1:8080/admin/`, logging in with `MB_ADMIN_USER` / `MB_ADMIN_PASSWORD` — `mako` / `quickshot` unless the environment overrides them. The defaults are the 2003 hardcoded pair and are public; they exist so nothing that worked before the gate stops working.

**On Windows, prefix `docker compose exec` with `MSYS_NO_PATHCONV=1`** when passing container paths, or Git Bash rewrites `/repo/...` into a Windows path and the command fails. The scripts in `tests/` already set it.
