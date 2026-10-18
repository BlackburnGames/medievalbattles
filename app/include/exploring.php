<?php
/**
 * A race's exploring rate: land times the rate, divided by 20, is how many
 * explorers one land a tick costs, so lower is better.
 *
 * The tick and the explore page both ask here. They used to answer apart: the
 * page offered Angels 0.85 and Demons 0.95, and the tick, which is what
 * actually moves land, knew only the Human's 0.8 and gave everyone else 0.9.
 * The page's rates were taken as the design, so the tick applies them now.
 */

if (!function_exists('mb_explore_rate')) {
	/**
	 * @param string $race
	 * @return float
	 */
	function mb_explore_rate($race)
	{
		global $GAMEDATA;
		if (isset($GAMEDATA['races'][$race]['explore_exp_num'])) {
			return $GAMEDATA['races'][$race]['explore_exp_num'];
		}
		return $GAMEDATA['exploring']['exp_num_default'];
	}
}

if (!function_exists('mb_explore')) {
	/**
	 * What a race's explorers find in one tick, and what each land costs. The
	 * tick applies it and the explore page shows it, from the one calculation:
	 * the page used to round where the tick floored, so it could promise a land
	 * the tick would not deliver.
	 *
	 * A land never costs fewer than one explorer. Under 23 land the cost
	 * rounded down to none, and the tick divided by it -- on PHP 8 a fatal,
	 * which ended the tick for every empire still to come.
	 *
	 * @return array per_land and per_mt, the explorers one land or mountain
	 *               costs; max_land and max_mts, the explorers that find the
	 *               most a tick allows; land_gain and mt_gain, what the
	 *               explorers given find this tick
	 */
	function mb_explore($race, $land, $mts, $expland, $expmt)
	{
		global $GAMEDATA;
		$rate = mb_explore_rate($race);
		$divisor = $GAMEDATA['exploring']['divisor'];
		$most = $GAMEDATA['exploring']['max_gain_per_tick'];

		// The page passes what functions.php read, which may be blank.
		$land = (int) $land;
		$mts = (int) $mts;
		$expland = (int) $expland;
		$expmt = (int) $expmt;

		$per_land = max(1, floor(round($land * $rate) / $divisor));
		$per_mt = max(1, floor(round($mts * $rate) / $divisor));

		return array(
			'per_land'  => $per_land,
			'per_mt'    => $per_mt,
			'max_land'  => $per_land * $most,
			'max_mts'   => $per_mt * $most,
			'land_gain' => min($most, floor($expland / $per_land)),
			'mt_gain'   => min($most, floor($expmt / $per_mt)),
		);
	}
}
