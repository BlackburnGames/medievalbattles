<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$setchg = mb_input('setchg');
$snum   = mb_input('snum');

echo "
<form type=post action=attack.php>
<div align=center>";

if($setchg == 1)	{
	$maxset = mb_settlement_count($db);

	if($snum > $maxset OR $snum <= 0)	{
		echo"<font class=yellow><div align=center>Settlement " . mb_h($snum) . " does not exist.</font></div><br><br>";
		die();
	}

	mb_query($db, "UPDATE user SET csnum=? WHERE userid=?", array((int) $snum, $userid));

	echo "
		<font class=inner2>You are viewing Settlement " . mb_h($snum) . "</font><br><br>
		<select name=empvalue>
			<option selected value=ns>-Land Attack-</option>";

	$result_id = mb_query($db, "SELECT userid, ename FROM user WHERE setid=?", array((int) $snum));
	while ($row = mysqli_fetch_row($result_id))	{
		echo "<option value=" . mb_attr($row[0]) . ">" . mb_h($row[1]) . "\n</option>";
	}

	echo "
		</select><br><br>";
			
}
else	{
	echo "
		<font class=inner2>You are viewing Settlement $csnum</font><br><br>
		<select name=empvalue>
			<option selected value=ns>-Land Attack-</option>";
	$result_id = mb_query($db, "SELECT userid, ename FROM user WHERE setid=?", array($csnum));
	while ($row = mysqli_fetch_row($result_id))	{
		echo "<option value=" . mb_attr($row[0]) . ">" . mb_h($row[1]) . "\n</option>";
    }

	echo "
		</select><br><br>";

}

echo "</div>";
?>
