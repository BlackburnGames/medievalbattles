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
