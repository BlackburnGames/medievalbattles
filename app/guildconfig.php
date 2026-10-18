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

// Both name a row in `user`, and every query below matches on one of them.
// Cast once here rather than at each of the thirteen sites -- and after
// igtop.php, which is what pulls in connect.php and with it mb_sql_int(). The
// raw values are still read further down by the 'ns' and self-kick checks,
// which compare strings.
$q_auserid = mb_sql_int($auserid);
$q_remp    = mb_sql_int($remp);

// are they a guild leader?
	$guild_owner_result = mb_query($db, "SELECT owner FROM guild WHERE owner=?", array($userid));
	$guild_owner = mysqli_fetch_array($guild_owner_result);
			
if($guild_owner['owner'] != $userid)	{
	echo"<div align=center><font class=yellow>You have to be a Guild Leader to view this page!</font></div>";
	die();
}
else	{
	$guild_info = mb_query($db, "SELECT info FROM guild WHERE owner=?", array($userid));
		$ginfo = mb_db_result($guild_info,"ginfo");
	$guild_flag = mb_query($db, "SELECT flag FROM guild WHERE owner=?", array($userid));
		$gflag = mb_db_result($guild_flag,"gflag");
	$guild_notice = mb_query($db, "SELECT notice FROM guild WHERE owner=?", array($userid));
		$gnotice = mb_db_result($guild_notice,"gnotice");
	$C_PW = mb_query($db, "SELECT cpw FROM guild WHERE owner=?", array($userid));
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
	$guild_info_query = mb_query($db, "SELECT * FROM guild WHERE owner=?", array($userid));
		$guild_info = mysqli_fetch_array($guild_info_query);
	// get number of members
	$guild_members_query = mb_query($db, "SELECT count(ename) FROM user WHERE guild=?", array($guild_info['gname']));
		$guild_members = mb_db_result($guild_members_query, "guild_members");
	// get their empire name
	$applicant_ename_query = mb_query($db, "SELECT ename FROM user WHERE userid=?", array((int) $auserid));
		$applicant_ename = mysqli_fetch_array($applicant_ename_query);
	// get their current guild
	$current_guild_query = mb_query($db, "SELECT guild FROM user WHERE userid=?", array((int) $auserid));
		$current_guild = mysqli_fetch_array($current_guild_query);

	/*
	 * Did this player actually apply to THIS guild?
	 *
	 * Nothing asked. include/guild_request.php draws the Accept link only for
	 * rows matching "gl_userid='$userid'", so the link is always honest -- but
	 * the handler took auserid off the URL and wrote the membership on that
	 * alone, so a leader could conscript any guildless empire in the game by
	 * typing the id. The reject branch below has always scoped its DELETE with
	 * "AND gl_userid='$userid'", which is what says this was an omission rather
	 * than a design.
	 */
	$request_check = mb_query($db, "SELECT applicant FROM guildrequests WHERE applicant=? AND gl_userid=?", array((int) $auserid, $userid));

	if(!$request_check || !mysqli_num_rows($request_check))	{
		echo "<div align=center><font class=yellow>That empire has not asked to join your guild.</font></div>";
		die();
	}

	if($guild_members > 14)	{
		echo "<div align=center><font class=yellow>Your guild is full! You cannot allow any more members in!</font></div>";
		die();
	}
	elseif($current_guild['guild'] == 'None')	{
		mb_query($db, "UPDATE user SET guild=? WHERE userid=?", array($guild_info['gname'], (int) $auserid));
		mb_news_emp($db, "$clock", 'blue', "You were accepted into $guild_info[gname].", $q_auserid);

		$Guild_MaxID = mysqli_query($db, "SELECT max(gnid) FROM guildnews");
			$mgnid = mb_db_result($Guild_MaxID, "mgnid");
			$gnid = $mgnid + 1;
	
		mb_news_guild($db, "$clock", 'blue', "$applicant_ename[ename] has joined $empireguild", $guild_info['gid'], $gnid);
		mb_query($db, "DELETE FROM guildrequests WHERE applicant=?", array((int) $auserid));

		echo "<div align=center><font class=yellow><b>" . mb_h($applicant_ename['ename']) . " has been accepted into the guild!</b></font></div>";
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
	$guild_info_query = mb_query($db, "SELECT * FROM guild WHERE owner=?", array($userid));
		$guild_info = mysqli_fetch_array($guild_info_query);
	// get their empire name
	$applicant_ename_query = mb_query($db, "SELECT ename FROM user WHERE userid=?", array((int) $auserid));
		$applicant_ename = mysqli_fetch_array($applicant_ename_query);
	// get their current guild
	$current_guild_query = mb_query($db, "SELECT guild FROM user WHERE userid=?", array((int) $auserid));
		$current_guild = mysqli_fetch_array($current_guild_query);

	/*
	 * The same question the accept branch asks, for the same reason. The
	 * DELETE below was always scoped to this leader's requests, but the news
	 * line ahead of it was not, so a leader could file "You were rejected from"
	 * into any empire's news by typing the id.
	 */
	$request_check = mb_query($db, "SELECT applicant FROM guildrequests WHERE applicant=? AND gl_userid=?", array((int) $auserid, $userid));

	if(!$request_check || !mysqli_num_rows($request_check))	{
		echo "<div align=center><font class=yellow>That empire has not asked to join your guild.</font></div>";
		die();
	}

	if(($current_guild['guild'] == 'None') OR ($current_guild['guild'] != 'None'))	 {
		mb_news_emp($db, "$clock", 'blue', "You were rejected from $guild_info[gname].", $q_auserid);
		mb_query($db, "DELETE FROM guildrequests WHERE applicant=? AND gl_userid=?", array((int) $auserid, $userid));

		echo "<div align=center><font class=yellow><b>" . mb_h($applicant_ename['ename']) . " has been rejected from the guild!</b></font></div>";
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
	mb_query($db, "UPDATE guild SET info=? WHERE owner=?", array($info, $userid));
	mb_query($db, "UPDATE guild SET notice=? WHERE owner=?", array($notice, $userid));
	mb_query($db, "UPDATE guild SET flag=? WHERE owner=?", array($flag, $userid));
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
	$guild_info_query = mb_query($db, "SELECT * FROM guild WHERE owner=?", array($userid));
		$guild_info = mysqli_fetch_array($guild_info_query);
	// get their empire name
	$empire_info_query = mb_query($db, "SELECT ename FROM user WHERE userid=?", array((int) $remp));
		$empire_info = mysqli_fetch_array($empire_info_query);
	// get their current guild
	$current_guild_query = mb_query($db, "SELECT guild FROM user WHERE userid=?", array((int) $remp));
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
		mb_query($db, "UPDATE user SET guild='None' WHERE userid=?", array((int) $remp));
		mb_query($db, "UPDATE guild SET mem=? WHERE owner=?", array((int) $new_mem, $userid));	
		mb_query($db, "DELETE FROM barter WHERE seller=? AND guild=?", array($empire_info[0], $empireguild));
		mb_news_emp($db, "$clock", 'blue', "You were removed from $guild_info[gname].", $q_remp);
		mb_news_guild($db, "$clock", 'blue', "$empire_info[0] has been removed from the guild.", $guild_info['gid'], 0);

		echo"<div align=center><font class=yellow>" . mb_h($empire_info[0]) . " has been removed from your guild.</font></div>";
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
		$GuildName = mb_query($db, "SELECT gname FROM guild WHERE owner=?", array($userid));
			$GN = mb_db_result($GuildName,"GN");
		//	determine guild id
		$GuildID = mb_query($db, "SELECT gid FROM guild WHERE owner=?", array($userid));
			$GIDD = mb_db_result($GuildID,"GIDD");

		mb_query($db, "UPDATE user SET guild='None' WHERE guild=?", array($GN));
		mb_query($db, "DELETE FROM guild WHERE owner=?", array($userid));
			
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