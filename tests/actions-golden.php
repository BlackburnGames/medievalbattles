<?php
/**
 * Golden master for the orders an empire gives itself: today, construction.
 *
 * The tick golden master starts from whatever timers the fixture holds, and
 * the crawl only proves the build page renders, so nothing pinned the timer an
 * order writes -- which is where Advanced Construction and the Night Elf
 * penalty act. This is the combat golden master's harness pointed at the
 * other pages: each scenario adjusts the tester's empire with a few UPDATEs
 * and posts the order; tests/lib/golden.php reloads the fixture around it and
 * prints every column that moved.
 *
 * Chosen by branch, as combat's are: a queue under the cap and over it, an
 * order added to a queue already running, each construction modifier alone
 * and both together, and one scenario per recorded quirk so the bug is pinned
 * as it is today.
 *
 *   bash tests/actions-golden.sh            compare against tests/golden/actions.txt
 *   bash tests/actions-golden.sh --accept   re-accept after an intended change
 *
 * It reloads the schema, so it destroys whatever world it runs against.
 */

require_once __DIR__ . '/lib/golden.php';

const TESTER = 1;   // TestLord, Human 'Warrior' -- no construction modifier of its own

$T = TESTER;

/**
 * A construction queue already running: the given count of each building under
 * way, and the ticks left on it. Columns as buildings names them -- d<type>
 * and <X>hrs.
 */
function underway($userid, array $queue)
{
    return set('buildings', $userid, $queue);
}

$build = function (array $orders) {
    return array('buildings.php', array('update' => 1) + $orders);
};

// A queue the order does not touch, so a scenario shows whether an order for
// one building moves another building's timer.
$farmsUnderway = underway($T, array('dfarm' => 4, 'Fhrs' => 4));

$SCENARIOS = array(

    /* ---- no modifier ---- */

    array(
        'name'  => 'build-short-queue',
        'why'   => 'A queue of N buildings takes N ticks.',
        'setup' => array(),
        'requests' => array($build(array('uhome' => 5))),
    ),
    array(
        'name'  => 'build-capped-queue',
        'why'   => 'A queue of 20 or more takes 20 ticks.',
        'setup' => array(),
        'requests' => array($build(array('ufarm' => 25))),
    ),
    array(
        'name'  => 'build-every-type',
        'why'   => 'All seven timers, each its own queue.',
        'setup' => array(researched($T, array(13))),
        'requests' => array($build(array('uhome' => 1, 'ubarrack' => 2, 'ufarm' => 3, 'uwp' => 4,
                                         'ulmill' => 5, 'ugm' => 6, 'uim' => 7))),
    ),
    array(
        'name'  => 'build-onto-queue',
        'why'   => 'Adding to a running queue adds its count to the ticks left; the farms already under way keep their timer.',
        'setup' => array(underway($T, array('dhome' => 6, 'Hhrs' => 6)), $farmsUnderway),
        'requests' => array($build(array('uhome' => 3))),
    ),
    array(
        'name'  => 'build-onto-queue-capped',
        'why'   => 'Adding to a running queue never takes it past 20 ticks.',
        'setup' => array(underway($T, array('dhome' => 15, 'Hhrs' => 15))),
        'requests' => array($build(array('uhome' => 10))),
    ),

    /* ---- the construction modifiers ---- */

    array(
        'name'  => 'quirk-advanced-construction',
        'why'   => 'QUIRK: Advanced Construction divides the timer by 0.5, doubling it, and applies itself again to every queue already running -- the farms, which this order does not touch, double too.',
        'setup' => array(researched($T, array(16)), $farmsUnderway),
        'requests' => array($build(array('uhome' => 5))),
    ),
    array(
        'name'  => 'quirk-advanced-construction-capped',
        'why'   => 'QUIRK: the cap scales with the modifier, so Advanced Construction caps a queue at 40.',
        'setup' => array(researched($T, array(16))),
        'requests' => array($build(array('uhome' => 25))),
    ),
    array(
        'name'  => 'quirk-night-elf-requeue',
        'why'   => 'QUIRK: Night Elves build at half speed, and every order doubles every queue already running again -- the homes under way and the farms the order does not touch.',
        'setup' => array(set('user', $T, array('race' => 'Night Elf')),
            underway($T, array('dhome' => 6, 'Hhrs' => 12)), $farmsUnderway),
        'requests' => array($build(array('uhome' => 3))),
    ),
    array(
        'name'  => 'night-elf-capped',
        'why'   => 'A Night Elf queue caps at 40 ticks, twice the usual 20.',
        'setup' => array(set('user', $T, array('race' => 'Night Elf'))),
        'requests' => array($build(array('uhome' => 25))),
    ),
    array(
        'name'  => 'quirk-night-elf-advanced-construction',
        'why'   => 'QUIRK: a Night Elf with Advanced Construction, both modifiers on one queue. Meant to cancel; today they compound to four times.',
        'setup' => array(set('user', $T, array('race' => 'Night Elf')), researched($T, array(16))),
        'requests' => array($build(array('uhome' => 5))),
    ),

    /* ---- refusals: nothing may move ---- */

    array(
        'name'  => 'refused-negative',
        'why'   => 'A negative count is refused.',
        'setup' => array(),
        'requests' => array($build(array('uhome' => -1))),
    ),

    array(
        'name'  => 'refused-no-land',
        'why'   => 'More land buildings than open land is refused.',
        'setup' => array(),
        'requests' => array($build(array('uhome' => 101))),
    ),
    array(
        'name'  => 'refused-lumber-mill-without-archery',
        'why'   => 'Lumber mills need Archery.',
        'setup' => array(),
        'requests' => array($build(array('ulmill' => 1))),
    ),
);

/*
 * Every order form, submitted as a browser submits it: one box filled and
 * every other box sent empty. The scenarios above leave the other boxes out,
 * which PHP reads as null; a browser sends "", which PHP 8 does not read as 0
 * -- `"" < 0` is true, so these were all refused as negative, and `"" + ""`
 * threw on the build and research pages. Only the filled box varies, so the
 * field list is each form's, as S_*.php and the tables render it.
 */
$forms = array(
    array('buildings.php', array('uhome', 'ubarrack', 'ufarm', 'uwp', 'ugm', 'uim'), 'uhome', 2,
          array('update' => 1), array()),
    array('dbuildings.php', array('uhome', 'ubarrack', 'ufarm', 'uwp', 'ugm', 'uim'), 'uhome', 2,
          array('update' => 1), array()),
    array('military.php', array('urecruit'), 'urecruit', 10,
          array('recruited' => 1), array()),
    array('military.php', array('usage', 'uthief', 'uexplorer', 'uwarrior', 'uwizard', 'upriest'), 'uwarrior', 2,
          array('trains' => 1), array()),
    array('disband.php', array('usage', 'uexplorer', 'uthief', 'uwarrior', 'uwizard', 'upriest'), 'uwarrior', 1,
          array('trains' => 1), array()),
    array('explore.php', array('exploreland', 'exploremt'), 'exploreland', 20,
          array('explore' => 1), array(set('military', $T, array('explorers' => 50)))),
    array('cexplore.php', array('cexploreland', 'cexploremt'), 'cexploreland', 5,
          array('cancel' => 1), array(set('explore', $T, array('expland' => 20)))),
    array('research.php', array('ur1', 'ur2', 'ur3', 'ur4', 'ur5', 'ur6', 'ur7', 'ur11', 'ur12', 'ur15', 'ur16',
                                'ur13', 'ur14', 'ur17', 'ur18'), 'ur1', 1,
          array('first' => 1), array()),
    array('cresearch.php', array('ur1', 'ur2', 'ur3', 'ur4', 'ur5', 'ur6', 'ur7', 'ur11', 'ur12', 'ur15', 'ur16',
                                 'ur13', 'ur14', 'ur17', 'ur18'), 'ur1', 1,
          array('first' => 1), array()),
    array('animate.php', array('ugolem', 'uirongolem'), 'ugolem', 1,
          array('trains' => 1), array(researched($T, array(17)))),
);
foreach ($forms as $f) {
    list($page, $boxes, $filled, $count, $submit, $setup) = $f;
    $fields = array_fill_keys($boxes, '');
    $fields[$filled] = $count;
    $SCENARIOS[] = array(
        'name'  => 'blank-boxes-' . basename($page, '.php') . '-' . $filled,
        'why'   => "$page with $filled filled in and every other box empty, as a browser sends it.",
        'setup' => $setup,
        'requests' => array(array($page, $submit + $fields)),
    );
}

runScenarios($SCENARIOS);
