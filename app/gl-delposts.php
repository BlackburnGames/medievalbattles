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
 * A duplicate of commong.php:22-26 used to sit here, and it made this page an
 * unconditional fatal: it called mysqli_query($db, ...) with $empireguild
 * interpolated, but commong.php -- which defines both, via connect.php and
 * functions.php -- is only included inside the branches below. On PHP 5 that
 * was two warnings and an empty result; on PHP 8 passing null to
 * mysqli_query() is a TypeError, so clicking Delete on a guild forum thread
 * produced a blank page.
 *
 * It was also wrong on its own terms. It read mb_db_result($setgid, ...) --
 * $setgid is assigned nowhere in the codebase, this line was its only
 * appearance -- so $gid came back empty and the $topicdb/$msgsdb names built
 * from it addressed "<guild>main" rather than "<guild>main<gid>". Neither
 * variable is read by anything here: the deletes below work on the shared
 * guildthreads/guildmsgs tables, so the whole block was vestigial, left over
 * from when guild forums had per-guild tables the way settlements still do.
 *
 * commong.php computes all four correctly, so simply deleting it is the fix.
 * sl-delposts.php, the working equivalent, never had the block at all.
 */

//	delete topic
if(!IsSet($delete))	{

}
else	{	
	include("commong.php");
	include("include/connect.php");

	mysqli_query($db, "DELETE FROM guildthreads WHERE topicid='$tid'"); 
	mysqli_query($db, "DELETE FROM guildmsgs WHERE topicid='$tid'"); 

	header ("Location: gl-forum.php"); 
}

//	delete post
if(!IsSet($delpost))	{

}
else	{	
	include("commong.php");
	include("include/connect.php");

	// Was topicid='$postid' on both, and $postid is assigned nowhere -- so this
	// branch deleted on an empty id and did nothing. Deleting one reply should
	// remove that message, not the whole thread, which is what the settlement
	// equivalent does; sl-delposts.php:41 is the model. Nothing links here yet
	// (gl-topic.php offers no per-post Delete the way sl-topic.php does), so
	// this is repaired rather than verified end to end.
	mysqli_query($db, "DELETE FROM guildmsgs WHERE messageid='$mid'");

	header ("Location: gl-topic.php?topicid=$tid");
}

// Close buffer
ob_end_flush();

?>