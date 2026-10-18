<?php
/**
 * A kingdom: finding an account's in a round, joining a round to make one,
 * what it starts with, and entering it at login.
 *
 * Signup made the account and its kingdom at once, and rolled the starting
 * resources inline; joining a round makes the kingdom now (docs/rounds.md),
 * through mb_kingdom_join(), and an account signs up once.
 *
 * mb_kingdom_reset() lived here too, for the two world wipes -- admin
 * newgame.php and gameconfig.php's "delete every kingdom" -- which kept the
 * administrators' kingdoms and put them back to a fresh roll. Both wipes are
 * retired: a new start is a new round, from bin/round.php, and every kingdom
 * in it is a new one.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/accounts.php';

if (!function_exists('mb_kingdom_find')) {
	/**
	 * The kingdom this account plays in a round, or null.
	 *
	 * By account id: the round holds no address, and a round that has ended
	 * would otherwise keep whichever one the account had then. And by round,
	 * because an account has a kingdom in every round it played.
	 *
	 * @return array|null  userid
	 */
	function mb_kingdom_find($db, $account_id, $round_id)
	{
		$r = mb_query($db, "SELECT userid FROM user WHERE account_id=? AND round_id=?", array((int) $account_id, (int) $round_id));
		$row = $r ? mysqli_fetch_assoc($r) : null;
		return $row ? $row : null;
	}
}

if (!function_exists('mb_kingdom_start')) {
	/**
	 * Roll a new kingdom's starting values for a race and class.
	 *
	 * Moved unchanged from checksignup.php, dice and all. The gold is two rolls
	 * concatenated as strings, and the second is not zero-padded, so a roll of
	 * 300 and 5 gives 3005 gold rather than 300005 -- that is the 2003 code and
	 * is kept as it was.
	 *
	 * @return array  table => column => value, for user, military and research
	 */
	function mb_kingdom_start($race, $class)
	{
		$part1 = rand(250, 350);
		$part2 = rand(000, 999);

		$gp = "$part1" . "$part2";
		$iron = rand(4000, 6000);
		$civ = rand(1000, 1250);
		$recruits = rand(150, 250);
		$war = rand(1,5);
		$wiz = rand(1,5);
		$pri = rand(1,5);
		$maxciv = rand(100,250);

		if($class == "Cleric")  {
			$part1 = rand(350, 450);
			$part2 = rand(000, 999);

			$gp = "$part1" . "$part2";
			$iron = rand(6000, 7500);
			$civ = rand(1200, 1400);
			$recruits = rand(200, 300);
			$war = rand(4,10);
			$wiz = rand(4,10);
			$pri = rand(4,10);
			$maxciv = rand(250,350);
		}

		// No archers and no Archery unless the race or the class grants them. The
		// Ranger check used to reset Archery with an else, after the Night Elf had
		// been granted it, so a Night Elf of any other class got archers it could
		// not field.
		$arch = 0;
		$r13pts = 0;

		// race advantages and disadvantages
		if($race == 'Giant')   {  $wiz = 0; $pri = 0;  }
		if($race == 'Demon')   {  $pri = 0;  }
		if($race == 'Night Elf')   {  $arch = rand(4, 10);  $r13pts  = 125000;   }

		// class advantages and disadvantages
		if($class == 'Ranger')  { $arch = rand(4, 10);  $r13pts = 125000; $wiz = 0; }
		if($class == 'Warlock') { $r1pts = 50000; }
			else  { $r1pts = 0; }
		if($class == 'Insurrectionist') { $suicide = rand(10, 20);  $r14pts = 125000; $wiz = 0; }
			else  { $suicide  = 0;  $r14pts = 0;  }

		return array(
			'user' => array(
				'gp' => $gp, 'iron' => $iron, 'exp' => 0, 'food' => 1500,
				'land' => 250, 'mts' => 200, 'safemode' => 48,
			),
			'military' => array(
				'civ' => (int) $civ, 'recruits' => (int) $recruits, 'warriors' => (int) $war,
				'wizards' => (int) $wiz, 'priests' => (int) $pri, 'maxciv' => (int) $maxciv,
				'archers' => (int) $arch, 'suicide' => (int) $suicide,
			),
			'research' => array(
				'r1pts' => (int) $r1pts, 'r13pts' => (int) $r13pts, 'r14pts' => (int) $r14pts,
			),
		);
	}
}

if (!function_exists('mb_kingdom_seed')) {
	/**
	 * Write a kingdom's buildings, military, research, explore and return rows.
	 *
	 * Everything but the user row, which signup inserts and a reset updates,
	 * and which must exist first: each row is keyed by userid alone and takes
	 * its round from it. The email and password-hash copies these tables used
	 * to carry are gone.
	 *
	 * @param array $start  what mb_kingdom_start() returned
	 */
	function mb_kingdom_seed($db, $userid, array $start)
	{
		$userid = (int) $userid;
		$m = $start['military'];
		$r = $start['research'];

		mb_query($db, "INSERT INTO buildings (home, barrack, farm, wp, gm, im, aland, amts, userid, round_id)
			SELECT '50', '50', '50', '0', '50', '50', '100', '100', userid, round_id FROM user WHERE userid=?", array($userid));

		mb_query($db, "INSERT INTO military (civ, recruits, warriors, wizards, priests, maxciv, userid, warpower, warspeedw, cweapon, wizpower, wizspeeds, cspell, pripower, prispeedw, cstaff, cbow, archspeedw, archpower, wararmor, wizarmor, priarmor, wardef, wizdef, pridef, warspeeda, wizspeeda, prispeeda, archers, suicide, round_id)
			SELECT ?, ?, ?, ?, ?, ?, userid, '2', '6','Dagger', '3', '4', 'Magic Missile', '2', '4', 'Quarterstaff', 'Bow', '4', '2', 'Studded Leather', 'Robe', 'Leather', '1', '1', '2', '0', '0', '1', ?, ?, round_id FROM user WHERE userid=?",
			array($m['civ'], $m['recruits'], $m['warriors'], $m['wizards'], $m['priests'], $m['maxciv'], $m['archers'], $m['suicide'], $userid));

		mb_query($db, "INSERT INTO research (userid, r1pts, r13pts, r14pts, round_id)
			SELECT userid, ?, ?, ?, round_id FROM user WHERE userid=?", array($r['r1pts'], $r['r13pts'], $r['r14pts'], $userid));

		mb_query($db, "INSERT INTO explore (userid, round_id) SELECT userid, round_id FROM user WHERE userid=?", array($userid));

		mb_query($db, "INSERT INTO returntbl (userid, round_id) SELECT userid, round_id FROM user WHERE userid=?", array($userid));
	}
}

if (!function_exists('mb_kingdom_join')) {
	/**
	 * Make an account a kingdom in a round: what joining a round does.
	 *
	 * This was the second half of checksignup.php, when signing up made the
	 * account and its kingdom at once (docs/rounds.md, "The player's path").
	 * It is unchanged but for being asked of one round, by one account: the
	 * checks, then the settlement with the fewest members (a random one,
	 * unless it is fuller than the emptiest), then signup's starting rolls.
	 *
	 * A kingdom's name is unique within its round, not across rounds, so a
	 * player may keep theirs from one round to the next.
	 *
	 * @param array $k  ename, race, class, msn, aim, ip, computer_id
	 * @return int|string  the new kingdom's userid, or why it was refused
	 */
	function mb_kingdom_join($db, $round_id, $account_id, array $k)
	{
		global $GAMEDATA;
		$round_id = (int) $round_id;
		$ename = strip_tags(trim((string) $k['ename']));
		$race  = (string) $k['race'];
		$class = (string) $k['class'];
		$msn   = strip_tags((string) $k['msn']);
		$aim   = strip_tags((string) $k['aim']);

		$taken = mysqli_fetch_row(mb_query($db, "SELECT ename FROM user WHERE round_id=? AND ename=?", array($round_id, $ename)));
		$players = mysqli_fetch_row(mb_query($db, "SELECT count(userid) FROM user WHERE round_id=?", array($round_id)));
		$same_ip = mysqli_fetch_row(mb_query($db, "SELECT count(userid) FROM user WHERE round_id=? AND ip=?", array($round_id, (string) $k['ip'])));

		if ($taken && strtolower($taken[0]) === strtolower($ename) && $ename !== '') { return "$ename is already being used!"; }
		if ($class === 'ns') { return "You must have a class to play the game!"; }
		if ($race === 'ns') { return "You must have a race to play the game!"; }
		if ($ename === '') { return "You must have an empire name to play the game!"; }
		// include/credentials.php says why: the name is interpolated bare on
		// every page once it is stored, and this is the only place it is written.
		if (!mb_valid_ename($ename)) { return "Empire names may use letters, numbers, spaces and . _ - only, up to 25 characters!"; }
		// The two drop-downs, held to what they offer: both are compared against
		// literals all over the engine.
		if (!isset($GAMEDATA['races'][$race])) { return "That race doesn't exist!"; }
		if (!isset($GAMEDATA['classes'][$class])) { return "That class doesn't exist!"; }
		if ((int) $players[0] >= 450) { return "Game is full! Try again later!"; }
		if ((int) $same_ip[0] >= 1) { return "You are only allowed one account per computer!"; }
		if (mb_kingdom_find($db, $account_id, $round_id)) { return "You already have a kingdom in this round!"; }

		// The settlement: a random one of the round's, or the emptiest if that
		// one is fuller.
		$least_set = mb_db_result(mb_query($db, "SELECT min(members) FROM settlement WHERE round_id=?", array($round_id)), "least_set");
		list($first, $last) = mb_settlement_range($db, $round_id);
		$sel_mem = rand($first, $last);
		$R_Mem = mb_db_result(mb_query($db, "SELECT members FROM settlement WHERE setid=?", array((int) $sel_mem)), "R_Mem");
		if ($least_set != $R_Mem) {
			$sel_mem = mb_db_result(mb_query($db, "SELECT setid FROM settlement WHERE round_id=? AND members=?", array($round_id, (int) $least_set)), "sel_mem");
		}
		mb_query($db, "UPDATE settlement SET members=? WHERE setid=?", array((int) $least_set + 1, (int) $sel_mem));

		$max = mb_db_result(mb_query($db, "SELECT max(userid) FROM user"), "buserid"); // mb-round-ok: ids are issued once across rounds
		$userid = (int) $max + 1;

		// What the empire starts with, as signup always rolled it.
		$start = mb_kingdom_start($race, $class);
		$u = $start['user'];
		// The kingdom joins its settlement's round.
		mb_query($db, "INSERT INTO user (ename, msn, aim, gp, iron, exp, food, land, mts, setid, class, userid, race, safemode, signup_comp_id, account_id, round_id)
			SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, setid, ?, ?, ?, ?, ?, ?, round_id FROM settlement WHERE setid=?",
			array($ename, $msn, $aim, $u['gp'], $u['iron'], $u['exp'], $u['food'], $u['land'], $u['mts'], $class, $userid, $race, $u['safemode'], (string) $k['computer_id'], (int) $account_id, (int) $sel_mem));
		mb_kingdom_seed($db, $userid, $start);

		include __DIR__ . '/clock.php';
		mb_news_set($db, "$clock", 'red', "$ename has joined the settlement", $sel_mem, 0);
		return $userid;
	}
}

if (!function_exists('mb_kingdom_enter')) {
	/**
	 * What logging into a kingdom records: where from, when, that it is
	 * online and active, and that the settlement browser starts at home.
	 * checklogin.php did it inline; joining a round logs the new kingdom in
	 * too, so both call this.
	 *
	 * The address is what the one-kingdom-per-computer check in
	 * mb_kingdom_join() compares.
	 */
	function mb_kingdom_enter($db, $userid, $ip, $computer_id)
	{
		include __DIR__ . '/clock.php';
		mb_query($db, "UPDATE user SET ip=?, countdown='336', lastlogin=?, online='1', current_comp_id=?, csnum=setid WHERE userid=?",
			array((string) $ip, $clock, (string) $computer_id, (int) $userid));
	}
}
