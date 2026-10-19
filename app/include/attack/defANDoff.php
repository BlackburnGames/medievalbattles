<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$empvalue   = mb_input('empvalue');

// The six unit counts are NOT read here, and must not be. This file is only
// ever included by attack.php, attackm.php and attackr.php, which read and
// check them -- and the Phase 2 port gave this file its own mb_input() copies,
// which threw the pages' checks away. attackm.php and attackr.php never read
// the two golem counts at all, so a mountain or resource attack could name any
// number of golems, own none and research nothing, and have every one counted
// into $cdefense below; calculations.php then took them off a stock of zero.
// The counts used here are the ones the page validated, in the page's scope.
		
// The defender is locked before it is read, as the attacker was in
// functions.php: everything below is a snapshot written back.
if(!mb_kingdom_lock_target($db, $empvalue, $userid))	{
	echo"<div align=center><font class=yellow>That empire is busy. Try again in a moment.</font></div><br><br>";
	include("include/attack/mdrop.php");
	include("include/attack/table.php");
	die();
}

// select user table for the empire being attacked
$result = mb_query($db, "SELECT * FROM user WHERE round_id=? AND userid=?", array((int) $round_id, (int) $empvalue));
$evu = mysqli_fetch_array($result);

// An id that names nobody. Every page that includes this has already passed
// the 'ns' check, so without this the battle ran against an empty row: the
// land attack scored it as land 0 and took the "destroyed" branch, which
// deletes by $evu['userid'] -- an empty string. Nothing has been sent yet;
// calculations.php is what moves the troops, and it comes later.
if(!$evu)	{
	echo"<div align=center><font class=yellow>That empire does not exist.</font></div><br><br>";
	include("include/attack/mdrop.php");
	include("include/attack/table.php");
	die();
}

// select buildings table for the empire being attacked
$result1 = mb_query($db, "SELECT * FROM buildings WHERE userid=?", array((int) $empvalue));
$evb = mysqli_fetch_array($result1);
	
// select military table for the empire being attacked
$result2 = mb_query($db, "SELECT * FROM military WHERE userid=?", array((int) $empvalue));
$evm = mysqli_fetch_array($result2);

if($evu['safemode'] > 0)	{
	echo"<div align=center><font class=yellow>You cannot attack an empire that is in safe mode.</font></div><br><br>";
	include("include/attack/mdrop.php");
	include("include/attack/table.php");
	die();
}
	
// Your guild id
$G_id = mb_query($db, "SELECT gid FROM guild WHERE round_id=? AND gname=?", array((int) $round_id, $empireguild));
$gid = mysqli_fetch_array($G_id);

// Empire being attacked guild id
$tG_id = mb_query($db, "SELECT gid FROM guild WHERE round_id=? AND gname=?", array((int) $round_id, $evu['guild']));
$tgid = mysqli_fetch_array($tG_id);
				
// Each side's own race and class: the attacker's on the attack, the
// defender's on the defence. This was one table, read for the attacker and
// applied to both totals, so the two cancelled and the modifiers never
// decided a battle -- only how many fell on each side. The status screen's
// defence figure and the manual both promised each empire its own.
// include/modifier.php is the one table now, and it reads $GAMEDATA.
require_once __DIR__ . '/../modifier.php';

$modifier = mb_offdef_modifier($race, $class);
$evmodifier = mb_offdef_modifier($evu['race'], $evu['class']);
				
$cdefense = round((($uwarrior * $warpower) + ($uwizard * $wizpower) + ($upriest * $pripower) + ($uarcher * $archpower) + ($ugolem * 38) + ($uirongolem * 50)) * $modifier);	
				
$evpower = round((($evm['warriors'] * $evm['warpower']) + ($evm['wizards'] * $evm['wizpower']) + ($evm['priests'] * $evm['pripower']) + ($evm['archers'] * $evm['archpower']) + ($evm['golem'] * 38) + ($evm['irongolem'] * 50) + ($evb['wp'] * 15) + ($evm['catapult'] * 30)) * $evmodifier);
?>