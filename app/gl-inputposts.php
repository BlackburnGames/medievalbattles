<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$addreply = mb_input('addreply');
$addtopic = mb_input('addtopic');
$message  = mb_input('message');
$topic    = mb_input('topic');
$topicid  = mb_input('topicid');


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
include("include/forum_guard.php");

// The "are they a gl?" query stood here and its result was never looked at.
// gl-forum.php runs the same two lines and then compares them; this file, which
// is where the writing happens, fetched the row and fell through to the INSERT
// regardless. So the leader-only board took posts from any guild member.
mb_forum_require_gl($db, $userid, $empireguild);

$q_topic   = mb_sql_str($db, $topic);
$q_message = mb_sql_str($db, $message);
$q_ename   = mb_sql_str($db, $ename);
$q_guild   = mb_sql_str($db, $empireguild);
$q_topicid = mb_sql_int($topicid);

if ($addtopic) {

	$replies = "0";
	$result = mysqli_query($db, "INSERT INTO guildthreads (name, topic, replies, message, datestamp, guildname) VALUES ($q_ename, $q_topic, '$replies', $q_message, '$clock', $q_guild)");

	$query4 = "UPDATE guildthreads SET lastpost='$clock' WHERE topic=$q_topic AND guildname = $q_guild";
	$result4 = mysqli_query($db, $query4);

	$query6 = "UPDATE guildthreads SET lastposter=$q_ename WHERE topic=$q_topic AND guildname = $q_guild";
	$result6 = mysqli_query($db, $query6);
	// A debug echo of both UPDATE statements stood here, printing the SQL to
	// the page on every guild-leader post since 2003.
	header ("Location: gl-forum.php");
}

elseif ($addreply) {
	// Two bugs on one line, and this page was never reached by the suite so
	// neither showed. The INSERT was missing the opening quote on $clock --
	// "..., $clock')" -- so it has always been a syntax error and no guild
	// leader has ever managed to reply to a thread. Then its return value was
	// passed straight back to mysqli_query() as a second query, which is the
	// same double-execute the other three post handlers carry.
	// Whose thread is $topicid? Nothing asked, and guildmsgs carries no guild of
	// its own, so the reply below is bound to a guild only by the thread it
	// names. Every statement in this branch matched on the id alone.
	mb_forum_require_guild_thread($db, $topicid, $empireguild);

	$result1 = mysqli_query($db, "INSERT INTO guildmsgs (name, topic, topicid, message, datestamp) VALUES ($q_ename, $q_topic, $q_topicid, $q_message, '$clock')");
	$lastid = mysqli_insert_id($db);

	$query5 = "UPDATE guildthreads SET lastpost='$clock' WHERE topicid=$q_topicid";
	$result5 = mysqli_query($db, $query5);

	$query2 = "UPDATE guildthreads SET lastposter= $q_ename WHERE topicid= $q_topicid";
	$result2 = mysqli_query($db, $query2);

	$query3 = "UPDATE guildthreads SET replies=replies+1 WHERE topicid=$q_topicid";
	$result3 = mysqli_query($db, $query3);
	header ("Location: gl-topic.php?topicid=$topicid");
}

else {
		echo "Your IP address has been logged. Get off this page now."; 
}
exit;
ob_end_flush();
?>	