# Porting notes

Traps, deliberate-looking-wrong decisions, and things fixed once that must not
be reintroduced. Read this before changing data access, error handling or the
Docker image.

## The `mysql_result` two-argument trap

The single most important thing to know before touching data access here, and
the cause of two separate silent breakages already found. The call sites are
all converted now, but the trap explains why `mb_db_result()` looks the way it
does — **do not "fix" it without reading this.**

`mysql_result($result, $row, $field)` took the **row** second. The 2003 code
almost always called the two-argument form with a *name*:

```php
$max_UID = mysql_result($max_userid, "max_UID");   // "max_UID" is the ROW
```

PHP cast `"max_UID"` to int `0`, so this returned row 0, column 0 — and worked.
Most of those names are fiction: the query behind it is
`SELECT max(userid) FROM user`, whose column is actually named `max(userid)`.
The queries behind `noplayers`, `least_set`, `R_Mem`, `sel_mem` and `mgid` have
no such column either.

The abandoned PHP 7 port "fixed" these by resolving the name, which returns
null and silently breaks the caller. Both instances were repaired in Phase 1:

- `mysqli_field_seek(...)` (which returns a bool, not a value) had been
  substituted for `mysql_result` in the converted files.
- `update.php` was rewritten to the 3-arg form asking for non-existent columns,
  which disabled **the entire game tick** — the user loop exited immediately.

`mb_db_result($result, $field, $row)` (in `app/include/db.php`) now serves every
call site. Note the argument order is *not* `mysql_result`'s: field comes
second. It resolves the field name when the query genuinely has that alias and
falls back to column 0 otherwise, which is what the 2003 code effectively did.
That fallback is load-bearing, not defensive — resolving strictly would return
null across signup, login and the guild code.

When porting a call site: reproduce *column 0 of row 0*, not the name, unless
you have confirmed the query has that alias.

## The compatibility layer

`auto_prepend_file` points at `docker/php/prepend.php`, which is harness support
and stays: it loads the compat layer and decides whether `display_errors` is on
for this request. **Errors are off in the browser and on for the test
harness**, which opts in with an `X-MB-Show-Errors: 1` header — a logged-in page
emits hundreds of notices, which makes the game unreadable but is exactly what
the crawler asserts on. Everything is logged to stderr regardless.

`docker/php/compat.php` is what it loads, and it is now down to a single
**disabled** block: the `register_globals` emulation gated behind
`MB_REGISTER_GLOBALS=1`, which is `"0"`. The `mysql_db_query()`,
`mysql_result()`, `ereg_replace()` and `session_register()` sections were
retired in Phase 2 when their call sites were converted.

`mb_db_result()` and `mb_client_hostname()` moved into the app, where they
belong — they were never shims for functions PHP removed, they only lived in the
prepend because that saved writing an include line at ~47 call sites:

- **`app/include/db.php`** — `mb_db_result()`, required by `connect.php` with
  `__DIR__` (not CWD-relative, because the CWD differs between pages,
  `update.php` and the admin area). Every call site runs a query, so every call
  site reaches `connect.php`, several only through `functions.php` or
  `igtop.php`.
- **`app/include/hostname.php`** — `mb_client_hostname()`, included by
  `common.php` and `commong.php`, its only two callers.

**Treat what is left as a debt checklist, not infrastructure.** The
`register_globals` block is kept only as a bisection aid — flip it to `"1"` to
test whether a bug on an uncrawled page is a porting regression. Delete the
file, the env var and `prepend.php`'s `require` once those pages have coverage;
after that the prepend is nothing but the `display_errors` switch, which stays.

## Environment and language

- The image defaults to PHP 8.3 and takes a `PHP_VERSION` build arg so 5.6 stays
  buildable as the behavioural reference. The 5.6 images are archived and their
  Debian jessie apt repos are dead, so the Dockerfile installs nothing via apt —
  **do not add an `apt-get` line**, it breaks that build only.
- **`mysqli_report(MYSQLI_REPORT_OFF)` in `connect.php` is deliberate.** PHP 8.1
  made mysqli throw on error instead of returning false. This code fires ~1300
  unchecked queries and several have never succeeded, so exceptions turn each one
  into a fatal. Turning reporting back on, and handling what it surfaces, is
  Phase 3 — expect real breakage when you do.
- The smoke test demotes PHP 8's `Undefined variable` / `Undefined array key` /
  `Trying to access array offset on null` warnings back to notice class. PHP 8
  raised these from E_NOTICE, and they are precisely the uninitialised reads this
  codebase does everywhere; without the demotion the suite is permanently red for
  reasons unrelated to any change. Every other warning still fails.
- Bare words as values (`$x = golems`) and unquoted array keys (`$row[email]`)
  were fixed at the source with the tokenizer, not sed: inside a double-quoted
  string `"$row[email]"` is legal and quoting it there is a parse error. If you
  reintroduce this class of fix, tokenize.
- `output_buffering` is on because several logic-only includes emit stray
  whitespace after their closing tag, which breaks `header()` calls. It masks the
  underlying problem; those files still need cleaning.
- `sendmail_path=/bin/true` — `update.php` mails a hardcoded Gmail address every
  tick. **Never let local runs deliver mail.**
- A literal `?>` inside a `//` comment terminates PHP mode and dumps the rest of
  the file to the page. Avoid writing it in comments.

## Fixed once — do not regress

- The `gethostname()` redeclarations in `common.php`/`commong.php` (a built-in
  since 5.3, so an instant fatal).
- The one-argument `mysqli_query()` calls in `index.php`.
- The unfiltered `include($_GET['page'] . ".php")` in `index.php`.
