<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$ccpw        = mb_input('ccpw');
$cpw         = mb_input('cpw');
$create      = mb_input('create');
$gname       = mb_input('gname');
$info        = mb_input('info');
$pageid      = mb_input('pageid');
$req_guild   = mb_input('req_guild');
$request     = mb_input('request');
$sendmessage = mb_input('sendmessage');
$umessage    = mb_input('umessage');

include("include/igtop.php");

if(!IsSet($sendmessage))	{
}
else	{

	if($umessage == "")	{
		echo "<div align=center><font class=yellow>You did not type anything to send.</font></div>";
		include("include/S_MESS.php"); 
		die();
	}
	else	{

		// The "message guild leader" form below posts no gid, so the target
		// guild is carried across the round trip in the session. Under PHP 4's
		// register_globals a session variable was injected back into the global
		// scope by session_start(), which is where $gid used to come from here.
		$gid = isset($_SESSION['gid']) ? $_SESSION['gid'] : '';

		$guild_name = mysqli_query($db, "SELECT gname FROM guild WHERE gid=" . mb_sql_int($gid));
			$g_name = mb_db_result($guild_name,"g_name");
		$owner_g = mysqli_query($db, "SELECT owner FROM guild WHERE gid=" . mb_sql_int($gid));	
			$owner = mb_db_result($owner_g,"owner");
		$THE_MNO = mysqli_query($db, "SELECT mno FROM user WHERE userid='$owner'");	
			$T_MNO = mb_db_result($THE_MNO,"T_MNO");
		$yourmid = mysqli_query($db, "SELECT max(mid) FROM messages");	
			$ymid = mb_db_result($yourmid,"ymid");

		$ymid = $ymid + 1;
		$thenum = $T_MNO + 1;
	  
		mysqli_query($db, "UPDATE user SET mno ='$thenum' WHERE userid='$owner'");

		$umessage = "<font class=red><b>Sent by Guild Center:</b></font>&nbsp;&nbsp;" . "$umessage";

		mysqli_query($db, "INSERT INTO messages (origin, datesent, yourid, message, mid)		VALUES	('$ename', '$clock', '$owner', " . mb_sql_str($db, $umessage) . ", '$ymid') ");
	
		echo "<div align=center><font class=yellow><b>Your message has been sent to the Guild Leader of $g_name.</b></font></div></center>";
		unset($_SESSION['gid']);
	}
}

if ($pageid == 'mgl')	{
	// Stash the guild being messaged for the POST above to pick up. The link
	// that reaches this branch supplies gid, so the request wins over any
	// stale session value -- session_register() preferred the session, which
	// meant a second mgl link opened while one was pending messaged the first
	// guild's leader.
	$gid = mb_input('gid');
	$_SESSION['gid'] = $gid;
		$guild_name = mysqli_query($db, "SELECT gname FROM guild WHERE gid=" . mb_sql_int($gid));	
			$g_name = mb_db_result($guild_name,"g_name");
	echo "<div align=center><font class=yellow><b>You are messaging the Guild Leader of <u>$g_name</u>.</b></font></div>";
?>
	
<form type=post action=gc.php>
<table border="0" bordercolor="silver" width="80%"  align=center>
	<tr>
		<td align=center><textarea name="umessage" rows=15 cols=50 wrap></textarea>
</table>
<center><input type="submit" name="sendmessage" value="Send Message" class=button></center>
<input type="hidden" name="sendmessage" value="1">
<? 
	die();
}
unset($_SESSION['gid']);


if($request == 'yes')	{

	$guild_info_query = mysqli_query($db, "SELECT * FROM guild WHERE gname=" . mb_sql_str($db, $req_guild));
		$guild_info = mysqli_fetch_array($guild_info_query);
	$gl_check_query = mysqli_query($db, "SELECT * FROM guild WHERE owner='$userid'");
		$gl_check = mysqli_fetch_array($gl_check_query);
	
	if($gl_check['owner'] === $userid)	 {
		echo "<div align=center><font class=yellow><b>Guild Leaders cannot request to join other guilds!</b></font></div>";
		die();
	}
	else	{
		mysqli_query($db, "INSERT INTO guildrequests (applicant, gl_userid)		VALUES	('$userid', '$guild_info[owner]') ");
		echo "<div align=center><font class=yellow><b>Your request to join <u>$guild_info[gname]</u> has been sent.</b></font></div>";	
	}	
}

echo "  <br><br>
<table border=0 width=90% align=center cellpadding=5>
	<tr>
		<td class=main colspan=6><b class=reg>Current Guilds</b></td>
	<tr>
		<td class=main2 colspan=6><font class=yellow size=2px><b>Only 15 Empires Per Guild</b></font></td>
	<tr>
		<td class=main2><b class=reg>Flag</b></td>
		<td class=main2><b class=reg>Name</b></td>
		<td class=main2 width=40%><b class=reg>Info</b></td>
		<td class=main2 width=4%><b class=reg>Members</b></td>
		<td class=main2><b class=reg>Created</b></td>
		<td class=main2></td>";

// guild.flag was missing from db.sql, so this query always failed and the
// listing always came back empty -- on PHP 8, passing the resulting false to
// mysqli_fetch_array() was a fatal TypeError. Phase 1 selected an empty
// placeholder to keep the query running; the column exists now, so this reads
// the real one again.
$query_string = "SELECT g.gname, g.info, g.datemade, g.gid, g.flag, count(u.ename) AS guildmem FROM guild g LEFT JOIN user u ON g.gname = u.guild GROUP BY u.guild ORDER BY guildmem DESC LIMIT 0, 60";
$result_id = mysqli_query($db, $query_string);
while ($row = mysqli_fetch_array($result_id))	{
	$row[0] = htmlspecialchars($row[0]);
	$row[1] = htmlspecialchars($row[1]);
	$urlencode_guild = urlencode($row[0]);
	// The flag is a URL the guild leader types in, echoed into an href and an
	// img src. It is the only field here that was never escaped, and it only
	// stopped being a stored-XSS vector by accident, because the column it
	// comes from did not exist.
	$flag = htmlspecialchars($row['flag'], ENT_QUOTES);

	echo "
	<tr align=center valign=top colspan=6>
		<td bgcolor=#404040><a href=\"$flag\" target=newwindow><img src=\"$flag\" width=50 height=50 border=0></a></td>
		<td bgcolor=#404040><a href=gc.php?pageid=mgl&gid=$row[3]>$row[0]</a></td>
		<td bgcolor=#404040>$row[1]</td>
		<td bgcolor=#404040>$row[5]</td>
		<td bgcolor=#404040>$row[2]</td>
		<td bgcolor=#404040><a href=gc.php?request=yes&req_guild=$urlencode_guild>Request<br>Mbrshp.</a></td>";
}
echo "</table><br><br>";

if($empireguild == "None")	{

if(!IsSet($create))	{
	include("include/S_GM.php");
}
else	{

	// parse whitespace out
		$creating_guild_name = trim($gname);
	// check to see if guild name is being used
		$result = mysqli_query($db, "SELECT gname FROM guild WHERE gname=" . mb_sql_str($db, $creating_guild_name));
		$namecheck = mysqli_fetch_array($result);
 	// check to see if they are a GL
		$check_owner_result = mysqli_query($db, "SELECT owner FROM guild WHERE owner='$ename'");
		$check_owner = mysqli_fetch_array($check_owner_result);
 			
	$GNAME_lower = strtolower($creating_guild_name);
	$SEL_G_lower = strtolower($namecheck['gname']);
	$gname_length = strlen($creating_guild_name);
	
	if($gname_length > 15)	{
		echo "<div align=center><font class=yellow>Your guild name can't be more than 15 characters!</font></div>";
		include("include/S_GM.php");
		die();
	}
	elseif($gname == "")	{
		echo "<div align=center><font class=yellow>A guild must have a name!</font></div>";
		include("include/S_GM.php");
		die();
	}
	elseif($gname == 'None')	{
		echo "<div align=center><font class=yellow>You cannot pick that name!</font></div>";
		include("include/S_GM.php");
		die();
	}
	elseif($empireguild != 'None')	{
		echo "<div align=center><font class=yellow>You can't make a guild when you are in a guild!</font></div>";
		include("include/S_GM.php");
		die();
	}
	elseif($check_owner['owner'] == $userid)	{
		echo "<div align=center><font class=yellow>You are already a Guild Leader!</font></div>";
		include("include/S_GM.php");
		die();
	}
	elseif($GNAME_lower == $SEL_G_lower)	{
		echo "<div align=center><font class=yellow>That name has been taken!</font></div>";
		include("include/S_GM.php");
		die();
	}
	elseif($cpw != $ccpw)	 {
		echo "<div align=center><font class=yellow>Passwords don't match!</font></div>";
		include("include/S_GM.php");
		die();
	}
	elseif($cpw =="" OR $ccpw == "")	{
		echo "<div align=center><font class=yellow>You must have a deletion password!</font></div>";
		include("include/S_GM.php");
		die();
	}
	else	{
		
		//	select max guild id
		$M_gid = mysqli_query($db, "SELECT max(gid) FROM guild");	
			$mgid = mb_db_result($M_gid, "mgid");	
		
		$info = htmlspecialchars($info);
		$creating_guild_name = htmlspecialchars($creating_guild_name);
		// Its own name. $gid up at the top of this file is the guild the player
		// is messaging, read from the request; this one is the id of the guild
		// about to be created. Sharing a name made the second look like the
		// first to anything reading the file, the audit included.
		$new_gid = $mgid + 1;

		include("include/connect.php");
		mysqli_query($db, "INSERT INTO guild (gname, info, gid, datemade, cpw, owner)	 VALUES	(" . mb_sql_str($db, $creating_guild_name) . ", " . mb_sql_str($db, $info) . ", '$new_gid', '$clock', " . mb_sql_str($db, $cpw) . ", '$userid') ");
		mysqli_query($db, "UPDATE user SET guild=" . mb_sql_str($db, $gname) . " WHERE email='$email' AND pw='$pw'");
		mysqli_query($db, "DELETE FROM guildrequests WHERE applicant='$userid'");
						
		echo"<div align=center><font class=yellow><b><u>$creating_guild_name</u> has been successfully created!</b></font></div>";
		include("include/S_GM.php");
		die();
	}
}
}	else	{
}
?>
</td>
</tr>
</table>
</body>
</html>