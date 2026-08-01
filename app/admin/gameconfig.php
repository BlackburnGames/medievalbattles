<?php
/**
 * Bulk game administration: wipe a table, reset the settlements, drop one
 * account.
 *
 * This page had two problems beyond the missing gate, and the second is why it
 * is rewritten rather than patched.
 *
 * The gate first. Its only authentication sat at the top and had been
 * commented out since 2003 -- it restored $pw from the session and compared it
 * to a hardcoded "melvin". With that gone, anyone who could reach the file
 * could drop every account in the game. gameconfig.php is not linked from the
 * navbar, and it used not to include include/igtop.php either, so it was the
 * one page in the area with nothing above it to inherit a check from -- which
 * is how it came to have none. It calls the gate itself, before igtop.php, so
 * that a refused request gets a clean 403 rather than half a rendered page.
 *
 * Then the queries. Thirty of its forty statements named tables that have
 * never existed in this schema: `setnews1`..`setnews10`, `setmain1`..`10` and
 * `setmsgs1`..`10` are the per-settlement tables of a much older schema -- the
 * game has kept settlement news in one `setnews` table with a `setid` column,
 * and its forums in `setforums`/`setforumsmsgs`, for as long as db.sql goes
 * back -- and `DELETE FROM return` wants `returntbl`. So "delete all news",
 * "delete all forums" and "delete all accounts" have never done anything, on
 * any version of PHP. app/admin/newgame.php names the same tables correctly
 * and is where the working spellings came from.
 *
 * Its "reset all accounts" section is gone rather than repaired. It wrote
 * about twenty columns -- `military.ssword`, `axe`, `club`, `icesword`,
 * `priout`, `buildings.lab` -- that this schema does not have either, and
 * deciding what a reset should now write to the columns that replaced them is
 * a game-design question, not a port. db/seed.sql and newgame.php are the two
 * things that build a world, and both are current.
 *
 * The four hundred lines of copy-pasted table markup are one loop now, over
 * the action list below.
 */

include("include/auth.php");
mb_admin_require();

// Request input, formerly supplied by register_globals.
include("include/request.php");
$action   = mb_input('action');
$empre    = mb_input('empre');
$daccount = mb_input('daccount');

include("include/igtop.php");

/**
 * The bulk actions, as label => list of statements.
 *
 * Deliberately not parameterised: every one of these is a fixed statement with
 * nothing interpolated into it, which is the property that makes a page that
 * can empty the database reviewable at a glance. The one action that does take
 * input -- deleting a named account -- is handled separately below.
 */
$MB_ADMIN_ACTIONS = array(
	'messages' => array(
		'label'      => 'Delete every private message',
		'statements' => array('DELETE FROM messages'),
	),
	'news' => array(
		'label'      => 'Delete every settlement news entry',
		'statements' => array('DELETE FROM setnews'),
	),
	'forums' => array(
		'label'      => 'Delete every settlement forum thread and reply',
		'statements' => array('DELETE FROM setforums', 'DELETE FROM setforumsmsgs'),
	),
	'guilds' => array(
		'label'      => 'Delete every guild, and its threads, news and requests',
		'statements' => array(
			'DELETE FROM guild', 'DELETE FROM guildthreads', 'DELETE FROM guildmsgs',
			'DELETE FROM guildnews', 'DELETE FROM guildrequests',
		),
	),
	'settlements' => array(
		'label'      => 'Return every settlement to its starting state',
		'statements' => array(
			"UPDATE settlement SET setname='None'",
			"UPDATE settlement SET setpic=''",
			"UPDATE settlement SET members='0'",
			"UPDATE settlement SET setnotice='Welcome to Medieval Battles'",
			"UPDATE settlement SET setstrength='0'",
		),
	),
	'accounts' => array(
		'label'      => 'Delete EVERY account',
		'statements' => array(
			'DELETE FROM user', 'DELETE FROM buildings', 'DELETE FROM military',
			'DELETE FROM research', 'DELETE FROM explore', 'DELETE FROM returntbl',
			'DELETE FROM barter', 'DELETE FROM empnews', 'DELETE FROM emailvalidate',
		),
	),
);

echo "<center><br><b>Game configuration</b><br><br>";

// --- a bulk action -----------------------------------------------------------

if (IsSet($action)) {
	// $action indexes the list above and is never interpolated into a query --
	// an unrecognised one selects nothing rather than reaching MySQL.
	if (!isset($MB_ADMIN_ACTIONS[$action])) {
		echo "<b>No such action.</b><br><br>";
	} else {
		foreach ($MB_ADMIN_ACTIONS[$action]['statements'] as $sql) {
			mysqli_query($db, $sql) or die(mysqli_error($db));
		}
		echo "<b>" . htmlspecialchars($MB_ADMIN_ACTIONS[$action]['label']) . "</b>: done.<br><br>";
	}
	echo "<a href=gameconfig.php>Return</a></center>";
	die();
}

// --- delete one account ------------------------------------------------------

if (IsSet($daccount)) {
	$clock   = date("m/d/y, H:ia");
	$q_empre = mb_sql_str($db, $empre);

	$theiremail = mysqli_query($db, "SELECT email FROM user WHERE ename=$q_empre");
	$tmail      = mb_db_result($theiremail, "tmail");

	// No such empire: stop. With $tmail unset every DELETE below read
	// `email=''`, which matches any row whose address happens to be blank.
	if ($tmail === null) {
		echo "<b>No empire called " . htmlspecialchars($empre) . ".</b><br><br>";
		echo "<a href=gameconfig.php>Return</a></center>";
		die();
	}

	$empreset = mysqli_query($db, "SELECT setid FROM user WHERE ename=$q_empre");
	$esetid   = mb_db_result($empreset, "esetid");

	// The old spelling built a table name -- "setnews" . $esetid -- and
	// inserted into it. That is the per-settlement schema this game has not
	// used since before db.sql, and it also meant a settlement id from the
	// database was interpolated outside quotes as an identifier.
	$news    = "[b]{$empre}[/b] has been deleted by an administrator";
	$q_tmail = mb_sql_str($db, $tmail);

	mb_news_set($db, "$clock", 'red', $news, $esetid, 0);

	mysqli_query($db, "DELETE FROM buildings WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM military WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM returntbl WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM research WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM explore WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM user WHERE email=$q_tmail");

	echo "<b>" . htmlspecialchars($empre) . " has been deleted.</b><br><br>";
	echo "<a href=gameconfig.php>Return</a></center>";
	die();
}

// --- the menu ----------------------------------------------------------------

echo "<table border=1 bordercolor=#000000 cellpadding=4 cellspacing=0>";
foreach ($MB_ADMIN_ACTIONS as $key => $spec) {
	echo "<tr><td>" . htmlspecialchars($spec['label']) . "</td>"
		. "<td><form method=post action=gameconfig.php>"
		. "<input type=hidden name=action value=\"" . htmlspecialchars($key) . "\">"
		. "<input type=submit value=\"Do it\"></form></td></tr>";
}
echo "<tr><td>Delete one account, by empire name</td>"
	. "<td><form method=post action=gameconfig.php>"
	. "<input type=text name=empre maxlength=50>"
	. "<input type=submit name=daccount value=\"Delete\"></form></td></tr>";
echo "</table><br>";

// --- every account, as the credential dump this page has always been ---------

echo "<table border=1 width=\"100%\">
	<tr>
		<td><b>Empire Name</b></td>
		<td><b>Password (MD5)</b></td>
		<td><b>Email</b></td>
		<td><b>AIM</b></td>
		<td><b>MSN</b></td>
		<td><b>Host Name</b></td>
	</tr>";

// One query, not one per row. The online marker used to be a second SELECT
// inside the loop, filtered on the empire name it had just fetched.
$result_id = mysqli_query($db, "SELECT ename, pw, email, aim, msn, ip, online FROM user ORDER BY ip");
while ($row = mysqli_fetch_row($result_id)) {
	$online = $row[6] == 1 ? "<font color=red>*</font>" : "";
	echo "<tr align=center valign=top>"
		. "<td>" . htmlspecialchars($row[0]) . " $online</td>"
		. "<td>" . htmlspecialchars($row[1]) . "</td>"
		. "<td>" . htmlspecialchars($row[2]) . "</td>"
		. "<td>" . htmlspecialchars($row[3]) . "</td>"
		. "<td>" . htmlspecialchars($row[4]) . "</td>"
		. "<td>" . htmlspecialchars($row[5]) . "</td></tr>\n";
}

echo "</table></center>
</body>
</html>";
