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
// How many wrong passwords an address or an IP may try: include/throttle.php.
require_once __DIR__ . '/include/throttle.php';
$mb_ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

// Before the password is looked at, so a guesser past the limit learns
// nothing from the answer -- not even whether the guess was right.
if (mb_login_throttled($db, $email, $mb_ip)) {
	unset($_SESSION['login'], $_SESSION['userid'], $_SESSION['mb_account'], $_SESSION['mb_account_fp']);
	echo "Too many failed logins. Wait " . (int) MB_LOGIN_WINDOW . " minutes and try again.<br>";
	echo "<a href=index.php>Login again.</a>";
	exit();
}

/*
 * The address is checked before it is used, not after.
 *
 * It is bound into the account lookup below, so this is not about SQL any
 * more; it is that checksignup.php and preferences.php both refuse to store an
 * address this would reject, so refusing it here can only ever reject a login
 * that could not have matched an account anyway, and costs one query less.
 */
if (!mb_valid_email($email)) {
	mb_login_failed($db, $email, $mb_ip);
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
	mb_login_succeeded($db, $email);
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
	 * There may be none. Accounts outlast kingdoms -- a round ends, the tick
	 * deletes an inactive one, an administrator deletes one -- and an account
	 * may never have joined this round. It is logged in all the same, with no
	 * kingdom, and sent to the round list to join one or read its history
	 * (docs/rounds.md, "The player's path").
	 */
	require_once __DIR__ . '/include/kingdom.php';
	$kingdom = mb_kingdom_find($db, $account['account_id'], (int) mb_round_current($db));
	if (!$kingdom) {
		unset($_SESSION['bad']);
		mb_session_login($account, 0);
		header("Location: rounds.php");
		exit();
	}
	$userid = $kingdom['userid'];

	// Every value is bound, so none of them -- the client's IP, the computer
	// id it claims -- is ever part of the SQL text.
	mb_kingdom_enter($db, $userid, $_SERVER["REMOTE_ADDR"], $computer_id);

	unset($_SESSION['bad']);
	$login = 1;
	// A fresh session id for the logged-in session; see include/session.php.
	mb_session_login($account, $userid);
	header("Location: main.php?pageid=news");
	exit();
} else {
	mb_login_failed($db, $email, $mb_ip);
	unset($_SESSION['login'], $_SESSION['userid'], $_SESSION['mb_account'], $_SESSION['mb_account_fp']);
	echo "Your email or password is incorrect.<br>";
	echo "<a href=index.php>Login again.</a>";
	exit();
}

// close buffer
// ob_end_flush();

?>
