<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$message = mb_input('message');

include("include/session.php");

include("functions.php");

// Was a local gethostname(), which has been a PHP built-in since 5.3 and so
// fatals on redeclaration. Moved to mb_client_hostname(), which also removes
// the clash with the identical copy in common.php.
include("include/hostname.php");
$hostaddress = mb_client_hostname();
$datestamp = date("d F H:i");
// The body is stored as exactly what was typed. It used to be stored as
// HTML -- nl2br(strip_tags($message,"<i>,<b>")) -- which is what made it
// unescapable at every page that renders it. mb_rich() does the breaks
// and the [b]/[i] tags at render time now; see include/html.php.
$color1 = "#303030";
$color2 = "#460101";
$color3 = "#303030";
$tablewidth= "80%";
// No per-guild table names any more. This file used to build them --
// "<guild>main<gid>" -- out of $empireguild with its spaces stripped, and
// stripped them in $empireguild itself, so every page that included it looked
// a guild with a space in its name up as a guild that does not exist. The
// guild forum has been two shared tables, guildthreads and guildmsgs, for as
// long as this schema has existed.
?>