<?php
/**
 * Removing an empire from the world.
 *
 * Two things end an empire: its player deleting the account (preferences.php)
 * and losing its last land in a land attack (attack.php). They are the same
 * operation, and in 2003 one was written as a copy of the other -- the deletion
 * branch still carried the attack's variable names. By the time this file
 * existed the two copies had broken in different places, and neither was
 * whole:
 *
 *   - attack.php withdrew the empire's vote with `WHERE ename='$V_check'`, an
 *     array interpolated into a string, so it matched the empire named "Array"
 *     and the vote stayed cast. Its `DELETE FROM militaryWHERE` did not parse,
 *     so a destroyed empire's army row was never removed.
 *   - preferences.php looked a led guild up by `owner='$ename'` (owner holds a
 *     userid), deleted from `guildrequest` (the table is `guildrequests`), and
 *     then ran `DROP TABLE main` and `DROP TABLE msgs` -- the per-guild tables
 *     of an older schema, named from two variables nothing assigns. It never
 *     told the settlement it had lost a member.
 *
 * Everything either copy got right is here once, keyed on the userid.
 */

if (!function_exists('mb_empire_remove')) {
	/**
	 * Delete an empire and everything that only means something while it exists.
	 *
	 * Does not write news or send mail -- the two callers say different things
	 * about why it happened.
	 *
	 * @param mysqli $db
	 * @param int    $userid
	 * @return bool  false if there was no such empire, and nothing was touched
	 */
	function mb_empire_remove($db, $userid)
	{
		$q_id = mb_sql_int($userid);

		$result = mysqli_query($db, "SELECT ename, setid, votefor FROM user WHERE userid=$q_id");
		$row = $result ? mysqli_fetch_array($result) : null;
		if (!$row) {
			return false;
		}
		$q_ename = mb_sql_str($db, $row['ename']);

		// The vote it was casting, and the votes cast for it. A vote for yourself
		// leaves with the row.
		if ($row['votefor'] != 'None' && $row['votefor'] != '' && $row['votefor'] != $row['ename']) {
			mysqli_query($db, "UPDATE user SET vote = vote - 1 WHERE ename=" . mb_sql_str($db, $row['votefor']));
		}
		mysqli_query($db, "UPDATE user SET votefor='None' WHERE votefor=$q_ename");

		// A guild it leads goes with it, as guildconfig.php's own delete does:
		// members back to None, then the row. `guild.owner` holds the userid.
		$result = mysqli_query($db, "SELECT gname FROM guild WHERE owner='$q_id'");
		$guild = $result ? mysqli_fetch_array($result) : null;
		if ($guild) {
			mysqli_query($db, "UPDATE user SET guild='None' WHERE guild=" . mb_sql_str($db, $guild['gname']));
			mysqli_query($db, "DELETE FROM guild WHERE owner='$q_id'");
			mysqli_query($db, "DELETE FROM guildrequests WHERE gl_userid='$q_id'");
		}
		// And any request it had filed with someone else's guild.
		mysqli_query($db, "DELETE FROM guildrequests WHERE applicant='$q_id'");

		mysqli_query($db, "UPDATE settlement SET members = members - 1 WHERE setid=" . mb_sql_int($row['setid']) . " AND members > 0");

		foreach (array('user', 'military', 'buildings', 'research', 'returntbl', 'explore', 'emailvalidate') as $table) {
			mysqli_query($db, "DELETE FROM $table WHERE userid=$q_id");
		}

		return true;
	}
}
