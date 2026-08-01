<?php
/**
 * Admin login handler.
 *
 * It used to compare the posted credential against a hardcoded pair and, on a
 * match, print "You may continue." with a link to main.php -- no session was
 * written, and nothing on the far side of that link checked anything. The
 * credential is in the environment now and a match is recorded in the session;
 * see include/auth.php.
 */

include("include/auth.php");

/*
 * Request input, formerly supplied by register_globals.
 *
 * The posted password gets a name of its own, and that is the whole story of
 * this file. The 2003 original compared $pw, which register_globals filled in
 * from the form -- and $pw is also what include/session.php restores from the
 * session for every logged-in player. Two different secrets, one name.
 *
 * The port then read $username and missed $pw, so the comparison tested an
 * undefined variable and the form rejected every login from the moment
 * register_globals went away. tests/register-globals-audit.php did not list
 * the miss because session.php assigns a $pw and counts as a shared include,
 * while this file includes nothing except request.php -- the blind spot
 * recorded in docs/modernization.md.
 *
 * Restoring the read is not enough on its own: auth.php pulls in session.php,
 * which sets $pw to '' for a caller who is not logged into the game. Reading
 * the form into $pw after that include would work by accident of ordering and
 * break the next time someone moved a line. Hence $admin_pw.
 */
include("include/request.php");
$admin_user = mb_input('username');
$admin_pw   = mb_input('pw');
$logout     = mb_input('logout');

if (IsSet($logout)) {
	mb_admin_logout();
	header("Location: index.php");
	exit;
}

if (mb_admin_login($admin_user, $admin_pw)) {
	header("Location: main.php");
	exit;
}

header('HTTP/1.1 403 Forbidden');
echo "<html><body bgcolor=white text=black><center><br><br>"
	. "<b>Get away!</b><br><br>"
	. "<a href=\"index.php\">Back</a>"
	. "</center></body></html>";
