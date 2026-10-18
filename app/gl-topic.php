<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$message = mb_input('message');
$topic   = mb_input('topic');
$topicid = (int) mb_input('topicid');


include("include/igtop.php");
include("commong.php");

// The leader's view of a thread, the guild counterpart of sl-topic.php: the
// same thread topicg.php shows every member, with a Delete link on each reply.
// gl-delposts.php and gl-inputposts.php both send a leader here when they are
// done. It used to read "<guild>main<gid>" and "<guild>msgs<gid>", per-guild
// tables from an older schema that db.sql has never had, so all it ever showed
// was an error -- and it asked only that the caller be in the guild, not lead it.
include("include/forum_guard.php");
mb_forum_require_gl($db, $userid, $empireguild);
mb_forum_require_guild_thread($db, $topicid, $empireguild, $round_id);

// The reply form below prefills its Topic field from $topic, which until now
// meant two things at once: the request parameter read above, and whatever the
// last row of the loops below left behind. The row won whenever the thread had
// any posts, which is every case that matters. Both meanings are kept -- the
// row overwrites the seed -- but they have separate names now, so the escaping
// is decidable and the audit can see it.
$r_topic = $topic;

$result1 = mb_query($db, "SELECT name, topic, message, lastpost FROM guildthreads WHERE topicid=? AND guildname=?", array($topicid, $empireguild)) or die("Error! " . mysqli_error($db));

if ($result1) { 
	echo"
	<table border=0 class=f align=center cellspacing=1 cellpadding=1 width=95%>
		<tr>
			<td valign=top> </td>
			<td valign=top align=center><a href=gl-forum.php name=top class=black-small>Return to Guild Leader Forum</a></td>
			<td valign=top align=right> <br><br></td>
		</tr>";
										
	while ($r1 = mysqli_fetch_array($result1)) {
	$r_name = $r1['name']; $r_topic = $r1['topic']; $r_message = $r1['message']; $r_lastpost = $r1['lastpost'];
	echo "
		<tr>
			<td bgcolor=$color3 width=15% align=left><b class=forum>" . mb_h($r_name) . "</b></td>
			<td bgcolor=$color3 width=80% align=left><b class=forum>" . mb_h($r_topic) . "</b></td>
			<td bgcolor=$color3></td>
		</tr>
		<tr>
			<td bgcolor=$color2 valign=top colspan=3><pre>" . mb_h($r_lastpost) . "</pre>" . mb_rich($r_message) . "<br><br></td>
		</tr>	";
	} 
echo "
	</table>";
mysqli_free_result($result1);	
}

// guildmsgs has no guild column of its own; the thread check above is what
// scopes it.
$result2 = mb_query($db, "SELECT messageid, name, topic, message, datestamp FROM guildmsgs WHERE topicid=? ORDER BY messageid ASC", array($topicid)) or die("Could not execute the query!");

if ($result2) { 
echo "
	<table border=0 class=f width=95% align=center cellspacing=1 cellpadding=1>";
	
	while ($r2 = mysqli_fetch_array($result2)) {
	$r_name = $r2['name']; $r_topic = $r2['topic']; $r_message = $r2['message'];
	$r_datestamp = $r2['datestamp']; $messageid = $r2['messageid'];

echo "
		<tr>
			<td bgcolor=$color3 width=15% align=left><b class=forum>" . mb_h($r_name) . "</b></td>
			<td bgcolor=$color3 width=80% align=left><b class=forum>" . mb_h($r_topic) . "</b></td>
			<td bgcolor=$color3><strong class=white><a href=" . mb_attr("gl-delposts.php?delpost=true&mid=$messageid&tid=$topicid") . "><font size=-2> &nbsp; Delete</a></strong></td>
		</tr>
		<tr>
			<td bgcolor=$color2 valign=top colspan=3><pre>" . mb_h($r_datestamp) . "</pre>" . mb_rich($r_message) . "<br><br></td>
		</tr>";
	} 
echo "
	</table><br><br>";	
mysqli_free_result($result2);
	}
?>

<form action="gl-inputposts.php" method="post" name="reply">
<input type="hidden" name="topicid" value="<? echo $topicid; ?>">	
<table border=0 class=f width=<? echo $tablewidth; ?> cellpadding=0 cellspacing=0 align="center" bgcolor=<? echo $color2; ?>>
	<tr>
		<td valign=top bgcolor=<? echo $color1; ?> align=center colspan=4><strong class=white>Post a Reply</strong></td>
	</tr>
	<tr>
		<td align=left colspan=2><strong class=black>Post As Real Name?:</strong> <input type=radio name=realname value=1></td>
		<td width=50% colspan=2>&nbsp;</td>
	</tr>
	<tr>
		<td align=right><strong class=black>Topic:&nbsp;</strong></td>  
		<td><input type="text" name="topic" maxlength=40 class="black-normal" value="<? echo mb_h($r_topic); ?>"></td>	
		<td align=right>&nbsp;</td>
	</tr>	
	<tr>
		<td valign=top align=right><strong class=black>Message:&nbsp;</strong></td> 
		<td colspan=3><textarea name="message" cols=60 rows=6 wrap="physical"></textarea></td>
	</tr>
	<tr>
		<td colspan=4 align=center><input type="submit" name="addreply" value="Reply with this message"></td>
	</tr>
</table>
</form>

</td>
</tr>
</table>
</body>
</html>
