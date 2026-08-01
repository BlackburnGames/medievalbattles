# Modernization

Medieval Battles v6 was written in 2003 for PHP 4 and released publicly for
historical purposes. The work in progress is a staged modernization: get it
running under test first, port it mechanically, then rewrite it structurally.

The ordering is deliberate. Every stage before the rewrite exists to make the
rewrite reviewable — a change to 2003 code is only safe if something can tell
you the behaviour did not move.

## Phase 1 — a test-covered baseline (done)

A running stack on PHP 5.6 in Docker, plus the characterization suite described
in [testing.md](testing.md). Nothing about the game's behaviour was changed.
Three latent fatals were fixed to make the app boot at all; they are listed
under "Fixed once" in [porting-notes.md](porting-notes.md).

## Phase 2 — the mechanical port (done)

The sources now **run on PHP 8.3**, and the full suite is green on both 8.3 and
5.6 against the same golden master. What was removed, roughly in order:

- `mysql_*` → `mysqli_*`, with `mb_db_result()` standing in for
  `mysql_result()`'s two-argument form (see the trap writeup in
  [porting-notes.md](porting-notes.md)).
- Bare array keys (`$row[email]`) and bare word constants (`$x = golems`),
  fixed with the tokenizer rather than sed.
- `ereg_replace()` → `preg_replace()`.
- `session_register()` → `app/include/session.php` writing `$_SESSION`
  directly.
- `register_globals` → `mb_input()`. The 315 reads that depended on injection
  now name their parameter explicitly, and the shim is switched off.

Two supporting pieces landed alongside it: the manual was rebuilt from the
engine ([game-manual.md](game-manual.md)), and the compatibility layer was
emptied down to the one disabled block that remains.

## Phase 3 — the structural and security rewrite

In progress. The known work, in no committed order:

- **SQL injection.** **In progress: 161 entries down to 36.** All ~1300 queries
  are built by string interpolation with no escaping. This is the reason the
  app must not be exposed to a network. `tests/sql-injection-audit.php` finds
  the reachable half of it — request input that survives into a
  `mysqli_query()` — and baselines it in `tests/sql-injection.txt` as a third
  ratchet.

  The fix is `mb_sql_str()` and `mb_sql_int()` in `app/include/db.php`, and
  which one a site needs is the interesting part. The audit records the quoting
  context because escaping only helps inside quotes: `WHERE topicid=$topicid`
  is interpolated bare, so no amount of escaping closes it and the value has to
  be cast instead.

  Prepared statements are the better answer and are not this change. Escaping
  every site can be checked by a suite that proves behaviour did not move;
  rewriting 1300 interpolations into bind calls cannot.

  What is left is 36 entries, and it is not a random 36:

  - **26 are in `barter.php` and `guildbarter.php`**, which are dead. `barter.php`
    dies at line 17 and has since 2003. Real code, unreachable code, and not
    worth rewriting blind — if the barter system is ever switched back on, it
    needs the crawl before it needs the escaping.
  - **9 are in `app/css/admin/`**, which no test touches at all. The admin area
    has its own login and sits outside both crawl roots.
  - **1 is `preferences.php`'s `$email`**, and it is honest. A player-chosen
    address is written to six tables and then assigned to `$email`, which later
    queries interpolate. Fixing it properly means escaping `$email` and `$pw` at
    the session boundary they come from, which is a change to about 1300 `WHERE`
    clauses rather than to one file.

  Two shapes of false positive turned up often enough to name. Both are one
  variable meaning two things, which a flow-insensitive audit cannot tell apart
  and neither can a reader — so both were fixed by renaming rather than
  suppressed:

  - **A flag and a value.** `wconstruct.php` reads `mb_input('longsword')` only
    to test `IsSet()`, then forty lines later assigns the same name the build
    time in ticks, and that is what the `UPDATE` writes. Twenty-two entries.
  - **A request id and a computed one.** `gc.php`'s `$gid` was both the guild
    being messaged and the id of the guild being created.

  And one shape of false *negative*, which matters more: `extract()` on a
  database row. It silently replaced the request value with a column of the same
  name, so four forum listings looked like they interpolated a request parameter
  when they did not — and it hid a genuine second-order injection in
  `S_VMESS.php` at the same time. All thirteen calls are gone.
- **Unescaped output.** Every template echoes user data raw.
- **Passwords.** Unsalted MD5, denormalized into four tables alongside the
  email that identifies the row.
- ~~**Error handling.**~~ **Done.** `mysqli_report()` is back on as
  `MYSQLI_REPORT_ERROR` (warnings, not exceptions), the smoke crawl and the tick
  both fail on a query error, and `tests/broken-queries.txt` is empty. Going
  further, to `STRICT` and exceptions, needs the ~1300 call sites to check their
  return values first. Getting there meant fixing what it surfaced —

  - **Research had never worked.** The tick adds points to `r1pts`–`r18pts` in
    one `UPDATE`, but the schema stopped at `r14pts`. A MySQL `UPDATE` is
    atomic, so the statement was rejected whole and the fourteen columns that
    did exist were not written either — assigning sages did nothing, for
    anyone, ever.
  - **Troops sent out were lost permanently.** The returning-army slot and its
    timer are cleared by separate statements, and the restore that moves the
    army back into `military` is a third. Two of the three name
    `golem1`–`golem4`, which `returntbl` did not have, so they failed while the
    timer beside them kept counting down — and once it passed 1 the
    `WHERE time1 = 1` release could never match again.
  - **The guild flag was not a cut feature, just an unsaveable one.** Only the
    column was missing. It is also escaped now, which it never was.
  - The tick ran a phantom first pass for userid 0, whose empty interpolations
    MySQL rejected as syntax errors.

  The first three shared a cause: an `UPDATE` naming a column that `db.sql` did
  not have, against code that checks no return value. The engine was right and
  the schema was short — so the fix was almost entirely `db.sql`.
- **The bug list.** `tests/register-globals.txt` and `$GAMEDATA['quirks']` are
  both inventories, kept in a form that shrinks as they are fixed. Treat the
  first as suspects rather than defects — it is per-file and flow-insensitive,
  so a variable assigned in one file and read in another looks unassigned.
- **Authorization.** **The forum moderation pages are done; the rest is not.**
  Nothing checks that you own what you are acting on beyond the queries' own
  `WHERE` clauses.

  `gl-delposts.php` and `sl-delposts.php` were the clearest cases and are
  fixed: neither checked that the caller led anything, and `gl-delposts.php`
  deleted purely on the topicid in the URL, so any logged-in player could
  remove any guild's threads by guessing. Both now refuse a non-leader and
  scope every delete to the caller's own guild or settlement.

  They were also the only two pages where a guard could be *verified*, because
  closing the coverage gap put them on the crawl. Anywhere the suite still does
  not reach, adding a check is a guess about what the check should permit.

  **The admin area under `app/css/admin/` has no gate at all.** `adminlogin.php`
  compares against a hardcoded `mako` / `quickshot` and, on success, prints a
  link to `main.php`. That is the entire mechanism: no session is written, and
  nothing in the area asks whether one exists, so every page in it serves to
  anyone who types the URL. Deciding what should replace it is a design
  question, not a port — the credentials, the session and the ~1300-query
  surface it exposes all need answering together.

- **Two blind spots in `register-globals-audit.php`,** both of which hid a
  read the Phase 2 port then missed. One is fixed and one is not:

  - ~~It skipped every name beginning with `_`~~ to avoid reporting the
    superglobals, and caught `$_mace` with it. **Fixed** — it names the nine
    superglobals now. That read being missing left the Mace button inert, and
    with it the six priest weapons that check the Mace as a prerequisite.
  - **It credits a shared include for a name even in files that do not include
    it.** `adminlogin.php` reads `$pw`, which `include/session.php` assigns —
    but that file includes nothing except `request.php`, so the read was
    suppressed and the port missed it. The admin login has rejected every
    attempt since. Fixing the audit means resolving includes per file rather
    than globally, and it will surface entries the ratchet has to absorb.
- **Invert the manual dependency** so the engine reads `$GAMEDATA` rather than
  its inline literals.

### Prerequisite: coverage — done

The smoke crawl reached 39 of 69 top-level scripts when Phase 3 opened. It
reaches 57, and every one of the twelve it does not reach has a reason recorded
in `$UNREACHABLE` that is checked on each run. Rewriting a page the suite does
not visit is not a port, it is a guess, so this came first — and it kept paying
out, because each page that came into the net arrived with defects attached.

Three steps got there:

1. Seeding the guild, settlement-leader and forum fixtures, and crawling as a
   rank-and-file member as well as a leader.
2. Seeding a message, so `vmessages.php` stopped rendering an empty table. Its
   loop — two queries per row — had never executed once.
3. Driving the forms. The crawler follows links, so every POST handler sat
   outside the net. `postForm()` now submits both arms of the four forum thread
   handlers, and the moderation handlers are called directly.

The remaining twelve are three includes, two POST handlers with no safe
fixture (`checksignup.php` creates a user from `rand()`), `disband.php` and
`logout.php` (both destroy what the run depends on), two dead files, and the
two-step account activation flow.

One thing learned doing it: **destructive requests have to run before the
crawl, not after.** `govt.php:20` clears `sl` for every member of a settlement
on each visit and re-elects whoever holds the most votes, and nobody in the
fixture votes — so the crawler merely opening the page deposes the tester, and
anything afterwards that needs settlement leadership is refused.
