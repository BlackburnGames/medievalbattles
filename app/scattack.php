<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$empvalue   = mb_input('empvalue');
$send       = mb_input('send');
$suicideatk = mb_input('suicideatk');
$toatk      = mb_input('toatk');
	 
include("include/igtop.php");	

echo "
<center>
<form type=post action=scattack.php>
	<b class=reg>Settlement:</b><input type=number name=snum size=3 maxlength=3>
	<input type=hidden name=setchg value=1>
	<input type=submit value=Change>
</form>
<br><br>";

if(!IsSet($suicideatk))	{
	include("include/attack/scattack.php");
}
else	{

	include("include/nexplode.php");

	// A whole number, as every other order form's counts are. It was compared
	// and subtracted as typed, so send=0.5 passed "> 0", killed a civilian and
	// cost nothing: MySQL rounded the stored suicide - 0.5 back up.
	$send = mb_count($send);

	if($safemode > 0)	 {
		echo "<div align=center><font class=yellow>You can't attack when you are in safe mode!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	elseif($empvalue == 'ns')	 {
		echo "<div align=center><font class=yellow>You can't attack an invisible person!</font></div>";
		include("include/attack/scattack.php");
		die();
	}

	// Who, before how many. The target used to be looked up only after every
	// unit check had passed, and nothing then asked whether the row existed:
	// an id that named nobody "succeeded" against an empty row and
	// spent the units.
	include("include/connect.php");

	// The target is locked before it is read: see mb_kingdom_lock_target().
	if(!mb_kingdom_lock_target($db, $empvalue, $userid))	{
		echo"<div align=center><font class=yellow>That empire is busy. Try again in a moment.</font></div>";
		include("include/attack/scattack.php");
		die();
	}

	// select user values
		$result = mb_query($db, "SELECT * FROM user WHERE round_id=? AND userid=?", array((int) $round_id, (int) $empvalue));
		$evu = mysqli_fetch_array($result);
	// select military values
		$result1 = mb_query($db, "SELECT * FROM military WHERE userid=?", array((int) $empvalue));
		$evm = mysqli_fetch_array($result1);
	// select explore values
		$result2 = mb_query($db, "SELECT * FROM explore WHERE userid=?", array((int) $empvalue));
		$eve = mysqli_fetch_array($result2);
	// select building values
		$result3 = mb_query($db, "SELECT * FROM buildings WHERE userid=?", array((int) $empvalue));
		$evb = mysqli_fetch_array($result3);

	if(!$evu)	{
		echo"<div align=center><font class=yellow>That empire does not exist.</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	// The check the three army pages make and this one never did. The guild
	// check below stopped a guild member, so the gap was a guildless empire
	// suiciding on itself.
	elseif($evu['ename'] === $ename)	{
		echo"<div align=center><font class=yellow>You cannot attack your ownself!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	elseif($evu['safemode'] > 0)	{
		echo"<div align=center><font class=yellow>You can't attack someone who's in safe mode!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	elseif($evu['guild'] === $empireguild AND $evu['guild'] != "None" AND $empireguild != "None")	{
		echo"<div align=center><font class=yellow>You can't attack someone who is in your guild!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	elseif($send == "")	{
		echo "<div align=center><font class=yellow>You didn't send any units!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	elseif($send <= 0)	{
		echo "<div align=center><font class=yellow>You didn't send any units!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	elseif($send > $suicide)	{
		echo"<div align=center><font class=yellow>You don't have that many units!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	elseif($send > $suicide)	{
		echo"<div align=center><font class=yellow>You don't have that many units!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	elseif($toatk !== 'civ' && $toatk !== 'thieve')	{
		echo"<div align=center><font class=yellow>You must choose a type of unit to suicide on!</font></div>";
		include("include/attack/scattack.php");
		die();
	}
	else	{

		if($toatk == 'civ')	{
			//	their civilians
				$their_civilians = $evm['civ'];
			// determine how many units survived
				$surviving_civilians = ($their_civilians - ($send * 2));
				$surviving_civilians = round($surviving_civilians);
					if($surviving_civilians <= 0)	{	$surviving_civilians = 0;	 }
				mb_query($db, "UPDATE military SET civ=? WHERE userid=?", array($surviving_civilians, (int) $empvalue));
			//	determine how many units died
				$dead_civs = $their_civilians - $surviving_civilians;
				$dead_thieves = 0;
		}
		if($toatk == 'thieve')	 {
			//	their thieves
				$their_thieves = $evm['thieves'];
			// determine how many units survived
				$surviving_thieves = ($their_thieves - ($send * 2));
				$surviving_thieves = round($surviving_thieves);
					if($surviving_thieves <= 0)	{	$surviving_thieves = 0;	 }
				mb_query($db, "UPDATE military SET thieves=? WHERE userid=?", array($surviving_thieves, (int) $empvalue));
			//	determine how many units died
				$dead_thieves = $their_thieves - $surviving_thieves;
				$dead_civs = 0;
		}

		// calculate and update your new suicide civs
			$new_suicide_civilians = $suicide - $send;
			mb_query($db, "UPDATE military SET suicide=? WHERE userid=?", array($new_suicide_civilians, $userid));
		//  attack news - their empire news		
			mb_query($db, "UPDATE user SET nno=nno+1 WHERE userid=?", array((int) $empvalue));
			mb_news_emp($db, "$clock", 'yellow', "$ename ($setid) has sucessfully suicided on your empire. You lost $dead_civs Civilian(s) and $dead_thieves Thieve(s).", $empvalue);
				
		echo "<div align=center><font class=yellow>You have successfully suicided on the empire of " . mb_h($evu['ename']) . " (" . mb_h($evu['setid']) . "). You killed $dead_civs Civilian(s) and $dead_thieves Thieve(s).</font></div>";
		include("include/attack/scattack.php");
		die();		
	}
}
?>
</td>
</tr>
</table>
</body>
</html>
<?
ob_end_flush();
?>