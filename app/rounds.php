<?php
/**
 * The round list: where an account with no kingdom in play lands after
 * logging in (docs/rounds.md, "The player's path").
 *
 * It shows the current round, with a form to join it; the account's own
 * history, from round_result; and each ended round's leading kingdoms and
 * guilds. A guild's strength there is the sum of its kingdoms' exp, as the
 * tick computes it -- round_result keeps the rows, not the sums.
 *
 * An account that does have a kingdom in play is sent into the game, logged
 * into it as checklogin.php would.
 */

include("include/session.php");
include("include/connect.php");
require_once __DIR__ . '/include/kingdom.php';

if (!$mb_account_id) {
	header("Location: index.php");
	exit();
}
if ($userid !== '') {
	header("Location: main.php?pageid=news");
	exit();
}

$account = mb_account_get($mb_account_id);
$current = mb_round_current($db);
$kingdom = $current ? mb_kingdom_find($db, $mb_account_id, $current) : null;
if ($kingdom) {
	$computer_id = isset($_COOKIE['computer_id']) ? (string) (int) $_COOKIE['computer_id'] : '';
	mb_kingdom_enter($db, $kingdom['userid'], $_SERVER["REMOTE_ADDR"], $computer_id);
	mb_session_login($account, $kingdom['userid']);
	header("Location: main.php?pageid=news");
	exit();
}

$round = $current ? mysqli_fetch_assoc(mb_query($db, "SELECT r.round_id, r.name, r.status,
	(SELECT count(*) FROM user u WHERE u.round_id = r.round_id) AS kingdoms
	FROM `round` r WHERE r.round_id = ?", array($current))) : null;
?>
<html>
<head>
  <title>Medieval Battles: Rounds</title>
  <link rel=stylesheet type="text/css" href="css/main-site.css">
</head>
<body>
<table width="100%" border="1" bordercolor="#990000" cellspacing="3" cellpadding="8">
<tr><td bgcolor="#000000" valign="top">

<p>Logged in as <b><?php echo mb_h($account['handle']); ?></b>.
<?php if ((int) $account['admin'] === 1) { echo '<a href="admin/rounds.php">Administration</a> '; } ?>
<a href="logout.php">Log out</a></p>

<hr color="#660000">
<h3>This round</h3>
<?php
if (!$round) {
	echo "<p>No round is open just now. Check back soon.</p>";
} else {
	echo "<p><b>" . mb_h($round['name']) . "</b>: "
		. ($round['status'] === 'open' ? "open for signups; the ticks have not begun" : "running")
		. ", with " . (int) $round['kingdoms'] . " kingdoms.</p>";
	echo "<p>Found your kingdom in it. Its name is yours for this round alone.</p>";
	echo "<form method='post' action='join.php'><table>";
	echo "<tr><td>Empire Name:</td><td><input type='text' name='ename' size='30' maxlength='25'></td></tr>";
	echo "<tr><td>Race:</td><td><select name='race'><option selected value='ns'>-----------</option>";
	foreach (array_keys($GAMEDATA['races']) as $race) {
		echo "<option value=" . mb_attr($race) . ">" . mb_h($race) . "</option>";
	}
	echo "</select></td></tr>";
	echo "<tr><td>Class:</td><td><select name='class'><option selected value='ns'>-------------------</option>";
	foreach (array_keys($GAMEDATA['classes']) as $class) {
		echo "<option value=" . mb_attr($class) . ">" . mb_h($class) . "</option>";
	}
	echo "</select></td></tr>";
	echo "<tr><td>MSN:</td><td><input type='text' name='msn' size='30' maxlength='50'></td></tr>";
	echo "<tr><td>AIM:</td><td><input type='text' name='aim' size='30' maxlength='20'></td></tr>";
	echo "<tr><td colspan='2' align='center'><input class='button' type='submit' name='join' value='Join (click only once)'></td></tr>";
	echo "</table></form>";
}
?>

<hr color="#660000">
<h3>Your rounds</h3>
<?php
$r = mb_query($db, "SELECT rr.place, rr.ename, rr.race, rr.class, rr.exp, rr.land, r.name,
	(SELECT count(*) FROM round_result x WHERE x.round_id = rr.round_id) AS kingdoms
	FROM round_result rr JOIN `round` r ON r.round_id = rr.round_id
	WHERE rr.account_id = ? ORDER BY rr.round_id DESC", array($mb_account_id));
$mine = 0;
echo "<table border='1' cellpadding='3' cellspacing='0'><tr><td>Round</td><td>Kingdom</td><td>Race / Class</td><td>Place</td><td>Experience</td><td>Land</td></tr>";
while ($row = mysqli_fetch_assoc($r)) {
	$mine++;
	echo "<tr><td>" . mb_h($row['name']) . "</td><td>" . mb_h($row['ename']) . "</td><td>"
		. mb_h($row['race']) . " " . mb_h($row['class']) . "</td><td>" . (int) $row['place'] . " of " . (int) $row['kingdoms']
		. "</td><td>" . number_format((int) $row['exp']) . "</td><td>" . number_format((int) $row['land']) . "</td></tr>";
}
echo "</table>";
if (!$mine) {
	echo "<p>You have not finished a round yet.</p>";
}
?>

<hr color="#660000">
<h3>Past rounds</h3>
<?php
$ended = mysqli_query($db, "SELECT round_id, name, ended_at FROM `round` WHERE status = 'ended' ORDER BY round_id DESC LIMIT 10");
$any = 0;
while ($past = mysqli_fetch_assoc($ended)) {
	$any++;
	echo "<h4>" . mb_h($past['name']) . " <font size='1'>(ended " . mb_h($past['ended_at']) . ")</font></h4>";
	echo "<table><tr valign='top'><td>";
	echo "<table border='1' cellpadding='3' cellspacing='0'><tr><td colspan='3'><b>Strongest kingdoms</b></td></tr>";
	$top = mb_query($db, "SELECT place, ename, exp FROM round_result WHERE round_id = ? ORDER BY place LIMIT 10", array((int) $past['round_id']));
	while ($row = mysqli_fetch_assoc($top)) {
		echo "<tr><td>" . (int) $row['place'] . "</td><td>" . mb_h($row['ename']) . "</td><td>" . number_format((int) $row['exp']) . "</td></tr>";
	}
	echo "</table></td><td>";
	echo "<table border='1' cellpadding='3' cellspacing='0'><tr><td colspan='3'><b>Strongest guilds</b></td></tr>";
	$guilds = mb_query($db, "SELECT guild, sum(exp) AS strength, count(*) AS members FROM round_result
		WHERE round_id = ? AND guild <> 'None' AND guild <> '' GROUP BY guild ORDER BY strength DESC LIMIT 5", array((int) $past['round_id']));
	while ($row = mysqli_fetch_assoc($guilds)) {
		echo "<tr><td>" . mb_h($row['guild']) . "</td><td>" . (int) $row['members'] . " members</td><td>" . number_format((int) $row['strength']) . "</td></tr>";
	}
	echo "</table></td></tr></table>";
}
if (!$any) {
	echo "<p>No round has ended yet.</p>";
}
?>

</td></tr>
</table>
</body>
</html>
