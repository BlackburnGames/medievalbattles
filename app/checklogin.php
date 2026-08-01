<?php

session_start();

function callback($buffer) {
  return (preg_replace("nothing", "nothing", $buffer));
}
// ob_start("callback");

$email = $_POST['email'];
$pw = md5($_POST['pw']);
$login = $_POST['login'];

include("include/connect.php");
include("include/clock.php");

$uename = $db->query("SELECT ename FROM user WHERE email='$email' AND pw='$pw'");
$ename = mb_db_result($uename, "ename");

// check user
$query = "SELECT pw FROM user WHERE email='$email'";
$result = $db->query($query);
$pwcheck = mysqli_fetch_array($result);

if ($pwcheck[0] == $pw)	{
	// insert ip address into db
	$ipaddress = $_SERVER["REMOTE_ADDR"];
	$db->query("UPDATE user SET ip='$ipaddress' WHERE ename='$ename'");
	$db->query("UPDATE user SET countdown='336' WHERE email='$email' AND pw='$pw'");
	$db->query("UPDATE user SET lastlogin='$clock' WHERE ename='$ename'");
	$db->query("UPDATE user SET online='1' WHERE ename='$ename'");
	$db->query("UPDATE user SET current_comp_id='$computer_id' WHERE ename='$ename'");

	$user_set_query = $db->query("SELECT setid FROM user WHERE email='$email' AND pw='$pw'");
	$set = mb_db_result($user_set_query, "set");

	$db->query("UPDATE user SET csnum='$set' WHERE email='$email'");
	unset($_SESSION['bad']);
	$login = 1;
	$_SESSION['login'] = $login;
	$_SESSION['email'] = $email;
	$_SESSION['pw'] = $pw;
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