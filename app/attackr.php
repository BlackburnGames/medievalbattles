<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$attack   = mb_input('attack');
$empvalue = mb_input('empvalue');
$uarcher  = mb_input('uarcher');
$ugolem     = mb_input('ugolem');
$uirongolem = mb_input('uirongolem');
$upriest  = mb_input('upriest');
$uwarrior = mb_input('uwarrior');
$uwizard  = mb_input('uwizard');

include("include/igtop.php");

echo "
<center>
<b>[&nbsp;<a href=attack.php>Land</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=attackr.php>Resource</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=attackm.php>Mountain</a>&nbsp;]</b>
	
<form type=post action=attackr.php>
 <b class=reg>Settlement:</b><input type=number name=snum size=3 maxlength=3>
  <input type=hidden name=setchg value=1>
  <input type=submit value=Change>
 </form>
</center>
<br><br>";

if(!IsSet($attack))	{
	include("include/attack/rdrop.php");
	include("include/attack/table.php");
}
else	{

	include("include/nexplode.php");
		
	// Whole numbers from here on. These are the only unit counts the attack
	// uses -- include/attack/defANDoff.php and calculations.php read no copy of
	// their own -- so an absent field has to arrive as 0, not as the "" that
	// implode() makes of it, which PHP 8 refuses to multiply.
	$uwarrior = (int) implode("", explode(",", $uwarrior));
	$uwizard = (int) implode("", explode(",", $uwizard));
	$upriest = (int) implode("", explode(",", $upriest));
	$uarcher = (int) implode("", explode(",", $uarcher));
	$ugolem = (int) implode("", explode(",", $ugolem));
	$uirongolem = (int) implode("", explode(",", $uirongolem));
		
	if($safemode > 0)	 {
		echo"<div align=center><font class=yellow>You cannot attack while in safe mode.</font></div><br><br>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($fleets == 0)	{
		echo"<div align=center><font class=yellow>You do not have any generals available.</font><br><br></div>";
		include("include/attack/rdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($empvalue == 'ns')	 {
		echo"<div align=center><font class=yellow>You did not specify anyone to attack!</font><br><br></div>";
		include("include/attack/rdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($uarcher > 0 AND $res['r13pts'] < 125000)	 {
		echo"<div align=center><font class=yellow>You have to research archery.<br><br></font></div>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	// The form in include/attack/table.php offers both golem fields on all
	// three attack pages, and attack.php checked them. This page never read
	// them, so include/attack/defANDoff.php's own copy of the request counted
	// any number of golems into the attack -- owned or not, researched or not.
	elseif(($ugolem > 0) AND ($res['r17pts'] < 125000))	{
		echo"<div align=center><font class=yellow>You have to research Animation.<br><br></font></div>";
		include("include/attack/rdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif(($uirongolem > 0) AND ($res['r18pts'] < 125000))	{
		echo"<div align=center><font class=yellow>You have to research Advanced Animation.<br><br></font></div>";
		include("include/attack/rdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($race == 'Giant' AND $uwizard > 0)	{
		echo"<div align=center><font class=yellow>Being that you are a Giant, you cannot attack with wizards.</div></font>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($race == 'Giant' AND $upriest > 0)	{
		echo"<div align=center><font class=yellow>Being that you are a Giant, you cannot attack with  priests.</div></font>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	// This was a second copy of the Giant check above, so Demons were never
	// asked -- and the barter board would sell them priests to bring.
	elseif($race == 'Demon' AND $upriest > 0)	{
		echo"<div align=center><font class=yellow>Being that you are a Demon, you cannot attack with priests.</div></font>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($class == 'Ranger' AND $uwizard > 0)	{
		echo"<div align=center><font class=yellow>Being that you are a Ranger, you cannot attack with wizards.</div></font>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	//include offense, defense values and ename, land, aland
	include("include/attack/defANDoff.php");
					
	$EMPs_guild_query = mb_query($db, "SELECT guild FROM user WHERE userid=?", array($evu['userid']));
		$EMPs_guild = mb_db_result($EMPs_guild_query,"EMPs_guild");

	// Nothing above zero, not merely nothing typed: a field of "0" passed the
	// old `== ""` test and calculations.php then divided by a tally of zero.
	if(!($uwarrior > 0 OR $uwizard > 0 OR $upriest > 0 OR $uarcher > 0 OR $ugolem > 0 OR $uirongolem > 0))	{
		echo"<div align=center><font class=yellow>You did not send any troops into combat!</font></div><br><br>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($EMPs_guild == $empireguild AND $EMPs_guild != "None" AND $empireguild != "None")	{
		echo"<div align=center><font class=yellow>You cannot attack someone that is in your guild.</font></div><br><br>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($evu['ename'] === $ename)	{
		echo"<div align=center><font class=yellow>You cannot attack your ownself!</font></div><br><br>";
		include("include/attack/rdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($warriors < $uwarrior OR $wizards < $uwizard OR $priests < $upriest OR $archers < $uarcher OR $golem < $ugolem OR $irongolem < $uirongolem)	{
		echo"<div align=center><font class=yellow>You cannot send that many units into combat.</font></div><br><br>";	
		include("include/attack/rdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($uwarrior < 0 OR $uwizard < 0 OR $upriest < 0 OR $uarcher < 0 OR $ugolem < 0 OR $uirongolem < 0)	{
		echo"<div align=center><font class=yellow>You cannot send negative or 0 units.</font></div><br><br>";
		include("include/attack/rdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($cdefense <= $evpower)	{
	
		include("include/attack/calculations.php");
			
		mb_news_set($db, "$clock", 'yellow', "$ename ($setid) failed to attack $evu[ename] ($evu[setid]) for resources", $setid, 0);
			
		if($gid[0] !="")	 {
			mb_news_guild($db, "$clock", 'yellow', "$ename ($setid) failed to attack $evu[ename] ($evu[setid]) for resources", $gid['0'], 0);
			}

//	The defender's own settlement, which used to be $tsetid -- a name nothing in
//	this file or anything it includes has ever assigned. Both of these rows were
//	filed against setid 0, so no settlement has ever heard about a raid on one of
//	its members. attack.php files the same sentence against $evu['setid'].
		mb_news_set($db, "$clock", 'orange', "$evu[ename] ($evu[setid]) successfully defended their resources against $ename ($setid)", $evu['setid'], 0);
			
		if($tgid[0] != "")	{
			mb_news_guild($db, "$clock", 'orange', "$evu[ename] ($evu[setid]) successfully defended their resources against $ename ($setid)", $tgid['0'], 0);
		}			
								

		mb_query($db, "UPDATE user SET nno = nno+1 WHERE userid=?", array((int) $empvalue));

		mb_news_emp($db, "$clock", 'yellow', "We have successfully defended our empire against $ename ($setid)", $empvalue);
		echo"<div align=center><font class=yellow>You have failed to attack $evu[ename]!</font><br><br><font class=orange>$your_losses<br><br></font></div>";
		include("include/attack/rdrop.php");
		include("include/attack/table.php");
		die();
	}
	else	{
		
		include("include/connect.php");
		include("include/attack/calculations.php");

		if($evu['gp'] < 0)	{	$gpgain = 0;	}
		if($evu['gp'] >= 0)	{	$gpgain = $evu['gp'] * .08;	 }
    
		$irongain = $evu['iron'] * .08;
		$civgain = $evm['civ'] * .08;
		$lumbergain = $evu['lumber'] * .08;
	
		$gpgain = round($gpgain);
		$irongain = round($irongain);
		$civgain = round($civgain);
		$lumbergain = round($lumbergain);
	
		mysqli_query($db, "UPDATE user SET gp = gp + $gpgain WHERE email='$email' AND pw='$pw'");
		mysqli_query($db, "UPDATE user SET iron = iron + $irongain WHERE email='$email' AND pw='$pw'");
		mysqli_query($db, "UPDATE user SET lumber = lumber + $lumbergain WHERE email='$email' AND pw='$pw'");
		mysqli_query($db, "UPDATE military SET civ = civ + $civgain WHERE email='$email' AND pw='$pw'");
		
		mysqli_query($db, "UPDATE user SET gp = gp - $gpgain WHERE userid = " . mb_sql_int($empvalue));
		mysqli_query($db, "UPDATE user SET iron = iron - $irongain WHERE userid = " . mb_sql_int($empvalue));
		mysqli_query($db, "UPDATE user SET lumber = lumber - $lumbergain WHERE userid = " . mb_sql_int($empvalue));
		mysqli_query($db, "UPDATE military SET civ  = civ - $civgain WHERE userid = " . mb_sql_int($empvalue));

		mb_news_set($db, "$clock", 'yellow', "$ename ($setid) successfully attacked $evu[ename] ($evu[setid]) for resources", $setid, 0);
				
		if($gid[0] != "")	{
			mb_news_guild($db, "$clock", 'yellow', "$ename ($setid) successfully attacked $evu[ename] ($evu[setid]) for resources", $gid['0'], 0);
		}
				
		mb_news_set($db, "$clock", 'lg', "$evu[ename] ($evu[setid]) lost resources to $ename ($setid)", $evu['setid'], 0);
				
		if($tgid[0] != "")	{
			mb_news_guild($db, "$clock", 'lg', "$evu[ename] ($evu[setid]) lost resources to $ename ($setid)", $tgid['0'], 0);
		}
 
		echo"<div align=center><font class=yellow>Success! You have managed to gain $gpgain gold pieces, $irongain iron, $lumbergain lumber, and $civgain civilians from $evu[ename]($evu[setid])!</font><br><br><font class=orange>$your_losses<br><br></font></div>";
		include("include/attack/rdrop.php");
		include("include/attack/table.php");

		mb_query($db, "UPDATE user SET nno = nno + 1 WHERE userid=?", array((int) $empvalue));

		mb_news_emp($db, "$clock", 'yellow', "We have unsuccessfully defended our empire against $ename ($setid) and lost $gpgain gold pieces, $irongain iron, $lumbergain lumber, and $civgain civilians.", $empvalue);
		die();
	}
}
?>	
</td>
</tr>
</table>
</body>
</html>