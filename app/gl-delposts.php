<?php

// Open buffer
function callback($buffer) {
  return ($buffer);
}
ob_start("callback");

$guild_id = mysqli_query($db, "SELECT gid FROM guild WHERE gname='$empireguild'");	
	$gid = mb_db_result($setgid,"thesgid");

$empireguild = str_replace(" ", "", $empireguild);
$topicdb = "$empireguild" . "main" . "$gid";
$msgsdb = "$empireguild" . "msgs" . "$gid";

//	delete topic
if(!IsSet($delete))	{

}
else	{	
	include("commong.php");
	include("include/connect.php");

	mysqli_query($db, "DELETE FROM guildthreads WHERE topicid='$tid'"); 
	mysqli_query($db, "DELETE FROM guildmsgs WHERE topicid='$tid'"); 

	header ("Location: gl-forum.php"); 
}

//	delete post
if(!IsSet($delpost))	{

}
else	{	
	include("commong.php");
	include("include/connect.php");

	mysqli_query($db, "DELETE FROM guildthreads WHERE topicid='$postid'"); 
	mysqli_query($db, "DELETE FROM guildmsgs WHERE topicid='$postid'"); 

	header ("Location: gl-topic.php"); 
}

// Close buffer
ob_end_flush();

?>