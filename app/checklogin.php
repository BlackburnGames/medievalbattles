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
 * It goes straight into "WHERE email='$email'" below, and this page is reached
 * by an unauthenticated POST -- so it is the one credential site where the
 * value has never been through include/session.php's check. checksignup.php
 * and preferences.php both refuse to store an address this would reject, so
 * refusing it here can only ever reject a login that could not have matched a
 * row anyway.
 */
if (!mb_valid_email($email)) {
	unset($_SESSION['login'], $_SESSION['email'], $_SESSION['pw']);
	echo "Your email or password is incorrect.<br>";
	echo "<a href=index.php>Login again.</a>";
	exit();
}

// One lookup, by address alone. It used to run this query and a second one
// filtering on the digest as well, then compare the two results with ==, which
// is a numeric comparison whenever both strings look numeric -- see
// mb_password_verify().
$result = mb_query($db, "SELECT pw FROM user WHERE email=?", array($email));
$pwcheck = mysqli_fetch_array($result);
$stored = isset($pwcheck[0]) ? $pwcheck[0] : '';

if (mb_password_verify($plain_pw, $stored)) {
	$pw = $stored;

	/*
	 * Upgrade an md5 row in place, before anything else queries with the
	 * credential.
	 *
	 * The hash is denormalized across six tables and is half of every WHERE
	 * clause in the game, so this has to be all six or none -- and $pw has to
	 * become the new value here, or the session carries a token that no longer
	 * matches any row and every page after the redirect renders empty.
	 */
	if (mb_password_needs_upgrade($stored)) {
		$upgraded = mb_password_hash($plain_pw);
		mb_password_store($db, $email, $stored, $upgraded);
		$pw = $upgraded;
	}

	// insert ip address into db
	$ipaddress = $_SERVER["REMOTE_ADDR"];
	$uename = mb_query($db, "SELECT ename FROM user WHERE email=? AND pw=?", array($email, $pw));
	$ename = mb_db_result($uename, "ename");

	// Every value below is bound, so none of them -- the address, the empire
	// name read back out of the database, the client's IP -- is ever part of
	// the SQL text.
	mb_query($db, "UPDATE user SET ip=? WHERE ename=?", array($ipaddress, $ename));
	mb_query($db, "UPDATE user SET countdown='336' WHERE email=? AND pw=?", array($email, $pw));
	mb_query($db, "UPDATE user SET lastlogin=? WHERE ename=?", array($clock, $ename));
	mb_query($db, "UPDATE user SET online='1' WHERE ename=?", array($ename));
	mb_query($db, "UPDATE user SET current_comp_id=? WHERE ename=?", array($computer_id, $ename));

	$user_set_query = mb_query($db, "SELECT setid FROM user WHERE email=? AND pw=?", array($email, $pw));
	$set = mb_db_result($user_set_query, "set");

	mb_query($db, "UPDATE user SET csnum=? WHERE email=?", array((int) $set, $email));
	unset($_SESSION['bad']);
	$login = 1;
	// A fresh session id for the logged-in session; see include/session.php.
	mb_session_login($email, $pw);
	header("Location: main.php?pageid=news");
	exit();
} else {
	unset($_SESSION['login']);
	unset($_SESSION['email']);
	unset($_SESSION['pw']);
	echo "Your email or password is incorrect.<br>";
	echo "<a href=index.php>Login again.</a>";
	exit();
}

// close buffer
// ob_end_flush();

?>
