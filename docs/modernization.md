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

Not started. The known work, in no committed order:

- **SQL injection.** All ~1300 queries are built by string interpolation with
  no escaping. This is the reason the app must not be exposed to a network.
- **Unescaped output.** Every template echoes user data raw.
- **Passwords.** Unsalted MD5, denormalized into four tables alongside the
  email that identifies the row.
- **Error handling.** Turn `mysqli_report()` back on and handle what it
  surfaces — several queries have never succeeded.
- **The bug list.** `tests/register-globals.txt` and `$GAMEDATA['quirks']` are
  both inventories of real defects, kept in a form that shrinks as they are
  fixed.
- **Invert the manual dependency** so the engine reads `$GAMEDATA` rather than
  its inline literals.

### Prerequisite: coverage

The smoke crawl reaches 39 of 69 top-level scripts. The forum pages (`gl-*`,
`sl-*`, `topic*`) need seeded threads and the runtime per-settlement tables
before they are reachable at all. Rewriting a page the suite does not visit is
not a port, it is a guess — so closing this gap comes before the rewrite work
that touches those files.
