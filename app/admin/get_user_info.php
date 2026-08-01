<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$empre = mb_input('empre');
// The page branches on $userinfo, which the form submits and nothing read: the
// port missed it the same way it missed adminlogin.php's $pw. Left unset, the
// IsSet() below was always false and the results half of this page had been
// unreachable since register_globals went away -- typing a name and pressing
// the button simply redrew the form.
$userinfo = mb_input('userinfo');


include("include/igtop.php");

if(!IsSet($userinfo))	{
	echo "<form type=post action=get_user_info.php>
		<table border=1 bordercolor=#000000 align=center cellpadding=0 cellspacing=0>
			<tr>
				<td colspan=2><b>User Information</b></td>
			<tr>
				<td><input type=\"text\" name=\"empre\" maxlength=\"50\"></td>
				<td><input type=\"submit\" name=\"userinfo\" value=\"Get Their Info\"></td>
				<input type=\"hidden\" name=\"userinfo\" value=\"6\"></td>
		</table>
	</form>";
}
else	{	

	$clock = date("m/d/y, H:ia");

	$result = mysqli_query($db, "SELECT * FROM user WHERE ename=" . mb_sql_str($db, $empre));
		$evu = mysqli_fetch_array($result);

	// The five queries below all filter on $evu[userid]. With no such empire
	// $evu is false, every one of them read `userid=''`, and the page rendered
	// a full report made of whichever rows happened to have a blank id.
	if (!$evu) {
		echo "<div align=center>No empire called " . htmlspecialchars($empre) . ".</div>";
		die();
	}

	$q_evuid = mb_sql_int($evu['userid']);

	$result1 = mysqli_query($db, "SELECT * FROM military WHERE userid=$q_evuid");
		$evm = mysqli_fetch_array($result1);

	$result2 = mysqli_query($db, "SELECT * FROM buildings WHERE userid=$q_evuid");
		$evb = mysqli_fetch_array($result2);

	$result3 = mysqli_query($db, "SELECT * FROM returntbl WHERE userid=$q_evuid");
		$evr = mysqli_fetch_array($result3);

	$result4 = mysqli_query($db, "SELECT * FROM research WHERE userid=$q_evuid");
		$evres = mysqli_fetch_array($result4);

	$result5 = mysqli_query($db, "SELECT * FROM explore WHERE userid=$q_evuid");
		$eve = mysqli_fetch_array($result5);

echo"
		<table border=1 bordercolor=#000000 align=center cellpadding=0 cellspacing=0 width=80%>
			<tr>
				<td align=center><b>User Info</b></td>
				<td><b>Military Info</b></td>
			</tr>
			<tr>
				<td>
					Empire Name: " . mb_h($evu['ename']) . "<br>
					Email: " . mb_h($evu['email']) . "<br>
					Password: " . mb_h($evu['pw']) . "<br>
					Setid: " . mb_h($evu['setid']) . "<br>
					GP: " . mb_h($evu['gp']) . "<br>
					Iron: " . mb_h($evu['iron']) . "<br>
					Lumber: " . mb_h($evu['lumber']) . "<br>
					Exp: " . mb_h($evu['exp']) . "<br>
					Land: " . mb_h($evu['land']) . "<br>
					Mountains: " . mb_h($evu['mts']) . "<br>
					AIM: " . mb_h($evu['aim']) . "<br>
					MSN: " . mb_h($evu['msn']) . "<br>
				</td>
				<td>
					Generals: " . mb_h($evu['fleets']) . "<br>
						<hr width=10%>
					Warriors: " . mb_h($evm['warriors']) . "<br>
					Warrior Weapon: " . mb_h($evm['cweapon']) . "<br>
					Warrior Armor: " . mb_h($evm['wararmor']) . "<br>
						<hr width=10%>
					Priests: " . mb_h($evm['priests']) . "<br>
					Priest Weapon: " . mb_h($evm['cstaff']) . "<br>
					Warrior Armor: " . mb_h($evm['priarmor']) . "<br>
						<hr width=10%>
					Wizards: " . mb_h($evm['wizards']) . "<br>
					Wizard Spell: " . mb_h($evm['cspell']) . "<br>
					Warrior Armor: " . mb_h($evm['wizarmor']) . "<br>
						<hr width=10%>
					Archers: " . mb_h($evm['archers']) . "<br>
					Archer Weapon: " . mb_h($evm['cbow']) . "<br>
					Warrior Armor: " . mb_h($evm['archarmor']) . "<br>
				</td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td align=center><b>Buildings Info</b></td>
				<td><b>Return Info</b></td>
			</tr>
			<tr>
				<td class=inner2>
					Available Mountains: " . mb_h($evb['amts']) . "<br>
					Available Land: " . mb_h($evb['aland']) . "<br>
					Homes: " . mb_h($evb['home']) . "<br>
					Barracks: " . mb_h($evb['barrack']) . "<br>
					Farms: " . mb_h($evb['farm']) . "<br>
					Wooden Platforms: " . mb_h($evb['wp']) . "<br>
					Lumber Mills: " . mb_h($evb['lmill']) . "<br>
					Gold Mines: " . mb_h($evb['gm']) . "<br>
					Iron Mines: " . mb_h($evb['im']) . "<br>
				</td>
				<td>
					Warriors (party 1): " . mb_h($evr['war1']) . "<br>
					Priests (party 1): " . mb_h($evr['pri1']) . "<br>
					Wizards (party 1): " . mb_h($evr['wiz1']) . "<br>
					Archers (party 1): " . mb_h($evr['arch1']) . "<br>
						<hr width=10%>
					Warriors (party 2): " . mb_h($evr['war2']) . "<br>
					Priests (party 2): " . mb_h($evr['pri2']) . "<br>
					Wizards (party 2): " . mb_h($evr['wiz2']) . "<br>
					Archers (party 2): " . mb_h($evr['arch2']) . "<br>
						<hr width=10%>
					Warriors (party 3): " . mb_h($evr['war3']) . "<br>
					Priests (party 3): " . mb_h($evr['pri3']) . "<br>
					Wizards (party 3): " . mb_h($evr['wiz3']) . "<br>
					Archers (party 3): " . mb_h($evr['arch3']) . "<br>
						<hr width=10%>	
					Warriors (party 4): " . mb_h($evr['war4']) . "<br>
					Priests (party 4): " . mb_h($evr['pri4']) . "<br>
					Wizards (party 4): " . mb_h($evr['wiz4']) . "<br>
					Archers (party 4): " . mb_h($evr['arch4']) . "<br>
				</td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<tdalign=center><b>Explore Info</b></td>
				<td ><b>Research Info</b></td>
			</tr>
			<tr>
				<td>
					Explorers on Land: " . mb_h($eve['expland']) . "<br>
					Explorers on Mountains: " . mb_h($eve['expmt']) . "<br>
				</td>
				<td>
					r1: " . mb_h($evres['r1']) . "&nbsp; &nbsp; &nbsp; r1pts: " . mb_h($evres['r1pts']) . "<br>
					r2: " . mb_h($evres['r2']) . "&nbsp; &nbsp; &nbsp; r2pts: " . mb_h($evres['r2pts']) . "<br>
					r3: " . mb_h($evres['r3']) . "&nbsp; &nbsp; &nbsp; r3pts: " . mb_h($evres['r3pts']) . "<br>
					r4: " . mb_h($evres['r4']) . "&nbsp; &nbsp; &nbsp; r4pts: " . mb_h($evres['r4pts']) . "<br>
					r5: " . mb_h($evres['r5']) . "&nbsp; &nbsp; &nbsp; r5pts: " . mb_h($evres['r5pts']) . "<br>
					r6: " . mb_h($evres['r6']) . "&nbsp; &nbsp; &nbsp; r6pts: " . mb_h($evres['r6pts']) . "<br>
					r7: " . mb_h($evres['r7']) . "&nbsp; &nbsp; &nbsp; r7pts: " . mb_h($evres['r7pts']) . "<br>
					r8: " . mb_h($evres['r8']) . "&nbsp; &nbsp; &nbsp; r8pts: " . mb_h($evres['r8pts']) . "<br>
					r9: " . mb_h($evres['r9']) . "&nbsp; &nbsp; &nbsp; r9pts: " . mb_h($evres['r9pts']) . "<br>
					r10: " . mb_h($evres['r10']) . "&nbsp; &nbsp; &nbsp; r10pts: " . mb_h($evres['r10pts']) . "<br>
					r11: " . mb_h($evres['r11']) . "&nbsp; &nbsp; &nbsp; r11pts: " . mb_h($evres['r11pts']) . "<br>
					r12: " . mb_h($evres['r12']) . "&nbsp; &nbsp; &nbsp; r12pts: " . mb_h($evres['r12pts']) . "<br>
					r13: " . mb_h($evres['r13']) . "&nbsp; &nbsp; &nbsp; r13pts: " . mb_h($evres['r13pts']) . "<br>
					r14: " . mb_h($evres['r14']) . "&nbsp; &nbsp; &nbsp; r14pts: " . mb_h($evres['r14pts']) . "
				</td>
			</tr>
			</table><br>";

	// $userid is the LOGGED-IN player's id and is not set in the admin area at
	// all, so this counted the news belonging to userid '' -- i.e. none, for
	// everybody. The empire news table below it therefore never rendered for
	// any user, whatever news they had. It wants the empire being looked at.
	$empnews_sel = mysqli_query($db, "SELECT count(yourid) FROM empnews WHERE yourid=$q_evuid");
	$emp_sel = mb_db_result($empnews_sel,"emp_sel");
	
	if($emp_sel == 0 OR $emp_sel == "")	{
		echo"<div align=center><font class=yellow>This user has no news to be displayed.</font></div>";
		die();
	}

echo "
		<table border=1 bordercolor=#000000 align=center cellpadding=0 cellspacing=0 width=80%>
			<tr>
				<td colspan=3 class=main><b>Empire News</b></td>
			<tr>
				<td class=inner2 width=20%><b>Date</b></td>
				<td class=inner22><b>News</b></td>";

	$query_string = "SELECT date, news, class FROM empnews WHERE yourid=$q_evuid ORDER BY date DESC";
	$result_id = mysqli_query($db, $query_string);
	while ($row = mysqli_fetch_row($result_id))	{
		echo "
			<tr align=left valign=TOP>
				<td bgcolor=404040>" . mb_h($row[0]) . "</td>
				<td bgcolor=404040>" . mb_news_html($row[2], $row[1]) . "</td>\n";
	}

	die();
}

echo "
</td>
</tr>
</table>
</body>
</html>";
?>