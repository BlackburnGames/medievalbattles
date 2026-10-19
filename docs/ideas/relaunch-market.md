# Relaunch ideas: fitting the game to today's market

**Status: ideas, not the plan.** Nothing here is decided, scheduled or built.
It is a brainstorm about what a seasonal PvP browser game needs to hold players
in 2026, kept apart from the real plan so it is not mistaken for it. The plan
is [modernization.md](../modernization.md); the decided design for the
relaunch's rules is [rulesets.md](../rulesets.md). If an idea below is taken up,
it moves into one of those and out of here.

## The premise holds up

Medieval Battles is a seasonal PvP game: a round opens, kingdoms grow and fight,
the round ends and a winner is recorded. That format has not aged. Seasons are
now the standard shape for live games, and Utopia and Travian still run on
nearly this design more than twenty years on. What has aged is everything
around the format.

## Who it is for

Not the mobile 4X market (Rise of Kingdoms, Whiteout Survival and the like).
Those are pay-to-win games with advertising budgets this project will never
have. The realistic audience is a niche:

- players of this genre from the 2000s, and
- strategy players tired of the pay-to-win model.

To them, a game that is fair, free and needs no download is the selling point.
Most of the ideas below exist to keep that audience once it arrives.

## What has aged, and ideas for each

### 1. Losing everything while you are away

The biggest reason players quit tick-based PvP. You log off, someone attacks you
all night, and your round is over. Players today will not put up with it.

- **Attack ranges by size.** A kingdom may attack only targets near its own
  size, as Utopia does. Stops the large hitting the small for free.
- **A limit on hits taken.** A kingdom can be hit only a few times a day, with
  a cooldown after each.
- **Protection for new and late joiners**, and some way for a late joiner to
  catch up rather than start hopelessly behind. The game already has half of
  this: a new kingdom starts in safe mode for 48 ticks, a day
  (`safemode_ticks`), during which it can neither attack nor be attacked.
  What it lacks is the catch-up, and anything after that first day — once
  safe mode runs out, any kingdom can hit any other, of any size, as often as
  it has the army for.

### 2. Runaway leaders

v6 armies cost nothing to keep, so the biggest kingdom only gets bigger.

- **Unit upkeep**, from [v7-design-notes.md](../v7-design-notes.md): armies
  cost food and gold each tick, with debt and desertion if unpaid. Making
  players choose what to keep is the point.
- **Diminishing returns** on growth as a kingdom gets larger.

### 3. Too few players

PvP needs people online. A half-empty world dies quickly, and an empty one
never starts.

- **Small worlds** of 50–150 kingdoms rather than one large one.
- **Scheduled starts**, so a round's players all join together.
- **Computer-run kingdoms** to fill a world out, and to give new players safe
  targets to learn on.
- **Shorter rounds**: two to four weeks, not months.

### 4. Nothing carries over between rounds

Players expect a reason to come back for the next one.

- **An account rank or record** that lasts across rounds.
- **Titles and cosmetics** for where you placed.
- **A history of past rounds** on the account. `round_result` is already the
  start of this.

### 5. The time it takes to play

It has to work in short sessions, on a phone.

- **A layout that works on a phone.**
- **Push notifications** — "you were attacked", "construction finished" —
  through an installable web app (PWA).
- **Fewer pages to click through**, with the common actions in one place.

### 6. Learning the game

v6 does not explain itself: the tick, land and mountains, and research are
learned by trial and error.

- **A guided first few ticks.**
- **Suggested starting choices** of race, class and build.
- **Hints on each page**, such as "you are short on food: build farms".
- **Fewer systems** in the modern ruleset — merge iron and lumber, say, or cut
  seven classes to three or four that are clearly different. Note that the v7
  design goes the other way: it adds mana, a skill tree, five resistances and
  more units. Take its ideas one at a time, not as a package.

### 7. Community

For this genre, guilds and a Discord server are the community.

- **Guild chat, guild goals and guild-vs-guild standings** matter more than any
  single feature.
- **A Discord server** from day one.

### 8. Money, if it is ever wanted

This audience leaves at the first sign of pay-to-win.

- **Cosmetics.**
- **A season pass** with no effect on play.
- **A small subscription** for convenience features only.

## A possible order

If these were taken up, this is the order that would matter most:

1. **Offline protection and attack ranges.** Without them nothing else counts,
   because a new player quits after the first night.
2. **Phone layout and notifications.**
3. **Short, scheduled rounds with a record that carries over.** Built on the
   existing rounds system.
4. **Computer-run kingdoms**, before any marketing.
5. **Simplified systems in the modern ruleset**, alongside classic as
   [rulesets.md](../rulesets.md) already intends.

Most of this extends the Phase 3 work — rounds, accounts and the ruleset
design — rather than replacing it.
