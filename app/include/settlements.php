<?php
/**
 * Which settlements there are: the ones the `settlement` table holds for the
 * round.
 *
 * The game used to answer two ways. db.sql ships ten, and signup,
 * settlement.php and the three attack drop-downs asked the schema with
 * max(setid). The intel, messaging and settlement-attack range checks carried
 * a hardcoded 30 instead -- moved into $GAMEDATA as one number when the tick's
 * constants were inverted, which is what made the disagreement visible -- and
 * the tick recalculated strength for ids up to 30. So settlements 11-30 were
 * selectable on three screens and empty on all of them, and twenty passes of
 * the strength loop matched nothing.
 *
 * The schema is the truth, because it is the only thing that can hold a
 * settlement. Every reader asks here.
 *
 * Since rounds, a setid is issued once across all of them (docs/rounds.md),
 * so a round's settlements are a run of ids that need not start at 1: round
 * 1 has 1-10, and the round after it 11-20. The range is the round's, and a
 * settlement number outside it is another round's -- which the range checks
 * that use this refuse as not existing.
 */

if (!function_exists('mb_settlement_range')) {
	/**
	 * @param mysqli $db
	 * @param int    $round_id
	 * @return array  array(first setid, last setid); the round's ids run from
	 *                one to the other with no gaps, and are array(0, 0) for a
	 *                round with none
	 */
	function mb_settlement_range($db, $round_id)
	{
		$r = mb_query($db, "SELECT min(setid), max(setid) FROM settlement WHERE round_id=?", array((int) $round_id));
		$row = $r ? mysqli_fetch_row($r) : null;
		return $row && $row[0] !== null ? array((int) $row[0], (int) $row[1]) : array(0, 0);
	}
}

if (!function_exists('mb_settlement_in_round')) {
	/**
	 * Whether a settlement number names one of the round's settlements.
	 *
	 * @return bool
	 */
	function mb_settlement_in_round($db, $round_id, $snum)
	{
		list($first, $last) = mb_settlement_range($db, $round_id);
		return $first > 0 && (int) $snum >= $first && (int) $snum <= $last;
	}
}
