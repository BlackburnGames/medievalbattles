<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$message = mb_input('message');

include("include/session.php");

include("functions.php");

// Was a local gethostname(), which has been a PHP built-in since 5.3 and so
// fatals on redeclaration. Moved to mb_client_hostname() in the compat layer,
// which also removes the clash with the identical copy in common.php.
$hostaddress = mb_client_hostname();
$datestamp = date("d F H:i");
$message = nl2br(strip_tags($message,"<i>,<b>"));
$color1 = "#303030";
$color2 = "#460101";
$color3 = "#303030";
$tablewidth= "80%";
	$guild_id_query = mysqli_query($db, "SELECT gid FROM guild WHERE gname='$empireguild'");	
		$gid = mb_db_result($guild_id_query, "gid");
$empireguild = str_replace(" ", "", $empireguild);
$topicdb = "$empireguild" . "main" . "$gid";
$msgsdb = "$empireguild" . "msgs" . "$gid";
?>