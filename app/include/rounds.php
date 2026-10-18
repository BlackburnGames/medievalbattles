<?php
/**
 * Rounds: which one is being played (docs/rounds.md).
 *
 * Every table a round owns carries its round_id. A row is written into its
 * owner's round -- a kingdom's buildings into the kingdom's, a message into
 * its recipient's, settlement news into the settlement's -- by taking the
 * round_id from the owner's row in the INSERT itself, so no page has to pass
 * one along. A new kingdom is the exception: it has no owner row yet, and
 * joins the round mb_round_current() names.
 */

require_once __DIR__ . '/db.php';

if (!function_exists('mb_round_current')) {
	/**
	 * The round new kingdoms join and the tick advances: the newest that has
	 * not ended. One round runs at a time until concurrent rounds (step 4 in
	 * docs/rounds.md) give the session a choice.
	 *
	 * @return int|null  null if every round has ended
	 */
	function mb_round_current($db)
	{
		$r = mb_query($db, "SELECT round_id FROM `round` WHERE status <> 'ended' ORDER BY round_id DESC LIMIT 1");
		$row = $r ? mysqli_fetch_row($r) : null;
		return $row ? (int) $row[0] : null;
	}
}

if (!function_exists('mb_round_running')) {
	/**
	 * The round the tick advances: the current one, if it has started. An
	 * open round takes signups but does not tick.
	 *
	 * @return int|null
	 */
	function mb_round_running($db)
	{
		$r = mb_query($db, "SELECT round_id FROM `round` WHERE status = 'running' ORDER BY round_id DESC LIMIT 1");
		$row = $r ? mysqli_fetch_row($r) : null;
		return $row ? (int) $row[0] : null;
	}
}

/*
 * A round's life: opened (signups, no tick), started (the tick runs), ended
 * (history). One round is not ended at a time, until concurrent rounds.
 * bin/round.php is the way in; each step refuses rather than guesses, with a
 * RuntimeException saying why.
 */

if (!function_exists('mb_round_open')) {
	/**
	 * Open a new round for signups, with its settlements.
	 *
	 * As many settlements as the last round had -- ten for the first, as db.sql
	 * seeds -- numbered on from the last round's, since a setid is never
	 * reused. Nothing else is seeded: kingdoms arrive by signup.
	 *
	 * @return int  the new round's id
	 */
	function mb_round_open($db, $name)
	{
		$name = trim((string) $name);
		if ($name === '' || strlen($name) > 64) {
			throw new RuntimeException('A round needs a name of 1 to 64 characters.');
		}
		$current = mb_round_current($db);
		if ($current) {
			throw new RuntimeException("Round $current has not ended; end it first.");
		}

		$last = mysqli_fetch_row(mb_query($db, "SELECT max(round_id) FROM `round`"));
		list($first, $lastset) = $last && $last[0] ? mb_settlement_range($db, $last[0]) : array(0, 0);
		$count = $first ? $lastset - $first + 1 : 10;

		mysqli_begin_transaction($db);
		$ok = mb_query($db, "INSERT INTO `round` (name, status) VALUES (?, 'open')", array($name));
		$round_id = (int) mysqli_insert_id($db);
		for ($i = 0; $ok && $i < $count; $i++) {
			$ok = mb_query($db, "INSERT INTO settlement (round_id) VALUES (?)", array($round_id));
		}
		if (!$ok) {
			mysqli_rollback($db);
			throw new RuntimeException('Opening the round failed: ' . mysqli_error($db));
		}
		mysqli_commit($db);
		return $round_id;
	}
}

if (!function_exists('mb_round_start')) {
	/**
	 * Start the open round: from now the tick advances it.
	 *
	 * @return int  the round started
	 */
	function mb_round_start($db)
	{
		$r = mysqli_fetch_row(mb_query($db, "SELECT round_id FROM `round` WHERE status = 'open' ORDER BY round_id DESC LIMIT 1"));
		if (!$r) {
			throw new RuntimeException('No round is open to start.');
		}
		mb_query($db, "UPDATE `round` SET status = 'running', started_at = NOW() WHERE round_id = ?", array((int) $r[0]));
		return (int) $r[0];
	}
}

if (!function_exists('mb_round_end')) {
	/**
	 * End the current round: write each kingdom's result and mark it ended,
	 * in one transaction, so a round is never ended without its results.
	 *
	 * Nothing is deleted. The round's rows stay, out of reach of every page
	 * and of the tick -- which advance only a round that has not ended -- and
	 * its players' sessions end on their next request (include/session.php).
	 * The accounts are untouched.
	 *
	 * @return int  the round ended
	 */
	function mb_round_end($db)
	{
		$round_id = mb_round_current($db);
		if (!$round_id) {
			throw new RuntimeException('No round is open or running.');
		}

		mysqli_begin_transaction($db);
		// Placed by exp, the networth; a tie goes to the earlier signup.
		$ok = mb_query($db, "INSERT INTO round_result
			(round_id, userid, account_id, ename, race, class, setid, setname, guild, land, mts, exp, place)
			SELECT u.round_id, u.userid, u.account_id, u.ename, u.race, u.class, u.setid,
				COALESCE(s.setname, ''), u.guild, u.land, u.mts, u.exp,
				ROW_NUMBER() OVER (ORDER BY u.exp DESC, u.userid)
			FROM user u LEFT JOIN settlement s ON s.setid = u.setid
			WHERE u.round_id = ?", array($round_id))
			&& mb_query($db, "UPDATE `round` SET status = 'ended', ended_at = NOW() WHERE round_id = ?", array($round_id));
		if (!$ok) {
			mysqli_rollback($db);
			throw new RuntimeException('Ending the round failed: ' . mysqli_error($db));
		}
		mysqli_commit($db);
		return $round_id;
	}
}
