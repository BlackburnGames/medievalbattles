<?php
/**
 * The barter board: list a lot, buy someone else's, cancel your own.
 *
 * Two pages use this -- barter.php for the open market and guildbarter.php for
 * the guild-only one. They were separate 430-line files that were copies of
 * each other, and they had drifted:
 *
 *   - guildbarter.php checked `$archer` where it meant `$archers`, so the
 *     guild board never verified you owned the archers you were selling.
 *   - Neither buy handler scoped its lookup to its own board, so a guild
 *     listing's id passed to barter.php sold it to anyone, skipping the
 *     same-guild check that is the entire point of the guild board.
 *   - include/guild_barter.php's Barter and End links both pointed at
 *     barter.php.
 *
 * They are one file now, switched on $barter_board, which the two pages set
 * before including this. Everything that differs between the boards is
 * collected at the top rather than spread through the body.
 *
 * The request reads stay in here on purpose. Moving them out to the two
 * wrappers would have hidden this file's queries from
 * tests/sql-injection-audit.php, which is per-file: a parameter read in one
 * file and interpolated in another is invisible to it, so an include is an
 * easy way to launder a page off the ratchet without fixing anything. Source
 * and sink stay together so the audit keeps checking them.
 */

// Request input, formerly supplied by register_globals.
include("include/request.php");
$add    = mb_input('add');
$amount = mb_input('amount');
$barter = mb_input('barter');
$bid    = mb_input('bid');
$cost   = mb_input('cost');
$end    = mb_input('end');
$method = mb_input('method');
$type   = mb_input('type');

/*
 * Which board, from a constant rather than a variable the caller set.
 *
 * A `$barter_board` global would work, and tests/register-globals-audit.php
 * would be right to flag it: it reports a name whose first appearance in a
 * file is a read, which is exactly what "the page that included me set this"
 * looks like, and telling that apart from a value that arrived from the
 * request is the whole point of the audit. A constant is not a variable, so
 * there is nothing there for register_globals to have injected.
 */
$mb_guild_board = (defined('MB_BARTER_BOARD') && MB_BARTER_BOARD === 'guild');
$mb_self        = $mb_guild_board ? 'guildbarter.php'          : 'barter.php';
$mb_panel       = $mb_guild_board ? 'include/guild_barter.php' : 'include/S_BARTER.php';

/**
 * What may be traded, and where each type is counted.
 *
 * Three things came out of one table here. The valid-type list was a ten-term
 * `$type != "Explorer" AND $type != "Scientist" AND ...` chain on the add
 * branch and simply absent on the buy branch -- a listing with an unrecognised
 * type fell through all ten `if`s, so it cost the buyer nothing and was still
 * deleted and announced as a sale.
 *
 * The rest is the stock movement. Buying adds, listing subtracts and
 * cancelling adds back, which was written out as three ten-way switches per
 * page: sixty near-identical UPDATE statements across the pair, each one its
 * own chance to interpolate something unescaped.
 *
 * Each entry is a list of (table, column, the global holding the current
 * count), and the FIRST slot is the one that gates a sale -- what you have
 * available, not what you own outright. Land and Mountain have two slots
 * because both are counted in `user` and again as "available" in `buildings`,
 * and it is the available figure you may sell from. Explorer reads
 * $aexplorers but writes `explorers` for the same reason: explorers away
 * surveying are not yours to trade.
 */
$MB_BARTER_STOCK = array(
	'Archer'    => array(array('military',  'archers',    'archers')),
	'Explorer'  => array(array('military',  'explorers',  'aexplorers')),
	'Land'      => array(array('buildings', 'aland',      'aland'), array('user', 'land', 'land')),
	'Mountain'  => array(array('buildings', 'amts',       'amts'),  array('user', 'mts',  'mts')),
	'Priest'    => array(array('military',  'priests',    'priests')),
	'Recruit'   => array(array('military',  'recruits',   'recruits')),
//	The dropdown offered a "Scientist" and both handlers moved
//	military.scientists, gated on $scientists. The game renamed that unit: it
//	is a Sage everywhere else -- functions.php reads military.sages into
//	$sages, nexplode.php un-formats $sages, and research.php, disband.php and
//	the manual all say Sage. $scientists has been unset for the life of this
//	code, so the sale was gated on 0 and could never be listed, and a bought
//	one landed in a column nothing reads. `military` still has both.
	'Sage'      => array(array('military',  'sages',      'sages')),
	'Thief'     => array(array('military',  'thieves',    'thieves')),
	'Warrior'   => array(array('military',  'warriors',   'warriors')),
	'Wizard'    => array(array('military',  'wizards',    'wizards')),
);
$MB_BARTER_TYPES = array_keys($MB_BARTER_STOCK);

if (!function_exists('mb_barter_move_stock')) {
	/**
	 * Add $delta units of $type to the logged-in player's holdings.
	 *
	 * Pass a negative $delta to take them away. Does nothing for a type that is
	 * not tradeable, which is the same thing the ten-way switches did.
	 *
	 * The counts are read from $GLOBALS because that is where functions.php
	 * puts them and where include/nexplode.php strips the thousands separators
	 * that functions.php added -- passing a dozen of them in by hand would move
	 * the coupling rather than remove it. Callers must have included
	 * nexplode.php first, or "1,200" arrives here and casts to 1.
	 *
	 * @param mysqli $db
	 * @param string $type  one of $MB_BARTER_STOCK's keys
	 * @param int    $delta signed change
	 */
	function mb_barter_move_stock($db, $type, $delta)
	{
		global $MB_BARTER_STOCK, $email, $pw;

		if (!isset($MB_BARTER_STOCK[$type])) {
			return;
		}

		$where = " WHERE email=" . mb_sql_str($db, $email) . " AND pw=" . mb_sql_str($db, $pw);

		foreach ($MB_BARTER_STOCK[$type] as $slot) {
			list($table, $column, $global) = $slot;

			$value = isset($GLOBALS[$global]) ? $GLOBALS[$global] + $delta : $delta;
			$GLOBALS[$global] = $value;

			mysqli_query($db, "UPDATE $table SET $column=" . mb_sql_int($value) . $where);
		}
	}
}

include("include/igtop.php");

/*
 * The 2003 shutdown notice stood here -- an echo and a die() above every other
 * statement in barter.php, so nothing below it had run since. guildbarter.php
 * carried its own copy, announcing that "there will not be a guild barter in
 * Version 6". Both are gone.
 */

if ($mb_guild_board && $empireguild == 'None') {
	echo "<div align=center><font class=yellow><b>You must be the member of a guild to use this barter!</b></font></div>";
	die();
}

if($barter_clock > 1)	 {
	echo "<font class=yellow><div align=center><b>You can barter items when your empires age is over 1 week!<br>You have $barter_clock tick(s) left!</b></div></font>";
	die();
}

echo "<center> <b class=reg> | <a href=barter.php> -Game- </a> | <a href=guildbarter.php> -Guild- </a> | </b></center><br>";

// ---------------------------------------------------------------------------
// Buy someone else's listing.
// ---------------------------------------------------------------------------

if(!IsSet($barter))	{
	include($mb_panel);
}
else	{
	include("include/nexplode.php");

	// One lookup, scoped to this board.
	//
	// It used to be two queries -- an existence check, then the same row again
	// -- and neither said which board the id belonged to. S_BARTER.php lists
	// only `page != 'guild'` and guild_barter.php only `page = 'guild'`, but
	// both buy handlers matched on barterid alone. Each page sells only what
	// it lists now.
	$q_bid  = mb_sql_int($bid);
	$mb_scope = $mb_guild_board ? "page='guild'" : "page != 'guild'";

	$barter_query = mysqli_query($db, "SELECT * FROM barter WHERE barterid=$q_bid AND $mb_scope");
	$barter_row   = mysqli_fetch_array($barter_query);

	if(!$barter_row)	 {
		echo"<font class=yellow><div align=center>There is no such barter.</font></div>";
		include($mb_panel);
		die();
	}
		$b_cost   = $barter_row['cost'];
		$b_type   = $barter_row['type'];
		$b_meth   = $barter_row['method'];
		$b_amount = $barter_row['amount'];
		$b_seller = $barter_row['seller'];
		$b_userid = $barter_row['userid'];
		$b_guild  = $barter_row['guild'];

	$q_b_userid = mb_sql_int($b_userid);

	$barter_user_query = mysqli_query($db, "SELECT * FROM user WHERE userid=$q_b_userid");
	$barter_user = mysqli_fetch_array($barter_user_query);
		$tsetid     = $barter_user['setid'];
		$barts_gp   = $barter_user['gp'];
		$barts_iron = $barter_user['iron'];
		$b_nno      = $barter_user['nno'];

	if($b_userid == $userid)	 {	echo"<font class=yellow><div align=center>You can't buy your own barter items!</div></font><br><br>";	die();	 }
	elseif($mb_guild_board AND $b_guild != $empireguild)	 {	echo"<font class=yellow><div align=center>They are not in your guild!</div></font><br><br>";	 die();	}
//	Neither of these two was checked at all. A row whose type matched no branch
//	transferred nothing and was still paid for, deleted and announced.
	elseif(!in_array($b_type, $MB_BARTER_TYPES, true))	{	echo"<font class=yellow><div align=center>Invalid type!</div></font><br><br>";	die();	 }
	elseif($b_meth != 'gp' AND $b_meth != 'iron')	{	echo"<font class=yellow><div align=center>Invalid method!</div></font><br><br>";	die();	 }
	elseif($gp < $b_cost AND $b_meth == 'gp')	{	echo"<font class=yellow><div align=center>Not enough gold!</div></font><br><br>";	die();	 }
	elseif($iron < $b_cost AND $b_meth == 'iron')	 {	echo"<font class=yellow><div align=center>Not enough iron!</div></font><br><br>";	die();	 }
	elseif($race == 'Giant' AND $b_type == 'Wizard')	{	echo"<font class=yellow><div align=center>Giants can't possess Wizards!</div></font><br><br>";	 die();	}
	elseif($race == 'Giant'  AND $b_type == 'Priest')	{	echo"<font class=yellow><div align=center>Giants can't possess Priests</div></font><br><br>";	die();	 }
	elseif($class == 'Ranger' AND $b_type == 'Wizard')	{	echo"<font class=yellow><div align=center>Rangers can't possess Wizards!</div></font><br><br>";	die();	 }
	elseif($res['r13pts'] < 125000 AND $b_type == 'Archer')	{echo"<font class=yellow><div align=center>You must research Archery for Archers.<br><br></div></font>";	die();	 }

	// Hand the units over, then take the payment. The payment used to sit
	// inside each of the ten unit branches -- forty UPDATEs that differed only
	// in which `if` they were written in. $b_type matches at most one, so the
	// copies were mutually exclusive and this does the same work once.
	mb_barter_move_stock($db, $b_type, $b_amount);

	$q_where_me = " WHERE email=" . mb_sql_str($db, $email) . " AND pw=" . mb_sql_str($db, $pw);

	if($b_meth == 'gp')	{
		$B_NEW_gp = $barts_gp + $b_cost;
		$newgp    = $gp - $b_cost;
		mysqli_query($db, "UPDATE user SET gp=" . mb_sql_int($newgp) . $q_where_me);
		mysqli_query($db, "UPDATE user SET gp=" . mb_sql_int($B_NEW_gp) . " WHERE userid=$q_b_userid");
	}
	if($b_meth == 'iron')	{
		$B_NEW_iron = $barts_iron + $b_cost;
		$newiron    = $iron - $b_cost;
		mysqli_query($db, "UPDATE user SET iron=" . mb_sql_int($newiron) . $q_where_me);
		mysqli_query($db, "UPDATE user SET iron=" . mb_sql_int($B_NEW_iron) . " WHERE userid=$q_b_userid");
	}

	// The news lines are built from stored columns -- the seller's empire name
	// and settlement id, and the type and method they picked when they listed
	// -- so the first-order audit cannot see them. They still land in an INSERT.
	$b_amount = number_format($b_amount);
	$c_cost   = number_format($b_cost);

	// $b_method never existed: the seller's line read "for $b_cost $b_meth
	// $b_method" and ended in a stray space, and it quoted a different number
	// from the buyer's copy because $c_cost was computed here and never used.
	$their_news = "<font class=yellow>$ename ($setid) has bought $b_amount $b_type(s) for $c_cost $b_meth</font>";
	$your_news  = "<font class=yellow>You have bought $b_amount $b_type(s) for $c_cost $b_meth from $b_seller ($tsetid)</font>";

	mysqli_query($db, "INSERT INTO empnews (date, news, yourid)	VALUES	('$clock', " . mb_sql_str($db, $their_news) . ", $q_b_userid) ");
	mysqli_query($db, "INSERT INTO empnews (date, news, yourid)	VALUES	('$clock', " . mb_sql_str($db, $your_news)  . ", '$userid') ");

	$your_new_nno = $nno + 1;
	$their_new    = $b_nno + 1;
	mysqli_query($db, "DELETE FROM barter WHERE barterid=$q_bid");
	mysqli_query($db, "UPDATE user SET nno=" . mb_sql_int($their_new) . " WHERE userid=$q_b_userid");
	mysqli_query($db, "UPDATE user SET nno=" . mb_sql_int($your_new_nno) . " WHERE userid='$userid'");
	echo"<font class=yellow><div align=center>You have bartered with $b_seller.<br><br></font></div>";
	include($mb_panel);
}

// ---------------------------------------------------------------------------
// List something of your own.
// ---------------------------------------------------------------------------

if(!IsSet($add))	{
	echo "
<form type=get action=\"$mb_self\">
	<table border=0 width=\"60%\" align=center>
	 <tr>
	   <td class=main colspan=4><b class=reg>Add</b></td>
	<tr>
	   <td class=main2><b class=reg>Amount:</b></td><td class=inner><center><input type=\"number\" name=\"amount\" size=15></td>
		<td class=inner2>
		<select name=\"type\">
	    <option selected value=\"ns\">-Choose type-</option>";

	foreach ($MB_BARTER_TYPES as $mb_option) {
		echo "\n\t\t<option value=\"$mb_option\">$mb_option</option>";
	}

	echo "
		</select></td>
	<tr>
	   <td class=main2><b class=reg>Payment Cost:</b></td><td class=inner><center><input type=\"number\" name=\"cost\" size=15></td>
		<td class=inner2>
	    <select name=method>
		<option selected value=ns>-Choose method-</option>
		<option value=gp>Gold Pieces</option>
		<option value=iron>Iron</option>
		</select></td>

	 </table>
	  <br>
		<center><input class=button type=submit name=add value=Add></center>
		<input type=\"hidden\" name=\"add\" value=\"1\">
	</form>
";
}
else	{

	include("include/nexplode.php");

	$bresult = mysqli_query($db, "SELECT count(userid) FROM barter WHERE userid='$userid'");
	$bcheck = mysqli_fetch_array($bresult);

	$cost = implode("", explode(",", $cost));
	$amount = implode("", explode(",", $amount));

	// The stock the seller is offering from. Each type has one number that
	// gates it, and the two checks below -- "you don't have that many" and
	// "invalid number" -- were twenty more elseif lines reading it by hand.
	$mb_have = 0;
	if (isset($MB_BARTER_STOCK[$type])) {
		list($mb_t, $mb_c, $mb_g) = $MB_BARTER_STOCK[$type][0];
		$mb_have = isset($GLOBALS[$mb_g]) ? $GLOBALS[$mb_g] : 0;
	}

	// $gp -- the seller's own gold -- stood where $cost belongs, so the "fill
	// every field in" check never looked at the price field at all.
	if($type == 'ns' OR $cost == "" OR $amount == "")	{	echo"<font class=yellow><div align=center>You must have something for each field</font></div><br><br>.";	die();	 }
	elseif($bcheck[0] >= 4)	 {	echo"<font class=yellow><div align=center>You alreay have 4 items up for sale!</div></font>";	 die();	}
//	various race/class checks
	elseif($type == 'Wizard' AND $class == 'Ranger')	{	echo"<font class=yellow><div align=center>Rangers can't possess Wizards!</font></div>";	die();	 }
	elseif($type == 'Wizard' AND $race == 'Giant')	{	echo"<div align=center><font class=yellow>Giants can't possess Wizards!</font></div><br><br>";	 die();	}
	elseif($type == 'Priest' AND $race == 'Giant')	{	echo"<div align=center><font class=yellow>Giants can't possess Priests!</font></div><br><br>";	die();	 }
//	$b_type is the type of the listing being BOUGHT and is not set on this
//	branch, so the archery requirement never fired when listing archers for sale.
	elseif($res['r13pts'] < 125000 AND $type == 'Archer')	{	echo"<font class=yellow><div align=center>You must research Archery for Archers.<br><br></div></font>";	die();	 }
//	various invalid checks
	elseif(!in_array($type, $MB_BARTER_TYPES, true))	 {	echo"<div align=center><font class=yellow>Invalid type!</font></div><br><br>";	die();	 }
	elseif($method != "gp" AND $method != "iron")	{	echo"<div align=center><font class=yellow>Invalid method!</font></div><br><br>";	die();	 }
	elseif($cost  <= 0)	{	echo"<font class=yellow><div align=center>Invalid cost!</font></div><br><br>.";	die();	 }
	elseif($amount <= 0)	 {	echo"<font class=yellow><div align=center>Invalid cost!</font></div><br><br>.";	die();	 }
//	number of unit checks. guildbarter.php's copy of this read $archer, not
//	$archers, so the guild board never checked that you owned the archers you
//	were selling -- and its "invalid number" line had the same hole.
	elseif($mb_have < $amount)	{	echo"<font class=yellow><div align=center>You don't have that many $type(s)!</font></div><br><br>";	die();	 }
	elseif($mb_have <= 0)	{	echo"<font class=yellow><div align=center>Invaild number.</font></div><br><br>";	die();	 }

	$themaxbarterid = mysqli_query($db, "SELECT max(barterid) FROM barter");
	$maxbid = mb_db_result($themaxbarterid,"maxbid");
	$maxbid = $maxbid + 1;

	// $cost and $amount are the two request parameters that reach a query here;
	// $type and $method are checked against the whitelist above but are quoted
	// too, because a validation twenty lines away is not something the next
	// reader should have to go and find.
	$mb_page  = $mb_guild_board ? 'guild' : '';
	$mb_guild = $mb_guild_board ? $empireguild : '';

	mysqli_query($db, "INSERT INTO barter (seller, cost, type, amount, barterid, userid, method, page, guild)	VALUES	("
		. mb_sql_str($db, $ename)  . ", "
		. mb_sql_int($cost)        . ", "
		. mb_sql_str($db, $type)   . ", "
		. mb_sql_int($amount)      . ", "
		. mb_sql_int($maxbid)      . ", '$userid', "
		. mb_sql_str($db, $method) . ", "
		. mb_sql_str($db, $mb_page)  . ", "
		. mb_sql_str($db, $mb_guild) . ") ");

	mb_barter_move_stock($db, $type, -$amount);

	echo "<font class=yellow><div align=center>Your barter has been placed.</font></div><br><br>";
	die();
}

// ---------------------------------------------------------------------------
// Cancel one of your own.
// ---------------------------------------------------------------------------

if(!IsSet($end))	{
}
else	{
	include("include/nexplode.php");

	// Not scoped to this board on purpose: the ownership check below is the one
	// that matters, and a listing is refunded to whoever put it up whichever
	// page they cancel it from.
	$q_bid = mb_sql_int($bid);
	$barter_query = mysqli_query($db, "SELECT * FROM barter WHERE barterid=$q_bid");
	$barter_row = mysqli_fetch_array($barter_query);

	if(!$barter_row)	 {
		echo"<font class=yellow><div align=center>There is no such barter.</font></div>";
		die();
	}
		$b_type   = $barter_row['type'];
		$b_amount = $barter_row['amount'];
		$b_userid = $barter_row['userid'];

	if($b_userid != $userid)	{	echo"<font class=yellow><div align=center>You cannot cancel other empire barters.</font></div><br><br>";	die();	 }

	mb_barter_move_stock($db, $b_type, $b_amount);

	mysqli_query($db, "DELETE FROM barter WHERE barterid=$q_bid");

	echo"<font class=yellow><div align=center>Your barter has been cancelled.</font></div>";
}
?>

</td>
</tr>
</table>
</body>
</html>
