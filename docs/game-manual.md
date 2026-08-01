# The game manual

`app/manual.php` renders the manual; `app/include/gamedata.php` holds every
number in it. The old static `app/manual.html` is gone — it had drifted so far
from the engine that a third of its claims were wrong (inverted race modifiers,
an auction system that does not exist, a 10% resource steal that is actually
8%).

## The one rule

**`gamedata.php` is the only place a game value may be written, and every value
carries a `file:line` citation** to the engine code it was read from.

`manual.php` computes its prose from those values rather than restating them — a
race's "+10% civilian growth" is rendered as the resulting rate next to the base
rate, because the engine's modifiers are *additive on small bases* and the
percentage phrasing is what made the old manual wrong (Night Elf's advertised
"−25% civilian growth" is `0.26 − 0.25`, a 96% cut).

## Two rules when touching game logic

- Change a cited line in the engine, and the matching entry in `gamedata.php`
  must change with it. The citations exist so this is a grep, not an audit.
- Where the engine is plainly buggy, `gamedata.php` records **what it does, not
  what it meant**, and the discrepancy goes in `$GAMEDATA['quirks']` — which the
  manual publishes. That list doubles as a Phase 3 checklist; fixing an engine
  bug means deleting its quirk entry.

Phase 3 should invert the dependency: have the engine read `$GAMEDATA` instead
of its inline literals, after which the manual cannot go stale at all.

## Constraints

`manual.php` is reachable logged out (the login page links it), so it must never
include `igtop.php` or touch the session. It is crawled by `smoke.php`.

## `docs/v7-design-notes.md` is not documentation of this game

It records the design on the project's GitHub wiki, which describes a
**different, unbuilt game** — different races and classes, a skill tree, mana,
unit upkeep, typed magic resistances. None of it exists in `app/`. It is kept
for reference only; **never answer a question about game behaviour from it.**
