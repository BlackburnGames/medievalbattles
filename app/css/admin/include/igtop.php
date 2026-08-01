<?php

// The admin area has its own include root, so it cannot reach the app's
// include/session.php; this is the same bootstrap inlined.
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
	session_start();
}
$login = isset($_SESSION['login']) ? $_SESSION['login'] : '';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$pw    = isset($_SESSION['pw'])    ? $_SESSION['pw']    : '';

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