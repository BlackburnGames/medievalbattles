<?php
/**
 * Canonical game data for Medieval Battles v6.
 *
 * Single source of truth for every number the game manual publishes. Each
 * value is transcribed from the engine and cited with the source file and
 * line it came from, so a change to the engine can be traced back here.
 *
 * The long-term goal (Phase 3) is to make the engine read these values
 * instead of its inline literals, at which point the manual can no longer
 * go stale. Until then, this file is the review checklist: if a cited line
 * changes, the value here must change with it.
 *
 * Where the engine's behaviour is plainly a bug (an effect that can never
 * fire, an inverted modifier), the value recorded here is what the engine
 * DOES, not what it was meant to do, and the quirk is listed in
 * $GAMEDATA['quirks'] so players and maintainers see the same truth.
 */

$GAMEDATA = array();

/* ------------------------------------------------------------------ *
 * Economy base rates -- the per-tick modifiers before race, class or
 * research adjustments.
 *
 * THE FIRST BLOCK THE ENGINE READS. update.php takes the ten values below
 * rather than carrying its own copies, so there is nothing here to keep in
 * step: change one and the tick changes with it, which the golden master will
 * report. The entries still name their reader, but the arrow points the other
 * way from the rest of this file -- these are definitions, not transcriptions.
 * ------------------------------------------------------------------ */
$GAMEDATA['economy'] = array(
    'gold_per_mine'     => 300,     // read by update.php: gp += (gm * 300) * gpmod
    'gpmod'             => 1.0,     // read by update.php: base gold modifier
    'iron_per_mine'     => 1.945,   // read by update.php
    'food_per_farm'     => 0.5,     // read by update.php
    'lumber_per_mill'   => 2.0,     // read by update.php
    'civ_growth_mod'    => 0.26,    // read by update.php: civ = round(homes * civmod)
    'civ_per_home'      => 20,      // read by update.php: overcrowding threshold
    'food_cap_per_farm' => 50,      // read by update.php: food above this decays
    'decay_factor'      => 0.97,    // read by update.php: overcrowd/starve/overflow
    'recruit_pool_rate' => 0.007,   // read by update.php: maxciv += .007 * civ
    // Not yet inverted -- still transcribed, still cited.
    'recruit_cost_gp'   => 150,     // military.php:41,48
    'troops_per_barrack' => 50,     // military.php:72, status.php:23
    'wp_defense'        => 15,      // functions.php:306, attack/defANDoff.php:68
    'catapult_defense'  => 30,      // functions.php:306, attack/defANDoff.php:68
    'golem_power'       => 38,      // functions.php:306, attack/defANDoff.php:66
    'irongolem_power'   => 50,      // functions.php:306, attack/defANDoff.php:66
);

/* ------------------------------------------------------------------ *
 * Buildings. Costs: functions.php:280-283, buildings.php:41-47.
 * Timing: buildings.php:79-100, update.php:441-461.
 * ------------------------------------------------------------------ */
$GAMEDATA['buildings'] = array(
    'cost_base'        => 300,    // functions.php:280-283: 300 + 4 * count
    'cost_per_owned'   => 4,      //   (count includes buildings under construction)
    'cost_cap'         => 20000,  // functions.php:281,283
    'demolish_cost'    => 75,     // dbuildings.php:41,55
    'ticks_per_building' => 1,    // buildings.php:94-100: queue of N takes N ticks
    'ticks_cap'        => 20,     // buildings.php:90: queues of 20+ take 20 ticks, scaled as the timer is
    'land'  => array(
        'Home'            => 'Houses your civilian population; each provides room for 20 civilians. Without homes there is no population, and without population there are no recruits.',
        'Barrack'         => 'Houses warriors, wizards, priests and archers -- 50 troops each (35 for Humans). With no free space you cannot train more of those units.',
        'Farm'            => 'Produces food each tick. Civilians above your food supply starve: the population shrinks 3% per tick until it fits.',
        'Wooden Platform' => 'A defensive tower. Each adds 15 points to your empire defense. Build them in quantity or not at all.',
        'Lumber Mill'     => 'Produces lumber each tick. Requires Archery research before it can be built.',
    ),
    'mountain' => array(
        'Gold Mine' => 'Produces gold pieces, the currency of Medieval Battles. Output improves with Advanced Gold Mining.',
        'Iron Mine' => 'Produces iron, needed for weapons and armor. Output improves with Advanced Iron Mining.',
    ),
);

/* ------------------------------------------------------------------ *
 * Exploring. Every value here is a definition, read by mb_explore() in
 * include/exploring.php -- the one calculation the tick applies and the
 * explore page shows -- along with each race's explore_exp_num below. The page
 * used to carry its own copy with the Angel and Demon rates in it, which the
 * tick never applied; the page's numbers were taken as the design.
 * ------------------------------------------------------------------ */
$GAMEDATA['exploring'] = array(
    'exp_num_default' => 0.9,   // read by mb_explore_rate(): races without their own
    'divisor'         => 20,    // read by mb_explore(): explorers per land = floor(round(land * rate) / 20), never below 1
    'max_gain_per_tick' => 20,  // read by mb_explore()
);

/* ------------------------------------------------------------------ *
 * Races. Deltas are applied on top of the economy base, mirroring the
 * engine's order (class first, then race). update.php:103-160.
 * Off/def modifier: offdef_delta here and the classes' offdef_mult are read by
 * include/modifier.php -- each side of a battle, and the status screen.
 *
 * Two entries are definitions: the Demon's gold and iron deltas, which
 * update.php reads. They are the only race or class economy values the tick
 * golden master computes -- the fixture's other empires are a Human, whose
 * one tick-side rate needs explorers in the field, and a Giant whose food
 * delta rounds away on a single farm -- so everything else here is still
 * transcribed and cited. See docs/modernization.md.
 * ------------------------------------------------------------------ */
$GAMEDATA['races'] = array(
    'Angel' => array(
        'flavor'   => 'Charged with guiding the younger races, the Angels wage a reluctant war to preserve the world.',
        'civ_mod_delta'    => +0.10,  // update.php:126
        'priest_cost_mult' => 0.75,   // functions.php:249
        'explore_exp_num'  => 0.85,   // read by mb_explore_rate() -- fewer explorers needed per land
        'bans'     => array('Golems and Iron Golems'),  // animate.php:4, trade.php:4
    ),
    'Demon' => array(
        'flavor'   => 'Cast out of the Angelic Order, the Demons enslave the weak and wage war on the strong.',
        'gpmod_delta'       => +0.5,   // read by update.php
        'ironmod_delta'     => +0.5,   // read by update.php
        'civ_mod_delta'     => -0.10,  // update.php:144 -- masked by overcrowding in the fixture
        'offdef_delta'      => +0.10,  // read by include/modifier.php
        'warrior_cost_mult' => 0.75,   // functions.php:248
        'explore_exp_num'   => 0.95,   // read by mb_explore_rate() -- more explorers needed per land
        'bans'     => array('Priests', 'Priest weapons'),  // military.php:121, wconstruct.php:433+
    ),
    'Dwarf' => array(
        'flavor'   => 'Proud miners and craftsmen of metal, hardy in battle and unmatched in fury.',
        'gpmod_delta'   => +0.15,  // update.php:130
        'ironmod_delta' => +0.15,  // update.php:131
        'bans'     => array(),
    ),
    'Elf' => array(
        'flavor'   => 'Swift and long-lived, the Elves strike quickly and are home before the alarm is raised.',
        'civ_mod_delta'    => -0.15,  // update.php:148
        'return_time_mult' => 0.80,   // attack/calculations.php:98-101
        'bans'     => array(),
    ),
    'Giant' => array(
        'flavor'   => 'Enormous strength and appetite; no patience for the subtleties of magic or faith.',
        'foodmod_delta' => +0.10,  // update.php:152
        'offdef_delta'  => +0.25,  // read by include/modifier.php
        'bans'     => array('Wizards', 'Priests', 'Wizard spells (research)', 'Priest weapons', 'Golems and Iron Golems'),
        // military.php:116, cresearch.php:27-86, wconstruct.php:430+, animate.php:4
    ),
    'Human' => array(
        'flavor'   => 'Greatly varied and curious; they learn fast and spread faster.',
        'explore_exp_num'  => 0.8,  // read by mb_explore_rate() -- fewer explorers needed per land
        'troops_per_barrack' => 35, // military.php:73, status.php:24
        'bans'     => array(),
    ),
    'Night Elf' => array(
        'flavor'   => 'Secretive cousins of the Elves, masters of the wood by darkness.',
        'foodmod_delta' => +0.25,  // update.php:156
        'lumbmod_delta' => +0.10,  // update.php:157
        'civ_mod_delta' => -0.25,  // update.php:158
        'offdef_delta'  => -0.10,  // read by include/modifier.php
        'return_time_mult' => 0.75,   // attack/calculations.php:102-105
        'build_time_mult' => 2,    // buildings.php:87
        'starts_with'   => array('4-10 Archers'),  // checksignup.php:130
        'bans'     => array(),
    ),
);

/* ------------------------------------------------------------------ *
 * Classes. update.php:103-122 (economy), :243-245 (research speed),
 * offdef_mult read by include/modifier.php, functions.php:243-252 (unit costs),
 * checksignup.php:113-138 (signup bonuses).
 * ------------------------------------------------------------------ */
$GAMEDATA['classes'] = array(
    'Cleric' => array(
        'flavor'  => 'Great faith leads to a path of holiness.',
        'perks'   => array('Noticeably better starting resources and troops at signup'),  // checksignup.php:113-125
        'exclusive_gear' => array('Flail of Isidole', "Eldamar's Star", 'Blessed Armor'),
    ),
    'Fighter' => array(
        'flavor'  => 'The warrior goes forth to bring destruction with fist and sword.',
        'gpmod'        => 0.95,  // update.php:105
        'offdef_mult'  => 1.05,  // read by include/modifier.php
        'exclusive_gear' => array("Toledo's Broad Sword", 'Sword of Gandalara', 'Medieval Armor'),
    ),
    'Insurrectionist' => array(
        'flavor'  => 'Fire and blasting powder answer questions steel cannot.',
        'gpmod'        => 0.90,  // update.php:121
        'offdef_mult'  => 1.05,  // read by include/modifier.php
        'suicide_cost_mult' => 0.50,  // functions.php:252
        'starts_with'  => array('Demolitions researched', '10-20 Suicide Civilians'),  // checksignup.php:137
        'bans'    => array('Wizards', 'Golems and Iron Golems'),  // military.php:91, animate.php:4
    ),
    'Mage' => array(
        'flavor'  => 'The power of your mind will bring your enemies to their knees.',
        'gpmod'        => 1.10,  // update.php:109
        'offdef_mult'  => 0.95,  // read by include/modifier.php
        'perks'   => array('Only class shown the top three spells: Cloud Kill, Lightning Bolt, Meteor Swarm'),  // S_RES.php:60
        'exclusive_gear' => array('Mythril Armor'),
    ),
    'Ranger' => array(
        'flavor'  => 'Wander the wilds and hunt your foes mercilessly.',
        'lumbmod_delta' => +0.10,  // update.php:113
        'starts_with'  => array('Archery researched', '4-10 Archers'),  // checksignup.php:133
        'bans'    => array('Wizards', 'Golems and Iron Golems'),  // military.php:86, animate.php:4
        'exclusive_gear' => array('HeartSong', "Shyrscream's Bow"),
    ),
    'Savant' => array(
        'flavor'  => 'Gold flows to those who understand its currents.',
        'gpmod'        => 1.50,  // update.php:117
        'offdef_mult'  => 0.80,  // read by include/modifier.php
        'research_mult' => 1.10, // update.php:243 -- research points accrue 10% FASTER
    ),
    'Warlock' => array(
        'flavor'  => 'Forbidden shortcuts to power, at a price paid later.',
        'offdef_mult'   => 0.95,  // read by include/modifier.php
        'wizard_cost_mult' => 0.75,  // functions.php:250
        'research_mult' => 0.75,  // update.php:244 -- research points accrue 25% SLOWER
        'starts_with'  => array('Fireball researched'),  // checksignup.php:135
        'note' => 'A Demon Warlock pays 1.05x for warriors instead of the Demon 0.75x discount.',  // functions.php:251
    ),
);

/* ------------------------------------------------------------------ *
 * Units. Costs: functions.php:243-277, military.php:106,133.
 * Training times: update.php:511 queue depths.
 * Barracks space: military.php:71-73,126-132.
 * ------------------------------------------------------------------ */
$GAMEDATA['units'] = array(
    // name => [description, cost description, ticks to train, uses barracks space]
    'Sage' => array(
        'desc' => 'Researchers. Each sage assigned to a project adds 1 research point per tick (Savants 1.1, Warlocks 0.75). Sages are not consumed by research.',
        'cost' => '500 gp + 1 recruit', 'ticks' => 2, 'barracks' => false,
    ),
    'Thief' => array(
        'desc' => 'Gather intelligence on other empires, and defend against enemy thieves: a spy attempt only succeeds if it sends at least as many thieves as you own.',
        'cost' => '200 gp + 1 recruit', 'ticks' => 2, 'barracks' => false,
    ),
    'Explorer' => array(
        'desc' => 'Sent out to claim new land or mountains each tick. They stay in the field, producing every tick, until recalled.',
        'cost' => '1,000 gp + 0.875 per explorer owned (max 10,000) + 1 recruit', 'ticks' => 2, 'barracks' => false,
    ),
    'Warrior' => array(
        'desc' => 'The backbone of most armies. Attack and defense power come entirely from the equipped weapon and armor.',
        'cost' => '500 gp + 0.8 per warrior owned (max 10,000; Demons pay 3/4) + 1 recruit', 'ticks' => 2, 'barracks' => true,
    ),
    'Wizard' => array(
        'desc' => 'Fight with researched spells, and are the raw material for golems. Unavailable to Giants, Rangers and Insurrectionists.',
        'cost' => '400 gp + 0.7 per wizard owned (max 10,000; Warlocks pay 3/4) + 1 recruit', 'ticks' => 2, 'barracks' => true,
    ),
    'Priest' => array(
        'desc' => 'Cheap early fighters with their own weapon line. Unavailable to Demons and Giants.',
        'cost' => '100 gp + 0.65 per priest owned (max 10,000; Angels pay 3/4) + 1 recruit', 'ticks' => 2, 'barracks' => true,
    ),
    'Archer' => array(
        'desc' => 'Unlocked by Archery research. Sturdy (the highest hit points of the four barracks units) with strong late-game bows.',
        'cost' => '450 gp + 0.75 per archer owned (max 10,000) + 1 recruit', 'ticks' => 2, 'barracks' => true,
    ),
    'Catapult' => array(
        'desc' => 'Pure defense: each adds 30 points to your empire defense but cannot be sent on attacks. Unlocked by Archery.',
        'cost' => '5,000 gp + 200 lumber + 1 recruit', 'ticks' => 3, 'barracks' => false,
    ),
    'Suicide Civilian' => array(
        'desc' => 'Unlocked by Demolitions. Sent on suicide attacks, each kills 2 enemy civilians or thieves and is lost forever.',
        'cost' => '5,000 gp (Insurrectionists 2,500) + 1 recruit', 'ticks' => 3, 'barracks' => false,
    ),
    'Golem' => array(
        'desc' => 'Unlocked by Animation. Two wizards animate into one golem: power 38, speed 20. Unavailable to Angels, Giants, Rangers and Insurrectionists.',
        'cost' => '5,000 gp + 0.5 per golem owned (max 20,000) + 2 wizards', 'ticks' => 1, 'barracks' => false,
    ),
    'Iron Golem' => array(
        'desc' => 'Unlocked by Advanced Animation. Two wizards and a shell of iron: power 50, speed 25.',
        'cost' => '5,000 gp + 2 per iron golem owned (max 30,000) + 200 iron + 2 wizards', 'ticks' => 1, 'barracks' => false,
    ),
);

$GAMEDATA['disband'] = array(
    'cost_gp'  => 200,  // disband.php:42,58
    'returns'  => '1 recruit per unit',  // disband.php:62
    'excluded' => array('Catapults', 'Suicide Civilians', 'Golems', 'Iron Golems'),
);

/* ------------------------------------------------------------------ *
 * Weapons and armor. Stats: equip.php:147-231,297-364. Costs and
 * construction times: wconstruct.php / aconstruct.php (lines cited in
 * the extraction notes). "EXP granted" is added to your construction
 * experience when the item is finished; there is no experience
 * REQUIREMENT to build anything -- items unlock in construction order.
 * rows: name, speed, power/def, exp granted, gold, iron-or-lumber, ticks, restriction
 * ------------------------------------------------------------------ */
$GAMEDATA['warrior_weapons'] = array(
    //  name                  spd pwr exp     gold     iron   ticks  restriction
    array('Dagger',             6,  2, 0,       0,       0,     0,  ''),
    array('Short Sword',        8,  4, 5000,    300000,  5000,  8,  ''),
    array('Long Sword',         9,  6, 8000,    500000,  8000,  14, ''),
    array('Bastard Sword',      11, 8, 16000,   600000,  12000, 20, ''),
    array('Scourge',            13, 10, 24000,  750000,  17000, 25, ''),
    array('Scimitar',           14, 12, 47000,  900000,  26000, 30, ''),
    array("Rom's Fury",         16, 14, 68000,  1000000, 32000, 36, ''),
    array("Toledo's Broad Sword", 17, 16, 89000, 2000000, 60000, 42, 'Fighter'),
    array('Sword of Gandalara', 20, 18, 105000, 3000000, 75000, 48, 'Fighter'),
);

$GAMEDATA['priest_weapons'] = array(
    array('Quarterstaff',       4,  2, 0,       0,       0,     0,  ''),
    array('Mace',               5,  4, 5000,    200000,  4000,  8,  ''),
    array('Flail',              7,  5, 10000,   350000,  8000,  12, ''),
    array('Scepter of Zakarum', 8,  6, 20000,   500000,  12000, 18, ''),
    array("Footman's Flail",    9,  8, 35000,   600000,  17000, 22, ''),
    array('Morning Star',       12, 10, 50000,  750000,  29000, 28, ''),
    array("Thyora's Tear",      14, 13, 70000,  900000,  30000, 30, ''),
    array('Flail of Isidole',   15, 14, 85000,  1000000, 40000, 34, 'Cleric'),
    array("Eldamar's Star",     18, 15, 100000, 2000000, 50000, 36, 'Cleric'),
);

$GAMEDATA['archer_weapons'] = array(
    // archer weapons cost LUMBER, not iron
    array('Bow',                4,  2, 0,       0,       0,     0,  ''),
    array('Short Bow',          6,  5, 5000,    400000,  6000,  8,  ''),
    array('Ferric Bow',         8,  7, 15000,   550000,  10000, 12, ''),
    array("Keldar's Arms",      9,  8, 30000,   600000,  14000, 15, ''),
    array("Splen's Sight",      11, 10, 40000,  800000,  20000, 18, ''),
    array('Bow of Tion',        13, 12, 60000,  950000,  26000, 26, ''),
    array('The Dynefian',       14, 14, 75000,  1000000, 32000, 30, ''),
    array('HeartSong',          15, 15, 90000,  1500000, 45000, 38, 'Ranger'),
    array("Shyrscream's Bow",   16, 16, 100000, 2000000, 60000, 42, 'Ranger'),
);

// Spells cost nothing to equip; they unlock purely by research points.
// equip.php:174-184, S_EQUIPO.php:45-55.
$GAMEDATA['spells'] = array(
    // name, speed, power, research points required
    array('Magic Missile',   4,  3,  0),
    array('Fireball',        6,  5,  50000),
    array('Ice Storm',       7,  7,  100000),
    array('Wall of Fire',    8,  9,  200000),
    array('Wall of Ice',     9,  11, 300000),
    array('Chain Lightning', 10, 13, 400000),
    array('Gust of Wind',    11, 15, 500000),
    array('Flaming Sphere',  12, 17, 600000),
    array('Cloud Kill',      13, 19, 700000),
    array('Lightning Bolt',  14, 21, 800000),
    array('Meteor Swarm',    15, 23, 900000),
);

$GAMEDATA['armor'] = array(
    // name, wearer, speed, defense, exp granted, gold, iron, ticks, restriction
    array('Studded Leather', 'Warrior & Archer', 0, 1, 0,      0,       0,     0,  ''),
    array('Chain Shirt',     'Warrior & Archer', 1, 3, 10000,  125000,  10000, 4,  ''),
    array('Chain Mail',      'Warrior & Archer', 2, 5, 20000,  200000,  20000, 12, ''),
    array('Breast Plate',    'Warrior & Archer', 3, 6, 40000,  450000,  35000, 20, ''),
    array('Medieval Armor',  'Warrior & Archer', 5, 9, 60000,  1000000, 60000, 28, 'Fighter'),
    array('Leather',         'Priest',           1, 2, 0,      0,       0,     0,  ''),
    array('Golden Armor',    'Priest',           3, 4, 30000,  300000,  25000, 14, ''),
    array('Blessed Armor',   'Priest',           5, 6, 60000,  1500000, 55000, 34, 'Cleric'),
    array('Robe',            'Wizard',           0, 1, 0,      0,       0,     0,  ''),
    array('Travellers Robe', 'Wizard',           1, 3, 20000,  1000000, 0,     10, ''),
    array('Magicians Robe',  'Wizard',           2, 5, 35000,  3000000, 0,     20, ''),
    array('Mythril Armor',   'Wizard',           3, 8, 50000,  5000000, 40000, 30, 'Mage'),
);

/* ------------------------------------------------------------------ *
 * Research. Points: research_queries.php, research.php:26-80,
 * update.php:243-247. Effects: update.php:161-175, buildings.php:85,
 * military.php:76-85, animate.php:12.
 * ------------------------------------------------------------------ */
$GAMEDATA['research'] = array(
    // name, points, prerequisite, effect, availability
    array('Fireball',             50000,  '',                'Unlocks the Fireball spell (speed 6, power 5)', 'All who can field wizards'),
    array('Ice Storm',            100000, 'Fireball',        'Unlocks Ice Storm (7 / 7)', ''),
    array('Wall of Fire',         200000, 'Ice Storm',       'Unlocks Wall of Fire (8 / 9)', ''),
    array('Wall of Ice',          300000, 'Wall of Fire',    'Unlocks Wall of Ice (9 / 11)', ''),
    array('Chain Lightning',      400000, 'Wall of Ice',     'Unlocks Chain Lightning (10 / 13)', ''),
    array('Gust of Wind',         500000, 'Chain Lightning', 'Unlocks Gust of Wind (11 / 15)', ''),
    array('Flaming Sphere',       600000, 'Gust of Wind',    'Unlocks Flaming Sphere (12 / 17)', ''),
    array('Cloud Kill',           700000, 'Flaming Sphere',  'Unlocks Cloud Kill (13 / 19)', 'Mage'),
    array('Lightning Bolt',       800000, 'Cloud Kill',      'Unlocks Lightning Bolt (14 / 21)', 'Mage'),
    array('Meteor Swarm',         900000, 'Lightning Bolt',  'Unlocks Meteor Swarm (15 / 23)', 'Mage'),
    array('Advanced Gold Mining', 100000, '',                'Each gold mine produces 30 more gold per tick', 'All'),
    array('Advanced Iron Mining', 100000, '',                'Each iron mine produces 0.1 more iron per tick', 'All'),
    array('Archery',              125000, '',                'Unlocks Archers, Catapults, Lumber Mills, and archer equipment', 'All'),
    array('Demolitions',          125000, '',                'Unlocks Suicide Civilians', 'All'),
    array('Advanced Farming',     100000, '',                'Each farm produces 2.5 food per tick instead of 0.5', 'All'),
    array('Advanced Construction', 100000, '',               'Halves construction time, and the 20-tick cap with it', 'All'),
    array('Animation',            125000, '',                'Unlocks Golems', 'All who can field wizards'),
    array('Advanced Animation',   125000, 'Animation',       'Unlocks Iron Golems', 'All who can field wizards'),
);

/* ------------------------------------------------------------------ *
 * Combat. attack.php / attackr.php / attackm.php / scattack.php,
 * include/attack/defANDoff.php, include/attack/calculations.php.
 * ------------------------------------------------------------------ */
$GAMEDATA['combat'] = array(
    'generals'          => 4,     // db.sql user.fleets default; one per army in the field
    'resource_gain_pct' => 8,     // attackr.php:185-195 -- gold, iron, lumber AND civilians
    'mountain_gain_pct' => 10,    // attackm.php:116
    'land_gain_pct'     => 10,    // attack.php:111 -- when target has 200+ land
    'land_gain_flat'    => 10,    // attack.php:112 -- when target has 10-199 land
    'suicide_kills'     => 2,     // scattack.php:112,124 -- civilians or thieves per suicide civilian
    'elf_return_mult'   => 0.80,  // attack/calculations.php:98-101
    'night_elf_return_mult' => 0.75,  // attack/calculations.php:102-105
);

$GAMEDATA['intel'] = array(
    'success_rule'  => 'succeeds only if you send at least as many thieves as the target owns',  // intel.php:68
    'loss_fail_pct'    => 10,  // intel.php:71 -- of thieves sent
    'loss_success_pct' => 3,   // intel.php:80 -- of thieves sent
);

/* ------------------------------------------------------------------ *
 * Timers, protection, housekeeping.
 * ------------------------------------------------------------------ */
$GAMEDATA['timers'] = array(
    // Still transcribed: both are schema column defaults, and the tick only
    // counts them down -- there is no literal in the engine to invert.
    'safemode_ticks'     => 48,   // checksignup.php:140-141
    'activation_ticks'   => 48,   // db.sql emailvalidate.clock
    'inactivity_ticks'   => 336,  // db.sql user.countdown
    // Inverted.
    'message_ttl_ticks'  => 192,  // read by update.php -- messages and both news tables
    'exp_floor'          => 1000, // read by update.php
);

$GAMEDATA['guilds'] = array(
    'max_members'      => 15,   // guildconfig.php:45
    'max_name_length'  => 15,   // gc.php:133
);

// The settlement count lived here as 'count' => 30, disagreeing with the ten
// rows db.sql ships. It is the schema's now: see include/settlements.php.

/* ------------------------------------------------------------------ *
 * Experience values -- your score is recomputed from scratch every tick
 * as a snapshot of what you currently hold.
 *
 * Eight of the fourteen are definitions: update.php's experience
 * recalculator reads them. The other six are still transcribed and cited,
 * because no fixture empire holds those units -- the golden master multiplies
 * their weights by zero, so a transcription error there would pass, and
 * inverting them would be a claim rather than a check. manual.php renders
 * this array in order, so the two kinds stay interleaved.
 * ------------------------------------------------------------------ */
$GAMEDATA['exp_values'] = array(
    'Land'             => 8,   // read by update.php: user.land
    'Mountain'         => 5,   // read by update.php: user.mts
    'Recruit'          => 5,   // read by update.php
    'Sage'             => 10,  // read by update.php -- assigned sages only, see docs/modernization.md
    'Thief'            => 10,  // update.php:347
    'Explorer'         => 10,  // update.php:346 -- held, and out exploring
    'Warrior'          => 23,  // read by update.php: at home and in the field
    'Priest'           => 22,  // read by update.php: at home and in the field
    'Wizard'           => 20,  // read by update.php: at home and in the field
    'Archer'           => 26,  // read by update.php: at home and in the field
    'Suicide Civilian' => 28,  // update.php:350
    'Catapult'         => 33,  // update.php:351
    'Golem'            => 30,  // update.php:353
    'Iron Golem'       => 35,  // update.php:354
);

/* ------------------------------------------------------------------ *
 * Known engine quirks -- places where the code does not do what the
 * feature was clearly meant to do. Recorded so the manual tells the
 * truth and so Phase 3 has a checklist.
 * ------------------------------------------------------------------ */
$GAMEDATA['quirks'] = array(
    'The Scepter of Zakarum sets its speed on equip but never its power, so the listed power is not actually applied. (equip.php:199)',
    'Night Elves are dealt 4-10 archers at signup, but the free Archery research is immediately overwritten unless they are also Rangers. (checksignup.php:130-134)',
    'Sage accounting disagrees with itself about how many research projects there are. Points accrue across all eighteen, the assigned-sage totals on the status and research screens count the first sixteen, and your score counts only the first fourteen -- so a sage on Animation or Advanced Animation is invisible to both totals, and one on Advanced Farming or Advanced Construction is invisible to your score. (update.php:247 vs functions.php:240 and cresearch.php:112 vs update.php:345)',
);
