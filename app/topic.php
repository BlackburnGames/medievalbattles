<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$message = mb_input('message');
$topic   = mb_input('topic');
$topicid = (int) mb_input('topicid');

include("include/igtop.php");
include("common.php");

$result= mysqli_query($db, "SELECT topicid, setid FROM setforums WHERE setid='$setid' AND topicid='$topicid'") or die("Error! " . mysqli_error($db));
$settlement_check = mysqli_fetch_array($result);

if($setid != $settlement_check['setid'])	{
	echo"<div align=center><font class=yellow>You cannot view forum posts in other settlements!</font></div>";
	die();
}

$result1 = mysqli_query($db, "SELECT name, topic, message, lastpost FROM setforums WHERE topicid='$topicid' AND setid='$setid'") or die("Error! " . mysqli_error($db));

if ($result1) { 
echo"
	<table border=0 class=f align=center cellspacing=1 cellpadding=1 width=95%>";
									
	while ($r1 = mysqli_fetch_array($result1)) {
	$name = $r1['name']; $topic = $r1['topic']; $message = $r1['message']; $lastpost = $r1['lastpost'];

	echo "
		<tr>
			<td bgcolor=$color3 width=15% align=left><b class=forum>$name</b></td>			
			<td bgcolor=$color3 width=85% align=left><b class=forum>$topic</b></td>
		</tr>
		<tr>
			<td bgcolor=$color2 valign=top colspan=4><pre>$lastpost</pre>$message<br><br></td>
		</tr>";
	} 

echo "
	</table>";
	mysqli_free_result($result1);	
}

$query2 = "SELECT * FROM setforumsmsgs WHERE topicid='$topicid' AND setid='$setid' ORDER BY messageid ASC";
$result2 = mysqli_query($db, $query2) or die("Could not execute the query!");

if ($result2) { 
echo	"
	<table border=0 class=f width=95% align=center cellspacing=1 cellpadding=1>";
	
	while ($r2 = mysqli_fetch_array($result2)) {
	$name = $r2['name']; $topic = $r2['topic']; $message = $r2['message']; $datestamp = $r2['datestamp'];

	echo "
		<tr>
			<td bgcolor=$color3 width=15% align=left><b class=forum>$name</b></td>
			<td bgcolor=$color3 width=85% align=left><b class=forum>$topic</b></td>
		</tr>
		<tr>
			<td bgcolor=$color2 valign=top colspan=4><pre>$datestamp</pre>$message<br><br></td>
		</tr>";
	} 

echo "
	</table><br><br>";	
	mysqli_free_result($result2);
}
?>

<form action="input.posts.php" method="post" name="reply">
<input type="hidden" name="topicid" value="<? echo $topicid; ?>">	
<table border=0 class=f width=<? echo $tablewidth; ?> cellpadding=0 cellspacing=0 align="center" bgcolor=<? echo $color2; ?>>
	<tr>
		<td valign=top bgcolor=<? echo $color1; ?> align=center colspan=4><strong class=white>Post a Reply</strong></td>
	</tr>
	<tr>
		<td align=right><strong class=black>Topic:&nbsp;</strong></td>  
		<td><input type="text" name="topic" maxlength=40 class="black-normal" value="<? echo $topic; ?>"></td>	
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
