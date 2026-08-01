<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$username = mb_input('username');
// The port added $username and missed $pw, so the comparison below has been
// testing an undefined variable against "quickshot" and the form has rejected
// every login since register_globals was switched off. The audit did not list
// it: include/session.php assigns a $pw and counts as a shared include, so the
// read looked satisfied -- but this file includes nothing except request.php.
// See docs/modernization.md.
$pw       = mb_input('pw');


function callback($buffer) {
  return ($buffer);
}
ob_start("callback");


if(($username == "mako") AND ($pw == "quickshot"))	{
	echo "<a href=main.php>You may continue.</a>";
	die();

}
else	{
	echo "Get away!";
	die();
}

// close buffer
ob_end_flush();

?>	