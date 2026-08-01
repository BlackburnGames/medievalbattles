<?php

include("include/igtop.php");

echo "<br><br>";

if($sl == 'no') {
	echo"<center><b class=yellow>You are not Settlement Leader.</b>";
	die();
}

include("common.php");
include("include/connect.php");

if (empty($offset))  {  $offset=0;  }

$numresults=mysqli_query($db, "SELECT topicid FROM setforums WHERE setid=$setid");
$numrows=mysqli_num_rows($numresults);

$query = "SELECT topicid, name, topic, replies, lastpost, lastposter FROM setforums WHERE setid = '$setid' ORDER by lastpost DESC";
$result= mysqli_query($db, $query) or die("Could not run the database query!");

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

		$topicreplies = mysqli_query($db, "SELECT count(topicid) FROM setforumsmsgs WHERE topicid=" . mb_sql_int($r['topicid']));
		$treplies = mb_db_result($topicreplies, "treplies");

echo "
					<tr bgcolor=$color2>
						<td valign=top><strong class=black-small><a href=" . mb_attr("sl-topic.php?topicid={$r['topicid']}") . ">{$r['topic']}</a></strong></td>
						<td valign=top align=center>$treplies</td>
						<td valign=top align=center><strong class=black-small>{$r['name']}$S_L</strong></td>
						<td valign=top align=center><strong class=black-small>{$r['lastposter']}</strong></td>
						<td valign=top align=center>{$r['lastpost']}</td>
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
