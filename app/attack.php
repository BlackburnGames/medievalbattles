<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$attack     = mb_input('attack');
$empvalue   = mb_input('empvalue');
$uarcher    = mb_input('uarcher');
$ugolem     = mb_input('ugolem');
$uirongolem = mb_input('uirongolem');
$upriest    = mb_input('upriest');
$uwarrior   = mb_input('uwarrior');
$uwizard    = mb_input('uwizard');

include("include/igtop.php");

echo "
<center>
<b>[&nbsp;<a href=attack.php>Land</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=attackr.php>Resource</a>&nbsp;]&nbsp;&nbsp;[&nbsp;<a href=attackm.php>Mountain</a>&nbsp;]</b>
	
<form type=post action=attack.php>
 <b class=reg>Settlement:</b><input type=number name=snum size=3 maxlength=3>
  <input type=hidden name=setchg value=1>
  <input type=submit value=Change>
 </form>
</center>
<br><br>";

if(!IsSet($attack))	{
	include("include/attack/ldrop.php");
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
		echo"<div align=center><font class=yellow>You cannot attack while in safe mode.<br><br></font></div>";
		include("include/attack/mdrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($fleets == 0)	{
		echo"<div align=center><font class=yellow>You do not have any generals available.<br><br></font></div>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($empvalue == 'ns')	 {
		echo"<div align=center><font class=yellow>You did not specify anyone to attack!<br><br></font></div>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif(($uarcher > 0) AND ($res['r13pts'] < 125000))	{
		echo"<div align=center><font class=yellow>You have to research Archery.<br><br></font></div>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif(($ugolem > 0) AND ($res['r17pts'] < 125000))	{
		echo"<div align=center><font class=yellow>You have to research Animation.<br><br></font></div>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif(($uirongolem > 0) AND ($res['r18pts'] < 125000))	{
		echo"<div align=center><font class=yellow>You have to research Advanced Animation.<br><br></font></div>";
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
		echo"<div align=center><font class=yellow>Being that you are a Giant, you cannot attack with priests.</div></font>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
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
	
	//	include off, def values and ename, land, aland
	include("include/attack/defANDoff.php");
 						 
	//	for attacker
	if($evu['land'] >= 200)	{	$landgain = round($evu['land'] * .1);	 }
	elseif($evu['land'] < 200 AND $evu['land'] >=10)	{	$landgain = 10;	}
	else	{	$landgain = $evu['land'];	 }
					
	$EMPs_guild_query = mb_query($db, "SELECT guild FROM user WHERE userid=?", array($evu['userid']));
		$EMPs_guild = mb_db_result($EMPs_guild_query,"EMPs_guild");

	// Nothing above zero, not merely nothing typed: a field of "0" passed the
	// old `== ""` test and calculations.php then divided by a tally of zero.
	if(!($uwarrior > 0 OR $uwizard > 0 OR $upriest > 0 OR $uarcher > 0 OR $ugolem > 0 OR $uirongolem > 0))	{
		echo"<div align=center><font class=yellow>You did not send anything into combat!</font></div><br><br>";
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
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($warriors < $uwarrior OR $wizards < $uwizard OR $priests < $upriest OR $archers < $uarcher OR $golem < $ugolem OR $irongolem < $uirongolem)	{
		print"<div align=center><font class=yellow>You cannot send that many units into combat.</font></div><br><br>";	
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($uwarrior < 0 OR $uwizard < 0 OR $upriest < 0 OR $uarcher < 0 OR $ugolem < 0 OR $uirongolem < 0)	{
		echo"<div align=center><font class=yellow>You cannot send negative or 0 units.</font></div><br><br>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	elseif($cdefense <= $evpower)	{
	
		include("include/attack/calculations.php");
					
		$Setid_maxid = mysqli_query($db, "SELECT max(sid) FROM setnews"); // mb-round-ok: ids are issued once across rounds
			$msid = mb_db_result($Setid_maxid,"msid");
		$Guild_MaxID = mysqli_query($db, "SELECT max(gnid) FROM guildnews"); // mb-round-ok: ids are issued once across rounds
			$mgnid = mb_db_result($Guild_MaxID,"mgnid");
					
		$sid = $msid + 1;
		mb_news_set($db, "$clock", 'yellow', "$ename ($setid) failed to attack $evu[ename] ($evu[setid]) for land", $setid, $sid);
					
		if($gid[0] != "")	{
			$gnid = $mgnid + 1;
			mb_news_guild($db, "$clock", 'yellow', "$ename ($setid) failed to attack $evu[ename] ($evu[setid]) for land", $gid['0'], $gnid);
		}
		
		$sid = $msid + 1;
		mb_news_set($db, "$clock", 'orange', "$evu[ename] ($evu[setid]) successfully defended their land against $ename ($setid)", $evu['setid'], $sid);
					
		if($tgid[0] != "")	{
			$gnid = $mgnid + 2;
			mb_news_guild($db, "$clock", 'orange', "$evu[ename] ($evu[setid]) successfully defended their land against $ename ($setid)", $tgid['0'], $gnid);
		}
						
		mb_query($db, "UPDATE user SET nno = nno + 1 WHERE userid=?", array((int) $empvalue));

		mb_news_emp($db, "$clock", 'yellow', "We have successfully defended our empire against $ename ($setid)", $empvalue);
					
		echo"<div align=center><font class=yellow>We have failed to attack $evu[ename]($evu[setid])!</font><br><br><font class=orange>$your_losses<br><br></font></div>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
		die();
	}
	else	{
		include("include/attack/calculations.php");
		
		$lgain = $landgain;						
		$ltally = 0;
		while($lgain > $ltally)	{
			$ltaken = $ltally;	// a pass that takes nothing would loop forever
			if($evb['aland'] > 0 AND $lgain > $ltally)	 {
				$evb['aland'] = $evb['aland'] - 1;
				$ltally = $ltally + 1;
			}
		
			if($evb['dhome'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0)	 {
				$evb['dhome'] = $evb['dhome'] - 1;
				$ltally = $ltally + 1;
			}
			if($evb['dbarrack'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0)	{
				$evb['dbarrack'] = $evb['dbarrack'] - 1;
				$ltally = $ltally + 1;
			}
			if($evb['dfarm'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0)	{
				$evb['dfarm'] = $evb['dfarm'] - 1;
				$ltally = $ltally + 1;
			}
			if($evb['dwp'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0)	{
				$evb['dwp'] = $evb['dwp'] - 1;
				$ltally = $ltally + 1;
			}
			if($evb['dlmill'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0)	{
				$evb['dlmill'] = $evb['dlmill'] - 1;
				$ltally = $ltally + 1;
			}

			if($evb['home'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0 AND $evb['dhome'] == 0 AND $evb['dbarrack'] == 0 AND $evb['dfarm'] == 0 AND $evb['dwp'] == 0 AND $evb['dlmill'] == 0)	{
				$evb['home'] = $evb['home'] - 1;
				$ltally = $ltally + 1;
			}
			if($evb['barrack'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0 AND $evb['dhome'] == 0 AND $evb['dbarrack'] == 0 AND $evb['dfarm'] == 0 AND $evb['dwp'] == 0 AND $evb['dlmill'] == 0)	{
				$evb['barrack'] = $evb['barrack'] - 1;
				$ltally = $ltally + 1;
			}
			if($evb['farm'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0 AND $evb['dhome'] == 0 AND $evb['dbarrack'] == 0 AND $evb['dfarm'] == 0 AND $evb['dwp'] == 0 AND $evb['dlmill'] == 0)	{
				$evb['farm'] = $evb['farm'] - 1;
				$ltally = $ltally + 1;
			}
			if($evb['wp'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0 AND $evb['dhome'] == 0 AND $evb['dbarrack'] == 0 AND $evb['dfarm'] == 0 AND $evb['dwp'] == 0 AND $evb['dlmill'] == 0)	{
				$evb['wp'] = $evb['wp'] - 1;
				$ltally = $ltally + 1;
			}
			if($evb['lmill'] > 0 AND $lgain > $ltally AND $evb['aland'] == 0 AND $evb['dhome'] == 0 AND $evb['dbarrack'] == 0 AND $evb['dfarm'] == 0 AND $evb['dwp'] == 0 AND $evb['dlmill'] == 0)	{
				$evb['lmill'] = $evb['lmill'] - 1;
				$ltally = $ltally + 1;
			}
			if($ltally == $ltaken)	{	break;	}
	}
	
	mb_query($db, "UPDATE user SET land = land + ? WHERE userid=?", array(mb_num($landgain), $userid));
	mb_query($db, "UPDATE buildings SET aland = aland + ? WHERE userid=?", array(mb_num($landgain), $userid));
										
	mb_query($db, "UPDATE user SET land = land - ? WHERE userid = ?", array(mb_num($landgain), (int) $empvalue));
	mb_query($db, "UPDATE buildings SET home=?, barrack=?, farm=?, wp=?, lmill=?, dhome=?, dbarrack=?, dfarm=?, dwp=?, dlmill=?, aland=? WHERE userid = ?", array(mb_num($evb['home']), mb_num($evb['barrack']), mb_num($evb['farm']), mb_num($evb['wp']), mb_num($evb['lmill']), mb_num($evb['dhome']), mb_num($evb['dbarrack']), mb_num($evb['dfarm']), mb_num($evb['dwp']), mb_num($evb['dlmill']), mb_num($evb['aland']), (int) $empvalue));
	 		
	if($landgain >= 10 AND $evu['land'] - $landgain >= 1)	{
		$sid + $msid + 1;
		mb_news_set($db, "$clock", 'yellow', "$ename ($setid) successfully attacked $evu[ename] ($evu[setid]) and gained $landgain land", $setid, $sid);
									
		if($gid[0] != "")	{
			$gnid = $mgnid + 1;
			mb_news_guild($db, "$clock", 'yellow', "$ename ($setid) successfully attacked $evu[ename] ($evu[setid]) and gained $landgain land", $gid['0'], $gnid);
		}
		
		$sid = $msid + 2;
		mb_news_set($db, "$clock", 'lg', "$evu[ename] ($evu[setid]) lost $landgain land to $ename ($setid)", $evu['setid'], $sid);
												
		if($tgid[0] != "")	{
			$gnid = $mgnid + 2;
			mb_news_guild($db, "$clock", 'lg', "$evu[ename] ($evu[setid]) lost $landgain land to $ename ($setid)", $tgid['0'], $gnid);
		}
	}
	if($evu['land'] - $landgain <= 0)	{
		$sid = $msid + 1;
		mb_news_set($db, "$clock", 'yellow', "$ename ($setid) gained $landgain land and destroyed $evu[ename] ($evu[setid]) [i]Medieval style[/i]", $setid, $sid);
												
		if($gid[0] != "")	{
			$gnid = $mgnid + 1;
			mb_news_guild($db, "$clock", 'yellow', "$ename ($setid) gained $landgain land and destroyed $evu[ename] ($evu[setid]) [i]Medieval style[/i]", $gid['0'], $gnid);
		}
		$sid = $msid + 2;
		mb_news_set($db, "$clock", 'lg', "$evu[ename] ($evu[setid]) was destroyed by $ename ($setid) [i]Medieval style[/i]", $evu['setid'], $sid);
												
		if($tgid[0] != "")	{
			$gnid = $mgnid + 2;
			mb_news_guild($db, "$clock", 'lg', "$evu[ename] ($evu[setid]) was destroyed by $ename ($setid) [i]Medieval style[/i]", $tgid['0'], $gnid);
		}
	}
	if($landgain >= 10 AND $evu['land'] - $landgain >= 1)	{
		echo"<div align=center><font class=yellow>You have conquered $landgain land from $evu[ename]($evu[setid])!</font><br><br><font class=orange>$your_losses<br><br></font>";
					
		mb_query($db, "UPDATE user SET nno = nno + 1 WHERE userid=?", array((int) $empvalue));				
		mb_news_emp($db, "$clock", 'yellow', "We have unsuccessfully defended our empire against $ename ($setid) and lost $landgain land", $empvalue);
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
	}
	if($evu['land'] - $landgain <= 0)	{
		echo"<div align=center><font class=yellow>You have gained $landgain land and destroyed $evu[ename]($evu[setid]) Medieval Style!</font><br><br><font class=orange>$your_losses<br><br></font>";
		include("include/attack/ldrop.php");
		include("include/attack/table.php");
	}
	if($evu['land'] - $landgain == 0)	{	
		include("include/connect.php");
		
		$subject = "Account destroyed";
		$body = "You have been destroyed by $ename.  If there is any spots open, you are welcome to signup again.";
		$from = "From: support@medievalbattles.com\r\nbcc: phb@sendhost\r\nContent-type: text/plain\r\nX-mailer: PHP/" . phpversion();
		$mailsend = mail("$evu[email]","$subject","$body","$from");
		
		// The same removal an account deletion does, which this branch was the
		// original of. Its own copy withdrew the vote with an array in the
		// WHERE clause and never parsed its military DELETE; include/empire.php
		// has the whole list.
		require_once __DIR__ . '/include/empire.php';
		mb_empire_remove($db, $evu['userid']);
	}
}
}
?>	
</td>
</tr>
</table>
</body>
</html>