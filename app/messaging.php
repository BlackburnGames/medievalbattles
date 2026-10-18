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


		$empattacked = mb_query($db, "SELECT ename FROM user WHERE round_id=? AND userid=?", array((int) $round_id, (int) $empvalue));
		$empireattacked = mb_db_result($empattacked,"empireattacked");

		// An empire of this round. One of another round -- or none -- would
		// otherwise be sent the message, into whichever round it is in.
		if ($empireattacked === null)	{
			echo"<div align=center><font class=yellow>That empire does not exist.</font></div>";
			include("include/S_MESS.php");
			die();
		}

		$THE_MNO = mb_query($db, "SELECT mno FROM user WHERE userid=?", array((int) $empvalue));
		$T_MNO = mb_db_result($THE_MNO,"T_MNO");

		$yourmid = mysqli_query($db, "SELECT max(mid) FROM messages"); // mb-round-ok: ids are issued once across rounds
		$ymid = mb_db_result($yourmid,"ymid");

		$ymid = $ymid + 1;
		$thenum = $T_MNO + 1;
		// Stored as typed; mb_rich() renders it. See include/html.php.

		mb_query($db, "UPDATE user SET mno=? WHERE userid=?", array($thenum, (int) $empvalue));
		mb_query($db, "INSERT INTO messages (origin, datesent, yourid, message, mid, round_id) SELECT ?, ?, userid, ?, ?, round_id FROM user WHERE userid=?", array($ename, $clock, $umessage, (int) $ymid, (int) $empvalue));
	
		echo"<div align=center><font class=yellow>Your message has been sent to " . mb_h($empireattacked) . ".</font></div></center>";
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