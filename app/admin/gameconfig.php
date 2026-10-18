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
 * any version of PHP. app/admin/newgame.php, since retired, named the same
 * tables correctly and is where the working spellings came from.
 *
 * Its "reset all accounts" section is gone rather than repaired. It wrote
 * about twenty columns -- `military.ssword`, `axe`, `club`, `icesword`,
 * `priout`, `buildings.lab` -- that this schema does not have either, and
 * deciding what a reset should now write to the columns that replaced them is
 * a game-design question, not a port. db/seed.sql and bin/round.php are the
 * two things that build a world.
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

// The round every action below is confined to.
$mb_round = (int) mb_round_current($db);

/**
 * The bulk actions, as label => list of statements.
 *
 * Every one is a fixed statement with nothing interpolated into it, which is
 * the property that makes a page that can empty the database reviewable at a
 * glance. Each takes one value, bound: the round it acts on, which is the
 * current one (docs/rounds.md) -- an ended round is history and none of these
 * reach it. The one action that takes input -- deleting a named account -- is
 * handled separately below.
 */
$MB_ADMIN_ACTIONS = array(
	'messages' => array(
		'label'      => 'Delete every private message',
		'statements' => array('DELETE FROM messages WHERE round_id=?'),
	),
	'news' => array(
		'label'      => 'Delete every settlement news entry',
		'statements' => array('DELETE FROM setnews WHERE round_id=?'),
	),
	'forums' => array(
		'label'      => 'Delete every settlement forum thread and reply',
		'statements' => array('DELETE FROM setforums WHERE round_id=?', 'DELETE FROM setforumsmsgs WHERE round_id=?'),
	),
	'guilds' => array(
		'label'      => 'Delete every guild, and its threads, news and requests',
		'statements' => array(
			'DELETE FROM guild WHERE round_id=?', 'DELETE FROM guildthreads WHERE round_id=?', 'DELETE FROM guildmsgs WHERE round_id=?',
			'DELETE FROM guildnews WHERE round_id=?', 'DELETE FROM guildrequests WHERE round_id=?',
		),
	),
	'settlements' => array(
		'label'      => 'Return every settlement to its starting state',
		'statements' => array(
			"UPDATE settlement SET setname='None' WHERE round_id=?",
			"UPDATE settlement SET setpic='' WHERE round_id=?",
			"UPDATE settlement SET members='0' WHERE round_id=?",
			"UPDATE settlement SET setnotice='Welcome to Medieval Battles' WHERE round_id=?",
			"UPDATE settlement SET setstrength='0' WHERE round_id=?",
		),
	),
	// "Delete every kingdom except administrators'" was here, with
	// admin/newgame.php beside it. A new start is a new round now, from
	// bin/round.php, which deletes nothing (docs/rounds.md).
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
			mb_query($db, $sql, array($mb_round)) or die(mysqli_error($db)); // mb-unbound-ok: fixed statements from $MB_ADMIN_ACTIONS
		}
		echo "<b>" . htmlspecialchars($MB_ADMIN_ACTIONS[$action]['label']) . "</b>: done.<br><br>";
	}
	echo "<a href=gameconfig.php>Return</a></center>";
	die();
}

// --- delete one account ------------------------------------------------------

if (IsSet($daccount)) {
	$clock   = date("m/d/y, H:ia");

	$theirid = mb_query($db, "SELECT userid FROM user WHERE round_id=? AND ename=?", array($mb_round, $empre));
	$tuid    = mb_db_result($theirid, "tuid");

	// No such empire: stop. With the old address-keyed DELETEs an unset value
	// read `email=''`, which matched any row whose address happened to be blank.
	if ($tuid === null) {
		echo "<b>No empire called " . htmlspecialchars($empre) . ".</b><br><br>";
		echo "<a href=gameconfig.php>Return</a></center>";
		die();
	}

	// An administrator's kingdom is not deleted from here, the admin running
	// this page's own included. Revoke the flag first: bin/admin.php. The
	// flag is the account's, and user.account_id is what links the two.
	$empreacct = mb_query($db, "SELECT account_id FROM user WHERE round_id=? AND ename=?", array($mb_round, $empre));
	$empreaccount = mb_account_get(mb_db_result($empreacct, "empreacct"));
	if ($empreaccount && (int) $empreaccount['admin'] === 1) {
		echo "<b>" . htmlspecialchars($empre) . " is an administrator. Revoke that first, with bin/admin.php.</b><br><br>";
		echo "<a href=gameconfig.php>Return</a></center>";
		die();
	}

	$empreset = mb_query($db, "SELECT setid FROM user WHERE round_id=? AND ename=?", array($mb_round, $empre));
	$esetid   = mb_db_result($empreset, "esetid");

	// The old spelling built a table name -- "setnews" . $esetid -- and
	// inserted into it. That is the per-settlement schema this game has not
	// used since before db.sql, and it also meant a settlement id from the
	// database was interpolated outside quotes as an identifier.
	$news    = "[b]{$empre}[/b] has been deleted by an administrator";

	mb_news_set($db, "$clock", 'red', $news, $esetid, 0);

	mb_query($db, "DELETE FROM buildings WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM military WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM returntbl WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM research WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM explore WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM user WHERE userid=?", array((int) $tuid));

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

// --- every kingdom ---------------------------------------------------------
//
// It was "the credential dump this page has always been": the second column was
// the password hash, which was also the session token. The hash is the
// account's now and the round holds no copy, so the column is gone.

echo "<table border=1 width=\"100%\">
	<tr>
		<td><b>Empire Name</b></td>
		<td><b>Email</b></td>
		<td><b>AIM</b></td>
		<td><b>MSN</b></td>
		<td><b>Host Name</b></td>
	</tr>";

// One query, not one per row. The online marker used to be a second SELECT
// inside the loop, filtered on the empire name it had just fetched.
// The address is the owner's, from the `account` table: include/kingdoms.php.
require_once __DIR__ . '/include/kingdoms.php';
foreach (mb_admin_kingdoms($db, array('ename', 'email', 'aim', 'msn', 'ip', 'online'), 'ip') as $row) {
	// Assigned in two statements rather than by a ternary on $row[5]: the marker
	// is a constant either way, and taking the value out of the assignment is
	// what makes that visible to tests/xss-audit.php as well as to a reader.
	$online = "";
	if ($row[5] == 1) {
		$online = "<font color=red>*</font>";
	}
	echo "<tr align=center valign=top>"
		. "<td>" . htmlspecialchars($row[0]) . " $online</td>"
		. "<td>" . htmlspecialchars($row[1]) . "</td>"
		. "<td>" . htmlspecialchars($row[2]) . "</td>"
		. "<td>" . htmlspecialchars($row[3]) . "</td>"
		. "<td>" . htmlspecialchars($row[4]) . "</td></tr>\n";
}

echo "</table></center>
</body>
</html>";
