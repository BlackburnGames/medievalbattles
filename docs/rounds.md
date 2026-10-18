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

## A round is a database

**Each round is its own database. Accounts live in one shared database beside
the rounds.**

```
mb_accounts          account, round, round_result
mb_round_1           today's schema: user, military, research, ... game_info
mb_round_2           the same schema, a fresh world
```

[rulesets.md](rulesets.md) already settled that *a world is a database*:
`MB_DB_NAME` chooses it, so a second world never needs `world_id` added to
every query. A round is a world with a start and an end, so it follows the
same rule. A second open round, such as a classic round running beside a
modern one, is just a second round database.

The alternative is one database with a `round_id` column on every per-round
table. It was rejected for three reasons:

- **The tick touches every kingdom ever created.** `update.php` walks
  `userid` from 1 to `max(userid)` and runs whole-table updates with no
  `WHERE` clause at all, such as `UPDATE user SET online = 0`
  (`update.php:420`) and the military training shift (`update.php:511`). With
  a `round_id` column, each one needs a round filter. A missed filter doesn't
  fail. It silently changes the kingdoms of finished rounds. In a separate
  database, it can't happen.
- **Round-wide queries are everywhere.** The five ranking pages, settlement
  and guild listings, the tick, and the admin pages all read "every kingdom", and each
  would need the filter added and checked. Per-kingdom queries would be fine,
  because `userid` would be unique across rounds. The round-wide ones are the
  dangerous half.
- **An ended round can be archived as a unit.** Dump it, take it offline, and
  drop it when it's no longer wanted. The round results in `mb_accounts` keep
  the history either way.

What it costs:

- **Schema changes have to reach every live round database.** Ended rounds
  are frozen and are read only through their round results, so they don't
  need migrating.
- **Creating a round creates a database.** The web application's MySQL user
  should not hold `CREATE DATABASE`, so rounds are opened from the command
  line (`bin/round.php`, below) with credentials the web server doesn't have.
  This is the same reasoning that made `bin/admin.php` the only way to grant
  the first admin.
- **A request has to know its round.** `connect.php` opens the round
  database the session names, not a fixed `MB_DB_NAME`, plus a connection to
  `mb_accounts` for the pages that need it: login, signup, the round list,
  history and admin. The tick takes the round as an argument and runs once
  per running round.

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
  - `open <name> --ruleset classic` creates the round database, loads the
    schema, seeds the settlements and writes `game_info`, including
    `game_info.ruleset` from [rulesets.md](rulesets.md).
  - `start <round>` and `end <round>` move the status. `end` stops the tick
    (`game_info.tick = 'no'`), writes every kingdom's round result to
    `mb_accounts`, and marks the round ended. After that, the pages refuse
    writes to it.
  - Deleting an ended round's database is a separate, explicit command. It is
    never a side effect of opening the next round.
- **`newgame.php` and gameconfig's "delete every account" are retired.**
  Deleting one player stays an admin action, but it now has two forms:
  deleting a kingdom from a round, or deleting an account. Deleting an account
  anonymizes its round results rather than removing them, so a round's final
  rankings don't develop gaps.

## Testing

- **The fixture becomes `mb_accounts` plus one round database.** The three
  fixture logins become accounts, each with a kingdom in round 1. Their round
  data is today's fixture, unchanged, so the tick and combat golden masters
  must not move. They run against the round database, and they prove that the
  split changed nothing a kingdom sees.
- **`reset-db.sh` drops every round database as well as `mb_accounts`.** It
  already has to drop every table rather than the known ones (see
  [testing.md](testing.md)). Leftover round databases from an earlier run
  would be the same mistake one level up.
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
- **How long are ended round databases kept?** Round results are kept
  indefinitely. The raw databases are needed only for anything that wants
  more than the snapshot.
- **Should an account have a public name across rounds?** Today, the only
  name is the kingdom's. A persistent handle would make history readable by
  other players, not just the account's owner.
