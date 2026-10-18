# Architecture

How Medieval Battles v6 is put together. This describes the code as it exists,
not as it should be — see [modernization.md](modernization.md) for what is
being changed and why.

## Shape of the codebase

Fully procedural: 133 PHP files containing 15 function declarations between
them, as released. (The modernization has since added helpers under
`app/include/` -- `db.php`, `html.php`, `credentials.php` and the like -- so the
count today is higher; none of them is a class.) No classes, no framework, no autoloader in the game. A page is a file;
control flow is `include`.

## Include paths and working directory

`app/` is the web root, and **all includes are CWD-relative**, so pages only
work when served with `app/` as the working directory. `update.php` sits at the
repo root and includes `app/include/...`, so it needs the repo root as CWD
instead — this is why the tick has to be run with `-w /repo`.

The admin area is `app/admin/`. It has its own include root — served from that
folder, `include/...` resolves inside it — whose files are thin forwarders to
`app/include/` rather than the copies they used to be. It lived under
`app/css/admin/`, inside the stylesheet directory, until Phase 3.

Its gate is `app/admin/include/auth.php`: an administrator is an account with
`account.admin` set, logged in through the game's own login. `mb_admin_require()`
reads the flag from the `account` table on every request, so a revocation or
a session that `session.php` logs out takes the privilege with it. Being logged
into the game is not being an admin, and the smoke crawl asserts both
refusals. Flags are granted and revoked with `bin/admin.php`; the world-wipe
actions delete every kingdom but the admins' and keep every account, and an
admin's kingdom cannot be deleted from `gameconfig.php`. Pages inherit the call through `include/igtop.php`;
`gameconfig.php` and `newgame.php` do not include it and call it themselves.

## Page flow

```
index.php (login) → checklogin.php → main.php?pageid=...
```

Game pages include `include/igtop.php` (session check and header, which also
pulls in `functions.php`) and `include/ignavbar.php`. The screens themselves
live in `app/include/S_*.php` and are selected by `main.php` from `pageid`.

**`functions.php` declares no functions.** It is a procedural include that
loads the logged-in user's entire state into globals (`$gp`, `$civ`, `$land`,
`$exp`, `$safemode`, …) which the page templates then echo.

## Auth

**One database, two halves.** `MB_DB_NAME` (`mbv6` locally, `db.sql`) holds
the world — kingdoms and everything hanging off them — and beside it the
`account` table: address, public player name, password hash, admin flag,
whether the address is confirmed. The account outlasts the round, so nothing
that ends a kingdom deletes a login. (It had a database of its own,
`mb_accounts`, while each round was going to be one; with every round in one
database it moved in.) There is one connection, `mb_db()` in
`app/include/db.php`; `connect.php`'s `$db` is it, and
`app/include/accounts.php` reaches it from any scope. The kingdom is linked
to its account by `user.account_id`, not by address: an address can change,
and an ended round keeps the one it had.
See [rounds.md](rounds.md).

The session carries the kingdom's `userid`, and nearly every query filters
`WHERE userid=?`. It used to be `WHERE email=? AND pw=?`, with the password
hash as the bearer token, copied into all six kingdom tables; the round holds
neither column now. The session also carries the account id and a SHA-256
fingerprint of the account's address and hash, and `$email` is the account's
address, read from it each request for showing and mailing. All of it lives in
the server-side session; what the browser holds is the session id, and
`app/include/session.php` is what guards it — cookie flags, a new id at login,
expiry, a full logout, no session for a request another site made, and a
per-request check that the account still has that address and hash and that
the kingdom is still its. Every session in the game, the admin area's
included, starts there.

The hash is **bcrypt** as of Phase 3 — `app/include/password.php`, pinned to
`PASSWORD_BCRYPT`. Accounts still holding the old unsalted MD5 verify once and
are rehashed in place by `checklogin.php`. Login verifies against the account,
then looks the kingdom up by `account_id`; an account with no kingdom in the round
is told so, and signing up again with the same address and password gives it
a new one.

A password change and an address change are each one `UPDATE` on the account.
So is confirming the address: `account.verified_at`, set by the link
`checksignup.php` and `activate_code.php` mail, and `include/igtop.php` refuses
every game page to an account without it.

`bin/accounts-migrate.php` moves a world that predates this (addresses and
hashes in all six tables, the flag in `user.admin`) into that shape, once.

`app/include/session.php` is the single bootstrap: it starts the session and
loads `$login`/`$userid`/`$email`/`$mb_account_id`, and every page that needs the credential
includes it — directly, or transitively via `igtop.php`, `functions.php` or
`common.php`. Only `checklogin.php` writes those slots; `preferences.php`
rotates the fingerprint when the player changes email or password.

## Request input

`mb_input('name')` in `app/include/request.php` reads `$_GET`, `$_POST` and
`$_COOKIE` in that precedence order. Pages open with an explicit block
assigning every parameter they use.

Two traps:

- **Its default is `null`, not `""`.** Absent parameters used to leave the
  variable *unset* under `register_globals`, and the code branches on
  `IsSet($update)` everywhere. `isset()` is false for null, so those branches
  still work; `""` would fire every handler on every request.
- **Most forms submit as GET.** They are written `<form type=post>`, but `type`
  is not a form attribute — the real one is `method`. Do not assume a handler's
  input arrives in `$_POST`.

## Game tick

`update.php` sets `game_info.tick='yes'` (every page then renders "Tick in
progress" and dies), loops userids applying economy growth with hardcoded
modifiers, mails inactive players, recomputes guild and settlement strength,
and resets `tick='no'` at line 492.

It is HTTP-invokable with no authentication.

## Schema

`db.sql`: 22 tables — 21 of the 2003 world and `account` — InnoDB and `utf8mb4_unicode_ci` since the move to
MySQL 8.4 (MyISAM with no charset declared before it), almost no indexes, types
nearly all `bigint(255)`/`varchar(255)`. It is **not schema-only** — it seeds
the `game_info` row and ten empty settlements. `db/seed.sql` adds the
deterministic test world on top, and the fixture's three accounts. Both load
on the volume's first boot and on every `reset-db.sh`. A real database is
built by the migrations instead (`db/migrations/`, `bin/migrate.php`), and
`tests/migrations.sh` holds the two to the same schema.

The app also creates per-settlement tables (`setmain<id>`, `setmsgs<id>`) at
runtime, which is why the test reset has to drop *all* tables rather than the
22 it knows about.
