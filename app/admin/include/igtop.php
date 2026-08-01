<?php

// The gate, before any output and before connect.php. Every page that includes
// this file is behind it; gameconfig.php and newgame.php do not include it and
// call mb_admin_require() for themselves.
//
// auth.php pulls in the app's include/session.php, which is what the bootstrap
// inlined here used to duplicate -- the admin area has its own include root but
// a __DIR__-relative require reaches the real one fine.
include("auth.php");
mb_admin_require();

function callback($buffer) {
	return ($buffer);
}

ob_start("callback");

include("connect.php");

echo "
<html>
<head>
<title>MB Administration</title>
</head>

<body bgcolor=white text=black alink=black link=black vlink=black>";
	include("ignavbar.php");

ob_end_flush();

?>
