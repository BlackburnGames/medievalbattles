<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$delete  = mb_input('delete');
$delpost = mb_input('delpost');
$mid     = mb_input('mid');
$tid     = mb_input('tid');


// Open buffer
function callback($buffer) {
  return ($buffer);
}
ob_start("callback");

//	delete topic
if(!IsSet($delete))	{

}
else	{	
	include("common.php");
	include("include/connect.php");

	mysqli_query($db, "DELETE FROM setforums WHERE topicid='$tid' AND setid='$setid'"); 
	mysqli_query($db, "DELETE FROM setforumsmsgs WHERE topicid='$tid' AND setid='$setid'"); 
	echo "The post was successfully deleted<br>";
	echo "Click <a href=index.php>here</a> to go back";

	header ("Location: sl-forum.php"); 
}

//	delete post
if(!IsSet($delpost))	{

}
else	{	
	include("common.php");
	include("include/connect.php");

	mysqli_query($db, "DELETE FROM setforumsmsgs WHERE messageid='$mid'"); 

	$mreplies = "SELECT replies FROM setforums WHERE topicid=$tid";
	$replies = mysqli_query($db, $mreplies);
	
	$ureplies = "UPDATE setforums SET replies=$replies-1 WHERE topicid='$tid'";
	mysqli_query($db, $ureplies);

	header ("Location: sl-topic.php?topicid=$tid"); 
}

// Close buffer
ob_end_flush();

?>