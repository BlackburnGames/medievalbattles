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

/*
 * May the caller moderate this guild's forum at all?
 *
 * Nothing on this page asked. Every other gl-* page opens by checking that the
 * caller owns a guild -- gl-forum.php:15 is the pattern -- and this one, the
 * only page that destroys anything, did not. Any logged-in player could reach
 * it, and the deletes below scoped by nothing but the id in the URL.
 *
 * Both that check and the thread-ownership one below live in
 * include/forum_guard.php now, because gl-inputposts.php needs the same pair.
 */
include("include/forum_guard.php");

//	delete topic
if(!IsSet($delete))	{

}
else	{
	include("commong.php");
	include("include/connect.php");
	mb_forum_require_gl($db, $userid, $empireguild);

	// Scoped to the caller's own guild. Deleting on the id alone let anyone who
	// reached this page remove any guild's thread by guessing a topicid; the
	// settlement equivalent had at least been scoping by setid all along.
	// guildmsgs carries no guildname of its own, so the thread is confirmed
	// first and both deletes hang off that.
	$q_tid = mb_sql_int($tid);
	mb_forum_require_guild_thread($db, $tid, $empireguild);

	mysqli_query($db, "DELETE FROM guildthreads WHERE topicid=$q_tid");
	mysqli_query($db, "DELETE FROM guildmsgs WHERE topicid=$q_tid");

	header ("Location: gl-forum.php");
}

//	delete post
if(!IsSet($delpost))	{

}
else	{
	include("commong.php");
	include("include/connect.php");
	mb_forum_require_gl($db, $userid, $empireguild);

	// Was topicid='$postid' on both, and $postid is assigned nowhere -- so this
	// branch deleted on an empty id and did nothing. Deleting one reply should
	// remove that message, not the whole thread, which is what the settlement
	// equivalent does; sl-delposts.php is the model.
	//
	// The message's thread has to be in the caller's guild, which takes a join:
	// guildmsgs has a topicid but no guildname.
	$q_mid = mb_sql_int($mid);
	$owned = mysqli_query($db, "SELECT m.messageid FROM guildmsgs m JOIN guildthreads t ON t.topicid = m.topicid WHERE m.messageid=$q_mid AND t.guildname=" . mb_sql_str($db, $empireguild));

	if (!$owned || !mysqli_num_rows($owned)) {
		echo "<div align=center><font class=yellow>That post is not in your guild.</font></div>";
		die();
	}

	mysqli_query($db, "DELETE FROM guildmsgs WHERE messageid=$q_mid");

	header ("Location: gl-topic.php?topicid=" . (int) $tid);
}

// Close buffer
ob_end_flush();

?>