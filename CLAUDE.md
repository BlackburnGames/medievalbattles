# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Medieval Battles v6 — a PHP/MySQL browser strategy game written in 2003 for PHP 4, released publicly for historical purposes (see README.md). It is fully procedural: 133 PHP files containing 15 function declarations between them as released (more now, from the Phase 3 helpers in `app/include/`), no classes, no framework, no Composer.

The work in progress is a staged modernization. Phase 1 (a running, test-covered baseline in Docker) and Phase 2 (the mechanical port, now **running on PHP 8.3** with the suite green on both 8.3 and 5.6) are done. Phase 3 is the structural and security rewrite. See [docs/modernization.md](docs/modernization.md).

**Do not expose this app to a network.** Nearly all of the ~1300 queries are still built by string interpolation — escaped where request input reaches them, but not bound; the pre-login pages are converted to `mb_query()` and the rest follow — and the admin area behind its gate can still delete the world from one link. Both container ports bind to 127.0.0.1 deliberately — keep it that way.

**Every session starts in `app/include/session.php`.** It sets the cookie rules (HttpOnly, SameSite=Strict, strict ids), refuses a session to any request another site made (`Sec-Fetch-Site`), expires sessions after 2 hours idle or 24 hours total, and ends a session whose email and hash no longer name a row — which is how a password change logs out the account's other sessions. Do not call `session_start()` anywhere else, and write the credential only through `mb_session_login()` / `mb_session_rotate()` / `mb_session_end()`. `tests/session.php` holds each rule to account.

## Documentation

The reference material lives in `docs/`. Read the relevant one before working in that area — each records decisions that look like bugs until you know why.

| Document | Read it before |
| --- | --- |
| [docs/architecture.md](docs/architecture.md) | Touching page flow, auth, request input, the tick or the schema |
| [docs/testing.md](docs/testing.md) | Changing the harness, or re-accepting any baseline |
| [docs/porting-notes.md](docs/porting-notes.md) | Touching data access, error handling or the Docker image |
| [docs/game-manual.md](docs/game-manual.md) | Changing any game value or engine formula |
| [docs/modernization.md](docs/modernization.md) | Deciding what to work on next |
| [docs/rulesets.md](docs/rulesets.md) | Changing gameplay, or moving a value into `gamedata.php` — design for running classic and modern rules side by side, not yet built |
| [docs/v7-design-notes.md](docs/v7-design-notes.md) | Reference only — it describes a **different, unbuilt game**. Never answer a question about game behaviour from it. |

Four things load-bearing enough to repeat here:

- **`mb_db_result()`'s column-0 fallback is deliberate**, not defensive. Resolving field names strictly returns null across signup, login and the guild code, and has already silently disabled the entire game tick once. Read the trap writeup in [docs/porting-notes.md](docs/porting-notes.md) before changing it.
- **`tests/known-issues.txt`, `tests/register-globals.txt`, `tests/broken-queries.txt`, `tests/sql-injection.txt`, `tests/sql-injection-stored.txt`, `tests/xss.txt` and `tests/xss-stored.txt` may only ever shrink.** They are ratchets, and the last six double as Phase 3 bug lists. Regenerate with `--accept` on the matching script (`--stored --accept` for the two stored lists). The four injection and output lists are all **empty**, and they stay that way only if new queries bind their values with `mb_query($db, $sql, $params)` (or, in a query not yet converted, go through `mb_sql_str()` / `mb_sql_int()`) and new output goes through `mb_h()` / `mb_attr()` — including values read back from the database. All four are per-file, so moving a query or an echo into an include away from its source clears the entry without fixing anything, and a value a shared include loads (`$empireguild`, `$ename`) is invisible to them: escape it anyway.
- **The credential is validated, not escaped, at `app/include/session.php`.** `$email` and `$pw` are interpolated bare into ~1300 `WHERE` clauses, so `app/include/credentials.php` constrains what they may be instead, at signup, at the preferences change and on the way back out of the session. Loosening that rule re-opens every one of those call sites. `mb_valid_pw_hash()` accepts bcrypt or the legacy MD5, and `app/include/password.php` pins `PASSWORD_BCRYPT` so that alphabet is a fact rather than whatever `PASSWORD_DEFAULT` becomes — a password change is six `UPDATE`s, and `mb_password_store()` is the only place that list may live.
- **`connect.php` reports query errors as warnings, not exceptions**, and the crawl and the tick both fail on one. Going further to `MYSQLI_REPORT_STRICT` needs the ~1300 call sites to check their return values first. `bash tests/query-audit.sh` regenerates the combined inventory.
- **`app/include/gamedata.php` is the only place a game value may be written**, and every value carries a `file:line` citation. Change a cited engine line and the citation must change with it.

## Commands

```bash
docker compose up -d --build     # start the stack on PHP 8.3; app on http://127.0.0.1:8080
docker compose logs -f web       # PHP errors always go to stderr, whatever display_errors says
bash tests/run-all.sh            # full suite (tick + combat golden masters, smoke crawl, audits)
bash tests/reset-db.sh           # restore the fixture world
```

PHP 5.6 is **no longer a per-change gate**. It was the Phase 2 port's proof
that behaviour did not move, and the shared golden master now does that on
8.3 alone. The image stays buildable for one question — was this bug there in
2003, or did the port introduce it? — and is to be frozen at a tagged commit
the first time Phase 3 needs syntax 5.6 cannot parse. Until then new code need
not avoid it, but say so in the commit when it does:

```bash
docker compose build --build-arg PHP_VERSION=5.6-apache web && docker compose up -d web
```

Individual tests:

```bash
bash tests/tick-golden.sh                 # tick regression test
bash tests/tick-golden.sh --accept        # re-accept after an intended change
bash tests/combat-golden.sh               # combat regression test (--accept likewise)
docker compose exec -T web php /repo/tests/combat-golden.php land-win   # one combat scenario
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

The admin area is `http://127.0.0.1:8080/admin/`. There is no admin password: an administrator is a player account with `user.admin` set, logged into the game as usual — the fixture's is `tester@`. Grant and revoke from the command line: `docker compose exec web php /repo/bin/admin.php list | grant <email> | revoke <email>`. See `app/admin/include/auth.php`.

**On Windows, prefix `docker compose exec` with `MSYS_NO_PATHCONV=1`** when passing container paths, or Git Bash rewrites `/repo/...` into a Windows path and the command fails. The scripts in `tests/` already set it.
