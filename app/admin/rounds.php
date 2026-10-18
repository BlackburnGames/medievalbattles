<?php
/**
 * Rounds, from the admin area: the steps bin/round.php takes, for an
 * administrator without a shell (docs/rounds.md, "Administration").
 *
 * Open a round for signups, start its tick, end it into its results. Each is
 * a POST -- ending a round logs every one of its players out, and a link a
 * crawler or a prefetch could follow is not how that should happen -- and
 * each refuses as the command line does, through app/include/rounds.php.
 */

include("include/auth.php");
mb_admin_require();

// From the POST body alone: not a query string, not a cookie.
$post = function ($key) {
	return isset($_POST[$key]) ? (string) $_POST[$key] : '';
};
$step = $post('step');
$name = $post('name');
$sure = $post('sure');

include("include/igtop.php");

echo "<center><br><b>Rounds</b><br><br>";

if ($step !== '') {
	try {
		if ($step === 'open') {
			$id = mb_round_open($db, $name);
			echo "<b>Opened round " . (int) $id . ". Signups are open; start it to begin the tick.</b>";
		} elseif ($step === 'start') {
			echo "<b>Started round " . (int) mb_round_start($db) . ".</b>";
		} elseif ($step === 'end' && $sure === 'yes') {
			echo "<b>Ended round " . (int) mb_round_end($db) . ". Its results are written and its players logged out.</b>";
		} elseif ($step === 'end') {
			echo "<b>Tick the box to end the round.</b>";
		} else {
			echo "<b>No such step.</b>";
		}
	} catch (RuntimeException $e) {
		echo "<b>" . mb_h($e->getMessage()) . "</b>";
	}
	echo "<br><br>";
}

echo "<table border=1 cellpadding=4 cellspacing=0>"
	. "<tr><td><b>Round</b></td><td><b>Name</b></td><td><b>Status</b></td><td><b>Kingdoms</b></td><td><b>Started</b></td><td><b>Ended</b></td></tr>";
$r = mysqli_query($db, "SELECT r.round_id, r.name, r.status, r.started_at, r.ended_at,
	(SELECT count(*) FROM user u WHERE u.round_id = r.round_id) AS kingdoms
	FROM `round` r ORDER BY r.round_id DESC");
while ($row = mysqli_fetch_assoc($r)) {
	echo "<tr><td>" . (int) $row['round_id'] . "</td><td>" . mb_h($row['name']) . "</td><td>" . mb_h($row['status'])
		. "</td><td>" . (int) $row['kingdoms'] . "</td><td>" . mb_h($row['started_at'] ?: '-')
		. "</td><td>" . mb_h($row['ended_at'] ?: '-') . "</td></tr>";
}
echo "</table><br>";

echo "<table border=1 cellpadding=4 cellspacing=0>";
echo "<tr><td>Open a new round for signups</td><td><form method=post action=rounds.php>"
	. "<input type=hidden name=step value=open><input type=text name=name maxlength=64> "
	. "<input type=submit value=\"Open\"></form></td></tr>";
echo "<tr><td>Start the open round's tick</td><td><form method=post action=rounds.php>"
	. "<input type=hidden name=step value=start><input type=submit value=\"Start\"></form></td></tr>";
echo "<tr><td>End the current round: write its results and log its players out</td><td><form method=post action=rounds.php>"
	. "<input type=hidden name=step value=end><input type=checkbox name=sure value=yes> I am sure "
	. "<input type=submit value=\"End\"></form></td></tr>";
echo "</table></center>
</body>
</html>";
