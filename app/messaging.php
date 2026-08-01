<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$empvalue    = mb_input('empvalue');
$sendmessage = mb_input('sendmessage');
$umessage    = mb_input('umessage');
	include("include/igtop.php");	?>

<br><br>
<center>
<form type=post action="messaging.php">
<b class=reg>Settlement:</b><input type="number" name="snum" size=3 maxlength=3>
<input type="hidden" name="setchg" value="1">
<input type="submit" value="Change">
</form>
<br><br>

<?php
if(!IsSet($sendmessage))	{
	include("include/S_MESS.php");
}
else	{
	
	if($empvalue == 'ns')		{
		echo"<div align=center><font class=yellow>You did not select an empire to message.</font></div>"; 
		include("include/S_MESS.php"); 
		die();
	}
	elseif($umessage === "")	{
		echo"<div align=center><font class=yellow>You did not type anything to send.</font></div>";
		include("include/S_MESS.php"); 
		die();
	}
	else	{

		$q_empvalue = mb_sql_int($empvalue);

		$empattacked = mysqli_query($db, "SELECT ename FROM user WHERE userid=$q_empvalue");
		$empireattacked = mb_db_result($empattacked,"empireattacked");

		$THE_MNO = mysqli_query($db, "SELECT mno FROM user WHERE userid=$q_empvalue");
		$T_MNO = mb_db_result($THE_MNO,"T_MNO");

		$yourmid = mysqli_query($db, "SELECT max(mid) FROM messages");	
		$ymid = mb_db_result($yourmid,"ymid");

		$ymid = $ymid + 1;
		$thenum = $T_MNO + 1;
		// Stored as typed; mb_rich() renders it. See include/html.php.

		mysqli_query($db, "UPDATE user SET mno='$thenum' WHERE userid=$q_empvalue");
		mysqli_query($db, "INSERT INTO messages (origin, datesent, yourid, message, mid)		VALUES	('$ename', '$clock', $q_empvalue, " . mb_sql_str($db, $umessage) . ", '$ymid') ");
	
		echo"<div align=center><font class=yellow>Your message has been sent to $empireattacked.</font></div></center>";
		include("include/S_MESS.php"); 
		die();
	}
}
?>

</TD>
</TR>
</TABLE>
</BODY>
</HTML>