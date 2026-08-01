# Architecture

How Medieval Battles v6 is put together. This describes the code as it exists,
not as it should be — see [modernization.md](modernization.md) for what is
being changed and why.

## Shape of the codebase

Fully procedural: 133 PHP files containing 15 function declarations between
them. No classes, no framework, no Composer, no autoloader. A page is a file;
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

Its gate is `app/admin/include/auth.php`: one credential from `MB_ADMIN_USER` /
`MB_ADMIN_PASSWORD`, recorded in `$_SESSION['mb_admin']`, checked by
`mb_admin_require()`. That slot is separate from the player session on purpose
— being logged into the game is not being an admin, and the smoke crawl asserts
both refusals. Pages inherit the call through `include/igtop.php`;
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

`$_SESSION['email']` plus `$_SESSION['pw']` (the MD5 hash) act as a bearer
credential, and nearly every query filters `WHERE email='$email' AND pw='$pw'`.

The email/hash pair is **denormalized into `buildings`, `military`, `research`
and `explore`**, so changing the auth model means touching the schema and
almost every query in the app.

`app/include/session.php` is the single bootstrap: it starts the session and
loads `$login`/`$email`/`$pw`, and every page that needs the credential
includes it — directly, or transitively via `igtop.php`, `functions.php` or
`common.php`. Only `checklogin.php` writes those slots; `preferences.php`
rewrites them when the player changes email or password.

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

`db.sql`: 22 MyISAM tables, almost no indexes, no charset declared, types
nearly all `bigint(255)`/`varchar(255)`. It is **not schema-only** — it seeds
the `game_info` row and ten empty settlements. `db/seed.sql` adds the
deterministic test world on top.

The app also creates per-settlement tables (`setmain<id>`, `setmsgs<id>`) at
runtime, which is why the test reset has to drop *all* tables rather than the
22 it knows about.
