<form type=post action="intel.php">
<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$setchg = mb_input('setchg');
$snum   = mb_input('snum');


if($setchg == 1)	{

	if(!mb_settlement_in_round($db, $round_id, $snum))	{
		echo "<font class=yellow><div align=center>Settlement " . mb_h($snum) . " does not exist.</font></div><br><br>";
		die();
	}

	mb_query($db, "UPDATE user SET csnum=? WHERE userid=?", array((int) $snum, $userid));
	echo "
		<font class='inner2'>You are viewing Settlement " . mb_h($snum) . "</font><br><br>
		<select name='empvalue'>
			<option selected value='ns'>-Select an Empire-</option>";

	$result_id = mb_query($db, "SELECT userid, ename FROM user WHERE setid=?", array((int) $snum));
	while ($row = mysqli_fetch_row($result_id))	{
		echo "<option value=" . mb_attr($row[0]) . ">" . mb_h($row[1]) . "\n</option>";
	}

	echo "
		</select><br><br>";
}
else	{

	echo "
		<font class='inner2'>You are viewing Settlement $csnum</font><br><br>
		<select name='empvalue'>
			<option selected value='ns'>-Select an Empire-</option>";

	$result_id = mb_query($db, "SELECT userid, ename FROM user WHERE setid=?", array($csnum));
	while ($row = mysqli_fetch_row($result_id))	{
		echo "<option value=" . mb_attr($row[0]) . ">" . mb_h($row[1]) . "\n</option>";
    }

	echo "
		</select><br><br>";
}

echo "
</center>
<table border='1' bordercolor='#000000' align='center' width='40%' cellpadding='0' cellspacing='0'>
	<tr><td class='main' colspan='2'><b class='reg'>Intelligence</b></td>
	<tr><td class='main2' colspan='2'>You have $thieves thieves available.</td>
	<tr><td class='inner2'>Thieves:</td><td class='inner2'><input type=number name=send size=15></td>
	<tr><td class='inner2' colspan='2'><input type='submit' name='gather' value='Send' class='button'></td>
		<input type='hidden' name='gather' value='1'>";
?>