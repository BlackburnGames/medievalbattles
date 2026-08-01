Medieval Battles v6. Originally ran in late 2003.

This code is old, embarassing, buggy and not maintained. I can not say for sure
that it will run well, if at all. This source is being released solely for
historical and educational purposes.

---

## Running it locally

> **Do not expose this to a network.** Every query is built by string
> interpolation with no escaping, passwords are unsalted MD5, output is
> unescaped, and `register_globals` is emulated. The Docker ports bind to
> `127.0.0.1` on purpose — leave them that way.

You need Docker with Compose v2 or newer. Nothing else — there is no PHP,
MySQL or Composer install required on the host.

```bash
docker compose up -d --build
```

That builds a PHP 8.3 + Apache container and a MySQL 5.7 container, and loads
the schema plus a small deterministic test world on first start.

Then open:

**http://127.0.0.1:8080**

Log in with one of the fixture accounts:

| Email                | Password   | Notes                     |
| -------------------- | ---------- | ------------------------- |
| `tester@example.com` | `test1234` | main account, has a settlement |
| `idle@example.com`   | `pass2`    | inactive player           |
| `poor@example.com`   | `pass3`    | no resources              |

Useful commands:

```bash
docker compose logs -f web    # PHP errors also go to stderr
docker compose down           # stop
docker compose down -v        # stop and wipe the database
bash tests/reset-db.sh        # restore the fixture world in place
```

### Running the game tick

The world only advances when `update.php` runs. It normally ran from cron; run
it by hand with the repo root as the working directory (unlike every page,
which needs `app/`):

```bash
docker compose exec -w /repo web php update.php
```

### Tests

```bash
bash tests/run-all.sh         # tick golden master + page smoke crawl
```

On Windows, run these from Git Bash. The scripts already set
`MSYS_NO_PATHCONV=1`; if you invoke `docker compose exec` yourself with a
container path like `/repo/...`, prefix it the same way or Git Bash will
rewrite the path and the command will fail.

### Modernization status

The revival is staged:

- **Phase 1 — done.** Running, test-covered baseline on PHP 5.6 in Docker.
- **Phase 2 — mostly done.** The game runs on PHP 8.3. All `mysql_*` calls are
  converted to `mysqli`, array keys and bare-word constants are quoted, and
  `ereg_replace` is gone. The suite passes on both 8.3 and 5.6 against the same
  golden master. Still outstanding: `session_register()` and the
  `register_globals` emulation, which need call-site changes.
- **Phase 3 — not started.** Structural and security rewrite.

The 5.6 image is kept buildable as the behavioural reference:

```bash
docker compose build --build-arg PHP_VERSION=5.6-apache web
```

See [CLAUDE.md](CLAUDE.md) for the architecture notes and the traps worth
knowing before changing anything.
