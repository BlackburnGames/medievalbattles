<?php
/**
 * Golden master for combat: the three army attacks and the suicide attack.
 *
 * The tick golden master runs no attacks, and the crawl drives two -- enough
 * to prove the pages run and file their news, not to pin a single number the
 * combat formula produces. So the formula, and the quirks recorded against it
 * in $GAMEDATA['quirks'], had nothing that would notice them change. This is
 * that thing, and it is what has to exist before any of those quirks can be
 * fixed: a fix should show up here as a diff, and nothing else should.
 *
 * Each scenario adjusts the two armies with a few UPDATEs and posts the
 * attack; tests/lib/golden.php reloads the fixture around it and prints every
 * column that moved.
 *
 * The scenarios are chosen by branch, not by balance: every outcome of every
 * page, the land-gain brackets, the order land is taken in, and one scenario
 * per recorded quirk, so the bug is pinned as it is today. The `why` line on
 * each is printed with it, so the golden file reads without this one open.
 *
 *   bash tests/combat-golden.sh            compare against tests/golden/combat.txt
 *   bash tests/combat-golden.sh --accept   re-accept after an intended change
 *
 * It reloads the schema, so it destroys whatever world it runs against.
 */

require_once __DIR__ . '/lib/golden.php';

// The cast. Every scenario attacks from the tester; the target is PoorSerf
// unless the scenario says otherwise, because PoorSerf is guildless and in
// another settlement, so nothing refuses the attack before the formula runs.
const ATTACKER = 1;   // TestLord, Human, settlement 1, Testguild
const GUILDMATE = 2;  // IdleBaron, Demon, settlement 1, Testguild
const DEFENDER = 3;   // PoorSerf, Giant Ranger (defends at 1.25), settlement 2, no guild

/* ------------------------------------------------------------------ *
 * Scenario helpers. Each returns SQL; a scenario's `setup` is a list.
 * set() and researched() are in tests/lib/golden.php.
 * ------------------------------------------------------------------ */

/**
 * An army with every unit count given, so a scenario says exactly what it
 * fields. Anything left out is zero rather than whatever the fixture holds.
 */
function army($userid, array $units)
{
    $all = array('warriors' => 0, 'wizards' => 0, 'priests' => 0, 'archers' => 0,
                 'golem' => 0, 'irongolem' => 0, 'catapult' => 0);
    return set('military', $userid, array_merge($all, $units));
}

/**
 * The defender's land, laid out so taking it walks every step of
 * attack.php's order: open land first, then every building under
 * construction, then built ones. 35 plots, more than the 25 a 250-acre
 * empire loses; land-win-short-of-buildings is the case where they run out.
 */
function landLayout($userid)
{
    return set('buildings', $userid, array(
        'aland' => 10, 'dhome' => 3, 'dbarrack' => 0, 'dfarm' => 2, 'dwp' => 0, 'dlmill' => 0,
        'home' => 5, 'barrack' => 5, 'farm' => 5, 'wp' => 3, 'lmill' => 2,
    ));
}

/* ------------------------------------------------------------------ *
 * The scenarios.
 * ------------------------------------------------------------------ */

$A = ATTACKER;
$D = DEFENDER;

// The defender's standing army, used wherever a scenario does not care what
// it is: every unit type that has its own loss line, so the defender's side
// of calculations.php runs every branch.
$defenders = army($D, array('warriors' => 20, 'wizards' => 10, 'priests' => 10, 'archers' => 5,
                            'golem' => 2, 'irongolem' => 1, 'catapult' => 2));
$defenderStats = set('military', $D, array('wardef' => 2, 'wizdef' => 1, 'pridef' => 2, 'archdef' => 1));
// An attacker that wins against $defenders, and one that does not.
$strong = army($A, array('warriors' => 300, 'wizards' => 100, 'priests' => 50));
$weak   = army($A, array('warriors' => 3));

// The same at a scale where no constant can round away: a one-point change to
// any unit's power or hit points, or to any modifier, moves at least one
// number below. Every unit type on both sides, and enough watchposts to count.
// Checked by changing each such constant by one and watching this fail.
$bigDefence = array(
    army($D, array('warriors' => 200000, 'wizards' => 50000, 'priests' => 50000, 'archers' => 30000,
                   'golem' => 2000, 'irongolem' => 1000, 'catapult' => 5000)),
    $defenderStats, landLayout($D), set('buildings', $D, array('wp' => 2000)),
);
// Golems under 1,000, which is where the attacker's count used to stop being
// a number; golems-in-thousands covers past it.
$bigAttackers = array('warriors' => 600000, 'wizards' => 200000, 'priests' => 100000, 'archers' => 50000,
                      'golem' => 900, 'irongolem' => 900);
// Archers need Archery, and armour so they are not archers-without-armour,
// golems need both Animations.
$bigAttack = array(army($A, $bigAttackers), researched($A, array(13, 17, 18)), set('military', $A, array('archdef' => 1)));
$send = function (array $units) {
    $fields = array();
    foreach ($units as $unit => $n) {
        $fields['u' . rtrim($unit, 's')] = $n;
    }
    return $fields;
};

$SCENARIOS = array(

    /* ---- attack.php: land ---- */

    array(
        'name'  => 'land-win',
        'why'   => 'Land attack won against a 250-acre empire: 10% gain, taken from open land, then buildings under construction, then built ones.',
        'setup' => array($strong, $defenders, $defenderStats, landLayout($D)),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300, 'uwizard' => 100, 'upriest' => 50))),
    ),
    array(
        'name'  => 'land-win-large',
        'why'   => 'Every unit type on both sides, in the hundreds of thousands. The other scenarios are small enough that a one-point change to a constant rounds away; here it cannot.',
        'setup' => array_merge($bigAttack, $bigDefence),
        'requests' => array(array('attack.php', array('empvalue' => $D) + $send($bigAttackers))),
    ),
    array(
        'name'  => 'land-loss',
        'why'   => 'Land attack lost: three warriors against the standard defence, all three killed, and the general spent.',
        'setup' => array($weak, $defenders, $defenderStats),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 3))),
    ),
    array(
        'name'  => 'land-win-small-target',
        'why'   => 'Under 200 acres the gain is a flat 10, not 10%.',
        'setup' => array($strong, $defenders, $defenderStats, set('user', $D, array('land' => 50))),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'land-win-destroys',
        'why'   => 'Under 10 acres the attacker takes it all and the defender is removed (include/empire.php).',
        'setup' => array($strong, $defenders, $defenderStats, set('user', $D, array('land' => 8))),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'land-win-short-of-buildings',
        'why'   => 'A defender whose land outnumbers its plots -- 250 acres against 10, a gain of 25. The loop takes all ten and stops; it used to spin until the request timed out.',
        'setup' => array($strong, $defenders, $defenderStats,
            set('buildings', $D, array('aland' => 4, 'home' => 3, 'barrack' => 3, 'farm' => 0, 'wp' => 0, 'lmill' => 0))),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'land-win-guild-news',
        'why'   => 'Both sides in a guild, so the four guild news lines are filed as well as the settlement ones.',
        'setup' => array($strong, $defenders, $defenderStats,
            "INSERT INTO guild (gname, cpw, strength, gid, datemade, info, owner, mem, notice, round_id) VALUES ('Rivalguild', '', 0, 2, '1/1/03, 12:00am', '', '3', 1, 'None', 1)",
            set('user', $D, array('guild' => 'Rivalguild'))),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'land-generals',
        'why'   => 'Three attacks on two free generals: the army goes to the highest empty return slot, then the next, then the third is refused.',
        'setup' => array($strong, $defenders, $defenderStats),
        'requests' => array(
            array('attack.php', array('empvalue' => $D, 'uwarrior' => 100)),
            array('attack.php', array('empvalue' => $D, 'uwarrior' => 100)),
            array('attack.php', array('empvalue' => $D, 'uwarrior' => 100)),
        ),
    ),
    array(
        'name'  => 'land-empty-defender',
        'why'   => 'A defender with no units at all: no defender tally, so no defender losses to divide.',
        'setup' => array($strong, army($D, array())),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 10))),
    ),

    /* ---- attackr.php: resources ---- */

    array(
        'name'  => 'resource-win',
        'why'   => 'Resource raid won: 8% of gold, iron, lumber and civilians, each rounded. Lumber was read off the military row, which has no lumber column, and so was always 0.',
        'setup' => array($strong, $defenders, $defenderStats,
            set('user', $D, array('gp' => 10000, 'iron' => 5000, 'lumber' => 3333)),
            set('military', $D, array('civ' => 1000))),
        'requests' => array(array('attackr.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'resource-win-in-debt',
        'why'   => 'A defender with negative gold yields none, rather than a negative gain.',
        'setup' => array($strong, $defenders, $defenderStats, set('user', $D, array('gp' => -500, 'iron' => 100))),
        'requests' => array(array('attackr.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'resource-loss',
        'why'   => 'Resource raid lost.',
        'setup' => array($weak, $defenders, $defenderStats),
        'requests' => array(array('attackr.php', array('empvalue' => $D, 'uwarrior' => 3))),
    ),

    /* ---- attackm.php: mountains ---- */

    array(
        'name'  => 'mountain-win',
        'why'   => 'Mountain attack won: 10% of the defender\'s mountains and experience for each, taken from open mountains, then mines under construction, then built ones. The loop that takes them read $mgain, which nothing assigns, and never ran.',
        'setup' => array($strong, $defenders, $defenderStats,
            set('buildings', $D, array('amts' => 5, 'dgm' => 2, 'dim' => 2, 'gm' => 10, 'im' => 10))),
        'requests' => array(array('attackm.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'mountain-win-short-of-mines',
        'why'   => 'A defender whose mountains outnumber its mountain buildings -- 200 against 10, a gain of 20. The loop takes all ten and stops; it used to spin until the request timed out.',
        'setup' => array($strong, $defenders, $defenderStats,
            set('buildings', $D, array('amts' => 0, 'dgm' => 0, 'dim' => 0, 'gm' => 5, 'im' => 5))),
        'requests' => array(array('attackm.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'mountain-loss',
        'why'   => 'Mountain attack lost.',
        'setup' => array($weak, $defenders, $defenderStats),
        'requests' => array(array('attackm.php', array('empvalue' => $D, 'uwarrior' => 3))),
    ),
    array(
        'name'  => 'mountain-none',
        'why'   => 'A defender with no mountains is refused -- after defANDoff.php has run, before anything moves.',
        'setup' => array($strong, $defenders, set('user', $D, array('mts' => 0))),
        'requests' => array(array('attackm.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),

    /* ---- the recorded quirks, pinned as they are ---- */

    array(
        'name'  => 'golem-survivors',
        'why'   => 'Attacking golems have a golem\'s own hit points, 2,000, as defending ones do. They were wizdef x 100, and the survivor formula multiplied by archer hit points, so 40 sent could come home as 141; archdef 3 and wizdef 1 would show either coming back.',
        'setup' => array(army($A, array('warriors' => 300, 'golem' => 40)), researched($A, array(17)),
            set('military', $A, array('archdef' => 3, 'wizdef' => 1)), $defenders, $defenderStats),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300, 'ugolem' => 40))),
    ),
    array(
        'name'  => 'iron-golems-with-archers',
        'why'   => 'Iron golems and archers come home in their own return slots. The iron golems used to be booked into the archer slot, replacing the archers that survived.',
        'setup' => array(army($A, array('warriors' => 300, 'archers' => 30, 'irongolem' => 20)),
            researched($A, array(13, 17, 18)), set('military', $A, array('archdef' => 1)), $defenders, $defenderStats),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300, 'uarcher' => 30, 'uirongolem' => 20))),
    ),
    array(
        'name'  => 'archers-without-armour',
        'why'   => 'Signup leaves archdef 0 until archer armour is bought, and archer hit points are archdef x 120. Unarmoured archers now defend at 1, as a defender\'s do; the division by zero was fatal on PHP 8, part-way through sending the army.',
        'setup' => array(army($A, array('warriors' => 300, 'archers' => 30)), researched($A, array(13)),
            $defenders, $defenderStats),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300, 'uarcher' => 30))),
    ),
    array(
        'name'  => 'iron-golems-alone',
        'why'   => 'Iron golems without archers. Booked into the archer slot, which nothing had named, the return UPDATE named no column, failed, and the golems were lost.',
        'setup' => array(army($A, array('warriors' => 300, 'irongolem' => 20)),
            researched($A, array(17, 18)), $defenders, $defenderStats),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300, 'uirongolem' => 20))),
    ),
    array(
        'name'  => 'golems-in-thousands',
        'why'   => 'Golem counts past 1,000, which functions.php formats as "2,000". One more than owned is refused and 500 leaves 1,500. Until nexplode.php stripped them, 500 of 2,000 was refused by a string comparison and 1,500 left a stock of 2 - 1,500.',
        'setup' => array(army($A, array('warriors' => 300, 'golem' => 2000)), researched($A, array(17)),
            $defenders, $defenderStats),
        'requests' => array(
            array('attack.php', array('empvalue' => $D, 'ugolem' => 2001)),
            array('attack.php', array('empvalue' => $D, 'uwarrior' => 300, 'ugolem' => 500)),
        ),
    ),
    array(
        'name'  => 'defender-wizards-clamped',
        'why'   => 'More damage than a defender\'s wizards can take. The clamp after the wizard loss used to test the warrior loss; the column is unsigned and sql_mode not strict, so MySQL floored it at 0 either way.',
        'setup' => array($strong, army($D, array('warriors' => 5, 'wizards' => 2)), $defenderStats),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'modifier-each-side',
        'why'   => 'A Giant Fighter (1.30) against a Giant Ranger (1.25), each on its own side. Same armies as land-win. The attacker\'s modifier used to scale both totals, so it cancelled.',
        'setup' => array($strong, set('user', $A, array('race' => 'Giant', 'class' => 'Fighter')),
            $defenders, $defenderStats, landLayout($D)),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300))),
    ),
    array(
        'name'  => 'return-time-elf',
        'why'   => 'Elves come home in 80% of the time, floored.',
        'setup' => array($strong, set('user', $A, array('race' => 'Elf')), $defenders, $defenderStats),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300, 'uwizard' => 100))),
    ),
    array(
        'name'  => 'return-time-night-elf',
        'why'   => 'Night Elves come home in 75% of the time, floored -- a bonus that never fired while the race was compared against " Night Elf". Same army as return-time-elf.',
        'setup' => array($strong, set('user', $A, array('race' => 'Night Elf')), $defenders, $defenderStats),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 300, 'uwizard' => 100))),
    ),

    /* ---- refusals that reach defANDoff.php: nothing may move ---- */

    array(
        'name'  => 'refused-guildmate',
        'why'   => 'A guildmate is refused, after defANDoff.php has looked them up.',
        'setup' => array($strong),
        'requests' => array(array('attack.php', array('empvalue' => GUILDMATE, 'uwarrior' => 10))),
    ),
    array(
        'name'  => 'refused-safe-mode',
        'why'   => 'A defender in safe mode is refused.',
        'setup' => array($strong, set('user', $D, array('safemode' => 5))),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 10))),
    ),
    array(
        'name'  => 'refused-too-many',
        'why'   => 'Sending more than you own is refused.',
        'setup' => array($weak, $defenders),
        'requests' => array(array('attack.php', array('empvalue' => $D, 'uwarrior' => 4))),
    ),

    /* ---- scattack.php: suicide civilians ---- */

    array(
        'name'  => 'suicide-civilians',
        'why'   => 'Each suicide civilian kills two civilians.',
        'setup' => array(set('military', $A, array('suicide' => 50)), set('military', $D, array('civ' => 300, 'thieves' => 30))),
        'requests' => array(array('scattack.php', array('suicideatk' => 1, 'empvalue' => $D, 'send' => 40, 'toatk' => 'civ'))),
    ),
    array(
        'name'  => 'suicide-thieves-overkill',
        'why'   => 'Two thieves each, floored at none left.',
        'setup' => array(set('military', $A, array('suicide' => 50)), set('military', $D, array('civ' => 300, 'thieves' => 30))),
        'requests' => array(array('scattack.php', array('suicideatk' => 1, 'empvalue' => $D, 'send' => 40, 'toatk' => 'thieve'))),
    ),
);

/*
 * The race and class modifiers, one attack each, at the large scale so each
 * one shows. Warriors and archers only, because every race and class may send
 * those. Human is the race for the class sweep and 'Warrior' -- the fixture's,
 * which is no class the modifier table names -- the class for the race sweep,
 * so each scenario moves exactly one modifier off 1.00. Elf and Night Elf are
 * covered above, with their return times.
 */
$sweepArmy = array('warriors' => 600000, 'archers' => 50000);
$sweepSetup = array_merge(
    array(army($A, $sweepArmy), researched($A, array(13)), set('military', $A, array('archdef' => 1))),
    $bigDefence);
$modifiers = array(
    array('Human', 'Fighter'), array('Human', 'Cleric'), array('Human', 'Ranger'), array('Human', 'Mage'),
    array('Human', 'Warlock'), array('Human', 'Savant'), array('Human', 'Insurrectionist'),
    array('Giant', 'Warrior'), array('Demon', 'Warrior'),
);
foreach ($modifiers as $m) {
    list($race, $class) = $m;
    $SCENARIOS[] = array(
        'name'  => 'modifier-' . strtolower($race === 'Human' ? $class : $race),
        'why'   => "A $race $class. Its modifier scales the attack; the Giant Ranger defending scales its own defence at 1.25.",
        'setup' => array_merge($sweepSetup, array(set('user', $A, array('race' => $race, 'class' => $class)))),
        'requests' => array(array('attack.php', array('empvalue' => $D) + $send($sweepArmy))),
    );
}

/*
 * Every page but the suicide attack submits with `attack`.
 */
runScenarios($SCENARIOS, function ($page, $fields) {
    return $page === 'scattack.php' ? $fields : array('attack' => 1) + $fields;
});
