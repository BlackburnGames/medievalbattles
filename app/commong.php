<?
session_register('login');
session_register('email');
session_register('pw');	

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
	$guild_id_query = mysql_db_query($dbnam, "SELECT gid FROM guild WHERE gname='$empireguild'");	
		$gid = mysql_result($guild_id_query, "gid");
$empireguild = ereg_replace(" ", "", "$empireguild");
$topicdb = "$empireguild" . "main" . "$gid";
$msgsdb = "$empireguild" . "msgs" . "$gid";
?>