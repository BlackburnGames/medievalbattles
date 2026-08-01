<?php

function callback($buffer) {
  return ($buffer);
}

ob_start("callback");

 include("include/connect.php");

include("include/session.php");

include("commong.php");	
include("include/clock.php");

if ($addtopic) {

	$result = mysqli_query($db, "INSERT INTO guildthreads (name, topic, message, datestamp, guildname)		VALUES ('$ename', '$topic', '$message', '$clock', '$empireguild')");

	$result4 = mysqli_query($db, "UPDATE guildthreads SET lastpost='$clock' WHERE topic='$topic' AND guildname = '$empireguild'");
	$result6 = mysqli_query($db, "UPDATE guildthreads SET lastposter='$ename' WHERE topic='$topic' AND guildname = '$empireguild'");

	header ("Location: gforums.php"); 
}

elseif ($addreply) {

	$query1 = mysqli_query($db, "INSERT INTO guildmsgs (name, topic, topicid, message, datestamp)	VALUES ('$ename', '$topic', '$topicid', '$message', '$clock')");

	$result1 = mysqli_query($db, $query1);
	$lastid = mysqli_insert_id($db);	

	$result5 = mysqli_query($db, "UPDATE guildthreads SET lastpost='$clock' WHERE topicid='$topicid'");
	$result2 = mysqli_query($db, "UPDATE guildthreads SET lastposter='$ename' WHERE topicid='$topicid'");


	header ("Location: topicg.php?topicid=$topicid");
}

else {
		echo "Your IP address has been logged. Get off this page now."; }
exit;
?>



<?php

ob_end_flush();

?>	