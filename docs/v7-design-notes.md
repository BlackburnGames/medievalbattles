# The unbuilt v7 design

## What this is, and why it is here

The [project wiki on GitHub](https://github.com/erunion/medievalbattles/wiki) documents a
version of Medieval Battles that **does not exist in this repository**. It is a design for
a substantially different game — different races, different classes, a skill system, a mana
economy — written up in some detail and then, as far as the code shows, never built.

This document preserves that design because it is the only surviving statement of where the
game was headed, and because it is genuinely a better game than v6 in several places worth
stealing from. It is recorded here rather than left on the wiki so that:

1. The wiki can be retired without losing the design.
2. Nobody mistakes it for documentation of the running game. It is not. Everything in
   `app/manual.php` describes what the engine does; everything here describes what it does
   not.

**Do not use this file to answer questions about how the game works.** For that, see
`app/manual.php` and its data file `app/include/gamedata.php`.

## How far apart the two are

Almost nothing survives the trip. A grep of `app/` for the wiki's names — Goblin, Shadow Elf,
Wood Elf, Beast Master, Necromancer, Paladin, Mana Spire, Hatchery, Chimera, Drake — returns
zero hits. The overlap between the shipped game and the design below is four race names
(Angel, Demon, Dwarf, Human), three class names (Cleric, Fighter, Mage, and Ranger), and the
concept of buildings sitting on land or mountains. Everything else is new.

| | v6 (this repo) | v7 (the wiki design) |
|---|---|---|
| Races | Angel, Demon, Dwarf, Elf, Giant, Human, Night Elf | Angel, Demon, Dwarf, Goblin, Human, Shadow Elf, Wood Elf |
| Classes | Cleric, Fighter, Insurrectionist, Mage, Ranger, Savant, Warlock | Beast Master, Cleric, Fighter, Mage, Necromancer, Paladin, Ranger |
| Class choice | Any class with any race | Each race permits a specific subset |
| Units | One shared roster (warrior, priest, wizard, archer, …) | Universal units + three units per race + units unlocked by class skills |
| Progression | Research points buy spells and empire upgrades | Research **plus** a 30-point skill tree per class |
| Combat stats | Attack, defense, speed | Attack, defense, return time, and five magic resistances |
| Resources | Gold, iron, lumber, food | Gold, iron, lumber, food, **mana** |
| Upkeep | None — units cost nothing to maintain | Per-tick upkeep in food, gold, iron or lumber; debt and desertion |
| Building time | 1 tick per building, capped at 20 | Fixed 20-tick batches, partial completion each tick |

The two ideas with the most leverage, if any of this is ever revisited, are **unit upkeep**
(v6 armies are free to keep, which is why the game degenerates into pure accumulation) and
**magic resistances** (v6 combat is a single scalar comparison with no counterplay).

---

## The design

What follows is the wiki's content, reorganised but not rewritten. Prose is the original
author's except where noted.

### Premise

> Medieval Battles is a free, browser based, multi-player online strategy text game set in an
> original medieval fantasy world.

### Races

The race is the base descriptor of an empire: it determines which units can be produced and
which classes may be chosen.

**Angel** — Charged with shaping the world and guiding the new races that have awoken in it,
they have been forced into war by the actions of the Demonic Order. Their intent is to
preserve the young world long enough for the mortal races to mature.
*Classes: Cleric, Fighter, Paladin, Ranger.*

**Demon** — Jealous of the honor given the Angelic Forces by the mortal races, they have
turned from their command to aid in the shaping of the world. Cast out of the Angelic Order,
they act to enslave the weaker races.
*Classes: Beast Master, Fighter, Mage, Necromancer, Ranger.*

**Dwarf** — The proud race of stout miners and craftsmen of metal are hardy in battle and
unmatched in fury. They dislike the use of 'unnatural' magic and can withstand most hardships
above those of other races.
*Classes: Beast Master, Fighter, Paladin, Ranger.*

**Goblin** — The cruel and cunning goblins live only to take the wooded realm of the Elves,
their sworn enemies. While not the strongest in magic or warfare, they multiply quickly and
swarm to overwhelm their opponents.
*Classes: Beast Master, Fighter, Mage, Ranger.*

**Human** — Greatly varied and curious, they learn fast and use that knowledge with cunning.
Their shorter life span has led to a vivacious culture that sees an individual's legacy as
important as the life lived.
*Classes: all seven.*

**Shadow Elf** — The secretive cousins of the Wood Elves are masters of dark magic. Hiding in
the shadows, they slip out to strike and fade away. Their wide acceptance of Necromancy is
cause for concern among those opposed to the Demonic invasion.
*Classes: Beast Master, Cleric, Mage, Necromancer, Ranger.*

**Wood Elf** — Determined to maintain a balance with nature, they keep to themselves when
possible. Their swift and deadly marksmen take out small parties of heavily armed warriors.
Their magical prowess has attracted the attention of the Angelic Forces, who see them as the
noblest of the mortal races.
*Classes: Beast Master, Cleric, Fighter, Mage, Ranger.*

### Classes

The class applies to you as the military leader, but grants abilities used by the empire as a
whole — the engine does not distinguish between the two.

- **Beast Master** — "A great love of the wilderness grants mysterious power over beasts."
  Magical skills derived from affinity with magical creatures; trains Dragons and Chimeras;
  broadly focused on physical combat.
- **Cleric** — "Great faith leads to a path of holiness." Defensive and healing magic.
  Strengths lie in limiting opponents' abilities rather than direct attack. Repels undead by
  force of will.
- **Fighter** — "The warrior goes forth to bring destruction with fist and sword." Completely
  dedicated to direct physical combat.
- **Mage** — "The power of your mind will bring your enemies to their knees." Elemental magic
  to decimate opposing forces, plus protection from the same. Must use strategy to strike at
  the best moment.
- **Necromancer** — "A twisted cleric that revels in death and darkness." Less destructive
  than a Mage; relies on poison, disease and fear. Raises undead.
- **Paladin** — "Honor and strength are in your grip." A holy knight blending Fighter and
  Cleric. Very balanced; not as destructive as a Fighter but holds its own against anything.
- **Ranger** — "Wander the wilds and hunt your foes mercilessly." Specialist in speed and
  stealth; tracks opponents with greater ease than any other class. Weak on defense but
  counters and recovers quickly.

### Skills

The most substantial addition, and the one with no v6 analogue at all.

Skills are abilities granted through training, paid for with **skill points (SP)** awarded at
experience milestones. Each skill costs one point, **cannot be unlearned**, and an empire can
hold at most **30 skill points ever** — after that, no more are earned.

Each class has **14 skills across two trees**, so no class can learn everything. More powerful
skills within a tree require a number of weaker skills first, which prevents cherry-picking
the best of both trees. An empire either spreads across both trees for flexibility or
specialises in one for mastery.

The tree names are themselves the design's flavour: Cleric has *Restoration* and *Faith*;
Beast Master has *Draconian Heritage* and *Call of the Wild*; Fighter has *Fury* and
*Discipline*; Mage has *Arcana* and *Destruction*; Necromancer has *Affliction* and
*Dominion*; Paladin has *Leadership* and *Faith*; Ranger has *Aspect of the Fox* and
*Discernment*.

Skill effects fall into recognisable categories, and several recur across classes (*Spellcraft*,
*Thick Skin*, *Force of Command*, *Revive*, *Brute Strength*):

- **Permanent passives** — increase troop attack, defense or speed; increase resistance to a
  damage school; increase the number of generals available.
- **Timed buffs and debuffs** — raise your own troops' defense/attack/speed for a short time,
  or reduce an enemy's; *Silence* prevents casting; *Chastise* halts an enemy's generals.
- **Direct damage** — typed by school (magic, fire, ice, lightning, poison), some with a
  chance of double damage or damage over time.
- **Unit unlocks** — *Summon Adept*, *Dragon Breeding*, *Chimera's Nest*, *Summon Revenant*,
  *Summon Spellbreaker*, *Summon Ghoul*, *Summon Acolyte*.
- **Resource and recovery** — *Life Tap* (sacrifice troops for mana), *Soul Drain* (steal
  mana), *Meditation* (channelled mana regeneration), *Rallying Cry* and *Revive* (recover
  dead or injured troops), *Reaper* (harvest the enemy's dead for yourself).
- **Intelligence** — the Ranger's *Discernment* tree is built around a "tracked" status: a
  successful intel marks one target, and *Familiar Terrain*, *Early Warning* and *True Aim*
  all key off it. *Cloak of Deception* and *Phantom* raise spy defense.

Two resource systems the skills imply but v6 does not have: **mana**, spent on casting and
regenerated over time, and **generals** as a limited commodity that some skills increase and
others consume (*Rampage* requires an extra general; *Defender of the Peace* rewards keeping
generals home).

### Units

> A single unit is generally equivalent to a single individual trained and equipped for a
> specific mode of combat. For all units, it takes a single tick for their training to
> complete. All units are *at home* or *defending* unless they are sent to attack.

**Stats:** RT (return time), ATK, DEF, and magic resistance per school — Magic (non-elemental),
Fire, Ice, Bolt, Poison — applied as a percentage reduction to damage taken. Two capability
flags: Spy and Explore.

**Costs:** an initial cost (gold, iron, lumber or mana) and, new to v7, a **per-tick upkeep**
(food, gold, iron or lumber). Failing to pay gold upkeep puts the empire into debt; failing to
feed the army kills or deserts a small percentage of units each tick.

**Universal units** — available regardless of race or class:

- *Recruits* — produced by homes, trained into everything else. No stats.
- *Scouts* — explore for land and mountains. Cannot fight.
- *Spies* — gather intelligence. Cannot fight.
- *Mercenaries* — hired rather than trained (no recruit cost), expensive to hire and keep, not
  intended as a main fighting force.

**Race units** — three per race, meant to be mixed. The design's own advice is that beginners
train even numbers of two types for a balance of return time, attack and defense.

| Race | Fast / cheap | Middle | Heavy |
|---|---|---|---|
| Angel | Seraphim — very fast, average stats, minor Magic and Bolt resist | Guardian — high defense, very low attack | Archangel — slower, strong stats, moderate Magic/Bolt resist; expensive |
| Demon | Fallen — fast, lesser stats, minor Fire resist | Serpent — moderate speed, average stats, minor Fire resist | Balrog — slow, very high attack, moderate Fire resist; very expensive |
| Dwarf | Axe Raider — fast, average stats | Hammer Thrower — slower, better defense, low attack | Berserker — very fast, better attack; expensive |
| Goblin | Archer — very fast, lower stats | Orc — moderate speed, better attack, lower defense | Troll — slower, strong attack, low defense |
| Human | Archer — very fast, lesser stats | Soldier — very fast, average stats | Trebuchet — very slow, better attack, low defense; expensive |
| Shadow Elf | Dark Archer — very fast, average stats, minor resist to *all* schools | Stalker — fast, stronger attack, lower defense | Wraith — moderate speed, better stats |
| Wood Elf | Stalker — fast, lower stats | Archer — very fast, better attack, average defense | Ballista — very slow, better stats; expensive |

**Class units** — unlocked by skills, often costing mana instead of recruits, and generally
carrying better-than-average magic resistance:

- Cleric: *Staff Adept* — slower, average stats, better resistance across all schools.
- Beast Master: *Drake* — fast, strong attack, low defense, exceptional Fire resist.
  *Chimera* — slower, strong attack, average defense, exceptional Fire resist.
- Necromancer: *Spellbreaker* — slower, lower stats, strong resistance to all schools.
  *Revenant* — slow, strong attack, low defense.
- Mage: *Revenant* and *Spellbreaker* also appear under the Mage's Destruction tree — the
  wiki is inconsistent about which class summons them.

### Buildings

Batches take a flat **20 ticks**, with a percentage completing each tick. Adding to a batch
under construction **resets the timer to 20** (v6 instead extends it by the amount added).

**On land** — 150 gold per building:

- *Home* — grows the civilian population and the recruit supply.
- *Barrack* — houses the primary fighting force; without space, training halts. **Capacity
  improves with empire experience**, which v6 does not do.
- *Farm* — feeds recruits and troops; insufficient food starves the army. **Output improves
  with empire experience.**
- *Guard Tower* — purely strategic defense (the v6 Wooden Platform).
- *Hatchery* — Beast Masters only; required to lay Dragon and Chimera eggs.

**On mountains** — 75 gold per building:

- *Gold Mine*, *Iron Mine*, *Lumber Mill* — as v6.
- *Mana Spire* — draws ambient magical energy into a form spell casters can use. The
  production building for the mana economy.

---

## Notes for anyone picking this up

- The design assumes several systems that would have to be built from nothing: mana as a
  resource, generals as a limited and modifiable commodity, per-tick upkeep, typed damage with
  resistances, timed buffs and debuffs with durations, and persistent per-target status
  ("tracked"). None of these have a hook in the v6 engine.
- The wiki gives no numbers anywhere — no costs, no stat values, no skill point milestones, no
  resistance percentages. Every table above is qualitative because the source is. Balancing
  would be from scratch.
- The two changes that would most improve v6 *without* adopting the rest are unit upkeep and
  typed resistances. Both are additive to the existing combat and economy code rather than
  replacements for it.
- v6's own combat is a single deterministic comparison of two scalars with no randomness (see
  `app/include/attack/defANDoff.php`). Anything in the skills list involving chance, duration
  or ordering has no place to live until that changes.
