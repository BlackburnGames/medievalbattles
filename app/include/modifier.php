<?php
/**
 * An empire's offence and defence modifier.
 *
 * Its class's multiplier, then its race's delta added -- the order the engine
 * always applied them in, so a Giant Fighter is 1.05 + 0.25 = 1.30. A class
 * or race with no entry is 1.00 and +0.00.
 *
 * There were two copies of this table, in include/attack/defANDoff.php and
 * functions.php, and the battle used only one empire's: the attacker's race
 * and class scaled the defender's power as well as their own, so the two
 * cancelled and no modifier ever decided a battle. Each side has its own now,
 * which is what the status screen's defence figure and the manual always said.
 *
 * The values are read from $GAMEDATA['classes'][...]['offdef_mult'] and
 * $GAMEDATA['races'][...]['offdef_delta'], which are definitions rather than
 * transcriptions: change one and battles change with it, which
 * tests/combat-golden.sh's modifier- scenarios report.
 */

require_once __DIR__ . '/gamedata.php';

if (!function_exists('mb_offdef_modifier')) {
	function mb_offdef_modifier($race, $class)
	{
		global $GAMEDATA;

		$modifier = 1;
		if (isset($GAMEDATA['classes'][$class]['offdef_mult'])) {
			$modifier = $GAMEDATA['classes'][$class]['offdef_mult'];
		}
		if (isset($GAMEDATA['races'][$race]['offdef_delta'])) {
			$modifier = $modifier + $GAMEDATA['races'][$race]['offdef_delta'];
		}
		return $modifier;
	}
}
