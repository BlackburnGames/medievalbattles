<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$addreply = mb_input('addreply');
$addtopic = mb_input('addtopic');
$message  = mb_input('message');
$topic    = mb_input('topic');
$topicid  = mb_input('topicid');

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
include("include/forum_guard.php");

// The leader's half of the settlement forum. sl-forum.php refuses anyone whose
// $sl is not 'yes' and sl-delposts.php does the same before it deletes; this
// file, which is the one that writes, asked nothing.
mb_forum_require_sl($sl);

// A settlement leader's name used to get "<font class=red>(SL)</font>" bolted
// on here, before the INSERT, so the badge was stored in `name` and in
// `lastposter` beside it. It is rendered now -- see mb_sl_badge().

$q_topic   = mb_sql_str($db, $topic);
$q_message = mb_sql_str($db, $message);
$q_ename   = mb_sql_str($db, $ename);
$q_topicid = mb_sql_int($topicid);

if ($addtopic) {
	$result = mysqli_query($db, "INSERT INTO setforums (setid, name, topic, replies, message, datestamp)	VALUES ('$setid', $q_ename, $q_topic, '$replies', $q_message, '$clock')");

	$topic_lastpost = mysqli_query($db, "UPDATE setforums SET lastpost='$clock' WHERE topic=$q_topic AND setid='$setid'");
	$topic_lastposter = mysqli_query($db, "UPDATE setforums SET lastposter=$q_ename WHERE topic=$q_topic AND setid='$setid'");

	header ("Location: sl-forum.php");
}
elseif ($addreply) {
	// See input.posts.php on why a foreign topicid was not harmless here.
	mb_forum_require_set_thread($db, $topicid, $setid);

	// See input.posts.php: the INSERT was being run twice, the second time with
	// its own return value as the query string.
	$result1 = mysqli_query($db, "INSERT INTO setforumsmsgs (setid, name, topic, topicid, message, datestamp)	 VALUES ('$setid', $q_ename, $q_topic, $q_topicid, $q_message, '$clock')");
	$lastid = mysqli_insert_id($db);

	$reply_lastpost = mysqli_query($db, "UPDATE setforums SET lastpost='$clock' WHERE topicid=$q_topicid AND setid='$setid'");
	$reply_lastposter = mysqli_query($db, "UPDATE setforums SET lastposter=$q_ename WHERE topicid=$q_topicid AND setid='$setid'");

	header ("Location: sl-topic.php?topicid=$topicid");
}
else {
		echo "Wah! This page has no text! Get off! Hurry!"; 
}

exit;
ob_end_flush();
?>	