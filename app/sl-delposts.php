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

/*
 * May the caller moderate this settlement's forum?
 *
 * sl-forum.php and sl-topic.php both open by refusing anyone whose $sl is not
 * 'yes'. This page, which is the one that deletes things, never asked -- so any
 * logged-in player could call it directly.
 *
 * The check itself lives in include/forum_guard.php now: sl-input.posts.php
 * needs the same rule, and a rule written twice is a rule that gets fixed once.
 */
include("include/forum_guard.php");

//	delete topic
if(!IsSet($delete))	{

}
else	{
	include("common.php");
	include("include/connect.php");
	mb_forum_require_sl($sl);

	mysqli_query($db, "DELETE FROM setforums WHERE topicid=" . mb_sql_int($tid) . " AND setid='$setid'");
	mysqli_query($db, "DELETE FROM setforumsmsgs WHERE topicid=" . mb_sql_int($tid) . " AND setid='$setid'");
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
	mb_forum_require_sl($sl);

	// setforumsmsgs carries its own setid, so the caller's settlement scopes
	// the delete directly -- no join needed, unlike the guild equivalent.
	mysqli_query($db, "DELETE FROM setforumsmsgs WHERE messageid=" . mb_sql_int($mid) . " AND setid='$setid'");

	/*
	 * The reply count is decremented by MySQL now, which is what the rest of
	 * the codebase does -- gl-inputposts.php:.. writes replies=replies+1 when a
	 * reply is added.
	 *
	 * What stood here read the count back with mysqli_query() and then
	 * interpolated the RESULT HANDLE into the UPDATE:
	 *
	 *     $replies  = mysqli_query($db, "SELECT replies FROM setforums ...");
	 *     $ureplies = "UPDATE setforums SET replies=$replies-1 WHERE ...";
	 *
	 * so the statement was built from an object. On PHP 8 converting
	 * mysqli_result to string is a fatal, and on 5.6 it was "Object id #7",
	 * which MySQL rejected -- the count has never gone down on either version.
	 * Doing the arithmetic in SQL removes the round trip along with the bug.
	 */
	$ureplies = "UPDATE setforums SET replies=replies-1 WHERE topicid=" . mb_sql_int($tid) . " AND setid='$setid'";
	mysqli_query($db, $ureplies);

	header ("Location: sl-topic.php?topicid=" . (int) $tid);
}

// Close buffer
ob_end_flush();

?>