# Deployment

Where the game runs outside Docker, how code gets there, and what has to be
true before it can. **Deployed, and public, since 2026-10-18** at
https://jacobp259.sg-host.com/ — `deploy.sh --go --public`; the deployed
commit is in `mb/REVISION`. What was left of the first deploy is in "First
deploy, in order".

A request with no `User-Agent` gets a 403 from SiteGround before it reaches
PHP. Browsers always send one; `tests/lib/client.php` does not, so the
suite's client cannot be pointed at the live site — use curl with a browser's
agent. The first live walk (2026-10-18) signed up, verified, logged in to the
round list, joined, loaded seven game pages and deleted the account, with no
PHP diagnostics on any page.

## Blockers

These stand between the code as it is and a server that runs it. The first was
the one that could quietly break the game, and is resolved.

1. ~~**MySQL 8.4 in production, 5.7 under test.**~~ **Resolved.** The host
   runs MySQL 8.4.10, and so does `docker-compose.yml`, with the server's
   defaults left as the host's are: strict `sql_mode`,
   `utf8mb4_0900_ai_ci`. What 8.0 changed, and where each now stands:

   - **Default `sql_mode`.** The 2003 queries fail under
     `STRICT_TRANS_TABLES` (partial `INSERT`s) and `ONLY_FULL_GROUP_BY`
     (aggregates), and a shared host's global mode cannot be changed, so
     every connection sets `NO_ENGINE_SUBSTITUTION` for itself
     (`mb_db_session()`, `app/include/db.php`).
   - **New reserved words** (`RANK`, `GROUPS`, `SYSTEM`, `ROWS`, `OVER`,
     `LEAD`, `CUME_DIST`, ...). No column in `db.sql` uses one (checked
     2026-10-18), and no query the suite reaches broke on one. A page it
     cannot reach still could.
   - **MyISAM.** Every table is InnoDB now, so whether SiteGround allows
     MyISAM no longer matters.
   - **Removed features**: `utf8` now means `utf8mb3` and warns, the old
     `PASSWORD()`, implicit `GROUP BY` sorting, integer display widths, and
     the query cache. The suite found none in use.
   - **Collation**: every table names `utf8mb4_unicode_ci`, and every
     connection asks for it, so the server's `utf8mb4_0900_ai_ci` default
     never meets the schema's in a comparison.

   The full suite passes on 8.4 with every golden master untouched (see
   modernization.md).

2. ~~**Mail.**~~ **Resolved.** The tick mailed the original author on every
   run, and every mail the game sent claimed to be from medievalbattles.com
   and linked there — the activation link every new account needs included.
   All of it goes through `mb_mail()` (`app/include/mail.php`) now, from
   `MB_MAIL_FROM`, with links to `MB_SITE_URL`; both are set in the two
   config files. On the host mail delivers for real; locally
   `sendmail_path` is `/bin/true`. `MB_TICK_MAIL=0` in `~/.mb-env` silences
   the tick's.

3. **Exposure.** CLAUDE.md: do not expose this app to a network. Until that
   decision is made the whole site sits behind Site Tools > Security >
   Protected URLs (HTTP basic auth on `/`), and `deploy.sh` refuses to ship
   to a site that does not answer 401.

4. ~~**Error display.**~~ **Resolved.** The repo's `app/.htaccess` turns
   `display_errors` on and is never shipped; the probe found `php_flag`
   honoured on the host, and the `.htaccess` template turns it off.

## The host

SiteGround shared hosting, site `jacobp259.sg-host.com`.

| | |
| --- | --- |
| SSH | `ssh siteground-mb` (`~/.ssh/config`; key `~/.ssh/siteground-medievalbattles`) — host `ssh.jacobp259.sg-host.com`, port 18765 |
| PHP (web) | 8.3, set in Site Tools > Devs > PHP Manager (which writes `public_html/.htaccess`) |
| PHP (cli) | `/usr/local/php83/bin/php-cli`. Not `.../bin/php`, which is the CGI binary and refuses `-r`. Bare `php` is 8.3 too (checked 2026-10-18) |
| MySQL | 8.4.10. Databases and users are made in Site Tools > Site > MySQL, which prefixes their names |
| Cron | No `crontab` command; Site Tools > Devs > Cron Jobs |
| Tools | `git`, `rsync`, `composer`, `mysql`, `mysqldump` |

## Layout

```
~/www/jacobp259.sg-host.com/
  public_html/            app/ — the docroot
    .htaccess             server-owned: PHP version, database settings
  mb/                     update.php, bin/, db/, db.sql, tick.sh, REVISION
    app -> ../public_html
~/.mb-env                 server-owned, chmod 600: the tick's database settings
~/logs/tick.log           every tick's output
```

The docroot is `app/`, as it is locally. `update.php` and `bin/` need the repo
root as their parent of `app/`, which the `mb/app` symlink gives them — only
PHP's CLI follows it, so Apache's symlink rules never come into it.

## Configuration

The app reads `MB_DB_HOST`, `MB_DB_NAME`, `MB_DB_USER`,
`MB_DB_PASS` and `MB_REPORT_QUERIES` through `getenv()`. Locally Docker sets
them. On the host there are two copies, because the web server and cron do not
share an environment:

- **Web:** `SetEnv` in `public_html/.htaccess` — template
  `deploy/htaccess.example`. That template also 404s `/include/` and
  `/admin/include/`, which are only ever `require`d.
- **Tick:** `~/.mb-env` — template `deploy/mb-env.example`.

Neither is in the repo or the deploy, and `deploy.sh` will not overwrite or
delete the `.htaccess`. A config file read by `connect.php` from outside the
docroot would make it one copy; that is a code change and has not been made.

## Scripts

All in `deploy/`, run from the repo root in Git Bash.

- **`probe.sh`** — asks the host what the rest depends on: PHP version and
  SAPI, whether `SetEnv` reaches `getenv()`, whether `php_flag` is honoured,
  the ini values the app needs (`short_open_tag` above all — about 70 files
  open with `<?`), and, once `~/.mb-env` exists, the MySQL version, its
  default `sql_mode` and whether the database exists. It writes one
  randomly named directory into `public_html` and deletes it on exit.
  `MB_PROBE_AUTH=user:pass` once the site is protected.

- **`deploy.sh`** — dry run by default; `--go` to deploy; a ref to deploy
  something other than `HEAD`, which is also how to roll back. It ships
  `git archive <ref>`, never the working tree, so uncommitted work cannot
  reach the server. It refuses unless the site answers 401 and the server's
  `.htaccess` has database settings. The archive is unpacked into
  `~/.mb-deploy/<sha>` and moved into place with the server's `rsync
  --delete`, protecting `.htaccess`, `.well-known/`, `php.ini` and
  `.user.ini`. The deployed commit is written to `mb/REVISION`.

- **`tick.sh`** — what cron runs. Loads `~/.mb-env`, runs `update.php` from
  `mb/` with the 8.3 CLI and the ini settings the code relies on
  (`short_open_tag`, `output_buffering`, `date.timezone`, mail off unless
  `MB_TICK_MAIL=1`), refuses to overlap a tick still running (`flock`), and
  appends everything to `~/logs/tick.log`.

## First deploy, in order

1. ~~Blocker 1: the suite green on MySQL 8.4.~~ Done.
2. ~~Site Tools > Security > Protected URLs.~~ Not used: the game went out
   public (2026-10-18), so every deploy passes `--public`. `deploy.sh`
   refuses a site that does not answer 401 without it.
3. ~~Site Tools > Site > MySQL: one database and one user with all
   privileges on it.~~ Done 2026-10-18, both labelled `medievalbattles`
   (Site Tools generates their names; they go in `MB_DB_NAME` and
   `MB_DB_USER`).
4. ~~Install `public_html/.htaccess` and `~/.mb-env` from the templates,~~ Done 2026-10-18 (the PHP Manager's original is `~/htaccess.before-mb`), with
   the database's names and password, and `MB_SITE_URL` / `MB_MAIL_FROM`.
   The `.htaccess` replaces the one PHP Manager wrote; the template carries
   the same `AddHandler` line.
5. ~~`bash deploy/probe.sh`~~ Web and CLI answered 2026-10-18: PHP 8.3.35,
   `SetEnv` reaches `getenv()`, `php_flag`/`php_value` are honoured,
   `short_open_tag` on, mysqli and pdo_mysql loaded. The CLI is
   `/usr/local/php83/bin/php-cli` — the scripts had `.../bin/php`, the CGI
   binary, which would have failed every migration and tick. Run it again
   once `~/.mb-env` exists, for the MySQL half.
6. ~~Load the schema by hand.~~ Nothing to do: the deploy runs the
   migrations, and on an empty database they build the schema — the
   baseline, then rounds, round results and login failures. The world they
   build is round 1, running, with ten settlements and no kingdoms.
7. ~~`bash deploy/deploy.sh`~~ Done: all four migrations ran.
8. ~~Run a tick by hand.~~ Done. The first showed two warnings no fixture
   tick could — a world with no guilds — both fixed and redeployed.
9. Site Tools > Devs > Cron Jobs: the same command, every 30 minutes
   (`*/30 * * * *`). The game's clocks are counted in ticks at that rate:
   inactivity deletion is 336 ticks, which the mail calls a week, and safe
   mode is 48, a day.
10. The first administrator: sign up on the site, then from the repo
    root —

    ```bash
    ssh siteground-mb 'cd ~/www/jacobp259.sg-host.com/mb && set -a && . ~/.mb-env && set +a \
      && /usr/local/php83/bin/php-cli bin/admin.php verify YOU@EXAMPLE.COM \
      && /usr/local/php83/bin/php-cli bin/admin.php grant YOU@EXAMPLE.COM'
    ```

    `verify` only if the activation mail has not arrived. Rounds are then
    run from `admin/rounds.php` or `bin/round.php` the same way.
