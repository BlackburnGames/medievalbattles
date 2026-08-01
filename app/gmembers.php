<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$leave = mb_input('leave');
 

include("include/igtop.php");

if($empireguild == 'None')	{
	echo"<div align=center><font class=yellow><b>You must be the member of a guild to view this page.</b></font></div>";
	die();
}
// leave the guild?
if(!IsSet($leave))	{
	echo "<div align=center><b>[ <a href=gmembers.php?leave=true>Leave: $empireguild</a> ]</b>";
}
else	{	
	
	//	insert leaving msg into guild news
	$guild_id_query = mysqli_query($db, "SELECT gid, owner FROM guild WHERE gname='$empireguild'");
		$guild_id = mysqli_fetch_array($guild_id_query);

	$Guild_MaxID = mysqli_query($db, "SELECT max(gnid) FROM guildnews");
		$mgnid = mb_db_result($Guild_MaxID, "mgnid");
		$gnid = $mgnid + 1;

	if($userid == $guild_id['owner'])	{
		echo "<div align=center><font class=yellow>You cannot leave your guild because you are Guild Leader.</a><br>";
		die();
	}

	echo "<div align=center><font class=yellow>You have left $empireguild</a><br>";
	
	mb_news_guild($db, "$clock", 'blue', "$ename has left $empireguild", $guild_id['gid'], $gnid);
	mysqli_query($db, "UPDATE user SET guild='None' WHERE email='$email' AND pw='$pw'");
}

//	display all empires in your guild
echo "
<br><br>
<table border='1' bordercolor='#000000' align='center' width='80%' cellpadding='0' cellspacing='0'>
	<tr>
		<td class='main' colspan='10'><b class=reg>Members of $empireguild</b></td>
	<tr>
		<td class='main2'><b class='reg'></b></td>
		<td class='main2'><b class='reg'>Empire Name</b></td>
		<td class='main2'><b class='reg'>AIM</b></td>
		<td class='main2'><b class='reg'>MSN</b></td>
		<td class='main2'><b class='reg'>Experience</b></td>
		<td class='main2'><b class='reg'>Last Login</b></td>";

$query_string = "SELECT ename, aim, msn, exp, userid, lastlogin FROM user WHERE guild='$empireguild' ORDER BY exp DESC";
$result_id = mysqli_query($db, $query_string);
while ($row = mysqli_fetch_row($result_id))	{
		
	$SETID_SELECT = mysqli_query($db, "SELECT setid FROM user WHERE ename='$row[0]'");	
		$t_setid = mb_db_result($SETID_SELECT, "t_setid");
	$ONLINE_NO = mysqli_query($db, "SELECT online FROM user WHERE userid='$row[4]'");	
		$OLINE = mb_db_result($ONLINE_NO, "OLINE");
	
	if($OLINE == 1)	{	$O_line = "<font class=red>*</font>";	 }
	else	{	$O_line = "";	}

	$row[3] = number_format($row[3]);	
	$their_name = urlencode($row[0]);
	$num = $num + 1;
		
    echo "
	<tr align='center' valign='top' colspan='6'>
		<td bgcolor=000000>$num</td>
		<td class='inner2'><a href=" . mb_attr("messaging.php?value=$their_name&snum=$t_setid&setchg=1") . ">" . mb_h($row[0]) . "(" . mb_h($t_setid) . ")</a>$O_line</td>
		<td class='inner2'>" . mb_h($row[1]) . "</td>
		<td class='inner2'>" . mb_h($row[2]) . "</td>
		<td class='inner2'>" . mb_h($row[3]) . "</td>
		<td class='inner2'>" . mb_h($row[5]) . "</td>\n";
}

$guild_strength_query = mysqli_query($db, "SELECT sum(exp) FROM user WHERE guild='$empireguild'");
	$guild_strength_total = mb_db_result($guild_strength_query, "guild_strength_total");
	$guild_strength_total = number_format($guild_strength_total);

echo "
</table>
<br><br>
<div align=center><b><font class=red>Guild Strength:</font></b> " . mb_h($guild_strength_total) . "</div>";
?>
</td>
</tr>
</table>
</body>
</html>