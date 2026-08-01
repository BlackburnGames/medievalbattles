<form type=post action="messaging.php">
<center>

<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$send_to = mb_input('send_to');
$setchg  = mb_input('setchg');
$snum    = mb_input('snum');

if($setchg == 1)	{
	// The settlement count is gamedata's; see the quirk there about it
	// disagreeing with the schema, and about the four sibling sites that ask
	// max(setid) instead.
	if($snum > $GAMEDATA['settlements']['count'] OR $snum <= 0)	{
		echo "<font class=yellow><div align=center>Settlement " . mb_h($snum) . " does not exist.</font></div><br><br>";
		die();
	}

	mysqli_query($db, "UPDATE user SET csnum=" . mb_sql_int($snum) . " WHERE email='$email' AND pw='$pw'");
	echo "
		<font class='inner2'>You are viewing Settlement " . mb_h($snum) . "</font><br><br>
		<select name='empvalue'>
			<option selected value='ns'>-Select an Empire-</option>";

	$query_string = "SELECT userid, ename FROM user WHERE setid=" . mb_sql_int($snum);
	$result_id = mysqli_query($db, $query_string);
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

		$query_string = "SELECT userid, ename FROM user WHERE setid='$csnum'";
		$result_id = mysqli_query($db, $query_string);
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