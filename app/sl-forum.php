<?php

include("include/igtop.php");

echo "<br><br>";

// The same fail-open test sl.php had: '' is what signup leaves, not 'no'.
include("include/forum_guard.php");
mb_forum_require_sl($sl);

include("common.php");
include("include/connect.php");


$numresults=mb_query($db, "SELECT topicid FROM setforums WHERE setid=?", array($setid));
$numrows=mysqli_num_rows($numresults);

// Who leads this settlement, for the (SL) badge. One lookup, before the
// loop -- the badge is rendered now rather than stored; see mb_sl_badge().
$mb_sl_q = mb_query($db, "SELECT ename FROM user WHERE sl='yes' AND setid=?", array((int) $setid));
$mb_leader = mb_db_result($mb_sl_q, "ename");

$result= mb_query($db, "SELECT topicid, name, topic, replies, lastpost, lastposter FROM setforums WHERE setid = ? ORDER by lastpost DESC", array($setid)) or die("Could not run the database query!");

if ($result) { 
echo "
	<table border=0 width=$tablewidth align=center cellspacing=0 cellpadding=0>
		<tr>
			<td bgcolor=$color1>
				<table border=0 width=100% cellspacing=1 cellpadding=0>
					<tr bgcolor=$color1>
						<td align=center><b class=forum>Topic</b></td>
						<td align=center><b class=forum>Posts</b></td>
						<td align=center><b class=forum>Orgin</b></td>
						<td align=center><b class=forum>Last Post by</b></td>
						<td align=center><b class=forum>Last Post</b></td>
						<td align=center><b class=forum>Delete</b></td>
					</tr>";										
	while ($r = mysqli_fetch_array($result)) {

		$topicreplies = mb_query($db, "SELECT count(topicid) FROM setforumsmsgs WHERE topicid=?", array((int) $r['topicid']));
		$treplies = mb_db_result($topicreplies, "treplies");

echo "
					<tr bgcolor=$color2>
						<td valign=top><strong class=black-small><a href=" . mb_attr("sl-topic.php?topicid={$r['topicid']}") . ">" . mb_h($r['topic']) . "</a></strong></td>
						<td valign=top align=center>" . mb_h($treplies) . "</td>
						<td valign=top align=center><strong class=black-small>" . mb_h($r['name']) . mb_sl_badge($r['name'], $mb_leader) . "</strong></td>
						<td valign=top align=center><strong class=black-small>" . mb_h($r['lastposter']) . mb_sl_badge($r['lastposter'], $mb_leader) . "</strong></td>
						<td valign=top align=center>" . mb_h($r['lastpost']) . "</td>
						<td valign=top align=center><strong class=black-small><a href=" . mb_attr("sl-delposts.php?delete=true&tid={$r['topicid']}") . "><font size=-2>Delete</a></strong></td>
					</tr>";
	}
}
echo"
				</table>
			</td>
		</tr>
		<tr>
	</table>";  
  
mysqli_free_result($result);
?>

<br><br>
<form action="sl-input.posts.php" method="post" name="post">
<table border=0 class=f width=60% cellpadding=0 cellspacing=0 bgcolor="<? echo $color2; ?>" align="center">
	<tr>
		<td valign=top bgcolor="<? echo $color1; ?>" align=center colspan=4><strong class=white>Post a Thread</b></td>
	</tr>
	<tr>
		<td align=right><strong class=black>Topic:</strong></td>  
		<td><input type="text" name="topic" maxlength=40 class=black-normal></td>
	</tr>
	<tr>
		<td valign=top align=right><strong class=black>Message: </strong></td> 
		<td><textarea name="message" cols=40 rows=10 wrap="physical"></textarea></td>
	</tr>
	<tr>
		<td colspan=4 align=center><input type="submit" name="addtopic" value="Post this message"></td>
	</tr>
</table>
</form>

</td>
</tr>
</table>
</body>
</html>
