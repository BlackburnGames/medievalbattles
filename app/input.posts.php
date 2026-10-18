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

$uename = mb_query($db, "SELECT ename FROM user WHERE email=? AND pw=?", array($email, $pw));
	$ename = mb_db_result($uename,"ename");
$usetid = mb_query($db, "SELECT setid FROM user WHERE email=? AND pw=?", array($email, $pw));
	$setid = mb_db_result($usetid,"setid");


include("common.php");
include("include/clock.php");
include("include/forum_guard.php");

// A settlement leader's name used to get "<font class=red>(SL)</font>" bolted
// on here, before the INSERT, so the badge was stored in `name` and in
// `lastposter` beside it. It is rendered now -- see mb_sl_badge().

// $ename is escaped alongside the request values. It is read back out of the
// `user` row rather than off the request, but the player chose it at signup.

if ($addtopic) {
	$replies = 0;   // a new topic has none
	// $clock, not $datestamp. Only commong.php sets $datestamp, and this page
	// includes common.php -- the settlement half -- so a new settlement topic
	// was stamped with the empty string and displayed a blank date ever after.
	// The reply below, and both branches of sl-input.posts.php writing the same
	// two tables, have always used $clock.
	$result = mb_query($db, "INSERT INTO setforums (setid, name, topic, replies, message, datestamp)	VALUES (?, ?, ?, ?, ?, ?)", array((int) $setid, $ename, $topic, $replies, $message, $clock));

	$topic_lastpost = mb_query($db, "UPDATE setforums SET lastpost=? WHERE topic=? AND setid=?", array($clock, $topic, (int) $setid));
	$topic_lastposter = mb_query($db, "UPDATE setforums SET lastposter=? WHERE topic=? AND setid=?", array($ename, $topic, (int) $setid));

	header ("Location: sforum.php");
}
elseif ($addreply) {
	/*
	 * The thread named by $topicid has to be in this settlement.
	 *
	 * This was the half-scoped one. The two UPDATEs below have always said AND
	 * setid, and the INSERT stamps the caller's own setid, so a topicid from
	 * another settlement wrote a row with a setid that does not match its
	 * thread -- and topic.php selects on both, so nothing displays it, ever. It
	 * went nowhere rather than somewhere it should not, which is luck and not a
	 * check. Refusing it says so.
	 */
	mb_forum_require_set_thread($db, $topicid, $setid);

	// The reply INSERT used to be run twice: once here, and again on the next
	// line with its own return value passed back as the query string. On a
	// successful INSERT that is mysqli_query($db, "1") -- a syntax error; on a
	// failed one it is the empty string, which is a fatal on PHP 8. It also
	// reset the insert id that $lastid reads. The second call is gone.
	$result1 = mb_query($db, "INSERT INTO setforumsmsgs (setid, name, topic, topicid, message, datestamp)	 VALUES (?, ?, ?, ?, ?, ?)", array((int) $setid, $ename, $topic, (int) $topicid, $message, $clock));
	$lastid = mysqli_insert_id($db);

	$reply_lastpost = mb_query($db, "UPDATE setforums SET lastpost=? WHERE topicid=? AND setid=?", array($clock, (int) $topicid, (int) $setid));
	$reply_lastposter = mb_query($db, "UPDATE setforums SET lastposter=? WHERE topicid=? AND setid=?", array($ename, (int) $topicid, (int) $setid));

	header ("Location: topic.php?topicid=$topicid");
}
else {
		echo "Wah! This page has no text! Get off! Hurry!"; }

exit;
ob_end_flush();
?>	