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
 * Each scenario starts from the fixture world -- db.sql and db/seed.sql,
 * reloaded, the same world the tick golden master is computed from -- adjusts
 * the two armies with a few UPDATEs, logs in as the tester, and makes its
 * requests over HTTP. What it prints is what the page said and every column
 * that changed in every table, as before -> after. The database is the real
 * output, as in the tick golden master, but here only the movement is shown,
 * because the movement is the combat.
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

require_once __DIR__ . '/lib/client.php';

$BASE = getenv('MB_BASE_URL') ? getenv('MB_BASE_URL') : 'http://localhost';

$host = getenv('MB_DB_HOST') ? getenv('MB_DB_HOST') : 'db';
$name = getenv('MB_DB_NAME') ? getenv('MB_DB_NAME') : 'mbv6';
$user = getenv('MB_DB_USER') ? getenv('MB_DB_USER') : 'mb';
$pass = getenv('MB_DB_PASS') ? getenv('MB_DB_PASS') : 'mb';

$db = @mysqli_connect($host, $user, $pass, $name);
if (!$db) {
    fwrite(STDERR, "Cannot connect: " . mysqli_connect_error() . "\n");
    exit(2);
}
mysqli_set_charset($db, 'utf8mb4');

/*
 * Columns left out of the comparison. The login before each scenario rehashes
 * the tester's MD5 into a salted bcrypt, so `pw` differs on every run; the
 * rest are wall-clock.
 */
$IGNORED_COLUMNS = array('pw', 'ip', 'lastlogin', 'lastlog', 'date', 'datestamp');

// The cast. Every scenario attacks from the tester; the target is PoorSerf
// unless the scenario says otherwise, because PoorSerf is guildless and in
// another settlement, so nothing refuses the attack before the formula runs.
const ATTACKER = 1;   // TestLord, Human, settlement 1, Testguild
const GUILDMATE = 2;  // IdleBaron, Demon, settlement 1, Testguild
const DEFENDER = 3;   // PoorSerf, Giant, settlement 2, no guild

/* ------------------------------------------------------------------ *
 * Scenario helpers. Each returns SQL; a scenario's `setup` is a list.
 * ------------------------------------------------------------------ */

/** Set columns on one empire's row in one table. */
function set($table, $userid, array $columns)
{
    $parts = array();
    foreach ($columns as $column => $value) {
        $parts[] = "`$column`=" . (is_int($value) ? $value : "'" . addslashes($value) . "'");
    }
    return "UPDATE `$table` SET " . implode(', ', $parts) . " WHERE userid=$userid";
}

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

/** Research the projects named, by points (r13 Archery, r17 Animation, r18 Advanced Animation). */
function researched($userid, array $projects)
{
    $columns = array();
    foreach ($projects as $p) {
        $columns["r{$p}pts"] = 125000;
    }
    return set('research', $userid, $columns);
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
            "INSERT INTO guild (gname, cpw, strength, gid, datemade, info, owner, mem, notice) VALUES ('Rivalguild', '', 0, 2, '1/1/03, 12:00am', '', '3', 1, 'None')",
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
        'name'  => 'quirk-golem-survivors',
        'why'   => 'QUIRK: golem hit points come from wizard defence, and the survivor formula multiplies by archer hit points. archdef 3 and wizdef 1 make both visible.',
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
        'name'  => 'quirk-modifier-both-sides',
        'why'   => 'QUIRK: a Giant Fighter\'s 1.30 modifier is applied to the defender\'s power too. Same armies as land-win; compare the losses.',
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
        'why'   => "A $race $class. The modifier scales both sides (see quirk-modifier-both-sides), so it shows in the losses, not the outcome.",
        'setup' => array_merge($sweepSetup, array(set('user', $A, array('race' => $race, 'class' => $class)))),
        'requests' => array(array('attack.php', array('empvalue' => $D) + $send($sweepArmy))),
    );
}

/* ------------------------------------------------------------------ *
 * Run.
 * ------------------------------------------------------------------ */

$only = isset($argv[1]) ? $argv[1] : null;

foreach ($SCENARIOS as $s) {
    if ($only !== null && $s['name'] !== $only) {
        continue;
    }

    loadFixture($db);
    foreach ($s['setup'] as $sql) {
        if (!mysqli_query($db, $sql)) {
            fwrite(STDERR, "FATAL: setup for {$s['name']} failed: " . mysqli_error($db) . "\n  $sql\n");
            exit(2);
        }
    }

    // After the setup, so the login sees the setup, and before the first
    // snapshot, so the login's own writes (the rehash, lastlogin) are not
    // reported as combat.
    $client = login($BASE, 'tester@example.com', 'test1234');

    echo "### {$s['name']}\n";
    echo "# {$s['why']}\n";

    foreach ($s['requests'] as $req) {
        list($page, $fields) = $req;
        if ($page !== 'scattack.php') {
            $fields = array('attack' => 1) + $fields;
        }

        $before = snapshot($db);
        $body = $client->post('/' . $page, $fields);
        $after = snapshot($db);

        echo "\n> $page " . http_build_query($fields, '', ' ') . "\n";
        if ($client->lastStatus !== 200) {
            echo "  HTTP {$client->lastStatus}\n";
        }
        foreach (pageSays($body) as $line) {
            echo "  says: $line\n";
        }
        foreach (diagnostics($body) as $line) {
            echo "  php:  $line\n";
        }
        $changes = changes($before, $after);
        foreach ($changes as $line) {
            echo "  $line\n";
        }
        if (!$changes) {
            echo "  (no change)\n";
        }
    }
    echo "\n";
}

/* ------------------------------------------------------------------ *
 * The fixture.
 * ------------------------------------------------------------------ */

/**
 * The fixture world, from scratch: every table dropped -- the app creates
 * tables at runtime that db.sql does not know -- and db.sql and db/seed.sql
 * loaded, as tests/reset-db.sh does. That script needs the database's root
 * and runs from the host; this runs per scenario, inside the container.
 */
function loadFixture($db)
{
    $result = mysqli_query($db, 'SHOW TABLES');
    $tables = array();
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = '`' . $row[0] . '`';
    }
    if ($tables) {
        mysqli_query($db, 'DROP TABLE ' . implode(', ', $tables));
    }

    foreach (array(__DIR__ . '/../db.sql', __DIR__ . '/../db/seed.sql') as $file) {
        if (!mysqli_multi_query($db, file_get_contents($file))) {
            fwrite(STDERR, "FATAL: loading $file: " . mysqli_error($db) . "\n");
            exit(2);
        }
        do {
            if ($r = mysqli_store_result($db)) {
                mysqli_free_result($r);
            }
            if (mysqli_errno($db)) {
                fwrite(STDERR, "FATAL: loading $file: " . mysqli_error($db) . "\n");
                exit(2);
            }
        } while (mysqli_more_results($db) && mysqli_next_result($db));
        if (mysqli_errno($db)) {
            fwrite(STDERR, "FATAL: loading $file: " . mysqli_error($db) . "\n");
            exit(2);
        }
    }
}

function login($base, $email, $pass)
{
    // The GET primes the computer_id cookie checklogin.php expects; see smoke.php.
    $client = new MbClient($base);
    $client->get('/index.php');
    $client->post('/checklogin.php', array('email' => $email, 'pw' => $pass, 'login' => 'Login'));
    if ($client->lastStatus !== 302) {
        fwrite(STDERR, "FATAL: login as $email did not redirect (got HTTP {$client->lastStatus}).\n");
        exit(2);
    }
    return $client;
}

/* ------------------------------------------------------------------ *
 * Observing a request.
 * ------------------------------------------------------------------ */

/**
 * Every table, as rows. A table with a userid column is keyed on it, so a
 * change reads as columns on an empire; any other table -- the news tables,
 * mostly -- is a bag of rows, and a change reads as rows added or removed.
 */
function snapshot($db)
{
    global $IGNORED_COLUMNS;

    $state = array();
    $result = mysqli_query($db, 'SHOW TABLES');
    $tables = array();
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    sort($tables);

    foreach ($tables as $table) {
        $rows = mysqli_query($db, "SELECT * FROM `$table`");
        $keyed = array();
        $bag = array();
        while ($row = mysqli_fetch_assoc($rows)) {
            foreach ($IGNORED_COLUMNS as $column) {
                unset($row[$column]);
            }
            foreach ($row as $column => $value) {
                $row[$column] = normalise($value);
            }
            if (array_key_exists('userid', $row) && !isset($keyed[$row['userid']])) {
                $keyed[$row['userid']] = $row;
            } else {
                $bag[] = rowText($row);
            }
        }
        sort($bag);
        $state[$table] = array('keyed' => $keyed, 'bag' => $bag);
    }
    return $state;
}

function changes(array $before, array $after)
{
    $lines = array();
    foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
        $b = isset($before[$table]) ? $before[$table] : array('keyed' => array(), 'bag' => array());
        $a = isset($after[$table]) ? $after[$table] : array('keyed' => array(), 'bag' => array());

        $ids = array_unique(array_merge(array_keys($b['keyed']), array_keys($a['keyed'])));
        sort($ids);
        foreach ($ids as $id) {
            if (!isset($a['keyed'][$id])) {
                $lines[] = "- $table [$id]";
                continue;
            }
            if (!isset($b['keyed'][$id])) {
                $lines[] = "+ $table [$id] " . rowText($a['keyed'][$id]);
                continue;
            }
            $moved = array();
            foreach ($a['keyed'][$id] as $column => $value) {
                $old = isset($b['keyed'][$id][$column]) ? $b['keyed'][$id][$column] : '<absent>';
                if ($old !== $value) {
                    $moved[] = "$column $old -> $value";
                }
            }
            if ($moved) {
                $lines[] = "  $table [$id] " . implode(', ', $moved);
            }
        }

        foreach (bagDiff($b['bag'], $a['bag']) as $row) {
            $lines[] = "- $table $row";
        }
        foreach (bagDiff($a['bag'], $b['bag']) as $row) {
            $lines[] = "+ $table $row";
        }
    }
    return $lines;
}

/** The rows of $x that are not in $y, counting duplicates. */
function bagDiff(array $x, array $y)
{
    $out = array();
    foreach ($x as $row) {
        $i = array_search($row, $y, true);
        if ($i === false) {
            $out[] = $row;
        } else {
            unset($y[$i]);
        }
    }
    return $out;
}

function rowText(array $row)
{
    $parts = array();
    foreach ($row as $column => $value) {
        $parts[] = "$column=$value";
    }
    return implode(' | ', $parts);
}

/** As tests/dump-state.php: wall-clock values masked, rows kept on one line. */
function normalise($value)
{
    if ($value === null) {
        return '<NULL>';
    }
    if (preg_match('#^\d{1,2}/\d{1,2}/\d{2}, \d{1,2}:\d{2}[ap]m$#', $value)) {
        return '<CLOCK>';
    }
    return str_replace(array("\r", "\n"), array('', '\\n'), $value);
}

/**
 * What the combat page told the player: the centred messages after the
 * site-wide banner, as text. The drop-down and unit table that follow are
 * the same on every response and say nothing about the battle.
 */
function pageSays($body)
{
    $marker = "Don't get your hopes up, however.";
    $at = strpos($body, $marker);
    if ($at === false) {
        return array('<no game page: ' . trim(substr(strip_tags($body), 0, 120)) . '>');
    }
    $body = substr($body, $at + strlen($marker));
    // The diagnostics are reported on their own.
    $body = preg_replace('#<br />\s*<b>[A-Za-z ]+</b>:.*?<br />#s', '', $body);

    // A win's message div is never closed: the drop-down runs straight on.
    preg_match_all('#<div align=center>(.*?)(?=</div>|<select|<form|<font class=inner2>You are viewing)#s', $body, $m);
    $says = array();
    foreach ($m[1] as $html) {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<br>', ' ', $html)))));
        if ($text !== '') {
            $says[] = $text;
        }
    }
    return $says;
}

/**
 * PHP's diagnostics, where they mean something to a battle: errors, and the
 * warnings that are a failed query. Not reads of unassigned names -- PHP 8
 * calls those warnings too, but what one does to a battle is already in the
 * changes printed below it, and listing them here would churn this file on
 * every commit that starts a tally from zero. They are the crawl's business,
 * as are notices and deprecations. Line numbers are dropped for the same
 * reason.
 */
function diagnostics($body)
{
    preg_match_all('#<b>(Warning|Fatal error|Parse error)</b>:\s*(.*?) in <b>(?:/var/www/html/|/repo/app/)?([^<]*)</b> on line#s',
        $body, $m, PREG_SET_ORDER);
    $out = array();
    foreach ($m as $hit) {
        $message = trim(strip_tags($hit[2]));
        if (preg_match('/^(Undefined (variable|array key|global variable)|Trying to access array offset)/', $message)) {
            continue;
        }
        // An uncaught exception carries its own location and a stack trace.
        $message = preg_replace('#\s+in /\S+:\d+\s+Stack trace:.*$#s', '', $message);
        // A failed query quotes itself, credential and all, and the login has
        // just rehashed that into a salted bcrypt.
        $message = preg_replace('#\$2y\$[^\' ]*#', '<HASH>', $message);
        $line = "{$hit[1]}: $message ({$hit[3]})";
        if (!in_array($line, $out, true)) {
            $out[] = $line;
        }
    }
    return $out;
}
