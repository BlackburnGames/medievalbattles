<?php 

include("include/igtop.php");

mysqli_query($db, "UPDATE user SET mno='0' WHERE email='$email' AND pw='$pw'"); 
$MSG_COUNT = mysqli_query($db, "SELECT count(message) FROM messages WHERE yourid='$userid'");	
	$MSG_C = mb_db_result($MSG_COUNT,"MSG_C");
	
if($MSG_C == 0)	{
	echo"<div align=center><font class=yellow>You do not have any messages to display.</font></center>";
	die();
}

if(!IsSet($deletem))	{
	echo "<form type='post' action='vmessages.php'>
	<center><input type='submit' name='deletem' value='Delete All Messages'></center>
	<input type='hidden' name='deletem' value='1'>
	</form>";
}
else	{
	mysqli_query($db, "DELETE FROM messages WHERE yourid='$userid'"); 
	echo "<div align='center'><font class='yellow'>All of your messages have been deleted.</font></div>";
	include("include/S_VMESS.php");
	die();
}

if(!IsSet($delete))	{
	include("include/S_VMESS.php");
}
else	{
	mysqli_query($db, "DELETE FROM messages WHERE yourid='$userid' AND mid='$check'"); 
	echo"<font class='yellow'><div align='center'>The selected messages have been deleted.</font></div>";
	include("include/S_VMESS.php");
}
?>
</TD>
</TR>
</TABLE>
</BODY>
</HTML>