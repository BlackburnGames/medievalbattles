<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$username = mb_input('username');


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