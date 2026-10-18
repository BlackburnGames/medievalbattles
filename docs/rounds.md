# Rounds and accounts

**Status: partly built.** Steps 1, 2 and 5 under "Order of work" are done:
permanent accounts in their own database, and a round that holds neither the
address nor the password. Rounds themselves — step 3 on — are design only. This
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
  experience and ranks, plus each settlement's and guild's final strength.
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
mb_accounts          account, round, round_result
mbv6                 user, military, research, ... — each with round_id
```

**How a request knows its round.** Login chooses the round — the account's
kingdom in a running round: one, and that round is chosen; several, and a
page asks; none, and the player goes to the round list. The session keeps
`round_id` beside `userid`, and round-wide queries filter on it. No
`kingdom` mapping table is needed, because a userid stays unique across
rounds — so the ~390 queries that already say `WHERE userid=?` are
round-safe as they stand. The tick runs once per running round.

Whether the accounts fold into the round database is still open; with one
round database there is little reason to keep them apart, but nothing forces
it, and moving them is a migration either way.

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
  `mb_accounts.round_result` either way; its raw rows are deleted by
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
- **History lists the account's round results:** every round it played, with
  the kingdom, its rank and its final standing, plus each round's top kingdoms
  and guilds.

## Administration

- **`account.admin` replaces `user.admin`.** `mb_admin_require()` reads it
  from `mb_accounts` on every request, as it reads `user.admin` now, so
  revoking still takes effect on the next click. An admin doesn't need a
  kingdom in any round.
- **The admin area acts on one round at a time**, chosen in the area. Its
  current pages are per-round already, because they query the round's tables.
- **`bin/round.php` replaces `newgame.php`:**
  - `open <name> --ruleset classic` inserts the `round` row, with its
    ruleset ([rulesets.md](rulesets.md)), and seeds that round's settlements
    and `game_info`.
  - `start <round>` and `end <round>` move the status. `end` stops the tick
    (`game_info.tick = 'no'`), writes every kingdom's round result to
    `mb_accounts`, and marks the round ended. After that, the pages refuse
    writes to it.
  - Deleting an ended round's rows is a separate, explicit command. It is
    never a side effect of opening the next round.
- **`newgame.php` and gameconfig's "delete every account" are retired.**
  Deleting one player stays an admin action, but it now has two forms:
  deleting a kingdom from a round, or deleting an account. Deleting an account
  anonymizes its round results rather than removing them, so a round's final
  rankings don't develop gaps.

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
2. ~~**Accounts.**~~ **Done.** `mb_accounts` holds the `account` table
   (`db/accounts.sql`, `app/include/accounts.php`). Login, signup, the
   session and `mb_admin_require()` use it; `user.admin` is gone, and
   `bin/accounts-migrate.php` moves an existing world over. There is still
   exactly one round database.

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
   - **Signup doubles as join until step 3.** An account with no kingdom that
     signs up again with its own password gets a new kingdom; with any other
     password the address is taken. Login tells such an account it has no
     kingdom rather than logging it in.
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
3. **Rounds.** Add the `round` and `round_result` tables and `bin/round.php`.
   Make the round list, join and history pages. Retire `newgame.php`.
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
- **Does a round end on a date or when an admin ends it?** A scheduled end
  that players can see is fairer, and `bin/round.php end` from cron would do
  it.
- **What does a round result rank by?** The game has five rankings: land
  (`emland.php`), mountains (`emmountains.php`), experience (`esscores.php`),
  settlement strength (`setstrength.php`) and guild strength (`sguild.php`).
  The round result should store all the values those rankings use, so any of
  them can be rebuilt later. Which one counts as "the winner" is a ruling for
  each ruleset to make.
- **How long are ended rounds' rows kept?** Round results are kept
  indefinitely. The raw rows are needed only for anything that wants more
  than the snapshot.
- ~~**Should an account have a public name across rounds?**~~ **Yes, and it
  has one:** `account.handle`, chosen at signup, unique ignoring case, held to
  the empire-name rule by `mb_valid_handle()`, and shown on the preferences
  page. A round result should record it beside the kingdom's name, so history
  reads as the player rather than as a different empire each round. It is
  not changeable from the game yet; whether it should be — and what history
  then shows — is a decision for step 3. Accounts moved by
  `bin/accounts-migrate.php` take their empire's name.
