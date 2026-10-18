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

$uename = mb_query($db, "SELECT ename FROM user WHERE userid=?", array($userid));
	$ename = mb_db_result($uename,"ename");

include("commong.php");
include("include/clock.php");
include("include/forum_guard.php");

// The "are they a gl?" query stood here and its result was never looked at.
// gl-forum.php runs the same two lines and then compares them; this file, which
// is where the writing happens, fetched the row and fell through to the INSERT
// regardless. So the leader-only board took posts from any guild member.
mb_forum_require_gl($db, $userid, $empireguild);


if ($addtopic) {

	$replies = "0";
	$result = mb_query($db, "INSERT INTO guildthreads (name, topic, replies, message, datestamp, guildname) VALUES (?, ?, ?, ?, ?, ?)", array($ename, $topic, $replies, $message, $clock, $empireguild));

	$result4 = mb_query($db, "UPDATE guildthreads SET lastpost=? WHERE topic=? AND guildname = ?", array($clock, $topic, $empireguild));

	$result6 = mb_query($db, "UPDATE guildthreads SET lastposter=? WHERE topic=? AND guildname = ?", array($ename, $topic, $empireguild));
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

	$result1 = mb_query($db, "INSERT INTO guildmsgs (name, topic, topicid, message, datestamp) VALUES (?, ?, ?, ?, ?)", array($ename, $topic, (int) $topicid, $message, $clock));
	$lastid = mysqli_insert_id($db);

	$result5 = mb_query($db, "UPDATE guildthreads SET lastpost=? WHERE topicid=?", array($clock, (int) $topicid));

	$result2 = mb_query($db, "UPDATE guildthreads SET lastposter= ? WHERE topicid= ?", array($ename, (int) $topicid));

	$result3 = mb_query($db, "UPDATE guildthreads SET replies=replies+1 WHERE topicid=?", array((int) $topicid));
	header ("Location: gl-topic.php?topicid=$topicid");
}

else {
		echo "Your IP address has been logged. Get off this page now."; 
}
exit;
ob_end_flush();
?>	