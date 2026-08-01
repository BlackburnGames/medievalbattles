<?php
function callback($buffer) {
  // replace all the apples with oranges
  return ($buffer);
}

ob_start("callback");

include("include/connect.php");
		
include("include/session.php");

$uename = mysqli_query($db, "SELECT ename FROM user WHERE email='$email' AND pw='$pw'");
	$ename = mb_db_result($uename,"ename");
$usetid = mysqli_query($db, "SELECT setid FROM user WHERE email='$email' AND pw='$pw'");
	$setid = mb_db_result($usetid,"setid");


include("common.php");	
include("include/clock.php");

if($sl == 'yes')	{
	$ename = "$ename". "<font class=red>(SL)</font>";
}


if ($addtopic) {
	$result = mysqli_query($db, "INSERT INTO setforums (setid, name, topic, replies, message, datestamp)	VALUES ('$setid', '$ename', '$topic', '$replies', '$message', '$datestamp')");

	$topic_lastpost = mysqli_query($db, "UPDATE setforums SET lastpost='$clock' WHERE topic='$topic' AND setid='$setid'");
	$topic_lastposter = mysqli_query($db, "UPDATE setforums SET lastposter='$ename' WHERE topic='$topic' AND setid='$setid'");

	header ("Location: sforum.php"); 
}
elseif ($addreply) {
	$query1 = mysqli_query($db, "INSERT INTO setforumsmsgs (setid, name, topic, topicid, message, datestamp)	 VALUES ('$setid', '$ename', '$topic', '$topicid', '$message', '$clock')");

	$result1 = mysqli_query($db, $query1);
	$lastid = mysqli_insert_id($db);	

	$reply_lastpost = mysqli_query($db, "UPDATE setforums SET lastpost='$clock' WHERE topicid='$topicid' AND setid='$setid'");
	$reply_lastposter = mysqli_query($db, "UPDATE setforums SET lastposter='$ename' WHERE topicid='$topicid' AND setid='$setid'");

	header ("Location: topic.php?topicid=$topicid");
}
else {
		echo "Wah! This page has no text! Get off! Hurry!"; }

exit;
ob_end_flush();
?>	