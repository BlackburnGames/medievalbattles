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

include("commong.php");
include("include/clock.php");
include("include/forum_guard.php");

// gforums.php, whose form posts here, refuses a player with no guild before it
// renders anything. This file asked nothing, so the same POST sent directly
// filed a thread under the guildname 'None'.
mb_forum_require_guild($empireguild);

$q_topic   = mb_sql_str($db, $topic);
$q_message = mb_sql_str($db, $message);
$q_ename   = mb_sql_str($db, $ename);
$q_guild   = mb_sql_str($db, $empireguild);
$q_topicid = mb_sql_int($topicid);

if ($addtopic) {

	$result = mysqli_query($db, "INSERT INTO guildthreads (name, topic, message, datestamp, guildname)		VALUES ($q_ename, $q_topic, $q_message, '$clock', $q_guild)");

	$result4 = mysqli_query($db, "UPDATE guildthreads SET lastpost='$clock' WHERE topic=$q_topic AND guildname = $q_guild");
	$result6 = mysqli_query($db, "UPDATE guildthreads SET lastposter=$q_ename WHERE topic=$q_topic AND guildname = $q_guild");

	header ("Location: gforums.php");
}

elseif ($addreply) {

	// The thread has to be on this guild's board. Both statements below matched
	// on the topicid alone, so a reply could be posted into another guild's
	// private thread -- and topicg.php renders guildmsgs by topicid unscoped,
	// which is where it appeared.
	mb_forum_require_guild_thread($db, $topicid, $empireguild);

	// See input.posts.php: the INSERT was being run twice, the second time with
	// its own return value as the query string.
	$result1 = mysqli_query($db, "INSERT INTO guildmsgs (name, topic, topicid, message, datestamp)	VALUES ($q_ename, $q_topic, $q_topicid, $q_message, '$clock')");
	$lastid = mysqli_insert_id($db);

	$result5 = mysqli_query($db, "UPDATE guildthreads SET lastpost='$clock' WHERE topicid=$q_topicid");
	$result2 = mysqli_query($db, "UPDATE guildthreads SET lastposter=$q_ename WHERE topicid=$q_topicid");


	header ("Location: topicg.php?topicid=$topicid");
}

else {
		echo "Your IP address has been logged. Get off this page now."; }
exit;
?>



<?php

ob_end_flush();

?>	