<?php
/**
 * How many settlements there are: as many as the `settlement` table holds.
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
 */

if (!function_exists('mb_settlement_count')) {
	/**
	 * @param mysqli $db
	 * @return int  the highest settlement id; ids run from 1 with no gaps
	 */
	function mb_settlement_count($db)
	{
		$r = mysqli_query($db, "SELECT max(setid) FROM settlement");
		$row = $r ? mysqli_fetch_row($r) : null;
		return $row ? (int) $row[0] : 0;
	}
}
