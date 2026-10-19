<?php
/**
 * Who leads a settlement.
 *
 * `user.sl` is the whole of it: 'yes' on the leader's row, and the leader is
 * whoever in the settlement holds the most votes. In 2003 that count ran at the
 * top of govt.php, on every view of the page, and cleared every member's flag
 * before deciding -- so a settlement where nobody had voted lost its leader the
 * moment any member looked at the government page, and a tie went to whichever
 * row MySQL happened to return first. It also ran, slightly differently, at
 * the end of each of govt.php's three vote branches.
 *
 * It runs only when a count changes now: when a vote is cast, moved or
 * withdrawn (govt.php), and when an empire leaves the world and takes its vote
 * and its votes with it (include/empire.php). Looking at a page changes nothing.
 */

if (!function_exists('mb_settlement_elect')) {
	/**
	 * Count one settlement's votes and set its leader.
	 *
	 * The leader is the member with the most votes. On a tie the sitting
	 * leader keeps the seat if they are among the tied, and otherwise the
	 * lowest userid wins -- something definite, where the old count left it to
	 * row order. With no votes cast at all, nobody leads: a leader is someone a
	 * member chose.
	 *
	 * @param mysqli $db
	 * @param int    $setid
	 */
	function mb_settlement_elect($db, $setid)
	{
		$setid = (int) $setid;

		// The count is taken from the votes, not kept beside them. `vote` used
		// to move by one as votes were cast and withdrawn, and every move was a
		// chance to get it wrong: six votes sent at once from six logins each
		// added one and took nothing away, and the counter crowned the result.
		// `votefor` is the vote itself -- one per member, overwritten when it
		// moves -- so counting it cannot drift.
		mb_query($db, "UPDATE user u LEFT JOIN (SELECT votefor, COUNT(*) AS n FROM user WHERE setid=? GROUP BY votefor) v ON v.votefor = u.ename SET u.vote = COALESCE(v.n, 0) WHERE u.setid=?", array($setid, $setid));

		$r = mb_query($db, "SELECT max(vote) FROM user WHERE setid=?", array($setid));
		$row = $r ? mysqli_fetch_row($r) : null;
		$max = (int) ($row ? $row[0] : 0);

		if ($max <= 0) {
			mb_query($db, "UPDATE user SET sl='no' WHERE setid=?", array($setid));
			return;
		}

		$r = mb_query($db, "SELECT userid FROM user WHERE setid=? AND vote=? ORDER BY (sl='yes') DESC, userid ASC LIMIT 1", array($setid, $max));
		$row = $r ? mysqli_fetch_row($r) : null;
		if (!$row) {
			return;
		}
		$winner = (int) $row[0];

		mb_query($db, "UPDATE user SET sl='no' WHERE setid=? AND userid != ?", array($setid, $winner));
		mb_query($db, "UPDATE user SET sl='yes' WHERE userid=?", array($winner));
	}
}
