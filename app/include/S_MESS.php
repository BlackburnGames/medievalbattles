<form type=post action="messaging.php">
<center>

<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$send_to = mb_input('send_to');
$setchg  = mb_input('setchg');
$snum    = mb_input('snum');

if($setchg == 1)	{
	if($snum > mb_settlement_count($db) OR $snum <= 0)	{
		echo "<font class=yellow><div align=center>Settlement " . mb_h($snum) . " does not exist.</font></div><br><br>";
		die();
	}

	mb_query($db, "UPDATE user SET csnum=? WHERE email=? AND pw=?", array((int) $snum, $email, $pw));
	echo "
		<font class='inner2'>You are viewing Settlement " . mb_h($snum) . "</font><br><br>
		<select name='empvalue'>
			<option selected value='ns'>-Select an Empire-</option>";

	$result_id = mb_query($db, "SELECT userid, ename FROM user WHERE setid=?", array((int) $snum));
	while ($row = mysqli_fetch_row($result_id))	{
		echo "<option value='" . mb_h($row[0]) . "' ";
		if($row[0] == $send_to)	{	echo "selected";	}
		echo " >" . mb_h($row[1]) . "</option>";
	}

	echo "
		</select>
		<br><br>";
	}
	else	{

		echo "
		<font class='inner2'>You are viewing Settlement $csnum</font><br><br>
		<select name='empvalue'>
			<option selected value='ns'>-Select an Empire-</option>";

		$result_id = mb_query($db, "SELECT userid, ename FROM user WHERE setid=?", array($csnum));
		while ($row = mysqli_fetch_row($result_id))	{
			echo "<option value='" . mb_h($row[0]) . "' ";
			if($row[0] == $send_to)	{	echo "selected";	}
			echo " >" . mb_h($row[1]) . "</option>";
		}

		echo "
			</select>
			<br><br>";
	}

echo "
</center>
<table border='0' bordercolor='silver' width='80%' align='center'>
	<tr>
		<td align='center'><textarea name='umessage' rows='10' cols='50' wrap='physical'></textarea>
</table>
<center><input type='submit' name='sendmessage' value='Send Message' class='button'></center>
<input type='hidden' name='sendmessage' value='1'>";
?>