<?php
// Already signed in? Straight through, so the login form is not a dead end
// after a page in the area bounces someone here.
include("include/auth.php");
if (mb_admin_logged_in()) {
	header("Location: main.php");
	exit;
}
?>
<html>
<head>
<title>Medieval Battles</title>
</head>

<body bgcolor=white text=black>
<center>

<!--
	method=post, not `type=post`. `type` is not a form attribute, so this form
	-- like most of the game's -- submitted as GET, which put the admin
	password in the query string and from there into the access log.
-->
<form method=post action=adminlogin.php>
	<font size=3>
	Username: <input type=text name=username maxlength=50 size=30><br>
	Password: <input type=password name=pw maxlength=50 size=30><br><br>
	<input class=button type=submit value=Login>
	</font>
</form>

</body>
</html>
