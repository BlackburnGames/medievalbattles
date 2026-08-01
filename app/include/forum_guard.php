<?php
/**
 * Who may act on a forum, and on which board.
 *
 * Both forums in this game are one shared table apiece -- `setforums` for every
 * settlement and `guildthreads` for every guild -- and which board a row belongs
 * to is a column on it. So there are two separate questions to ask before a
 * write, and the four post handlers asked neither:
 *
 *   1. May the caller act on this SURFACE at all? The gl-* and sl-* pages are
 *      the leader's half of each forum; gl-forum.php:15 and sl-forum.php:7 are
 *      where that rule is written down.
 *   2. Is the row they named on the caller's OWN board? A reply carries a
 *      topicid straight off the request, and nothing looked at whose thread it
 *      was.
 *
 * The second is the one that mattered. `guildmsgs` has no guildname of its own,
 * so a reply is bound to a guild only through the thread it hangs off -- and
 * both guild reply handlers matched on the topicid alone. Any player in any
 * guild could post into any other guild's private thread by guessing an id, and
 * topicg.php renders `guildmsgs WHERE topicid` unscoped, so it showed up there.
 * The same shape as the delete on the id alone that gl-delposts.php carried.
 *
 * The settlement half was half-scoped already: the UPDATEs said AND setid, and
 * the reply INSERT stamps the caller's own setid, so a foreign topicid wrote a
 * row that no page can ever display rather than one on someone else's thread.
 * It is refused here too -- a write that silently goes nowhere is not a defence,
 * it is the absence of one.
 *
 * The role guards were already written twice, inline in the two moderation
 * pages, which is one place too many for a rule six pages now share.
 */

/**
 * Settlement leader, for the sl-* surface.
 */
function mb_forum_require_sl($sl)
{
	if ($sl != 'yes') {
		echo "<center><b class=yellow>You are not Settlement Leader.</b>";
		die();
	}
}

/**
 * Guild leader, for the gl-* surface.
 *
 * `guild.owner` holds the owner's userid despite the column name -- gc.php
 * inserts '$userid' and every reader compares one.
 */
function mb_forum_require_gl($db, $userid, $empireguild)
{
	$gresult = mysqli_query($db, "SELECT owner FROM guild WHERE owner=" . mb_sql_int($userid));
	$gnamecheck = mysqli_fetch_array($gresult);

	if (!$gnamecheck || $gnamecheck[0] != $userid) {
		echo "<div align=center><font class=yellow>You are not a Guild Leader.</font></div>";
		die();
	}
	mb_forum_require_guild($empireguild);
}

/**
 * In a guild at all, for the member surface.
 *
 * gforums.php:10 refuses 'None' before it renders anything; inputpostsg.php,
 * the handler its form posts to, did not -- so a player with no guild could
 * file threads under the guildname 'None', on a board every other guildless
 * player reads.
 */
function mb_forum_require_guild($empireguild)
{
	if ($empireguild == 'None' || $empireguild == '') {
		echo "<div align=center><font class=yellow>You have to be in a guild to view this page!</font></div>";
		die();
	}
}

/**
 * Is this thread on the caller's own guild board?
 *
 * $empireguild must be the value commong.php leaves behind -- it strips spaces
 * out of the name, and that stripped form is what `guildthreads.guildname`
 * holds.
 */
function mb_forum_require_guild_thread($db, $topicid, $empireguild)
{
	$owned = mysqli_query($db, "SELECT topicid FROM guildthreads WHERE topicid=" . mb_sql_int($topicid) . " AND guildname=" . mb_sql_str($db, $empireguild));

	if (!$owned || !mysqli_num_rows($owned)) {
		echo "<div align=center><font class=yellow>That thread is not in your guild.</font></div>";
		die();
	}
}

/**
 * Is this thread on the caller's own settlement board?
 */
function mb_forum_require_set_thread($db, $topicid, $setid)
{
	$owned = mysqli_query($db, "SELECT topicid FROM setforums WHERE topicid=" . mb_sql_int($topicid) . " AND setid=" . mb_sql_int($setid));

	if (!$owned || !mysqli_num_rows($owned)) {
		echo "<div align=center><font class=yellow>That thread is not in your settlement.</font></div>";
		die();
	}
}
?>
