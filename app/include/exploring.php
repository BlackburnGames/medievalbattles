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
