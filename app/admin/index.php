<?php
// The admin area's menu, and its front door. It was main.php, with this file
// only a redirect to it -- but SiteGround's firewall refuses /admin/main.php
// by name, before PHP sees the request, so the menu lives here and /admin/
// opens it. The area has no login of its own: an administrator logs into
// the game, and the account's `admin` flag opens it; include/igtop.php
// asks (include/auth.php).

include("include/igtop.php");

$num_players_query = mb_query($db, "SELECT count(ename) FROM user WHERE round_id=?", array((int) mb_round_current($db)));
	$num_players = mb_db_result($num_players_query, "num_players");
$online_query = mb_query($db, "SELECT count(online) FROM user WHERE round_id=? AND online='1'", array((int) mb_round_current($db)));
	$users_online = mb_db_result($online_query, "users_online");
$spots_left = 450 - $num_players;


echo "
	<p>&nbsp;</p>
	<b>Current Game Information</b><br>
	<br>
	Slots: 450<br>
	Slots Taken: " . mb_h($num_players) . "<br>
	Slots Left: $spots_left<br>

	</body>
	</html>";

?>