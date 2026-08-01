<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$attack   = mb_input('attack');
$empvalue = mb_input('empvalue');
$uarcher  = mb_input('uarcher');
$upriest  = mb_input('upriest');
$uwarrior = mb_input('uwarrior');
$uwizard  = mb_input('uwizard');

include("include/igtop.php");

echo "
<center>
<b>[&nbsp;<a href=attack.php>Land</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=attackr.php>Resource</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=attackm.php>Mountain</a>&nbsp;]</b>

<form type=post action=attackm.php>
 <b class=reg>Settlement:</b><input type=number name=snum size=3 maxlength=3>
  <input type=hidden name=setchg value=1>
  <input type=submit value=Change>
 </form>
</center>
<br><br>";

if(!IsSet($attack))	{
	include("include/attack/mdrop.php");
	include("include/attack/table.php");
}
else	{
	include("include/nexplode.php");
		
	$uwarrior = implode("", explode(",", $uwarrior));
	$uwizard = implode("", explode(",", $uwizard));
	$upriest = implode("", explode(",", $upriest));
	$uarcher = implode("", explode(",", $uarcher));
		
	if($safemode > 0)	 {
		echo"<div align=center><font class=yellow>You cannot attack while in safe mode.<br><br></font></div>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($fleets == 0)	{
		echo"<div align=center><font class=yellow>You do not have any generals available.</font><br><br></div>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($empvalue == 'ns')	 {
		echo"<div align=center><font class=yellow>You did not specify anyone to attack!</font><br><br></div>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($uarcher > 0 AND $res['r13pts'] < 125000)	 {
		echo"<div align=center><font class=yellow>You have to research archery.<br><br></font></div>";
		include("include/attack/ldrop.php");
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
	elseif($race == 'Giant' AND $upriest > 0)	{
		echo"<div align=center><font class=yellow>Being that you are a Giant, you cannot attack with priests.</div></font>";
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
				
	include("include/attack/defANDoff.php");
			
	//	for attacker
	$mtgain = $evu['mts'] * .1;
	$mtgain = round($mtgain);

	$EMPs_guild_query = mysqli_query($db, "SELECT guild FROM user WHERE userid='$evu[userid]'");
		$EMPs_guild = mb_db_result($EMPs_guild_query,"EMPs_guild");
	
	if($evu['mts'] <= 0)	{
		echo"<div align=center><font class=yellow>$evu[ename] has $evu[mts] so there is no sense in battle.</font></div>";
		die();
	}
	elseif($uwarrior == "" AND $uwizard == "" AND $upriest == "" AND $uarcher == "")	 {
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
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($warriors < $uwarrior OR $wizards < $uwizard OR $priests < $upriest OR $archers < $uarcher)	{
		echo"<div align=center><font class=yellow>You cannot send that many units into combat.</font></div><br><br>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($uwarrior < 0 OR $uwizard < 0 OR $upriest < 0 OR $uarcher < 0)	{
		echo"<div align=center><font class=yellow>You cannot send negative or 0 units.</font></div><br><br>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($cdefense <= $evpower )	{
	
		include("include/attack/calculations.php");
			
		mb_news_set($db, "$clock", 'yellow', "$ename ($setid) failed to attack $evu[ename] ($evu[setid]) for mountains", $setid, 0);
			
		if($gid[0] != "")	{
			mb_news_guild($db, "$clock", 'yellow', "$ename ($setid) failed to attack $evu[ename] ($evu[setid]) for mountains", $gid['0'], 0);
		}

//	The defender's own settlement, which used to be $tsetid -- a name nothing in
//	this file or anything it includes has ever assigned. Both of these rows were
//	filed against setid 0, so no settlement has ever heard about a raid on one of
//	its members. attack.php files the same sentence against $evu['setid'].
		mb_news_set($db, "$clock", 'orange', "$evu[ename] ($evu[setid]) successfully defended their mountains against $ename ($setid)", $evu['setid'], 0);
			
		if($tgid[0] != "")	{
			mb_news_guild($db, "$clock", 'orange', "$evu[ename] ($evu[setid]) successfully defended their mountains against $ename ($setid)", $tgid['0'], 0);
		}
							
		mysqli_query($db, "UPDATE user SET nno = nno + 1 WHERE userid=" . mb_sql_int($empvalue));
		mb_news_emp($db, "$clock", 'yellow', "We have successfully defended our empire against $ename ($setid)", $empvalue);
		echo"<div align=center><font class=yellow>You have failed to attack $evu[ename]!</font><br><br><font class=orange>$your_losses<br><br></font></div>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	else	{
		include("include/connect.php");
		include("include/attack/calculations.php");
	
		While($mgain > $mtally)	 {
			if($evb['amts'] > 0 AND $mgain > $mtally)	{
				$evb['amts'] = $evb['amts'] - 1;
				$mtally = $mtally + 1;
			}
			if($evb['dgm'] > 0 AND $mgain > $mtally AND $evb['amts'] == 0)	 {
				$evb['dgm'] = $evb['dgm'] - 1;
				$mtally = $mtally + 1;
			}
			if($evb['dim'] > 0 AND $mgain > $mtally AND $evb['amts'] == 0)	{
				$evb['dim'] = $evb['dim'] - 1;
				$mtally = $mtally + 1;
			}
		

			if($evb['gm'] > 0 AND $mgain > $mtally AND $evb['amts'] == 0 AND $evb['dgm'] == 0 AND $evb['dim'] == 0)	{
					$evb['gm'] = $evb['gm'] - 1;
					$mtally = $mtally + 1;
				}
			if($evb['im'] > 0 AND $mgain > $mtally AND $evb['amts'] == 0 AND $evb['dgm'] == 0 AND $evb['dim'] == 0)	{
					$evb['im'] = $evb['im'] - 1;
					$mtally = $mtally + 1;
				}
		}
		
		mb_news_set($db, "$clock", 'yellow', "$ename ($setid) successfully attacked $evu[ename] ($evu[setid]) and gained $mtgain mountains", $setid, 0);
		
		if($gid[0] != "")	{
			mb_news_guild($db, "$clock", 'yellow', "$ename ($setid) successfully attacked $evu[ename] ($evu[setid]) and gained $mtgain mountains", $gid['0'], 0);
		}

		mb_news_set($db, "$clock", 'lg', "$evu[ename] ($evu[setid]) lost $mtgain mountains to $ename ($setid)", $evu['setid'], 0);
											
		if($tgid[0] != "")	{
			mb_news_guild($db, "$clock", 'lg', "$evu[ename] ($evu[setid]) lost $mtgain mountains to $ename ($setid)", $tgid['0'], 0);
		}


		//	for attackee
		$tmtloss = $evu['mts'] - $mtgain;
							
		//	attackers
		$A_A_Mts = $mts + $mtgain;
		$A_AMTS = $amts + $mtgain;
		
		mysqli_query($db, "UPDATE user SET mts='$A_A_Mts' WHERE email='$email' AND pw='$pw'");
		mysqli_query($db, "UPDATE buildings SET amts='$A_AMTS' WHERE email='$email' AND pw='$pw'");
      
		echo"<div align=center><font class=yellow>You have conquered $mtgain mountains from $evu[ename] ($evu[setid])!</font><br><br><font class=orange>$your_losses<br><br></font></div>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
	
		mysqli_query($db, "UPDATE user SET exp2 = exp2 + ($mtgain * $mtexpa) WHERE email='$email' AND pw='$pw'");
      
		mysqli_query($db, "UPDATE user SET mts='$tmtloss' WHERE userid = " . mb_sql_int($empvalue));
		mysqli_query($db, "UPDATE buildings SET amts='$evb[amts]' WHERE userid = " . mb_sql_int($empvalue));
		mysqli_query($db, "UPDATE buildings SET gm='$evb[gm]' WHERE userid = " . mb_sql_int($empvalue));
		mysqli_query($db, "UPDATE buildings SET im='$evb[im]' WHERE userid = " . mb_sql_int($empvalue));
		mysqli_query($db, "UPDATE buildings SET dgm='$evb[dgm]' WHERE userid = " . mb_sql_int($empvalue));
		mysqli_query($db, "UPDATE buildings SET dim='$evb[dim]' WHERE userid = " . mb_sql_int($empvalue));

		mysqli_query($db, "UPDATE user SET nno = nno + 1 WHERE userid=" . mb_sql_int($empvalue));

		mb_news_emp($db, "$clock", 'yellow', "We have unsuccessfully defended our empire against $ename ($setid) and lost $mtgain mountains", $empvalue);
		die();
	}
}
?>	
</td>
</tr>
</table>
</body>
</html>