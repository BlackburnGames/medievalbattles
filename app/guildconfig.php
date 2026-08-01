<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$accept  = mb_input('accept');
$auserid = mb_input('auserid');
$change  = mb_input('change');
$cpw     = mb_input('cpw');
$deleteg = mb_input('deleteg');
$flag    = mb_input('flag');
$info    = mb_input('info');
$kick    = mb_input('kick');
$notice  = mb_input('notice');
$reject  = mb_input('reject');
$remp    = mb_input('remp');


include("include/igtop.php");

// are they a guild leader?
	$guild_owner_result = mysqli_query($db, "SELECT owner FROM guild WHERE owner='$userid'");
	$guild_owner = mysqli_fetch_array($guild_owner_result);
			
if($guild_owner['owner'] != $userid)	{
	echo"<div align=center><font class=yellow>You have to be a Guild Leader to view this page!</font></div>";
	die();
}
else	{
	$guild_info = mysqli_query($db, "SELECT info FROM guild WHERE owner='$userid'");
		$ginfo = mb_db_result($guild_info,"ginfo");
	$guild_flag = mysqli_query($db, "SELECT flag FROM guild WHERE owner='$userid'");
		$gflag = mb_db_result($guild_flag,"gflag");
	$guild_notice = mysqli_query($db, "SELECT notice FROM guild WHERE owner='$userid'");
		$gnotice = mb_db_result($guild_notice,"gnotice");
	$C_PW = mysqli_query($db, "SELECT cpw FROM guild WHERE owner='$userid'");
		$C_pass = mb_db_result($C_PW,"C_pass");
}

################
##	accept/reject
################
include("include/guild_request.php");

if(!IsSet($accept))	{
}
else	{	
	// retrieve necessary guild info
	$guild_info_query = mysqli_query($db, "SELECT * FROM guild WHERE owner='$userid'");
		$guild_info = mysqli_fetch_array($guild_info_query);
	// get number of members
	$guild_members_query = mysqli_query($db, "SELECT count(ename) FROM user WHERE guild='$guild_info[gname]'");
		$guild_members = mb_db_result($guild_members_query, "guild_members");
	// get their empire name
	$applicant_ename_query = mysqli_query($db, "SELECT ename FROM user WHERE userid='$auserid'");
		$applicant_ename = mysqli_fetch_array($applicant_ename_query);
	// get their current guild
	$current_guild_query = mysqli_query($db, "SELECT guild FROM user WHERE userid='$auserid'");
		$current_guild = mysqli_fetch_array($current_guild_query);

	if($guild_members > 14)	{
		echo "<div align=center><font class=yellow>Your guild is full! You cannot allow any more members in!</font></div>";
		die();
	}
	elseif($current_guild['guild'] == 'None')	{
		mysqli_query($db, "UPDATE user SET guild='$guild_info[gname]' WHERE userid='$auserid'");
		mysqli_query($db, "INSERT INTO empnews (date, news, yourid) VALUES	('$clock', '<font class=blue>You were accepted into $guild_info[gname].</font>', '$auserid') ");

		$Guild_MaxID = mysqli_query($db, "SELECT max(gnid) FROM guildnews");
			$mgnid = mb_db_result($Guild_MaxID, "mgnid");
			$gnid = $mgnid + 1;
	
		mysqli_query($db, "INSERT INTO guildnews (date, news, gid, gnid)	VALUES	('$clock', '<font class=blue>$applicant_ename[ename] has joined $empireguild</font>', '$guild_info[gid]' , '$gnid') ");
		mysqli_query($db, "DELETE FROM guildrequests WHERE applicant='$auserid'");

		echo "<div align=center><font class=yellow><b>$applicant_ename[ename] has been accepted into the guild!</b></font></div>";
		die();
	}
	else	{
		echo "<div align=center><font class=yellow>That person is curently in a guild!</font></div>";
		die();
	}
}
if(!IsSet($reject))	{
}
else	{	
	// retrieve necessary guild info
	$guild_info_query = mysqli_query($db, "SELECT * FROM guild WHERE owner='$userid'");
		$guild_info = mysqli_fetch_array($guild_info_query);
	// get their empire name
	$applicant_ename_query = mysqli_query($db, "SELECT ename FROM user WHERE userid='$auserid'");
		$applicant_ename = mysqli_fetch_array($applicant_ename_query);
	// get their current guild
	$current_guild_query = mysqli_query($db, "SELECT guild FROM user WHERE userid='$auserid'");
		$current_guild = mysqli_fetch_array($current_guild_query);

	if(($current_guild['guild'] == 'None') OR ($current_guild['guild'] != 'None'))	 {
		mysqli_query($db, "INSERT INTO empnews (date, news, yourid) VALUES	('$clock', '<font class=blue>You were rejected from $guild_info[gname].</font>', '$auserid') ");
		mysqli_query($db, "DELETE FROM guildrequests WHERE applicant='$auserid' AND gl_userid='$userid'");

		echo "<div align=center><font class=yellow><b>$applicant_ename[ename] has been rejected from the guild!</b></font></div>";
		die();
	}
}

################
##	change info
################
if(!IsSet($change))	{
	include("include/S_GCONFIG.php");
}
else	{
	include("include/S_SINFOS.php");
	mysqli_query($db, "UPDATE guild SET info='$info' WHERE owner='$userid'");
	mysqli_query($db, "UPDATE guild SET notice='$notice' WHERE owner='$userid'");
	mysqli_query($db, "UPDATE guild SET flag='$flag' WHERE owner='$userid'");
	echo "<div align=center><font class=yellow><br>Guild Settings Updated!</font></div>";
	include("include/S_GCONFIG.php");
	include("include/S_GKICK.php");
	include("include/S_GDEL.php");
	die();
}

################
##	kick member
################
if(!IsSet($kick))	{
	include("include/S_GKICK.php");
}
else	{
	// retrieve necessary guild info
	$guild_info_query = mysqli_query($db, "SELECT * FROM guild WHERE owner='$userid'");
		$guild_info = mysqli_fetch_array($guild_info_query);
	// get their empire name
	$empire_info_query = mysqli_query($db, "SELECT ename FROM user WHERE userid='$remp'");
		$empire_info = mysqli_fetch_array($empire_info_query);
	// get their current guild
	$current_guild_query = mysqli_query($db, "SELECT guild FROM user WHERE userid='$remp'");
		$current_guild = mysqli_fetch_array($current_guild_query);

	if($remp == 'ns')		{
		echo"<div align=center><font class=yellow>You didn't select anybody to remove!</font></div>";
		include("include/S_GKICK.php");
		include("include/S_GDEL.php");
		die();
	}
	elseif($remp == $userid)	{
		echo "<div align=center><font class=yellow>You can't remove yourself!</font></div>";
		include("include/S_GKICK.php");
		include("include/S_GDEL.php");
		die();
	}
	elseif($empireguild != $current_guild['guild'])	{	
		echo "<div align=center><font class=yellow>That person is curently in another guild!</font></div>";
		include("include/S_GKICK.php");
		include("include/S_GDEL.php");
		die();
	}
	else	{
		$new_mem = $guild_info['mem'] - 1;
		mysqli_query($db, "UPDATE user SET guild='None' WHERE userid='$remp'");
		mysqli_query($db, "UPDATE guild SET mem='$new_mem' WHERE owner='$userid'");	
		mysqli_query($db, "DELETE FROM barter WHERE seller='$empire_info[0]' AND guild='$empireguild'");
		mysqli_query($db, "INSERT INTO empnews (date, news, yourid) VALUES	('$clock', '<font class=blue>You were removed from $guild_info[gname].</font>', '$remp') ");
		mysqli_query($db, "INSERT INTO guildnews (date, news, guildid) VALUES	('$clock', '<font class=blue>$empire_info[0] has been removed from the guild.</font>', '$guild_info[gid]') ");

		echo"<div align=center><font class=yellow>$empire_info[0] has been removed from your guild.</font></div>";
		include("include/S_GKICK.php");
		include("include/S_GDEL.php");
		die();
	}
}

################
##	delete guild
################
if(!IsSet($deleteg))	{
	include("include/S_GDEL.php");
}
else	{
	if($C_pass == $cpw)	 {
		//	determine guild name
		$GuildName = mysqli_query($db, "SELECT gname FROM guild WHERE owner='$userid'");
			$GN = mb_db_result($GuildName,"GN");
		//	determine guild id
		$GuildID = mysqli_query($db, "SELECT gid FROM guild WHERE owner='$userid'");
			$GIDD = mb_db_result($GuildID,"GIDD");

		mysqli_query($db, "UPDATE user SET guild='None' WHERE guild='$GN'");
		mysqli_query($db, "DELETE FROM guild WHERE owner='$userid'");
			
		echo "<div align=center><font class=yellow>Your guild has been deleted.</font></div>";
		include("include/S_GDEL.php");
		die();
	}
	else	{
		echo "<div align=center><font class=yellow>You have entered an incorrect password.</font></div>"; 
		include("include/S_GDEL.php");
		die();
	}
}
?>
</td>
</tr>
</table>
</body>
</html>