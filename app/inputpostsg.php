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


if ($addtopic) {

	$result = mb_query($db, "INSERT INTO guildthreads (name, topic, message, datestamp, guildname)		VALUES (?, ?, ?, ?, ?)", array($ename, $topic, $message, $clock, $empireguild));

	$result4 = mb_query($db, "UPDATE guildthreads SET lastpost=? WHERE topic=? AND guildname = ?", array($clock, $topic, $empireguild));
	$result6 = mb_query($db, "UPDATE guildthreads SET lastposter=? WHERE topic=? AND guildname = ?", array($ename, $topic, $empireguild));

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
	$result1 = mb_query($db, "INSERT INTO guildmsgs (name, topic, topicid, message, datestamp)	VALUES (?, ?, ?, ?, ?)", array($ename, $topic, (int) $topicid, $message, $clock));
	$lastid = mysqli_insert_id($db);

	$result5 = mb_query($db, "UPDATE guildthreads SET lastpost=? WHERE topicid=?", array($clock, (int) $topicid));
	$result2 = mb_query($db, "UPDATE guildthreads SET lastposter=? WHERE topicid=?", array($ename, (int) $topicid));


	header ("Location: topicg.php?topicid=$topicid");
}

else {
		echo "Your IP address has been logged. Get off this page now."; }
exit;
?>



<?php

ob_end_flush();

?>	