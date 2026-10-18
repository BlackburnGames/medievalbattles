<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$deletem = mb_input('deletem');
 

include("include/igtop.php");

mb_query($db, "UPDATE user SET mno='0' WHERE email=? AND pw=?", array($email, $pw)); 
$MSG_COUNT = mb_query($db, "SELECT count(message) FROM messages WHERE yourid=?", array($userid));	
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
	mb_query($db, "DELETE FROM messages WHERE yourid=?", array($userid)); 
	echo "<div align='center'><font class='yellow'>All of your messages have been deleted.</font></div>";
	include("include/S_VMESS.php");
	die();
}

// There was a second branch here, "delete the selected messages", keyed on
// $delete and a $check that nothing assigns. No page ever rendered a checkbox
// or a delete field for it -- include/S_VMESS.php lists messages and nothing
// else -- so it was unreachable, and reached by hand it deleted nothing.
// Selecting messages was never built; this page deletes all or none.
include("include/S_VMESS.php");
?>
</TD>
</TR>
</TABLE>
</BODY>
</HTML>