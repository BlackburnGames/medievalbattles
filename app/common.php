<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$message = mb_input('message');

include("include/connect.php");

include("include/session.php");

include("functions.php");

// Was a local gethostname(), which has been a PHP built-in since 5.3 and so
// fatals on redeclaration. Moved to mb_client_hostname() in the compat layer,
// which also removes the clash with the identical copy in commong.php.
$hostaddress = mb_client_hostname();
$message = nl2br(strip_tags($message,"<i>,<b>"));
$color1 = "#303030";
$color2 = "#460101";
$color3 = "#303030";
$tablewidth= "80%";
$topicdb = "setmain" . "$setid";
$msgsdb = "setmsgs" . "$setid";
?>