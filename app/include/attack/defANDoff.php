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
		
// select user table for the empire being attacked
$result = mysqli_query($db, "SELECT * FROM user WHERE userid=" . mb_sql_int($empvalue));
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
$result1 = mysqli_query($db, "SELECT * FROM buildings WHERE userid=" . mb_sql_int($empvalue));
$evb = mysqli_fetch_array($result1);
	
// select military table for the empire being attacked
$result2 = mysqli_query($db, "SELECT * FROM military WHERE userid=" . mb_sql_int($empvalue));
$evm = mysqli_fetch_array($result2);

if($evu['safemode'] > 0)	{
	echo"<div align=center><font class=yellow>You cannot attack an empire that is in safe mode.</font></div><br><br>";
	include("include/attack/mdrop.php");
	include("include/attack/table.php");
	die();
}
	
// Your guild id
$G_id = mysqli_query($db, "SELECT gid FROM guild WHERE gname=" . mb_sql_str($db, $empireguild) . "");
$gid = mysqli_fetch_array($G_id);

// Empire being attacked guild id
$tG_id = mysqli_query($db, "SELECT gid FROM guild WHERE gname=" . mb_sql_str($db, $evu['guild']) . "");
$tgid = mysqli_fetch_array($tG_id);
				
$modifier = 1;

if($class == 'Fighter')	{	$modifier = 1.05;	}
if($class == 'Cleric')	{	$modifier = 1.00;	}
if($class == 'Ranger')	{	$modifier = 1.00;	}
if($class == 'Mage')	{	$modifier = .95;		}
if($class == 'Warlock')	{	$modifier = .95;		}
if($class == 'Savant')	{	$modifier = .80;		}
if($class == 'Insurrectionist')	{	$modifier = 1.05;		}
if($race == 'Giant')		{	$modifier = $modifier + .25;	 }
if($race == 'Demon')		{	$modifier = $modifier + .10;	 }
if($race == 'Night Elf')		{	$modifier = $modifier - .10;	 }
				
$cdefense = round((($uwarrior * $warpower) + ($uwizard * $wizpower) + ($upriest * $pripower) + ($uarcher * $archpower) + ($ugolem * 38) + ($uirongolem * 50)) * $modifier);	
				
$evpower = round((($evm['warriors'] * $evm['warpower']) + ($evm['wizards'] * $evm['wizpower']) + ($evm['priests'] * $evm['pripower']) + ($evm['archers'] * $evm['archpower']) + ($evm['golem'] * 38) + ($evm['irongolem'] * 50) + ($evb['wp'] * 15) + ($evm['catapult'] * 30)) * $modifier);
?>