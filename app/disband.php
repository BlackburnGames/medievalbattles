<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$trains    = mb_input('trains');
$uarcher   = mb_input('uarcher');
$uexplorer = mb_input('uexplorer');
$upriest   = mb_input('upriest');
$usage     = mb_input('usage');
$uthief    = mb_input('uthief');
$uwarrior  = mb_input('uwarrior');
$uwizard   = mb_input('uwizard');
 
include("include/igtop.php");

echo "
<div align=center>
<b>&nbsp;[&nbsp;<a href=disband.php>Disband</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=equip.php>Equip</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=wconstruct.php>Construct Weapon</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=aconstruct.php>Construct Armor</a>&nbsp;]</b>
</div>
<br><br>";

if(!IsSet($trains))	 {
	include("include/disband_table.php");
}
else	{

	include("include/nexplode.php");
	// nexplode.php leaves these four as text, for equip.php's weapon names.
	$uwarrior = mb_count($uwarrior);
	$uwizard = mb_count($uwizard);
	$upriest = mb_count($upriest);
	$uarcher = mb_count($uarcher);

	if($uarcher > 0 AND $res['r13pts'] < 125000)	{
		echo"<div align=center><font class=yellow>You do not have Archers!</font></div>"; 
		include("include/disband_table.php");
		die();
	}
	elseif($uwizard > 0 AND $class == 'Ranger')	{
		echo"<div align=center><font class=yellow>You do not have Wizards!</div></font>";
		include("include/disband_table.php");
		die();
	}
	elseif($uwizard > 0 AND $class == 'Insurrectionist')	{
		echo"<div align=center><font class=yellow>You do not have Wizards!</div></font>";
		include("include/disband_table.php");
		die();
	}
	// Demon is a race, not a class; as $class this could never be true.
	elseif($upriest > 0 AND $race == 'Demon')	{
		echo"<div align=center><font class=yellow>You do not have Priests!</div></font>";
		include("include/disband_table.php");
		die();
	}
	elseif($uthief < 0 OR $usage < 0 OR $uexplorer < 0 OR $uwarrior < 0 OR $uwizard < 0 OR $upriest < 0 OR $uarcher < 0)	 {
		echo"<div align=center><font class=yellow>You can't disband negative or 0 units!</font></div>"; 
		include("include/disband_table.php");
		die();
	}
	elseif($gp < (($uthief + $uexplorer + $usage + $upriest + $uwizard + $uwarrior + $uarcher) * 200))	 {
		echo"<div align=center><font class=yellow>You don't have that many gold pieces!</font></div>";
		include("include/disband_table.php");
		die(); 
	} 
	elseif(($thieves < $uthief) OR ($sages < $usage) OR ($explorers < $uexplorer) OR ($priests < $upriest) OR ($wizards < $uwizard) OR ($warriors < $uwarrior) OR ($archers < $uarcher))	 {
		echo"<div align=center><font class=yellow>You don't have that many units!</font></div>";
		include("include/disband_table.php");
		die(); 
	} 
	elseif($upriest > 0 AND $uwizard > 0 AND $race == 'Giant')	{
		echo"<div align=center><font class=yellow>You do not have Wizards or Priests!</font></div>";
		include("include/disband_table.php");
		die(); 
	}
	
	$gp = $gp - (($uthief + $usage + $uexplorer + $upriest + $uwizard + $uwarrior + $uarcher) * 200);	
	$gp = round($gp);
	mb_query($db, "UPDATE user SET gp=? WHERE email=? AND pw=?", array($gp, $email, $pw)); 

	$new_recruits = $recruits + ($uthief + $usage + $uexplorer + $upriest + $uwizard + $uwarrior + $uarcher);
	mb_query($db, "UPDATE military SET recruits=? WHERE email=? AND pw=?", array($new_recruits, $email, $pw));

	$new_thieves = $thieves - $uthief;
	$new_sages = $sages - $usage;
	$new_explorers = $aexplorers - $uexplorer;
	$new_priests = $priests - $upriest;
	$new_wizards = $wizards - $uwizard;
	$new_warriors = $warriors - $uwarrior;
	$new_archers = $archers - $uarcher;

	mb_query($db, "UPDATE military SET thieves=? WHERE email=? AND pw=?", array($new_thieves, $email, $pw));
	mb_query($db, "UPDATE military SET sages=? WHERE email=? AND pw=?", array($new_sages, $email, $pw));
	mb_query($db, "UPDATE military SET explorers=? WHERE email=? AND pw=?", array($new_explorers, $email, $pw));
	mb_query($db, "UPDATE military SET priests=? WHERE email=? AND pw=?", array($new_priests, $email, $pw));
	mb_query($db, "UPDATE military SET wizards=? WHERE email=? AND pw=?", array($new_wizards, $email, $pw));
	mb_query($db, "UPDATE military SET warriors=? WHERE email=? AND pw=?", array($new_warriors, $email, $pw));
	mb_query($db, "UPDATE military SET archers=? WHERE email=? AND pw=?", array($new_archers, $email, $pw));

	echo"<div align=center><font class=yellow>The specified units have been disbanded.</font></div>";
	include("include/disband_table.php");
	die();
}
?>
</TD>
</TR>
</TABLE>
</BODY>
</HTML>