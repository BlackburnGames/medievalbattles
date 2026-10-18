<?php

// Not a bare session_start(): include/session.php sets the cookie and id rules
// first, and refuses a session to a login another site submitted.
include("include/session.php");

function callback($buffer) {
  return (preg_replace("nothing", "nothing", $buffer));
}
// ob_start("callback");

$email = $_POST['email'];
// The browser id index.php hands out, which the admin area lists accounts by
// to spot one player running several. Interpolated below and read nowhere --
// it was a register_globals read of the cookie -- so current_comp_id had been
// recorded blank on every login since the shim went. The client sets it, so it
// is held to the integer index.php issues.
$computer_id = isset($_COOKIE['computer_id']) ? (string) (int) $_COOKIE['computer_id'] : '';
// The password as typed. It used to be md5()d on this line and never seen
// again, because the digest was both the thing compared and the thing stored;
// with a salted hash the comparison needs the plaintext, and the value that
// goes in the session is whatever the database already holds.
$plain_pw = isset($_POST['pw']) ? $_POST['pw'] : '';
$login = $_POST['login'];

include("include/connect.php");
include("include/clock.php");
// mb_valid_email(), the same rule checksignup.php and preferences.php apply.
require_once __DIR__ . '/include/credentials.php';

/*
 * The address is checked before it is used, not after.
 *
 * It is bound into the account lookup below, so this is not about SQL any
 * more; it is that checksignup.php and preferences.php both refuse to store an
 * address this would reject, so refusing it here can only ever reject a login
 * that could not have matched an account anyway, and costs one query less.
 */
if (!mb_valid_email($email)) {
	unset($_SESSION['login'], $_SESSION['userid'], $_SESSION['mb_account'], $_SESSION['mb_account_fp']);
	echo "Your email or password is incorrect.<br>";
	echo "<a href=index.php>Login again.</a>";
	exit();
}

// One lookup, by address alone, and in the `account` table: the password is
// the account's, not the kingdom's. It used to run this query and a second one
// filtering on the digest as well, then compare the two results with ==, which
// is a numeric comparison whenever both strings look numeric -- see
// mb_password_verify().
$account = mb_account_find($email);
$stored = $account ? $account['pw'] : '';

if ($account && mb_password_verify($plain_pw, $stored)) {
	/*
	 * Upgrade an md5 account in place. One row, in the `account` table; the
	 * round never held a copy of the hash after this change, so nothing in it
	 * moves.
	 */
	if (mb_password_needs_upgrade($stored)) {
		$stored = mb_password_hash($plain_pw);
		mb_password_store($account['account_id'], $stored);
		$account['pw'] = $stored;
	}

	/*
	 * The kingdom this account plays in the round, found by account id. Its
	 * userid is what the session carries, and what every page's
	 * "WHERE userid=?" matches.
	 *
	 * There may be none. Accounts outlast kingdoms -- the tick deletes an
	 * inactive one, an administrator can delete one, and a new game deletes
	 * every player's -- and the way back is signing up again with the same
	 * address and password, which makes this account a new kingdom.
	 */
	require_once __DIR__ . '/include/kingdom.php';
	$kingdom = mb_kingdom_find($db, $account['account_id'], (int) mb_round_current($db));
	if (!$kingdom) {
		unset($_SESSION['login'], $_SESSION['userid'], $_SESSION['mb_account'], $_SESSION['mb_account_fp']);
		echo "Your account has no kingdom in this round.<br>";
		echo "<a href=index.php?page=signup>Sign up</a> with the same email and password to start one.";
		exit();
	}
	$userid = $kingdom['userid'];

	// insert ip address into db
	$ipaddress = $_SERVER["REMOTE_ADDR"];

	// Every value below is bound, so none of them -- the client's IP, the
	// computer id it claims -- is ever part of the SQL text.
	mb_query($db, "UPDATE user SET ip=? WHERE userid=?", array($ipaddress, $userid));
	mb_query($db, "UPDATE user SET countdown='336' WHERE userid=?", array($userid));
	mb_query($db, "UPDATE user SET lastlogin=? WHERE userid=?", array($clock, $userid));
	mb_query($db, "UPDATE user SET online='1' WHERE userid=?", array($userid));
	mb_query($db, "UPDATE user SET current_comp_id=? WHERE userid=?", array($computer_id, $userid));

	$user_set_query = mb_query($db, "SELECT setid FROM user WHERE userid=?", array($userid));
	$set = mb_db_result($user_set_query, "set");

	mb_query($db, "UPDATE user SET csnum=? WHERE userid=?", array((int) $set, $userid));
	unset($_SESSION['bad']);
	$login = 1;
	// A fresh session id for the logged-in session; see include/session.php.
	mb_session_login($account, $userid);
	header("Location: main.php?pageid=news");
	exit();
} else {
	unset($_SESSION['login'], $_SESSION['userid'], $_SESSION['mb_account'], $_SESSION['mb_account_fp']);
	echo "Your email or password is incorrect.<br>";
	echo "<a href=index.php>Login again.</a>";
	exit();
}

// close buffer
// ob_end_flush();

?>
