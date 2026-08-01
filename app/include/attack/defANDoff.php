<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$empvalue   = mb_input('empvalue');
$uarcher    = mb_input('uarcher');
$ugolem     = mb_input('ugolem');
$uirongolem = mb_input('uirongolem');
$upriest    = mb_input('upriest');
$uwarrior   = mb_input('uwarrior');
$uwizard    = mb_input('uwizard');
		
// select user table for the empire being attacked
$result = mysqli_query($db, "SELECT * FROM user WHERE userid='$empvalue'");
$evu = mysqli_fetch_array($result);

// select buildings table for the empire being attacked
$result1 = mysqli_query($db, "SELECT * FROM buildings WHERE userid='$empvalue'");
$evb = mysqli_fetch_array($result1);
	
// select military table for the empire being attacked
$result2 = mysqli_query($db, "SELECT * FROM military WHERE userid='$empvalue'");
$evm = mysqli_fetch_array($result2);

if($evu['safemode'] > 0)	{
	echo"<div align=center><font class=yellow>You cannot attack an empire that is in safe mode.</font></div><br><br>";
	include("include/attack/mdrop.php");
	include("include/attack/table.php");
	die();
}
	
// Your guild id
$G_id = mysqli_query($db, "SELECT gid FROM guild WHERE gname='$empireguild'");
$gid = mysqli_fetch_array($G_id);

// Empire being attacked guild id
$tG_id = mysqli_query($db, "SELECT gid FROM guild WHERE gname='$evu[guild]'");
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