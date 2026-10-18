# Rulesets

**Status: design, not built.** Nothing below exists in the code yet. This
records how the relaunch is meant to run the 2003 game and a modernized one
from the same codebase, and the decisions that shape it, so the work in
[modernization.md](modernization.md) can be aimed at it.

## What this is for

The game is being relaunched, with its gameplay modernized. Players of the
original will want the original, so there are two rulesets:

- **Classic** is v6 as designed: its races, classes, units, buildings and
  balance as of 2003.
- **Modern** is the relaunch's game: balance changes and new features, such as
  new-player protection and whatever is taken from
  [v7-design-notes.md](v7-design-notes.md).

Both run from one codebase, and each world is fixed to one of them.

The obvious alternative is to tag the 2003 rules and run classic from the tag.
It was rejected because classic would then never get the Phase 3 work. Every
bound query, every admin fix and every session rule would need backporting into
code that is otherwise frozen, and a classic server would be the less safe of
the two indefinitely. With one codebase, security work lands once and both
rulesets have it. The `classic-v6` tag still gets made, when the classic
ruleset first lands, but as a marker rather than something to deploy.

## Classic means v6 with its bugs fixed

**Classic does not keep bugs on purpose.** Every entry in `$GAMEDATA['quirks']`
is a defect, not a feature a player would miss: a Night Elf bonus defeated by a
stray leading space, Advanced Construction doubling build time when it was
meant to halve it, iron golems coming home as archers. (The first and last are
fixed now, on both rulesets at once.) A relaunch is for players, and historical
accuracy doesn't outweigh something broken.

That has three consequences:

- **There are no per-ruleset flags for preserving a bug.** A fix to the shared
  engine fixes both rulesets at once, which is what should happen.
- **`$GAMEDATA['quirks']` describes the engine, not a ruleset**, so it stays one
  shared list and shrinks as fixes land.
- **Some fixes need a ruling on intent first.** For some quirks the intent is
  plain. For others the code doesn't say what it was meant to compute: the
  golem survivor formula, the wizard-loss clamp, and the project count the
  sage totals should agree on. The chosen reading is a design decision. It is
  made explicitly and recorded in the commit that fixes the bug, not inferred
  by whoever gets there first.

The line between a bug fix and a balance change is whether v6's own design
asked for the result. Halving Advanced Construction's timer is a fix, because
the research says it speeds construction. Making gold mines produce 350 is
balance, so it belongs to modern.

## What a ruleset is

**A ruleset is data, not code.** It is a `$GAMEDATA`-shaped array of values,
plus a set of feature flags. The engine is shared and reads the active
ruleset.

### Layout

```
app/include/rulesets/classic.php   today's gamedata.php, values and citations
app/include/rulesets/modern.php    an overlay on classic: only what differs
app/include/gamedata.php           the loader: picks a ruleset, builds $GAMEDATA
```

`classic.php` is today's `gamedata.php`, moved and unchanged, citations
included. It is the complete statement of the rules, and every other ruleset is
defined relative to it.

`modern.php` lists only its differences from classic, so the file reads as the
relaunch's changelog. Each entry carries a reason instead of a citation:

```php
return array(
    'economy' => array(
        'gold_per_mine' => 350,   // v6's late game is gold-starved; see #NN
    ),
    'features' => array(
        'protection' => true,
    ),
);
```

### Merging an overlay

**Not `array_replace_recursive()`.** It merges numerically keyed lists by
index, so an overlay that shortens `disband.excluded` from three units to two
would keep classic's third. The loader's `mb_ruleset_overlay()` recurses into
string-keyed arrays and replaces lists wholesale. A list in an overlay is the
whole list, never a patch to one.

**An overlay may not introduce a key classic doesn't have.** Misspell
`gold_per_mine` in `modern.php` and a plain merge would add a new key that
nothing reads, while modern silently keeps classic's value. The loader refuses
an overlay key with no counterpart in classic, and a test runs the loader over
every ruleset. A genuinely new value, such as a feature's tuning number, goes
into classic with the value that leaves the feature off, so classic stays the
complete key set.

### Feature flags

Not every difference is a number. New-player protection and anything
from the v7 design change behaviour, so they're flags in
`$GAMEDATA['features']`, read through `mb_feature('protection')`.

- **Classic has every flag off.** It's the baseline, so a feature's off branch
  must be the code that ran before the feature existed, unchanged. The classic
  golden master enforces that: a feature that disturbs classic fails it.
- **The engine branches on the flag at the point of difference**, not at the
  top of a page. A whole page can be swapped out only if the page really is
  wholly different.
- **A formula moves into a per-ruleset function only when it differs
  completely**, such as a combat model that shares nothing with v6's. Until
  then, a flag and a branch are cheaper to read. This codebase has very few
  functions, and a hook framework built in advance would be the most
  complicated thing in it.

## Which ruleset a world runs

**A world is a round**, and every round shares one database with a
`round_id` ([rounds.md](rounds.md), "Where a round lives" — decided
2026-10-18, because the host cannot create a database from a script). An
earlier draft of this section made each world its own database, so that
`world_id` never reached a query; the host ruled that out.

**The world records its ruleset**, in a new `game_info.ruleset` column, not
in the environment. `game_info` is per-round — it gains a `round_id` like
every other round table — so the column is the round's ruleset. The pages and
the tick have to agree, and a deploy typo must not be able to boot a classic
world under modern rules. Since the round's rows hold the world, they say
what the world is.

That changes the load order. `connect.php` requires `gamedata.php` *before* it
connects today, because `$GAMEDATA` doesn't depend on anything. The loader has
to run after the connection opens and the round is known, read
`game_info.ruleset`, and only then
build `$GAMEDATA`. Everything that reads `$GAMEDATA` already includes
`connect.php`, so the readers don't change.

**An unknown or empty ruleset refuses to run.** It doesn't fall back to classic.
A fallback would turn a misconfigured modern world into a classic one with no
error. The tick in particular has to fail loudly, because a tick that runs
under the wrong rules can't be undone.

**A world's ruleset is fixed for the length of a round.** Changing it
mid-round changes the rules under players who planned for the old ones.
Running another ruleset means opening a new round. It is not an admin toggle.

**Accounts are shared across worlds.** This was going to be "accounts belong
to a world", with one signup per ruleset. [rounds.md](rounds.md) replaces
that: an account lives in a shared `mb_accounts` database, and each round
holds at most one kingdom of that account. That design is what lets
a reset stop deleting players.

## Testing

**Each ruleset has its own golden master.** `tests/golden/tick.txt` becomes
`tick-classic.txt`, unchanged, and `tick-modern.txt` joins it. Both are
produced from the same fixture world. `tick-golden.sh --ruleset modern` sets
`game_info.ruleset` after the reset, so the two goldens differ only where the
rules do. That difference is itself a reviewable statement of what modern
changes. `tests/golden/combat.txt` splits the same way, and its scenarios set
the ruleset after each fixture reload.

**The two goldens have different re-accept rules**, and this is how classic is
kept from drifting:

| Golden | May be re-accepted for | The commit must |
| --- | --- | --- |
| `tick-classic.txt` | a bug fix only | remove the `$GAMEDATA['quirks']` entry it fixes, or name the bug |
| `tick-modern.txt` | a bug fix or a balance change | say which values moved and why |

A classic re-accept with no quirk leaving the list is a balance change in the
wrong ruleset.

**The smoke crawl runs once per ruleset.** Flags change what pages render, so a
page broken only under modern needs a crawl under modern to find it. Each
ruleset gets its own baseline. The audits (injection, XSS, register globals)
read source, not a running world, so they run once.

**The manual renders from the active ruleset**, which it nearly does already:
`manual.php` reads `$GAMEDATA` throughout. What it doesn't do yet is compute its
worked examples. "At 250 land that is…" is classic's arithmetic written out by
hand, and under modern it would be wrong. Those examples have to be computed
from the values before the manual can be trusted for a second ruleset.

## What has to happen first

**1. Finish moving the engine's values into gamedata.** This is the
prerequisite that matters. Any value still written as a literal in the engine
is a classic value that modern can't override, and nothing reports it: the
overlay sets the key, the engine never reads it, and modern quietly plays
classic in that one respect. As of this writing the economy rates, timers,
eight of the fourteen score weights, the Demon's two deltas and every race and
class off/def modifier are read from `$GAMEDATA`. The rest are still literals with citations. Many of them can't be
moved safely yet, because the fixture can't see them (see "Invert the manual
dependency" in [modernization.md](modernization.md)).

**2. Broaden the fixture.** A move is only proven if perturbing the value fails
a test. The other six score weights and most race and class modifiers wait for
a fixture empire that holds the units or plays the combination. Combat no
longer waits: `tests/combat-golden.sh` attacks with every unit type at a scale
where a one-point change shows, and once as each class and modified race. Every
combat constant and combat modifier it was checked against fails it when
moved (see [testing.md](testing.md)). It also pins each combat quirk, so the
quirks can now be fixed one at a time, each as its own reviewable diff.

**3. The loader.** Move `gamedata.php` to `rulesets/classic.php`, add
`game_info.ruleset`, write the loader and `mb_ruleset_overlay()`, and add the
per-ruleset golden and crawl. Up to this point classic is the only ruleset and
nothing observable changes. The classic golden master proves that.

**4. Rounds.** These were going to be "ages", modern's first feature. They
aren't a ruleset feature: classic needs to end and start again too, and today
it can only do that by deleting its players. [rounds.md](rounds.md) has the
design. Running two rulesets side by side is two rounds open at once, so
step 3's loader and rounds' concurrent step land together. Everything else in
modern (protection, balance, the mobile UI) comes after.

Steps 1 and 2 are Phase 3 work anyway, and step 3 is small once they're done.

## Open questions

- **How far can modern drift before an overlay stops making sense?** Balance
  changes and features overlay cleanly. The v7 design's new races, mana economy
  and skill system don't. Those would be a different game sharing an engine,
  and past some point it's cheaper for modern to be a complete file than a
  delta on classic. That decision can wait until a feature forces it.
- **Do the two rulesets share UI and theme?** Nothing here requires them to. A
  phone-first UI for modern alone would be a feature flag like any other, but
  classic players would probably want it too.
