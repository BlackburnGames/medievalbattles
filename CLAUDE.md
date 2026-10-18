# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Medieval Battles v6 — a PHP/MySQL browser strategy game written in 2003 for PHP 4, released publicly for historical purposes (see README.md). It is fully procedural: 133 PHP files containing 15 function declarations between them as released (more now, from the Phase 3 helpers in `app/include/`), no classes, no framework. Composer brings in tooling only — Phinx, for the schema migrations — and nothing in `app/` loads it.

The work in progress is a staged modernization. Phase 1 (a running, test-covered baseline in Docker) and Phase 2 (the mechanical port, now **running on PHP 8.3** with the suite green on both 8.3 and 5.6) are done. Phase 3 is the structural and security rewrite. See [docs/modernization.md](docs/modernization.md).

**Do not expose this app to a network.** Every query takes its values through `mb_query()`, output is escaped, and every admin action is a POST (a link does nothing) — and logins are throttled — but nobody has yet reviewed the whole for exposure. Both container ports bind to 127.0.0.1 deliberately — keep it that way.

**Every session starts in `app/include/session.php`.** It sets the cookie rules (HttpOnly, SameSite=Strict, strict ids), refuses a session to any request another site made (`Sec-Fetch-Site`), expires sessions after 2 hours idle or 24 hours total, and ends a session whose account is gone or has a different address or password hash than it logged in against, or whose kingdom is gone or not its account's — which is how a password or address change logs out the account's other sessions. Do not call `session_start()` anywhere else, and write the credential only through `mb_session_login()` / `mb_session_rotate()` / `mb_session_end()`. `tests/session.php` holds each rule to account.

## Documentation

The reference material lives in `docs/`. Read the relevant one before working in that area — each records decisions that look like bugs until you know why.

| Document | Read it before |
| --- | --- |
| [docs/architecture.md](docs/architecture.md) | Touching page flow, auth, request input, the tick or the schema |
| [docs/testing.md](docs/testing.md) | Changing the harness, or re-accepting any baseline |
| [docs/porting-notes.md](docs/porting-notes.md) | Touching data access, error handling or the Docker image |
| [docs/game-manual.md](docs/game-manual.md) | Changing any game value or engine formula |
| [docs/modernization.md](docs/modernization.md) | Deciding what to work on next |
| [docs/deployment.md](docs/deployment.md) | Touching `deploy/`, the host, or the host — production and the local stack are both MySQL 8.4 |
| [docs/rulesets.md](docs/rulesets.md) | Changing gameplay, or moving a value into `gamedata.php` — design for running classic and modern rules side by side, not yet built |
| [docs/rounds.md](docs/rounds.md) | Touching accounts, signup, login, joining or ending a round — built, one round in play at a time |
| [docs/v7-design-notes.md](docs/v7-design-notes.md) | Reference only — it describes a **different, unbuilt game**. Never answer a question about game behaviour from it. |

The things load-bearing enough to repeat here:

- **`mb_db_result()`'s column-0 fallback is deliberate**, not defensive. Resolving field names strictly returns null across signup, login and the guild code, and has already silently disabled the entire game tick once. Read the trap writeup in [docs/porting-notes.md](docs/porting-notes.md) before changing it.
- **`tests/known-issues.txt`, `tests/register-globals.txt`, `tests/broken-queries.txt`, `tests/sql-injection.txt`, `tests/sql-injection-stored.txt`, `tests/xss.txt`, `tests/xss-stored.txt` and `tests/round-scope.txt` may only ever shrink.** They are ratchets, and the last six double as Phase 3 bug lists. Regenerate with `--accept` on the matching script (`--stored --accept` for the two stored lists). The four injection and output lists are all **empty**, and they stay that way only if new queries bind their values with `mb_query($db, $sql, $params)` — `tests/unbound-queries.txt` is empty too, and fails on any query that builds its SQL out of values — and new output goes through `mb_h()` / `mb_attr()` — including values read back from the database. All four are per-file, so moving a query or an echo into an include away from its source clears the entry without fixing anything, and a value a shared include loads (`$empireguild`, `$ename`) is invisible to them: escape it anyway.
- **The kingdom is `$userid`, and the round holds no credential.** `app/include/session.php` sets `$userid` (a string, as `functions.php` always produced it — the guild-leader check compares it with `===`) and nearly every query filters `WHERE userid=?`. The address and the password hash live once, on the account, in the `account` table beside the round's (`app/include/accounts.php`); a kingdom belongs to its account by `user.account_id`. `$email` is the account's address, for showing and mailing only — never filter on it. `$pw` no longer exists. A password change is one `UPDATE` through `mb_password_store()`, an address change one through `mb_account_set_email()`, and each must call `mb_session_rotate()` or the session that made it is logged out with the others. See [docs/rounds.md](docs/rounds.md).
- **`connect.php` reports query errors as warnings, not exceptions**, and the crawl and the tick both fail on one. Going further to `MYSQLI_REPORT_STRICT` needs the ~1300 call sites to check their return values first. `bash tests/query-audit.sh` regenerates the combined inventory.
- **`app/include/gamedata.php` is the only place a game value may be written**, and every value carries a `file:line` citation. Change a cited engine line and the citation must change with it.
- **Every query on a round's tables is confined to one round.** Each carries a `round_id`; filter on it — `$round_id`, the session's kingdom's round, on a game page; `mb_round_current()` / `mb_round_running()` elsewhere — or on an id issued once across rounds (userid, setid, gid, …). A name is not such an id. An `INSERT` takes its round from its owner's row. `tests/round-audit.php` fails otherwise, and `tests/round-scope.txt` is empty. See [docs/rounds.md](docs/rounds.md).
- **One database, one connection.** The world and the accounts share `MB_DB_NAME`; `mb_db()` (`app/include/db.php`) is the connection, and `connect.php`'s `$db` is it.
- **A schema change is two edits: a Phinx migration and the same change to `db.sql`.** The fixture is built from `db.sql` and never migrated; a real database is built by `bin/migrate.php` from the baseline up. `tests/migrations.sh` fails until the two agree. Never edit the baseline (`db/migrations/20261018000000_baseline.*`) once a real database has run it — it is history. Migrations run before the new code on deploy, so one must leave the previous commit's code working.

## Commands

```bash
docker compose up -d --build     # start the stack on PHP 8.3; app on http://127.0.0.1:8080
docker compose logs -f web       # PHP errors always go to stderr, whatever display_errors says
bash tests/run-all.sh            # full suite (tick + combat golden masters, smoke crawl, audits)
bash tests/reset-db.sh           # restore the fixture world (mbv6, accounts included)
docker compose exec web composer install -d /repo        # install vendor/ (Phinx) into the mounted repo
docker compose exec web php /repo/bin/migrate.php status # migrations (also: migrate, create <Name>)
bash tests/migrations.sh         # db.sql and the migrations build the same database
```

The database volume is `dbdata84`; the 5.7 stack's `dbdata` cannot be opened by 8.4 and is no longer used. MySQL runs its init scripts only on an empty volume, so after a schema change run `bash tests/reset-db.sh`. A world from before accounts existed is moved over with `docker compose exec web php /repo/bin/accounts-migrate.php`, which keeps its players.

PHP 5.6 is **no longer a per-change gate**. It was the Phase 2 port's proof
that behaviour did not move, and the shared golden master now does that on
8.3 alone. It is **frozen at the tag `php5.6-freeze`** (`03b9158`), the last
commit that runs on 5.6 — the full suite passes there on 5.6.40 — because the
next one calls `random_bytes()`. `main` no longer runs on 5.6 and new code need
not try. The tag stays buildable for one question — was this bug there in
2003, or did the port introduce it? Check it out in a separate worktree and:

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

The admin area is `http://127.0.0.1:8080/admin/`. There is no admin password: an administrator is an account with `account.admin` set, logged into the game as usual — the fixture's is `tester@`. Grant and revoke from the command line: `docker compose exec web php /repo/bin/admin.php list | grant <email> | revoke <email>`. A new signup is unverified and held at the activation gate; local mail goes nowhere, so confirm it with `bin/admin.php verify <email>`. See `app/admin/include/auth.php`.

Rounds are opened, started and ended from the command line — `docker compose exec web php /repo/bin/round.php list | open <name> | start | end` — or from `admin/rounds.php`. One round is in play at a time; `end` writes `round_result` and logs that round's players out. Signup makes an account only; an account with no kingdom in the current round logs in to `rounds.php`, joins with `join.php`, and reads its history there. The fixture's world is round 1, running. See [docs/rounds.md](docs/rounds.md).

**On Windows, prefix `docker compose exec` with `MSYS_NO_PATHCONV=1`** when passing container paths, or Git Bash rewrites `/repo/...` into a Windows path and the command fails. The scripts in `tests/` already set it.
