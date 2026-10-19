# Rounds and accounts

**Status: built, one round at a time.** Steps 1, 2, 3 and 5 under "Order of
work" are done: permanent accounts, a round that holds neither the address
nor the password, and rounds themselves -- every round table carries a
`round_id` and every query on one is confined to a round, a round is opened,
started and ended into its results from `bin/round.php` or the admin area,
and a player joins one from the round list and reads their history there.
Step 4, more than one round in play at once, is design only. This
records how the game is meant to end a round and start the next one without
deleting its players, and the decisions that shape it.

## What is wrong today

**A new game is a mass deletion.** `app/admin/newgame.php` and the "Delete
every account except administrators" action in `app/admin/gameconfig.php` run
`DELETE FROM user WHERE admin = 0`. Then they delete every research, military,
buildings, explore, return, barter and empire-news row whose `userid` is gone.
The player's login goes with the kingdom. Coming back for the next round means
signing up again, and nothing records that the account ever played.

**Administrators were not reset at all.** Since administrators became flagged
player accounts, both wipes keep them. The child rows were deleted only
`WHERE userid NOT IN (SELECT userid FROM user)`, so an admin's research,
military and buildings rows survived along with the `user` row: gold, iron,
lumber, food, land, experience, `countdown` and `safemode`, plus explores and
returns still in flight. An admin started round two with their round-one
kingdom while everyone else started from nothing. The interim fix (step 1
under "Order of work") resets them now, but the wipe is still a wipe.

`game_info` correctly carries over. `tick` is the flag for a tick in
progress, and `last_comp_id` is the counter that issues computer ids.
Resetting the counter would reissue ids that existing accounts already hold,
which breaks multi-account detection.

The underlying problem is that one row, `user`, is both *who you are* (email,
password hash, the admin flag) and *what you played this round* (race, class,
resources, land). A round can't end without destroying the first along with
the second.

## The model

Split the two:

- **An account is permanent.** It holds the email, the password hash, the
  admin flag and any later permissions, the signup computer id, and the date
  it was created. Logging in authenticates an account.
- **A kingdom belongs to one round.** It is today's `user` row and everything
  hanging off it: research, military, buildings, explores, returns, barter and
  empire news. An account has at most one kingdom per round.
- **A round** has a name, a ruleset, a status (open for signup, running, or
  ended), and start and end times.
- **A round result** is a snapshot of each kingdom, taken when its round ends:
  kingdom name, race, class, settlement, guild, final land, mountains,
  experience (`exp`, the networth) and place. A settlement's or a guild's
  final strength is the sum of its kingdoms' `exp`, as the tick computes it,
  so it is derived from the rows rather than stored beside them.
  This is the history. It stays small and outlives the round's own data.

A reset becomes "end this round, open the next one". Nothing is deleted, nobody
loses their login, and an admin is an account with no kingdom it must keep.

## Where a round lives — one database, with a `round_id`

**Decided 2026-10-18: every round shares one database, and each per-round
table carries a `round_id`.** This section first settled on one database per
round, then reopened it when the host changed the trade: SiteGround creates
databases only in Site Tools, with a prefix, and neither the app nor a script
can make one ([deployment.md](deployment.md)). A database per round would
make opening every round a manual step against a plan that caps how many
databases it allows, and would make every schema change a loop over live
rounds.

```
one database         account, round, round_result,
                     user, military, research, ... — each with round_id
```

**How a request knows its round.** Login chooses the round — the account's
kingdom in a running round: one, and that round is chosen; several, and a
page asks; none, and the player goes to the round list. The session keeps
`round_id` beside `userid`, and round-wide queries filter on it. No
`kingdom` mapping table is needed, because a userid stays unique across
rounds — so the ~390 queries that already say `WHERE userid=?` are
round-safe as they stand. The tick runs once per running round.

The accounts share it (decided 2026-10-18). They had a database of their own,
`mb_accounts`, while each round was going to be one; with one database for
every round there was nothing left to keep them apart for, and one database
is one thing to create on the host, one connection and one schema to
migrate.

What it buys:

- **Opening a round is a script**, start to finish: `bin/round.php open`
  inserts a row and seeds its settlements. Nothing to do in Site Tools, and
  nothing to cap.
- **One schema to migrate** ([modernization.md](modernization.md), "Schema
  migrations").

What it costs, and the guard:

- **Every round-wide statement needs `AND round_id=?`**, and a missed one does
  not fail: it silently changes finished rounds. That is the tick's
  unfiltered whole-table updates (`UPDATE user SET online = 0`, the military
  training shift), the rankings, the listings and the admin pages — a
  smaller set than every query, and a findable one.

  The guard is the one that closed injection: an audit in the suite that
  fails any query on a per-round table unless it filters on `userid` or
  `round_id`, or carries `// mb-round-ok: <why>`. Longer term it is the
  data-access layer ([modernization.md](modernization.md)), which applies
  the filter in one place rather than at each site, and makes the audit a
  backstop.
- **An ended round no longer archives as a unit.** Its results are in
  `round_result` either way; its raw rows are deleted by
  `round_id` when no longer wanted.

The rejected design, one database per round, is in this file's history
(`5e26798`) — what it had going for it was that a missed filter could not
reach another round.

## The credential

**Done, and further than the bridge this section first designed.** Most
queries identified the player with `WHERE email='$email' AND pw='$pw'`, about
400 of them, and `pw` was the password hash, copied into all six kingdom
tables. The plan was a bridge: keep the pair, but put a random kingdom key in
`pw` so the password could leave the round, and retire the key later.

It shipped that way for one commit (step 2), and then the key was retired
too (step 5), because by then every query bound its values and the rewrite was
mechanical: 365 of the 386 sites ended their parameters with `$email, $pw`
and had the pair as their last two placeholders, and became `WHERE userid=?`
in one pass. The rest were the tick's per-user deletions and the barter's
stock updates, by hand.

What the round holds now is neither the address nor the password. The
session carries the kingdom's `userid`, the account's id and a SHA-256
fingerprint of the account's address and hash:

- **The password and the address each exist in one place**, the account row.
  A password change and an address change are each one `UPDATE`.
- **Revocation is the account's.** A session ends when its account is gone,
  when the address or hash changed since it logged in -- which is how either
  change logs out the account's other sessions -- or when its kingdom no
  longer exists or is not its account's.
- **`credentials.php` no longer guards a query.** It still says what an
  address must be to be stored and mailed.

## The player's path

- **Signup creates an account only.** It needs an email and a password. It no
  longer asks for an empire name, race or class.
- **Joining a round creates a kingdom.** It asks for the kingdom name, race,
  class and settlement placement: what `checksignup.php` does today after the
  credential, run against the round database. `mb_valid_ename()` still
  applies. Names are unique within a round, not across rounds.
- **Login authenticates the account.** If the account has a kingdom in a
  running round, the player goes into the game. If not, they go to the round
  list, where they can join an open round or read their history.
- **A forgotten password is reset by mail.** `index.php?page=forgot` mails
  a link to `index.php?page=reset`, good once for an hour, and only while the
  address and password are the ones it was issued against; setting the new
  password ends the account's sessions and confirms its address. Limited per
  account and per IP, and answered the same whether the address has an
  account or not. `app/include/password_reset.php`.
- **History lists the account's round results:** every round it played, with
  the kingdom, its rank and its final standing, plus each round's top kingdoms
  and guilds.

## Administration

- **`account.admin` replaces `user.admin`.** `mb_admin_require()` reads it
  from the `account` table on every request, as it read `user.admin`, so
  revoking still takes effect on the next click. An admin doesn't need a
  kingdom in any round.
- **The admin area acts on the current round** -- the one in play, while
  only one can be. Choosing among several waits for step 4.
- **`bin/round.php` replaces `newgame.php`** (built), and `admin/rounds.php`
  takes the same steps from the area, each a POST, ending only with a box
  ticked:
  - `open <name>` inserts the `round` row and seeds its settlements. It does
    not take a ruleset yet: there is one, `classic`, the column's default
    ([rulesets.md](rulesets.md)).
  - `start` and `end` move the current round's status. `end` writes every
    kingdom's round result to `round_result` and marks the round ended, in
    one transaction. The tick only ever advances a running round, so it
    needs no flag set, and the session refuses a kingdom of an ended round,
    which is how the pages refuse writes to it.
  - Deleting an ended round's rows is to be a separate, explicit command,
    never a side effect of opening the next. Not built: nothing yet needs
    the space.
- **`newgame.php` and gameconfig's "delete every account" are retired**
  (done). An administrator deletes a kingdom from the current round on
  gameconfig.php, and a player deletes their own account from preferences.
  Deleting an account anonymizes its round results -- `account_id` 0 --
  rather than removing them, so a round's final rankings don't develop
  gaps.

## Testing

- **The fixture gains a round.** The three fixture logins are already
  accounts; their kingdoms become round 1's. Their round data is today's
  fixture with `round_id = 1` added, so the tick and combat golden masters
  must not move except by that column, and they prove that the change moved
  nothing a kingdom sees.
- **A second, ended round in the fixture**, so the round audit has something
  to catch: the tick and every admin action run against round 1 must leave
  round 2's rows byte-identical.
- **New tests:**
  - Ending a round leaves its accounts able to log in and its round results
    written.
  - An account with no kingdom in the running round reaches the round list,
    not a game page.
  - A session whose kingdom was deleted is ended.
  - A password change ends the account's other sessions, carried over from
    `tests/session.php`.
  - A session pointed at another account's kingdom is ended.
- **The smoke crawl gains the round list, join and history pages**, as a
  logged-in account with no kingdom and as one with a kingdom.

## Order of work

1. ~~**Interim: reset administrators' kingdoms in both wipes.**~~ **Done.**
   `app/include/kingdom.php` holds signup's starting rolls and inserts, and
   `mb_kingdom_reset()` returns a kingdom to them. `tests/newgame.php`
   compares each reset kingdom against a twin signed up afterwards.
2. ~~**Accounts.**~~ **Done.** The `account` table (`db.sql`,
   `app/include/accounts.php`) — first in a database of its own,
   `mb_accounts`, and since 2026-10-18 beside the round's tables. Login,
   signup, the session and `mb_admin_require()` use it; `user.admin` is gone,
   and `bin/accounts-migrate.php` moves an existing world over.

   The golden masters didn't move except where they had to: the tick golden
   was re-accepted with only its `pw` values, the dropped `user.admin` and
   the new `user.account_id` changed, checked by diffing with those
   normalised away.
   The combat and actions goldens passed untouched, and now compare `pw`
   rather than ignoring it.

   Decided on the way, ahead of step 3:

   - **The link is `user.account_id`**, not the address. An address can
     change, and an ended round is frozen with whatever address it had, so
     an id is the only link that survives into history. `mb_kingdom_find()`
     looks the kingdom up by it; signup refuses a second kingdom for the
     same account by it; the wipes and the admin checks use it.
   - **Nothing in the round deletes an account.** The two wipes, the tick's
     inactivity deletions and the admin's "delete kingdom" remove the kingdom
     only. Only a player deleting their own account in `preferences.php`
     removes the account too.
   - **Signup doubled as join until step 3.** An account with no kingdom that
     signed up again with its own password got a new kingdom. Joining is its
     own page now (step 3, slice 4), and an address in use is simply taken.
   - **Revocation**: a SHA-256 fingerprint of the account's address and hash
     in the session, compared with the account's on every request, plus the
     kingdom still being that account's. `tests/session.php` drives each.
   - **Confirming an address is the account's**: `account.verified_at` and
     `verify_code`, replacing the round's `emailvalidate`, so an address is
     confirmed once rather than once a round. It is enforced again — signup
     had stored every account as activated since 2007 — and the tick deletes
     the kingdom, not the account, of one unconfirmed after
     `activation_hours`. `bin/admin.php verify` stands in for the mail
     locally.
   - **Addresses may be any valid address up to 254 characters**, the
     column width. A longer one would be silently truncated on insert and
     could never log in; an apostrophe, refused while addresses were
     interpolated, is allowed again.
3. **Rounds.** In four slices, each committed green:

   1. ~~**The schema.**~~ **Done.** The `round` table, and a `round_id` with
      an index on the eighteen tables a round owns, by a migration that makes
      the world it finds round 1, running. A fresh install starts the same.
      No query filters on it yet; the goldens moved by the column alone,
      checked by diffing old against new with `round_id=1` stripped.

      **Ids stay unique across rounds.** `userid`, `setid`, `gid`, `mid`,
      `sid`, `gnid`, `barterid` and the forum ids are each issued once and
      never reused by a later round, so a query that filters on one is
      already confined to its round — the reasoning that made the ~390
      `WHERE userid=?` queries round-safe, extended to the rest. It also
      keeps the per-settlement tables made at runtime (`setmain<id>`) apart.
      What it costs: a later round's settlements are numbered on from the
      last round's, so a player's first may be settlement 11. A per-round
      display number can come later if that matters.

      **A row takes its round from its owner, in the INSERT.** A kingdom's
      buildings, military, research, explore and return rows from its `user`
      row; a message from its recipient's; empire news from the kingdom's;
      settlement news and forum posts from the settlement's; guild news from
      the guild's; a guild, its threads and requests from the player's. So
      no page passes a round along, and a row whose owner is gone is not
      written at all. A new kingdom joins its settlement's round. `round_id`
      has no default, so a forgotten one fails under a strict session.
   2. ~~**The filter.**~~ **Done.** `tests/round-audit.php` fails any query
      on a round table that filters on none of those ids or on `round_id`,
      and any insert that names no round; it found 229, and is at zero. A
      game page filters on `$round_id`, which the session sets from the
      kingdom (so a page never shows one round's kingdom beside another's
      world); signup and login use `mb_round_current()`, the newest round not
      ended; the tick advances `mb_round_running()` alone, walking its
      userids, its settlement range and its guild ids. Lookups by kingdom or
      guild name are by round, since a name can recur. `mb_kingdom_find()`
      takes the round, so an account with a kingdom in an ended round can
      sign up for the next. A round's settlements are a range of setids
      (`mb_settlement_range()`), and every range check that used to stop at
      the count now stops at the round's edges — which is also what keeps a
      player from viewing or targeting another round's settlement. The admin
      wipes act on the current round only, until they are retired.

      `tests/round-isolation.php` holds it to account: an ended round with a
      row in every table and round 1's names, which the tick and every admin
      action must leave byte-identical. Every golden passed untouched.

      **Ids from the request.** A page that takes a target filters on that
      id and passes the audit, so each checks the target is in the
      player's round where it takes it: the attack, intel and settlement
      attack lookups, the message recipient, the guild leader's kick, the
      guild-leader message, the barter purchase, and the guild forum guards
      — which matched a thread by its guild's name. Settlement forums and
      votes were safe already, being held to the player's settlement, and a
      guild request is held to its applicant and leader. The isolation test
      aims each at the ended round and requires a refusal.
   3. ~~**Ending one.**~~ **Done.** `round_result` (a migration and
      `db.sql`), and `bin/round.php list | open <name> | start | end` over
      `mb_round_open()`, `mb_round_start()` and `mb_round_end()`
      (`app/include/rounds.php`). `open` refuses while a round is in play,
      and seeds as many settlements as the last round had, numbered on from
      its. `end` writes a result per kingdom, placed by `exp` with a tie to
      the earlier signup, and marks the round ended, in one transaction.
      Nothing is deleted.

      **A session ends with its round.** The session's kingdom check joins
      the round and refuses an ended one, so a player cannot play on in
      history; the account logs in again and is told it has no kingdom, and
      signs up into the next round once it opens — under the same kingdom
      name if it likes. `tests/rounds.php` drives the whole life: end round
      1, the results, the ended session, the refused signup and idle tick,
      round 2 opened, joined, logged into, left alone by the tick until
      started and advanced once it is, with round 1's rows unmoved
      throughout. The tick golden moved only by the empty `round_result`
      table appearing in its dump.
   4. **The pages.** Under way.

      ~~**The player's path.**~~ **Done.** Signup makes an account and
      nothing else; its welcome mail no longer carries the password. Login
      authenticates the account, and one with no kingdom in the current
      round gets a session with no kingdom in it -- `$login` and `$userid`
      empty, so no game page admits it, `$mb_account_id` set -- and goes to
      `rounds.php`: the current round with a form to join it, the account's
      own results, and each ended round's top ten kingdoms and top five
      guilds, a guild's strength summed from its rows. `join.php` makes the
      kingdom through `mb_kingdom_join()` (`app/include/kingdom.php`) --
      signup's checks and settlement placement, unchanged but asked of one
      round and one account -- and logs it in. Login and join share
      `mb_kingdom_enter()`, what logging into a kingdom records.

      ~~**Retire the wipes.**~~ **Done.** `admin/newgame.php` and
      gameconfig.php's "delete every kingdom" are gone, with
      `mb_kingdom_reset()` and the helpers only they used, and
      `tests/newgame.php`. gameconfig.php's other bulk actions stay, on the
      current round.

      ~~**Administration.**~~ **Done.** `admin/rounds.php` opens, starts
      and ends rounds as `bin/round.php` does, from POSTs only, and ending
      wants a box ticked. The admin gate asks for the account alone, so an
      administrator whose kingdom ended with the round can still open the
      next. Deleting an account sets its round results' `account_id` to 0.
4. **Concurrent rounds.** The session chooses among running rounds, and the
   tick runs once per round. This lands with, or after, the ruleset loader
   from [rulesets.md](rulesets.md), since two rounds at once is how classic
   and modern run side by side.
5. ~~**Retire the key.**~~ **Done, ahead of step 3.** Every query filters on
   `userid`, and `email` and `pw` are gone from all six round tables, which
   are indexed on `userid` instead. See "The credential" above. The tick
   golden was re-accepted with only those two columns gone, checked by
   comparing it, row-sorted, with them stripped.

## Open questions

- ~~**Where does a round live?**~~ **One database, with a `round_id`** —
  see "Where a round lives".
- **One round at a time at launch, or concurrent from the start?** The design
  allows either. Step 4 can wait until a second ruleset gives a reason for it.
- ~~**Does a round end on a date or when an admin ends it?**~~ **When an
  admin ends it** (decided 2026-10-18): `bin/round.php end`, or its admin
  page. No scheduled end.
- ~~**What does a round result rank by?**~~ **`user.exp`, the networth**
  (decided 2026-10-18). Despite the name it is not accumulated experience:
  the tick recomputes it each run (`update.php`, "calculate all units") as a
  weighted sum of every unit including those away, assigned sages,
  explorers, land, mountains and weapon upgrades. It is what "Strongest
  Empires" (`esscores.php`) ranks by, and settlement and guild strength are
  sums of it. The round result stores `exp`, `land` and `mts` and the
  kingdom's settlement and guild, so the other four rankings can be rebuilt
  too. A later ruleset may rule differently.
- **How long are ended rounds' rows kept?** Round results are kept
  indefinitely. The raw rows are needed only for anything that wants more
  than the snapshot.
- ~~**Should an account have a public name across rounds?**~~ **Yes, and it
  has one:** `account.handle`, chosen at signup, unique ignoring case, held to
  the empire-name rule by `mb_valid_handle()`, and shown on the preferences
  page. Accounts moved by `bin/accounts-migrate.php` take their empire's
  name.

  **The leaderboard and history show the kingdom's name, not the handle**
  (decided 2026-10-18). A player names their kingdom each round — `user.ename`
  is per kingdom already, chosen at signup now and at join once step 3
  lands — and that name is what a round is remembered by. The round result
  records `account_id` beside the name, so a player's own history can list
  their kingdoms; the handle is not part of it, so whether a handle may change
  no longer touches history.
