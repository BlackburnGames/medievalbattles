<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$message = mb_input('message');
$topic   = mb_input('topic');
$topicid = (int) mb_input('topicid');


include("include/igtop.php");
include("commong.php");

$result= mysqli_query($db, "SELECT topicid, guildname FROM guildthreads WHERE guildname='$empireguild' AND topicid='$topicid'") or die("Error! " . mysqli_error($db));
$guild_check = mysqli_fetch_array($result);

if($empireguild != $guild_check['guildname'])	{
	echo"<div align=center><font class=yellow>You cannot view forum posts in other guilds!</font></div>";
	die();
}

if($empireguild == 'None')	{
	echo"<div align=center>You have to be in a guild to view this page!</div>";
	die();
}

// The reply form below prefills its Topic field from $topic, which until now
// meant two things at once: the request parameter read above, and whatever the
// last row of the loops below left behind. The row won whenever the thread had
// any posts, which is every case that matters. Both meanings are kept -- the
// row overwrites the seed -- but they have separate names now, so the escaping
// is decidable and the audit can see it.
$r_topic = $topic;

$result1 = mysqli_query($db, "SELECT name, topic, message, lastpost FROM $topicdb WHERE topicid='$topicid'") or die("Error! " . mysqli_error($db));

if ($result1) { 
	echo"
	<table border=0 class=f align=center cellspacing=1 cellpadding=1 width=95%>
		<tr>
			<td valign=top> </td>
			<td valign=top align=center><a href=gforums.php name=top class=black-small>Return to Guild Forums</a></td>
			<td valign=top align=right> <br><br></td>
		</tr>";
										
	while ($r1 = mysqli_fetch_array($result1)) {
	$r_name = $r1['name']; $r_topic = $r1['topic']; $r_message = $r1['message']; $r_lastpost = $r1['lastpost'];
	echo "
		<tr>
			<td bgcolor=$color3 width=15% align=left><b class=forum>" . mb_h($r_name) . "</b></td>
			<td bgcolor=$color3 width=85% align=left><b class=forum>" . mb_h($r_topic) . "</b></td>
		</tr>
		<tr>
			<td bgcolor=$color2 valign=top colspan=3><pre>" . mb_h($r_lastpost) . "</pre>" . mb_rich($r_message) . "<br><br></td>
		</tr>	";
	} 
echo "
	</table>";
mysqli_free_result($result1);	
}

$query2 = "SELECT messageid, name, topic, message, datestamp FROM $msgsdb WHERE topicid='$topicid' ORDER BY datestamp";
$result2 = mysqli_query($db, $query2) or die("Could not execute the query!");

if ($result2) { 
echo "
	<table border=0 class=f width=95% align=center cellspacing=1 cellpadding=1>";
	
	while ($r2 = mysqli_fetch_array($result2)) {
	$r_name = $r2['name']; $r_topic = $r2['topic']; $r_message = $r2['message']; $r_datestamp = $r2['datestamp'];

echo "
		<tr>
			<td bgcolor=$color3 width=15% align=left><b class=forum>" . mb_h($r_name) . "</b></td>
			<td bgcolor=$color3 width=85% align=left><b class=forum>" . mb_h($r_topic) . "</b></td>
		</tr>
		<tr>
			<td bgcolor=$color2 valign=top colspan=3><pre>" . mb_h($r_datestamp) . "</pre>" . mb_rich($r_message) . "<br><br></td>
		</tr>
		<tr>
			<td></td>
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
