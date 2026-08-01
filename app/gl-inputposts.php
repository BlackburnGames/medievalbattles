<?php

function callback($buffer) {
	return ($buffer);
}

ob_start("callback");
 
include("include/connect.php");

include("include/session.php");

$uename = mysqli_query($db, "SELECT ename FROM user WHERE email='$email' AND pw='$pw'");
	$ename = mb_db_result($uename,"ename");

include("commong.php");	
include("include/clock.php");

// are they a gl?
$gresult = mysqli_query($db, "SELECT owner FROM guild WHERE owner='$userid'");
$gnamecheck = mysqli_fetch_array($gresult);
			
if ($addtopic) {
	
	$replies = "0";
	$result = mysqli_query($db, "INSERT INTO guildthreads (name, topic, replies, message, datestamp, guildname) VALUES ('$ename', '$topic', '$replies', '$message', '$clock', '$empireguild')");

	$query4 = "UPDATE guildthreads SET lastpost='$clock' WHERE topic='$topic' AND guildname = '$empireguild'";
	$result4 = mysqli_query($db, $query4);

	$query6 = "UPDATE guildthreads SET lastposter='$ename' WHERE topic='$topic' AND guildname = '$empireguild'";
	$result6 = mysqli_query($db, $query6);
echo $query4 . "<BR>" . $query6;
	header ("Location: gl-forum.php"); 
}

elseif ($addreply) {
	$query1 = mysqli_query($db, "INSERT INTO guildmsgs (name, topic, topicid, message, datestamp) VALUES ('$ename', '$topic', '$topicid', '$message', $clock')");
	$result1 = mysqli_query($db, $query1);
	$lastid = mysqli_insert_id($db);	

	$query5 = "UPDATE guildthreads SET lastpost='$clock' WHERE topicid='$topicid'";
	$result5 = mysqli_query($db, $query5);
	
	$query2 = "UPDATE guildthreads SET lastposter= '$ename' WHERE topicid= '$topicid'";
	$result2 = mysqli_query($db, $query2);

	$query3 = "UPDATE guildthreads SET replies=replies+1 WHERE topicid='$topicid'";
	$result3 = mysqli_query($db, $query3);
	header ("Location: gl-topic.php?topicid=$topicid");
}

else {
		echo "Your IP address has been logged. Get off this page now."; 
}
exit;
ob_end_flush();
?>	