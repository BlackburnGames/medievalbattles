<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$changepw  = mb_input('changepw');
$cnewpw    = mb_input('cnewpw');
$currentpw = mb_input('currentpw');
$delete    = mb_input('delete');
$dpw       = mb_input('dpw');
$empnews   = mb_input('empnews');
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
 * The queries below bind $email and $pw with mb_query(), so the AIM and MSN
 * updates after an address change filter on the new address by reading
 * $email after it is reassigned.
 *
 * The password and the address are the account's, in the accounts database
 * (include/accounts.php); $pw is the kingdom's key. So every password check
 * here is against the account's hash, and an address change moves the
 * account as well as the kingdom's six rows.
 */
$mb_account = mb_account_get($mb_account_id);
$account_hash = $mb_account ? $mb_account['pw'] : '';

if(!IsSet($empnews))	{
	echo "<div align=center><a href=preferences.php?empnews=true>Delete Empire News</a>";
}
else	{	
	
	echo "<div align=center><font class=yellow>Your Empire News has been deleted</a><br>";
	mb_query($db, "DELETE FROM empnews WHERE yourid=?", array($userid));
	include("include/chngpw_table.php");
	include("include/S_PREF.php");
	include("include/S_PD.php");
	die();
}

if(!IsSet($changepw))	{
}
else	{
	include("include/S_SINFOS.php");

	// Verified against the account's hash rather than compared to it. With md5
	// the check could be digest == digest; a salted hash cannot be recomputed
	// from the password alone, which is the point of it, so the plaintext goes
	// to include/password.php instead. Not against $pw, which was the hash
	// once and is the kingdom's key now.
	if(!mb_password_verify($currentpw, $account_hash))	{
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
	// mb_password_hash($newpw), not md5(htmlspecialchars($newpw)). Escaping the
	// password before hashing it was the same 2003 reflex as escaping everything
	// else on the way in, and here it locked people out: checksignup.php and
	// checklogin.php both hash the raw string, so a password containing & < > "
	// or ' was stored as the digest of a value login could never reproduce.
	// credentials.php constrains the hash, not the password, so nothing stopped
	// it. The line above it did the same thing to the *current* password check
	// and was harmless only because the next line immediately overwrote it.
	//
	// One row, the account's. It was six UPDATEs while the hash was copied into
	// every kingdom table; the kingdom's key does not change with the password.
	$newpw_hash = mb_password_hash($newpw);
	mb_password_store($mb_account_id, $newpw_hash);
	$account_hash = $newpw_hash;
	// Every other session on this account still holds the old hash's
	// fingerprint, and is ended by include/session.php on its next request.
	mb_session_rotate($email, $pw, $account_hash);

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

	$E_Result = mb_query($db, "SELECT email FROM user WHERE email=?", array($cnewemail));
	$New_Email = mysqli_fetch_array($E_Result);

	$New_Email[0] = strtolower($New_Email[0]);

	// And the accounts, which hold addresses whose kingdoms are gone. Another
	// account's address is taken whether or not it plays this round.
	$E_Account = mb_account_find($cnewemail);
	$taken_by_account = $E_Account && (int) $E_Account['account_id'] !== $mb_account_id;

	if(($New_Email[0] == $cnewemail AND $newemail != $email) OR $taken_by_account)	{
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
	// image this suite also runs against. The values are bound now, and
	// mb_valid_email() above has already refused every character it was
	// standing between MySQL and. The table names are a literal list.
	//
	// The account first, then the round's copy in the kingdom's six tables.
	// The kingdom is linked to the account by user.account_id, so the copy is
	// not what holds them together -- but it is what every "email=? AND pw=?"
	// in the game matches, and this session moves onto it below. An ended
	// round keeps the address it had.
	mb_account_set_email($mb_account_id, $newemail);
	foreach (array('user', 'buildings', 'military', 'research', 'returntbl', 'explore') as $table) {
		mb_query($db, "UPDATE $table SET email=? WHERE email=? AND pw=?", array($newemail, $email, $pw)) or die(mysqli_error($db)); // mb-unbound-ok: table names from a literal list
	}
	$email = $newemail;
	mb_session_rotate($email, $pw, $account_hash);

	// The six UPDATEs above have just moved the kingdom onto the new address,
	// and $email now holds it, so these filter on that one.
	mb_query($db, "UPDATE user SET aim=? WHERE email=? AND pw=?", array($newaim, $email, $pw));
	mb_query($db, "UPDATE user SET msn=? WHERE email=? AND pw=?", array($newmsn, $email, $pw));
		
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

	// Checked as typed. The digest was taken on the line above this test, and
	// md5("") is a 32-character string rather than an empty one -- so the "you
	// must specify a password" branch could never run and an empty box fell
	// through to the mismatch message below it. Both refuse the deletion; only
	// one of them says why.
	if($dpw == "")	{
		echo"<div align=center><font class=yellow>In order to delete, you must specify a password.</font></div>";
		include("include/chngpw_table.php");
		include("include/S_PREF.php");
		include("include/S_PD.php");
		die();
	}
	elseif(!mb_password_verify($dpw, $account_hash))	{
		echo"<div align=center><font class=yellow>Incorrect password.</font><div>";
		include("include/chngpw_table.php");
		include("include/S_PREF.php");
		include("include/S_PD.php");
		die();
	}
	else	{
		
		mb_news_set($db, "$clock", 'red', "$ename has deleted their account", $setid, 0);
	  
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


		// The vote, the guild it leads, the settlement's member count and the
		// six rows -- see include/empire.php for what this branch used to do
		// instead, which included two DROP TABLEs.
		require_once __DIR__ . '/include/empire.php';
		mb_empire_remove($db, $userid);
		// And the account: this is the one place a player deletes theirs, not
		// just the kingdom it plays this round.
		mb_account_delete($mb_account_id);

		mb_session_end();
		$login = $email = $pw = '';

		// Not a redirect. This page has no output buffer and igtop.php has
		// rendered the whole frame by now, so header("Location: index.php")
		// could only ever warn -- and then fall through into the preference
		// forms of an account that no longer exists. Stop here instead, with
		// the way out the redirect was meant to be.
		echo "<div align=center><a href=index.php>Return to the front page</a></div>";
		die();
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