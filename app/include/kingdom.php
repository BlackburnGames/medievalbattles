<?php
/**
 * What a kingdom starts with, and putting one back to it.
 *
 * checksignup.php used to roll the starting resources and troops inline, and
 * nothing else needed them -- until administrators became player accounts.
 * A new game (admin/newgame.php, and gameconfig.php's account wipe) deletes
 * every other account but keeps the administrators', and it used to keep their
 * kingdoms with them: every table is cleared only of rows whose userid is
 * gone, so an admin went into the next round with the last one's gold, land,
 * research, army and explorers still out, while everyone else started over.
 *
 * So signup and the reset share one definition of a fresh kingdom. Signup
 * calls mb_kingdom_start() and mb_kingdom_seed(); mb_kingdom_reset() calls the
 * same two on a kingdom that already exists. A change to what an empire
 * starts with is made here, once, and both agree.
 *
 * This is the interim fix. docs/rounds.md is the real one: permanent accounts,
 * and a new round as a new world rather than a wipe of the old. The accounts
 * are permanent now -- include/accounts.php -- so a wipe deletes kingdoms
 * and leaves every login standing.
 */

require_once __DIR__ . '/db.php';
// mb_account_admin_ids(): who an administrator is is the account's to say.
require_once __DIR__ . '/accounts.php';

if (!function_exists('mb_kingdom_key')) {
	/**
	 * A new kingdom's key: what its six `pw` columns hold, and what the
	 * session carries as $pw.
	 *
	 * Those columns used to hold the password hash, which made the hash the
	 * bearer token for every "WHERE email=? AND pw=?" in the game and put a
	 * copy of it in six tables. The password is the account's now, in one row
	 * of the accounts database, and the round holds a token that means only
	 * "this kingdom". 64 lowercase hex characters, which is exactly what
	 * include/credentials.php's mb_valid_kingdom_key() lets through.
	 *
	 * @return string
	 */
	function mb_kingdom_key()
	{
		return bin2hex(random_bytes(32));
	}
}

if (!function_exists('mb_kingdom_find')) {
	/**
	 * The kingdom this account plays in this round, or null.
	 *
	 * By account id, not by address. The round keeps a copy of the address in
	 * its six tables for the game's "email=? AND pw=?" queries, and that copy
	 * is the kingdom's, not the account's: a round that has ended keeps the
	 * address it had, whatever the account has moved to since.
	 *
	 * @return array|null  userid, email -- the round's copy -- and pw, the key
	 */
	function mb_kingdom_find($db, $account_id)
	{
		$r = mb_query($db, "SELECT userid, email, pw FROM user WHERE account_id=?", array((int) $account_id));
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
	 * Everything but the user row, which signup inserts and a reset updates.
	 * `email` and `pw` -- the kingdom key -- are written to each, because the
	 * game's queries filter every one of the six tables on the pair.
	 *
	 * @param array $start  what mb_kingdom_start() returned
	 */
	function mb_kingdom_seed($db, $userid, $email, $pw, array $start)
	{
		$userid = (int) $userid;
		$m = $start['military'];
		$r = $start['research'];

		mb_query($db, "INSERT INTO buildings (email, pw, home, barrack, farm, wp, gm, im, aland, amts, userid)
			VALUES (?, ?, '50', '50', '50', '0', '50', '50', '100', '100', ?)", array($email, $pw, $userid));

		mb_query($db, "INSERT INTO military (email, pw, civ, recruits, warriors, wizards, priests, maxciv, userid, warpower, warspeedw, cweapon, wizpower, wizspeeds, cspell, pripower, prispeedw, cstaff, cbow, archspeedw, archpower, wararmor, wizarmor, priarmor, wardef, wizdef, pridef, warspeeda, wizspeeda, prispeeda, archers, suicide)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '2', '6','Dagger', '3', '4', 'Magic Missile', '2', '4', 'Quarterstaff', 'Bow', '4', '2', 'Studded Leather', 'Robe', 'Leather', '1', '1', '2', '0', '0', '1', ?, ?)",
			array($email, $pw, $m['civ'], $m['recruits'], $m['warriors'], $m['wizards'], $m['priests'], $m['maxciv'], $userid, $m['archers'], $m['suicide']));

		mb_query($db, "INSERT INTO research (email, pw, userid, r1pts, r13pts, r14pts)
			VALUES (?, ?, ?, ?, ?, ?)", array($email, $pw, $userid, $r['r1pts'], $r['r13pts'], $r['r14pts']));

		mb_query($db, "INSERT INTO explore (email, pw, userid)
			VALUES (?, ?, ?)", array($email, $pw, $userid));

		mb_query($db, "INSERT INTO returntbl (email, pw, userid) VALUES (?, ?, ?)", array($email, $pw, $userid));
	}
}

if (!function_exists('mb_kingdom_reset')) {
	/**
	 * Put an existing kingdom back to what signup would have given it.
	 *
	 * Keeps who the kingdom is -- its account, email, kingdom key, empire name, race, class,
	 * contact fields, computer ids -- and its settlement, which
	 * the caller recounts. Everything the game changes is returned to its
	 * column default and then to a fresh roll for the kingdom's race and
	 * class. Its child rows are deleted and re-seeded rather than updated, so
	 * a column signup never wrote cannot keep last round's value.
	 *
	 * @return bool  false if no kingdom has that userid
	 */
	function mb_kingdom_reset($db, $userid)
	{
		$userid = (int) $userid;
		$row = mysqli_fetch_assoc(mb_query($db,
			"SELECT email, pw, race, class FROM user WHERE userid=?", array($userid)));
		if (!$row) {
			return false;
		}

		// Written out, not looped over: a table name cannot be bound, and the
		// unbound-queries audit is right to count one interpolated.
		mb_query($db, "DELETE FROM buildings WHERE userid=?", array($userid));
		mb_query($db, "DELETE FROM military WHERE userid=?", array($userid));
		mb_query($db, "DELETE FROM research WHERE userid=?", array($userid));
		mb_query($db, "DELETE FROM explore WHERE userid=?", array($userid));
		mb_query($db, "DELETE FROM returntbl WHERE userid=?", array($userid));
		mb_query($db, "DELETE FROM barter WHERE userid=?", array($userid));
		mb_query($db, "DELETE FROM empnews WHERE yourid=?", array($userid));

		// Every column the game writes during a round. Not setid or csnum: the
		// kingdom stays where it is, and the caller recounts the settlements.
		mb_query($db, "UPDATE user SET
			gp=DEFAULT, iron=DEFAULT, lumber=DEFAULT, exp=DEFAULT, food=DEFAULT,
			land=DEFAULT, mts=DEFAULT, online=DEFAULT, sl=DEFAULT, vote=DEFAULT,
			votefor=DEFAULT, exp2=DEFAULT, mno=DEFAULT, nno=DEFAULT, fleets=DEFAULT,
			safemode=DEFAULT, countdown=DEFAULT, guild=DEFAULT, barterclock=DEFAULT
			WHERE userid=?", array($userid));

		$start = mb_kingdom_start($row['race'], $row['class']);
		$u = $start['user'];
		mb_query($db, "UPDATE user SET gp=?, iron=?, exp=?, food=?, land=?, mts=?, safemode=? WHERE userid=?",
			array($u['gp'], $u['iron'], $u['exp'], $u['food'], $u['land'], $u['mts'], $u['safemode'], $userid));

		mb_kingdom_seed($db, $userid, $row['email'], $row['pw'], $start);
		return true;
	}
}

if (!function_exists('mb_kingdom_reset_admins')) {
	/**
	 * Reset every administrator's kingdom, for the two world wipes.
	 *
	 * After a wipe the administrators' are the only kingdoms left, so this is
	 * every kingdom there is. It is still scoped to the flag, so that running
	 * it on a world nobody has wiped cannot touch a player.
	 *
	 * @return int  how many were reset
	 */
	function mb_kingdom_reset_admins($db)
	{
		$admins = mb_account_admin_ids();
		if (!$admins) {
			return 0;
		}
		$ids = array();
		$marks = implode(',', array_fill(0, count($admins), '?'));
		$r = mb_query($db, "SELECT userid FROM user WHERE account_id IN ($marks)", $admins); // mb-unbound-ok: one placeholder per account
		while ($row = mysqli_fetch_row($r)) {
			$ids[] = (int) $row[0];
		}
		foreach ($ids as $id) {
			mb_kingdom_reset($db, $id);
		}
		return count($ids);
	}
}

if (!function_exists('mb_kingdom_delete_players')) {
	/**
	 * Delete every kingdom but the administrators', for the two world wipes.
	 *
	 * Only the `user` rows; the wipes then clear every other table of rows
	 * whose userid is gone. The accounts are kept -- they are not the round's
	 * to delete -- so a player whose kingdom went comes back through signup
	 * with the same address and password, and gets a new one.
	 *
	 * @return void
	 */
	function mb_kingdom_delete_players($db)
	{
		$admins = mb_account_admin_ids();
		if (!$admins) {
			mb_query($db, "DELETE FROM user");
			return;
		}
		$marks = implode(',', array_fill(0, count($admins), '?'));
		mb_query($db, "DELETE FROM user WHERE account_id NOT IN ($marks)", $admins); // mb-unbound-ok: one placeholder per account
	}
}
