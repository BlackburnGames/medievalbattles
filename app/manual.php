<?php
/**
 * Medieval Battles game manual.
 *
 * A renderer, not a document: every number on this page is read from
 * include/gamedata.php, which carries a source citation for each value.
 * Nothing here is hand-typed prose about a number, so the manual cannot
 * drift away from the engine the way the old static manual.html did.
 *
 * Reachable logged out (linked from the login page), so it must not
 * include igtop.php or touch the session.
 */

include 'include/gamedata.php';

/** Number with thousands separators, or an em dash for nothing. */
function mb_man_num($n)
{
    return $n > 0 ? number_format($n) : '&mdash;';
}

/** Restriction cell: highlighted class name, or a muted "All". */
function mb_man_restrict($who)
{
    return $who === ''
        ? '<span class="none">All</span>'
        : '<span class="restrict">' . htmlspecialchars($who) . '</span>';
}

/**
 * An equipment table. All four share one shape; only the third column
 * heading (Power vs Defense) and the resource column differ.
 */
function mb_man_gear_table($rows, $stat_label, $resource_label)
{
    echo '<table class="data">' . "\n";
    echo '<tr><th>Name</th><th class="num">Speed</th><th class="num">' . $stat_label . '</th>'
       . '<th class="num">EXP Gained</th><th class="num">Gold</th><th class="num">' . $resource_label . '</th>'
       . '<th class="num">Ticks</th><th>Available To</th></tr>' . "\n";
    foreach ($rows as $r) {
        echo '<tr><td>' . htmlspecialchars($r[0]) . '</td>'
           . '<td class="num">' . $r[1] . '</td>'
           . '<td class="num">' . $r[2] . '</td>'
           . '<td class="num">' . mb_man_num($r[3]) . '</td>'
           . '<td class="num">' . mb_man_num($r[4]) . '</td>'
           . '<td class="num">' . mb_man_num($r[5]) . '</td>'
           . '<td class="num">' . mb_man_num($r[6]) . '</td>'
           . '<td>' . mb_man_restrict($r[7]) . "</td></tr>\n";
    }
    echo "</table>\n";
}

/**
 * Turn a race's raw modifier deltas into readable effects.
 *
 * Deliberately computed rather than written out: the old manual described
 * Night Elves as "-25% civilian growth" when the engine subtracts 0.25
 * from a base of 0.26, which is a 96% cut. Showing the resulting rate
 * next to the base makes that impossible to misstate.
 */
function mb_man_race_effects($r, $econ)
{
    $out = array();

    if (isset($r['gpmod_delta'])) {
        $rate = ($econ['gpmod'] + $r['gpmod_delta']) * $econ['gold_per_mine'];
        $out[] = 'Gold: <b>' . round($rate) . '</b> per mine per tick (normally ' . $econ['gold_per_mine'] . ')';
    }
    if (isset($r['ironmod_delta'])) {
        $rate = $econ['iron_per_mine'] + $r['ironmod_delta'];
        $out[] = 'Iron: <b>' . $rate . '</b> per mine per tick (normally ' . $econ['iron_per_mine'] . ')';
    }
    if (isset($r['foodmod_delta'])) {
        $rate = $econ['food_per_farm'] + $r['foodmod_delta'];
        $out[] = 'Food: <b>' . $rate . '</b> per farm per tick (normally ' . $econ['food_per_farm'] . ')';
    }
    if (isset($r['lumbmod_delta'])) {
        $rate = $econ['lumber_per_mill'] + $r['lumbmod_delta'];
        $out[] = 'Lumber: <b>' . $rate . '</b> per mill per tick (normally ' . $econ['lumber_per_mill'] . ')';
    }
    if (isset($r['civ_mod_delta'])) {
        $rate = round($econ['civ_growth_mod'] + $r['civ_mod_delta'], 2);
        $out[] = 'Civilian growth: <b>' . $rate . '</b> per home per tick (normally ' . $econ['civ_growth_mod'] . ')';
    }
    if (isset($r['offdef_delta'])) {
        $pct = ($r['offdef_delta'] > 0 ? '+' : '') . round($r['offdef_delta'] * 100);
        $out[] = '<b>' . $pct . '%</b> offense and defense';
    }
    if (isset($r['priest_cost_mult'])) {
        $out[] = 'Priests cost <b>' . $r['priest_cost_mult'] . 'x</b> the normal price';
    }
    if (isset($r['warrior_cost_mult'])) {
        $out[] = 'Warriors cost <b>' . $r['warrior_cost_mult'] . 'x</b> the normal price';
    }
    if (isset($r['return_time_mult'])) {
        $pct = round((1 - $r['return_time_mult']) * 100);
        $out[] = 'Armies return <b>' . $pct . '% faster</b> from attacks';
    }
    if (isset($r['explore_exp_num'])) {
        $out[] = 'Needs <b>fewer explorers</b> per land gained (rate '
               . $r['explore_exp_num'] . ' instead of ' . $GLOBALS['GAMEDATA']['exploring']['exp_num_default'] . ')';
    }
    if (isset($r['troops_per_barrack'])) {
        $out[] = 'Barracks hold only <b>' . $r['troops_per_barrack'] . '</b> troops each (normally '
               . $econ['troops_per_barrack'] . ')';
    }
    if (isset($r['build_time_mult'])) {
        $out[] = 'Buildings take <b>' . $r['build_time_mult'] . 'x longer</b> to construct';
    }
    if (!empty($r['starts_with'])) {
        $out[] = 'Starts with ' . implode(', ', $r['starts_with']);
    }
    if (!empty($r['bans'])) {
        $out[] = '<b>Cannot field:</b> ' . implode(', ', $r['bans']);
    }

    return $out;
}

/** The same treatment for classes. */
function mb_man_class_effects($c, $econ)
{
    $out = array();

    if (isset($c['gpmod'])) {
        $rate = $c['gpmod'] * $econ['gold_per_mine'];
        $out[] = 'Gold: <b>' . round($rate) . '</b> per mine per tick (normally ' . $econ['gold_per_mine'] . ')';
    }
    if (isset($c['lumbmod_delta'])) {
        $rate = $econ['lumber_per_mill'] + $c['lumbmod_delta'];
        $out[] = 'Lumber: <b>' . $rate . '</b> per mill per tick (normally ' . $econ['lumber_per_mill'] . ')';
    }
    if (isset($c['offdef_mult'])) {
        $pct = round(($c['offdef_mult'] - 1) * 100);
        $out[] = '<b>' . ($pct > 0 ? '+' : '') . $pct . '%</b> offense and defense';
    }
    if (isset($c['research_mult'])) {
        $pct = round(abs($c['research_mult'] - 1) * 100);
        $out[] = $c['research_mult'] > 1
            ? 'Research points accrue <b>' . $pct . '% faster</b>'
            : 'Research points accrue <b>' . $pct . '% slower</b>';
    }
    if (isset($c['wizard_cost_mult'])) {
        $out[] = 'Wizards cost <b>' . $c['wizard_cost_mult'] . 'x</b> the normal price';
    }
    if (isset($c['suicide_cost_mult'])) {
        $out[] = 'Suicide Civilians cost <b>' . $c['suicide_cost_mult'] . 'x</b> the normal price';
    }
    if (!empty($c['perks'])) {
        foreach ($c['perks'] as $p) { $out[] = $p; }
    }
    if (!empty($c['starts_with'])) {
        $out[] = 'Starts with ' . implode(', ', $c['starts_with']);
    }
    if (!empty($c['exclusive_gear'])) {
        $out[] = 'Sole builder of ' . implode(', ', $c['exclusive_gear']);
    }
    if (!empty($c['bans'])) {
        $out[] = '<b>Cannot field:</b> ' . implode(', ', $c['bans']);
    }
    if (!empty($c['note'])) {
        $out[] = $c['note'];
    }

    return $out;
}

$econ = $GAMEDATA['economy'];
$sections = array(
    'basics'    => 'The Basics',
    'races'     => 'Races',
    'classes'   => 'Classes',
    'land'      => 'Land, Mountains and Buildings',
    'economy'   => 'Economy and Population',
    'exploring' => 'Exploring',
    'units'     => 'Military Units',
    'equipment' => 'Weapons and Armor',
    'research'  => 'Research',
    'combat'    => 'Attacking',
    'intel'     => 'Intelligence',
    'social'    => 'Settlements, Guilds and Messages',
    'exp'       => 'Experience and Score',
    'rules'     => 'Game Rules',
    'quirks'    => 'Known Quirks',
);
?>
<html>
<head>
<title>Medieval Battles Game Manual</title>
<link rel="stylesheet" type="text/css" href="css/manual.css">
</head>
<body>
<div id="page">

<div id="masthead">
	<h1>Medieval Battles</h1>
	<div class="sub">Game Manual &mdash; Version 6</div>
</div>

<div id="toc">
	<h2>Contents</h2>
	<ol>
<?php foreach ($sections as $id => $title) { ?>
		<li><a href="#<?php echo $id; ?>"><?php echo $title; ?></a></li>
<?php } ?>
	</ol>
</div>

<!-- ============================================================ -->
<h2 class="section"><a name="basics"></a>The Basics</h2>

<p>Medieval Battles is a game of slow accumulation. You rule an empire defined by
a <a href="#races">race</a> and a <a href="#classes">class</a>, and everything you
own grows on a fixed schedule called a <b>tick</b>. Every tick your mines produce
gold and iron, your farms produce food, your mills produce lumber, your population
grows, your explorers claim land, your researchers make progress, your buildings
get closer to finished, and your armies get closer to home.</p>

<p>You do not act in real time. You spend what you have between ticks &mdash; queue
buildings, train troops, assign researchers, launch attacks &mdash; and the tick
resolves it all at once. While a tick is running the game shows "Tick in progress"
and refuses input; it takes only a moment.</p>

<div class="note">
	<b>New empires are protected.</b> For your first
	<b><?php echo $GAMEDATA['timers']['safemode_ticks']; ?> ticks</b> you are in Safe
	Mode: nobody can attack you or spy on you, and you cannot attack or spy on anyone
	either. Use the time to build homes and farms. There is no way to end it early.
</div>

<p>Two other clocks matter. You must activate your account by email within
<b><?php echo $GAMEDATA['timers']['activation_ticks']; ?> ticks</b> or it is deleted,
and an account that is never logged into for
<b><?php echo $GAMEDATA['timers']['inactivity_ticks']; ?> ticks</b> is deleted as well.
Simply logging in resets the second clock. Messages and news items are purged after
<b><?php echo $GAMEDATA['timers']['message_ttl_ticks']; ?> ticks</b>.</p>

<!-- ============================================================ -->
<h2 class="section"><a name="races"></a>Races <a class="top" href="#toc">top</a></h2>

<p>Your race is the base description of your empire. It sets your production rates,
your combat modifier, and which units you are allowed to field. It cannot be changed
after signup.</p>

<table class="data">
<tr><th>Race</th><th>Effects</th></tr>
<?php foreach ($GAMEDATA['races'] as $name => $r) { ?>
<tr>
	<td><b><?php echo htmlspecialchars($name); ?></b><br>
		<span class="none"><?php echo htmlspecialchars($r['flavor']); ?></span></td>
	<td><?php
		$effects = mb_man_race_effects($r, $econ);
		echo $effects ? implode('<br>', $effects) : '<span class="none">No modifiers.</span>';
	?></td>
</tr>
<?php } ?>
</table>

<!-- ============================================================ -->
<h2 class="section"><a name="classes"></a>Classes <a class="top" href="#toc">top</a></h2>

<p>Your class describes you personally as the commander of your empire, but its
effects apply to the empire as a whole. Like race, it is permanent.</p>

<table class="data">
<tr><th>Class</th><th>Effects</th></tr>
<?php foreach ($GAMEDATA['classes'] as $name => $c) { ?>
<tr>
	<td><b><?php echo htmlspecialchars($name); ?></b><br>
		<span class="none"><?php echo htmlspecialchars($c['flavor']); ?></span></td>
	<td><?php
		$effects = mb_man_class_effects($c, $econ);
		echo $effects ? implode('<br>', $effects) : '<span class="none">No modifiers.</span>';
	?></td>
</tr>
<?php } ?>
</table>

<!-- ============================================================ -->
<h2 class="section"><a name="land"></a>Land, Mountains and Buildings <a class="top" href="#toc">top</a></h2>

<p>Your territory comes in two kinds. <b>Land</b> is open plain and carries the
buildings that support your population and army. <b>Mountains</b> are rugged and
carry the mines and the lumber mill. Each building occupies one tile of the
appropriate type, and you gain more territory by
<a href="#exploring">exploring</a> or by <a href="#combat">attacking</a>.</p>

<h3>On Land</h3>
<table class="data">
<tr><th>Building</th><th>What it does</th></tr>
<?php foreach ($GAMEDATA['buildings']['land'] as $name => $desc) { ?>
<tr><td><b><?php echo htmlspecialchars($name); ?></b></td><td><?php echo htmlspecialchars($desc); ?></td></tr>
<?php } ?>
</table>

<h3>On Mountains</h3>
<table class="data">
<tr><th>Building</th><th>What it does</th></tr>
<?php foreach ($GAMEDATA['buildings']['mountain'] as $name => $desc) { ?>
<tr><td><b><?php echo htmlspecialchars($name); ?></b></td><td><?php echo htmlspecialchars($desc); ?></td></tr>
<?php } ?>
</table>

<h3>Cost and construction time</h3>

<p>Every building costs the same, and the price rises as you develop: it is
<b><?php echo $GAMEDATA['buildings']['cost_base']; ?> gold plus
<?php echo $GAMEDATA['buildings']['cost_per_owned']; ?> gold for every building you
already own or have under construction</b>, capped at
<?php echo number_format($GAMEDATA['buildings']['cost_cap']); ?> gold. Land buildings
and mountain buildings are priced separately, so mines do not make homes more expensive.
Demolishing costs <?php echo $GAMEDATA['buildings']['demolish_cost']; ?> gold per building
and frees the tile.</p>

<p>A batch takes <b>one tick per building</b>, up to a maximum of
<b><?php echo $GAMEDATA['buildings']['ticks_cap']; ?> ticks</b> however large the batch.
A share of the batch finishes each tick rather than all of it at the end. Adding to a
batch already under construction <b>extends the timer for the whole batch</b>, so it is
usually better to let a batch finish than to keep topping it up.</p>

<!-- ============================================================ -->
<h2 class="section"><a name="economy"></a>Economy and Population <a class="top" href="#toc">top</a></h2>

<p>These are the baseline rates before your race, class and research are applied.</p>

<table class="data">
<tr><th>Source</th><th class="num">Per tick</th></tr>
<tr><td>Each gold mine</td><td class="num"><?php echo $econ['gold_per_mine']; ?> gold</td></tr>
<tr><td>Each iron mine</td><td class="num"><?php echo $econ['iron_per_mine']; ?> iron</td></tr>
<tr><td>Each farm</td><td class="num"><?php echo $econ['food_per_farm']; ?> food</td></tr>
<tr><td>Each lumber mill</td><td class="num"><?php echo $econ['lumber_per_mill']; ?> lumber</td></tr>
<tr><td>Recruitable civilians added to your pool</td><td class="num"><?php echo $econ['recruit_pool_rate']; ?> &times; population</td></tr>
</table>

<h3>Population</h3>

<p>Homes set the ceiling on your population: each houses
<b><?php echo $econ['civ_per_home']; ?> civilians</b>. Population grows toward that
ceiling every tick at a rate set by your race. Two conditions cause it to shrink
by <b><?php echo round((1 - $econ['decay_factor']) * 100); ?>% per tick</b> instead:</p>

<ul>
	<li><b>Overcrowding</b> &mdash; more civilians than your homes can hold.</li>
	<li><b>Starvation</b> &mdash; more civilians than you have food.</li>
</ul>

<p>Food is not eaten; it is simply compared against your population. Stockpiling far
beyond your needs is wasted, though: food above
<b><?php echo $econ['food_cap_per_farm']; ?> per farm</b> rots away at
<?php echo round((1 - $econ['decay_factor']) * 100); ?>% per tick, and no new food is
produced that tick. A common guideline is one farm for every two homes.</p>

<h3>Recruits</h3>

<p>Civilians do not become soldiers on their own. Each tick a small share of your
population joins the pool of civilians willing to serve, and you convert them into
<b>recruits</b> at <b><?php echo $econ['recruit_cost_gp']; ?> gold each</b> on the
Military page. Recruits are then trained into the units below. Disbanding a unit costs
<?php echo $GAMEDATA['disband']['cost_gp']; ?> gold and returns
<?php echo $GAMEDATA['disband']['returns']; ?>;
<?php echo implode(', ', $GAMEDATA['disband']['excluded']); ?> cannot be disbanded.</p>

<!-- ============================================================ -->
<h2 class="section"><a name="exploring"></a>Exploring <a class="top" href="#toc">top</a></h2>

<p>Explorers are the free way to grow. Send them out and they claim territory every
tick, indefinitely, until you recall them. The catch is that the bigger you are, the
more explorers each new tile costs.</p>

<p>The number of explorers needed for <b>one land per tick</b> is your land multiplied
by your race's exploring rate (<?php echo $GAMEDATA['exploring']['exp_num_default']; ?>
for most races, <?php echo $GAMEDATA['exploring']['exp_num_human']; ?> for Humans),
divided by <?php echo $GAMEDATA['exploring']['divisor']; ?>. At 250 land that is
<?php echo (int) floor(round(250 * $GAMEDATA['exploring']['exp_num_default']) / $GAMEDATA['exploring']['divisor']); ?>
explorers for the first land per tick, and the same again for each additional one.
Mountains work the same way. The Explore page does this arithmetic for you.</p>

<div class="note">
	You cannot gain more than <b><?php echo $GAMEDATA['exploring']['max_gain_per_tick']; ?> land
	and <?php echo $GAMEDATA['exploring']['max_gain_per_tick']; ?> mountains per tick</b>
	no matter how many explorers you field, so there is a point past which more explorers
	buy you nothing.
</div>

<!-- ============================================================ -->
<h2 class="section"><a name="units"></a>Military Units <a class="top" href="#toc">top</a></h2>

<p>Unit prices rise as you train more of the same kind, so armies get progressively
more expensive to grow. Only warriors, wizards, priests and archers occupy barracks
space &mdash; <b><?php echo $econ['troops_per_barrack']; ?> per barrack</b>, or
<?php echo $GAMEDATA['races']['Human']['troops_per_barrack']; ?> for Humans. Training
is blocked outright once your barracks are full.</p>

<table class="data">
<tr><th>Unit</th><th>Cost</th><th class="num">Ticks</th><th class="num">Barracks</th></tr>
<?php foreach ($GAMEDATA['units'] as $name => $u) { ?>
<tr>
	<td><b><?php echo htmlspecialchars($name); ?></b><br>
		<span class="none"><?php echo htmlspecialchars($u['desc']); ?></span></td>
	<td><?php echo htmlspecialchars($u['cost']); ?></td>
	<td class="num"><?php echo $u['ticks']; ?></td>
	<td class="num"><?php echo $u['barracks'] ? 'Yes' : '<span class="none">No</span>'; ?></td>
</tr>
<?php } ?>
</table>

<!-- ============================================================ -->
<h2 class="section"><a name="equipment"></a>Weapons and Armor <a class="top" href="#toc">top</a></h2>

<p>A warrior, priest or archer is only as good as what it carries: a unit's entire
attack and defense contribution comes from its equipment. Equipment is built once,
on the Construct pages, and then equipped to your whole army at once on the Equip
page &mdash; you do not buy one sword per soldier.</p>

<p>Items unlock in order: you must build the previous item in a line before the next
becomes available. <b>Speed</b> determines how long your army takes to come home
after an attack (lower is better; the slowest unit you send sets the pace for all of
them). <b>EXP Gained</b> is the experience added to your score when the item finishes.</p>

<h3>Warrior Weapons</h3>
<?php mb_man_gear_table($GAMEDATA['warrior_weapons'], 'Power', 'Iron'); ?>

<h3>Priest Weapons</h3>
<p class="none">Demons and Giants cannot field priests and cannot build these.</p>
<?php mb_man_gear_table($GAMEDATA['priest_weapons'], 'Power', 'Iron'); ?>

<h3>Archer Weapons</h3>
<p class="none">Requires Archery research. These cost lumber rather than iron.</p>
<?php mb_man_gear_table($GAMEDATA['archer_weapons'], 'Power', 'Lumber'); ?>

<h3>Armor</h3>
<table class="data">
<tr><th>Name</th><th>Worn by</th><th class="num">Speed</th><th class="num">Defense</th>
	<th class="num">EXP Gained</th><th class="num">Gold</th><th class="num">Iron</th>
	<th class="num">Ticks</th><th>Available To</th></tr>
<?php foreach ($GAMEDATA['armor'] as $a) { ?>
<tr><td><?php echo htmlspecialchars($a[0]); ?></td>
	<td><?php echo htmlspecialchars($a[1]); ?></td>
	<td class="num"><?php echo $a[2]; ?></td>
	<td class="num"><?php echo $a[3]; ?></td>
	<td class="num"><?php echo mb_man_num($a[4]); ?></td>
	<td class="num"><?php echo mb_man_num($a[5]); ?></td>
	<td class="num"><?php echo mb_man_num($a[6]); ?></td>
	<td class="num"><?php echo mb_man_num($a[7]); ?></td>
	<td><?php echo mb_man_restrict($a[8]); ?></td></tr>
<?php } ?>
</table>

<h3>Wizard Spells</h3>
<p>Wizards are equipped with spells rather than built weapons. Spells cost nothing to
equip &mdash; the only price is the <a href="#research">research</a> that unlocks them.</p>
<table class="data">
<tr><th>Spell</th><th class="num">Speed</th><th class="num">Power</th><th class="num">Research Points</th></tr>
<?php foreach ($GAMEDATA['spells'] as $s) { ?>
<tr><td><?php echo htmlspecialchars($s[0]); ?></td>
	<td class="num"><?php echo $s[1]; ?></td>
	<td class="num"><?php echo $s[2]; ?></td>
	<td class="num"><?php echo $s[3] > 0 ? number_format($s[3]) : '<span class="none">starting spell</span>'; ?></td></tr>
<?php } ?>
</table>

<!-- ============================================================ -->
<h2 class="section"><a name="research"></a>Research <a class="top" href="#toc">top</a></h2>

<p>Research is measured in points, and points come from sages. <b>Each sage assigned
to a project produces one point per tick</b>, adjusted by class: Savants
<?php echo $GAMEDATA['classes']['Savant']['research_mult']; ?>&times;, Warlocks
<?php echo $GAMEDATA['classes']['Warlock']['research_mult']; ?>&times;. Sages are not
consumed &mdash; move them between projects freely. Research costs no gold beyond the
price of the sages themselves.</p>

<table class="data">
<tr><th>Research</th><th class="num">Points</th><th>Requires</th><th>Effect</th><th>Available To</th></tr>
<?php foreach ($GAMEDATA['research'] as $r) { ?>
<tr><td><b><?php echo htmlspecialchars($r[0]); ?></b></td>
	<td class="num"><?php echo number_format($r[1]); ?></td>
	<td><?php echo $r[2] === '' ? '<span class="none">&mdash;</span>' : htmlspecialchars($r[2]); ?></td>
	<td><?php echo htmlspecialchars($r[3]); ?></td>
	<td><?php echo $r[4] === '' ? '<span class="none">as above</span>' : htmlspecialchars($r[4]); ?></td></tr>
<?php } ?>
</table>

<!-- ============================================================ -->
<h2 class="section"><a name="combat"></a>Attacking <a class="top" href="#toc">top</a></h2>

<p>You command <b><?php echo $GAMEDATA['combat']['generals']; ?> generals</b>. Each
army you send takes one, and it is unavailable until that army returns, so you can
have at most <?php echo $GAMEDATA['combat']['generals']; ?> attacks in the field at
once. You cannot attack anyone in your own guild, anyone in Safe Mode, or yourself.</p>

<h3>How a battle is decided</h3>

<p>Combat is not a dice roll. Your attack power is the sum of the units you send,
each counted at its equipped weapon's power. Their defense is the sum of everything
they have at home, counted the same way, plus
<b><?php echo $econ['wp_defense']; ?> per wooden platform</b> and
<b><?php echo $econ['catapult_defense']; ?> per catapult</b>. Golems add
<?php echo $econ['golem_power']; ?> and iron golems
<?php echo $econ['irongolem_power']; ?>. Both totals are then adjusted by race and
class modifiers. <b>If your attack power exceeds their defense, you win.</b></p>

<p>Both sides take losses in proportion to the power thrown at them, win or lose.
Survivors are unavailable until they return home; return time is set by the slowest
unit type you sent, and Elves get theirs
<?php echo round((1 - $GAMEDATA['combat']['elf_return_mult']) * 100); ?>% faster.</p>

<h3>Types of attack</h3>

<table class="data">
<tr><th>Attack</th><th>What you take</th></tr>
<tr><td><b>Resource Attack</b></td>
	<td><b><?php echo $GAMEDATA['combat']['resource_gain_pct']; ?>%</b> of the target's
	gold, iron, lumber and civilians.</td></tr>
<tr><td><b>Mountain Attack</b></td>
	<td><b><?php echo $GAMEDATA['combat']['mountain_gain_pct']; ?>%</b> of the target's
	mountains.</td></tr>
<tr><td><b>Land Attack</b></td>
	<td><b><?php echo $GAMEDATA['combat']['land_gain_pct']; ?>%</b> of the target's land
	if they hold <?php echo $GAMEDATA['combat']['land_gain_flat'] * 20; ?> or more; a flat
	<?php echo $GAMEDATA['combat']['land_gain_flat']; ?> land if they hold between
	<?php echo $GAMEDATA['combat']['land_gain_flat']; ?> and
	<?php echo ($GAMEDATA['combat']['land_gain_flat'] * 20) - 1; ?>; and everything they
	have left below that &mdash; which destroys the empire outright. Land is taken from
	their empty tiles first, then buildings under construction, then finished buildings.</td></tr>
<tr><td><b>Suicide Attack</b></td>
	<td>Requires Demolitions. Each Suicide Civilian sent kills
	<b><?php echo $GAMEDATA['combat']['suicide_kills']; ?> enemy civilians or
	<?php echo $GAMEDATA['combat']['suicide_kills']; ?> enemy thieves</b> (you choose
	which). It costs no general and cannot fail, but every civilian sent is gone forever.</td></tr>
</table>

<!-- ============================================================ -->
<h2 class="section"><a name="intel"></a>Intelligence <a class="top" href="#toc">top</a></h2>

<p>Thieves gather intelligence, and thieves defend against it. A spy attempt
<b><?php echo $GAMEDATA['intel']['success_rule']; ?></b>. There is no element of
chance: keeping more thieves than your rivals are willing to send is complete
protection.</p>

<p>You lose <b><?php echo $GAMEDATA['intel']['loss_fail_pct']; ?>%</b> of the thieves
you sent on a failed attempt and <b><?php echo $GAMEDATA['intel']['loss_success_pct']; ?>%</b>
on a successful one. Either way <b>the target is told</b> &mdash; spying is never
silent. A successful report shows their race and class, their gold, iron, lumber,
civilians and thieves, their unit counts with the power of each unit's equipment,
and their total defense.</p>

<!-- ============================================================ -->
<h2 class="section"><a name="social"></a>Settlements, Guilds and Messages <a class="top" href="#toc">top</a></h2>

<h3>Settlements</h3>

<p>The world is divided into <b><?php echo $GAMEDATA['settlements']['count']; ?> settlements</b>,
and you are placed in one at signup permanently. The Settlement Browser lets you look
through every settlement in the game; your own is where you find most of your targets
and neighbours. Each settlement elects a <b>Settlement Leader</b> &mdash; one vote per
empire, cast for anyone in your own settlement, changeable at any time, and whoever
holds the most votes holds the office. The leader sets the settlement's name, picture
and notice. Settlement News records attacks, arrivals and departures; the Settlement
Forums are for everything else.</p>

<h3>Guilds</h3>

<p>Guilds cross settlement boundaries. Anyone can found one &mdash; names are up to
<?php echo $GAMEDATA['guilds']['max_name_length']; ?> characters and you must set a
deletion password &mdash; or request membership in an existing guild from the Guild
Center. A guild holds up to <b><?php echo $GAMEDATA['guilds']['max_members']; ?> empires</b>.
A guild's strength is the sum of its members' scores, recalculated every tick.</p>

<p>You cannot attack a guildmate. A Guild Leader cannot leave their own guild or join
another; members may leave whenever they like. If a Guild Leader's empire is destroyed,
the guild is dissolved.</p>

<h3>Messages</h3>

<p>You can message any empire in the game from the Send page, and reply from your
Inbox. Basic <b>bold</b> and <i>italic</i> markup survives; everything else is stripped.
Messages and news are deleted after
<?php echo $GAMEDATA['timers']['message_ttl_ticks']; ?> ticks.</p>

<!-- ============================================================ -->
<h2 class="section"><a name="exp"></a>Experience and Score <a class="top" href="#toc">top</a></h2>

<p>Your score is a <b>snapshot, not a total</b>. Every tick it is recalculated from
scratch out of everything you currently hold, so losing an army lowers your score
immediately and winning a battle raises it only through the land and units you keep.
Attacking does not itself award points. Everyone has a floor of
<?php echo number_format($GAMEDATA['timers']['exp_floor']); ?>.</p>

<table class="data">
<tr><th>Holding</th><th class="num">EXP each</th></tr>
<?php foreach ($GAMEDATA['exp_values'] as $what => $pts) { ?>
<tr><td><?php echo htmlspecialchars($what); ?></td><td class="num"><?php echo $pts; ?></td></tr>
<?php } ?>
</table>

<p>Built weapons and armor add to your score as well, at the values shown in the
<a href="#equipment">equipment tables</a>.</p>

<!-- ============================================================ -->
<h2 class="section"><a name="rules"></a>Game Rules <a class="top" href="#toc">top</a></h2>

<ol>
	<li>You are permitted one account only.</li>
	<li>Acceptable language must be used at all times.</li>
	<li>Do not spam the forums or other players.</li>
	<li>Log into your own account and no one else's. If a friend goes on holiday you
		may not manage their empire for them; both accounts will be deleted if you do.</li>
	<li>Deleting and recreating your account is not allowed, for any reason. Check your
		details carefully when you sign up.</li>
	<li>No disrespect toward administrators or moderators. If you do not care for them,
		do not play.</li>
</ol>

<!-- ============================================================ -->
<h2 class="section"><a name="quirks"></a>Known Quirks <a class="top" href="#toc">top</a></h2>

<p>Medieval Battles was written in 2003 and this manual is generated from the code as
it actually runs, not from what the code was meant to do. Where the two differ, the
tables above describe real behaviour. These are the differences worth knowing about:</p>

<div class="warn">
	<ul>
<?php foreach ($GAMEDATA['quirks'] as $q) { ?>
		<li><?php echo htmlspecialchars($q); ?></li>
<?php } ?>
	</ul>
</div>

<div id="footer">
	Generated from the game engine. Every figure on this page is defined in
	<tt>include/gamedata.php</tt>, where each value cites the source file and line it
	was taken from.
</div>

</div>
</body>
</html>
