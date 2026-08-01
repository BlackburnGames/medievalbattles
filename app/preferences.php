<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$changepw  = mb_input('changepw');
$cnewpw    = mb_input('cnewpw');
$currentpw = mb_input('currentpw');
$delete    = mb_input('delete');
$dpw       = mb_input('dpw');
$empnews   = mb_input('empnews');
$empvalue  = mb_input('empvalue');
$newaim    = mb_input('newaim');
$newemail  = mb_input('newemail');
$newmsn    = mb_input('newmsn');
$newpw     = mb_input('newpw');
$update    = mb_input('update');
 
include("include/igtop.php");

/**
 * The credential, escaped once, for the twenty WHERE clauses below.
 *
 * This file is the last entry on tests/sql-injection.txt, and it is the honest
 * one: a player picks a new address here, it is written to six tables and then
 * assigned to $email -- the name that every "WHERE email='$email' AND
 * pw='$pw'" in the game interpolates.
 *
 * Two things close it. include/credentials.php constrains what an address may
 * be, at signup, here, and again when include/session.php reads it back, which
 * is what makes the other ~1300 call sites safe without touching them. And
 * this, which escapes the twenty in this file, because a page that lets you
 * change the value should not be relying on a rule enforced somewhere else.
 *
 * Recomputed after the address changes, below -- the AIM and MSN updates that
 * follow it filter on the new one.
 */
$q_cred = "email=" . mb_sql_str($db, $email) . " AND pw=" . mb_sql_str($db, $pw);

if(!IsSet($empnews))	{
	echo "<div align=center><a href=preferences.php?empnews=true>Delete Empire News</a>";
}
else	{	
	
	echo "<div align=center><font class=yellow>Your Empire News has been deleted</a><br>";
	mysqli_query($db, "DELETE FROM empnews WHERE yourid='$userid'");
	include("include/chngpw_table.php");
	include("include/S_PREF.php");
	include("include/S_PD.php");
	die();
}

if(!IsSet($changepw))	{
}
else	{
	include("include/S_SINFOS.php");
	$md5currentpw = htmlspecialchars($currentpw);
	$md5currentpw = md5($currentpw);
			
	if($md5currentpw != $pw)	{
		echo"<div align=center><font class=yellow>Your current password is not correct.</font></div>";
		include("include/chngpw_table.php");
		include("include/S_PREF.php");
		include("include/S_PD.php");
		die();
	}
	if($newpw != $cnewpw)	{
		echo"<div align=center><font class=yellow>Those passwords do not match.</font></div>";
		include("include/chngpw_table.php");
		include("include/S_PREF.php");
		include("include/S_PD.php");
		die();
	}

	// change their password
	//
	// The digest gets its own name. What the six UPDATEs interpolate is 32 hex
	// characters and always was, but with the request value and the hash of it
	// sharing one name, that is only true if you read all three lines in order.
	$newpw_hash = md5(htmlspecialchars($newpw));
	mysqli_query($db, "UPDATE user SET pw='$newpw_hash' WHERE $q_cred") or die(mysqli_error($db));
	mysqli_query($db, "UPDATE buildings SET pw='$newpw_hash' WHERE $q_cred") or die(mysqli_error($db));
 	mysqli_query($db, "UPDATE military SET pw='$newpw_hash' WHERE $q_cred") or die(mysqli_error($db));
 	mysqli_query($db, "UPDATE research SET pw='$newpw_hash' WHERE $q_cred") or die(mysqli_error($db));
 	mysqli_query($db, "UPDATE returntbl SET pw='$newpw_hash' WHERE $q_cred") or die(mysqli_error($db));
 	mysqli_query($db, "UPDATE explore SET pw='$newpw_hash' WHERE $q_cred") or die(mysqli_error($db));
	$pw = $newpw_hash;
	$_SESSION['pw'] = $pw;

	echo"<div align=center><font class=yellow>Your password has been changed.</font></div>";
	include("include/chngpw_table.php");
	include("include/S_PREF.php");
	include("include/S_PD.php");
	die();
}

if(!IsSet($update))	{
}
else	{
	include("include/S_SINFOS.php");
			
	$cnewemail = strtolower($newemail);

	// The new address is checked before anything is written, by the same rule
	// signup and include/session.php use. Without it a player could put a
	// quote into $email, and $email is what ~1300 "WHERE email='$email'"
	// clauses across the game interpolate on every page they load. See
	// include/credentials.php.
	if(!mb_valid_email($cnewemail))	{
		echo"<div align=center><font class=yellow>That is not a valid email address.</font></div>";
		include("include/chngpw_table.php");
		include("include/S_PREF.php");
		include("include/S_PD.php");
		die();
	}

	$E_Result = mysqli_query($db, "SELECT email FROM user WHERE email=" . mb_sql_str($db, $cnewemail));
	$New_Email = mysqli_fetch_array($E_Result);

	$New_Email[0] = strtolower($New_Email[0]);

	if($New_Email[0] == $cnewemail AND $newemail != $email)	{
		echo"<div align=center><font class=yellow>This email is already in use.</font></div>";
		include("include/chngpw_table.php");
		include("include/S_PREF.php");
		include("include/S_PD.php");
		die();
	}
	
	// update email
	//
	// htmlspecialchars() used to stand in for escaping here, and whether it
	// stopped a quote reaching MySQL depended on the PHP version -- ENT_QUOTES
	// only became the default in 8.1, so single quotes survived it on the 5.6
	// image this suite also runs against. The escaping is explicit now, and
	// mb_valid_email() above has already refused every character it was
	// standing between MySQL and.
	$q_newemail = mb_sql_str($db, $newemail);
	mysqli_query($db, "UPDATE user SET email=$q_newemail WHERE $q_cred") or die(mysqli_error($db));
	mysqli_query($db, "UPDATE buildings SET email=$q_newemail WHERE $q_cred") or die(mysqli_error($db));
 	mysqli_query($db, "UPDATE military SET email=$q_newemail WHERE $q_cred") or die(mysqli_error($db));
 	mysqli_query($db, "UPDATE research SET email=$q_newemail WHERE $q_cred") or die(mysqli_error($db));
 	mysqli_query($db, "UPDATE returntbl SET email=$q_newemail WHERE $q_cred") or die(mysqli_error($db));
 	mysqli_query($db, "UPDATE explore SET email=$q_newemail WHERE $q_cred") or die(mysqli_error($db));
	$email = $newemail;
	$_SESSION['email'] = $email;

	// The six UPDATEs above have just moved the account onto the new address,
	// so anything after this point has to filter on that one.
	$q_cred = "email=" . mb_sql_str($db, $email) . " AND pw=" . mb_sql_str($db, $pw);

	// update aim and msn
	mysqli_query($db, "UPDATE user SET aim=" . mb_sql_str($db, $newaim) . " WHERE $q_cred");
	mysqli_query($db, "UPDATE user SET msn=" . mb_sql_str($db, $newmsn) . " WHERE $q_cred");
		
	$email = $newemail;
	$msn = $newmsn;
	$aim = $newaim;

	echo"<div align=center><font class=yellow>Your settings have been updated.</font></div>";
	include("include/chngpw_table.php");
	include("include/S_PREF.php");
	include("include/S_PD.php");
	die();
}

if(!IsSet($delete))	{
}
else	{

	$dpw = md5($dpw);
	if($dpw == "")	{
		echo"<div align=center><font class=yellow>In order to delete, you must specify a password.</font></div>";
		include("include/chngpw_table.php");
		include("include/S_PREF.php");
		include("include/S_PD.php");
		die();
	}
	elseif($pw != $dpw)	{
		echo"<div align=center><font class=yellow>Incorrect password.</font><div>";
		include("include/chngpw_table.php");
		include("include/S_PREF.php");
		include("include/S_PD.php");
		die();
	}
	else	{
		
		mysqli_query($db, "INSERT INTO setnews (date, news, setid)	 VALUES	('$clock', '<font class=red>$ename has deleted their account</font>', '$setid') ");
	  
		// check who they are voting for
		$V_result = mysqli_query($db, "SELECT votefor FROM user WHERE userid=" . mb_sql_int($empvalue));
		$V_check = mysqli_fetch_array($V_result);
		
		if($V_check[0] != 'None' AND $V_check[0] != $empireattacked)	 {
			$Votedfor_emp = mysqli_query($db, "SELECT vote FROM user WHERE ename='$VF_emp'");
			$VF_emp = mb_db_result($Votedfor_emp,"VF_emp");
				
			$newvote = $VF_emp - 1;
			mysqli_query($db, "UPDATE user SET vote='$newvote' WHERE ename='$empireattacked'");
			}

	  	echo "<div align=center><font class=yellow>Your account has been deleted.</font></div>";

		$subject = "Account deleted";
		$body = "Your account at Medieval Battles has been succesfully deleted.";

		$from = "From: support@medievalbattles.com\r\nbcc: phb@sendhost\r\nContent-type: text/plain\r\nX-mailer: PHP/" . phpversion();
		$mailsend = mail("$email","$subject","$body","$from");
		// mail() returns a bool, so this prints "1" or nothing -- leftover
		// debugging from 2003. It goes through the escaper anyway, because the
		// rule is that everything reaching output does and the audit checks it,
		// not because a bool could carry markup.
		print(mb_h($mailsend));


		// check user
		$gresult = mysqli_query($db, "SELECT owner FROM guild WHERE owner=$userid");
		$guildcheck = mysqli_fetch_array($gresult);
		
		if($guildcheck[0] == $userid)	{
			//	select guild name
				$guild_info_query = mysqli_query($db, "SELECT * FROM guild WHERE owner='$ename'");
				$guild_info = mysqli_fetch_array($guild_info_query);
		
			mysqli_query($db, "UPDATE user SET guild='None' WHERE guild='$guild_info[gname]'");
			mysqli_query($db, "DELETE FROM guildrequest WHERE gl_userid='$userid'");
				
			$tblname = "$GN" . "main" ."$GIDD";
			$tblname2 = "$GN" . "msgs" . "$GIDD";	

			mysqli_query($db, "DELETE FROM guild WHERE owner='$userid'");
			mysqli_query($db, "DROP TABLE $tblname");
			mysqli_query($db, "DROP TABLE $tblname2");
		}
	
		mysqli_query($db, "DELETE FROM user WHERE $q_cred"); 
		mysqli_query($db, "DELETE FROM military WHERE $q_cred");
		mysqli_query($db, "DELETE FROM buildings WHERE $q_cred");
		mysqli_query($db, "DELETE FROM research WHERE $q_cred");
		mysqli_query($db, "DELETE FROM returntbl WHERE $q_cred");
		mysqli_query($db, "DELETE FROM explore WHERE $q_cred");
		mysqli_query($db, "UPDATE user SET votefor='None' WHERE votefor='$ename'");
		
		unset($_SESSION['login'], $_SESSION['email'], $_SESSION['pw']);
		$login = $email = $pw = '';

		header("Location: index.php");
	}	 
}
include("include/chngpw_table.php");
include("include/S_PREF.php");
include("include/S_PD.php"); 
?>

</td>
</tr>
</table>
</body>
</html>

<?
ob_end_flush();
?>