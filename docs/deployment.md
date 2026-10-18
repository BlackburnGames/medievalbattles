# Deployment

Where the game runs outside Docker, how code gets there, and what has to be
true before it can. **Nothing is deployed yet.** The host is provisioned and
the scripts exist; the blockers below come first.

## Blockers

These stand between the code as it is and a server that runs it. The first is
the one that can quietly break the game.

1. **MySQL 8.4 in production, 5.7 under test — critical.** The host runs
   MySQL 8.4.10. `docker-compose.yml` runs `mysql:5.7`, so every golden
   master, the crawl and every audit have only ever proved the code against a
   server it will not run on. 8.0 changed enough to matter here:

   - **Default `sql_mode`.** Locally the server is started with
     `--sql-mode=NO_ENGINE_SUBSTITUTION` because the 2003 queries fail under
     `STRICT_TRANS_TABLES` (partial `INSERT`s) and `ONLY_FULL_GROUP_BY`
     (aggregates). On shared hosting the global mode cannot be changed, so
     the connection has to set it — `SET SESSION sql_mode = ...` in
     `connect.php` and `accounts.php`, right after `mysqli_set_charset()`.
   - **New reserved words** (`RANK`, `GROUPS`, `SYSTEM`, `ROWS`, `OVER`,
     `LEAD`, `CUME_DIST`, ...). No column in `db.sql` uses one (checked
     2026-10-18), but an alias or table name in a query would break unquoted.
     `check` has already bitten once on 5.7 (activation; see
     modernization.md).
   - **MyISAM.** Every table in `db.sql` is `ENGINE=MyISAM`. 8.4 still has
     it, but loading the schema on the host is the first real check that
     SiteGround allows it.
   - **Removed features**: `utf8` now means `utf8mb3` and warns, the old
     `PASSWORD()`, implicit `GROUP BY` sorting, integer display widths, and
     the query cache.
   - **Collation**: 8.x defaults to `utf8mb4_0900_ai_ci`. The local server
     uses `utf8mb4_unicode_ci`; mixing them in a join or comparison is an
     "Illegal mix of collations" error. The databases should be created with
     `utf8mb4_unicode_ci` explicitly, or the schema should name it.

   The fix is to move the local `db` service to `mysql:8.4`, set the mode in
   the connection rather than on the server command line, and run the whole
   suite. Anything it finds is a Phase 3 bug. Until the suite is green on 8.4
   the game must not be deployed.

2. **The tick mails a hardcoded address** (`update.php`, "Sending email to
   Mako") on every run — the original author's. Locally `sendmail_path` is
   `/bin/true`. `deploy/tick.sh` sets the same, so cron cannot send it, but
   the line should go. Signup, preferences and `attack.php` also mail
   whatever address a player gives, and on the host those deliver for real.

3. **Exposure.** CLAUDE.md: do not expose this app to a network. Until that
   decision is made the whole site sits behind Site Tools > Security >
   Protected URLs (HTTP basic auth on `/`), and `deploy.sh` refuses to ship
   to a site that does not answer 401.

4. **Error display.** The repo's `app/.htaccess` turns `display_errors` on.
   It is not shipped; the server's own `.htaccess` replaces it. Whether
   `php_flag` works there at all is a `probe.sh` question — under FastCGI it
   does not, and the setting moves to Site Tools' PHP variables.

## The host

SiteGround shared hosting, site `jacobp259.sg-host.com`.

| | |
| --- | --- |
| SSH | `ssh siteground-mb` (`~/.ssh/config`; key `~/.ssh/siteground-medievalbattles`) — host `ssh.jacobp259.sg-host.com`, port 18765 |
| PHP (web) | 8.3, set in Site Tools > Devs > PHP Manager (which writes `public_html/.htaccess`) |
| PHP (cli) | `/usr/local/php83/bin/php`. Bare `php` is 8.2; 7.3 – 8.6 are all installed |
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

The app reads `MB_DB_HOST`, `MB_DB_NAME`, `MB_ACCOUNTS_DB_NAME`, `MB_DB_USER`,
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
  default `sql_mode` and whether the two databases exist. It writes one
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

1. Blocker 1: the suite green on MySQL 8.4.
2. Site Tools > Security > Protected URLs: protect `/`.
3. Site Tools > Site > MySQL: two databases (round and accounts), one user
   with all privileges on both.
4. Install `public_html/.htaccess` and `~/.mb-env` from the templates.
5. `bash deploy/probe.sh` — check every answer, especially `getenv`,
   `short_open_tag` and `sql_mode`.
6. Load the schema: `db.sql` into the round database, `db/accounts.sql`
   into the accounts database (from `mb/` over SSH, with `mysql`).
7. `bash deploy/deploy.sh`, read the dry run, then `--go`.
8. Run `bash ~/www/jacobp259.sg-host.com/mb/tick.sh` by hand once and read
   `~/logs/tick.log`.
9. Site Tools > Devs > Cron Jobs: the same command, at the tick interval.
