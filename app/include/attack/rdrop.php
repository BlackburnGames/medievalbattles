<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$setchg = mb_input('setchg');
$snum   = mb_input('snum');

echo "
<form type=post action=attackr.php>
<div align=center>";

if($setchg == 1)	{
	$maxset0 = mysqli_query($db, "SELECT max(setid) AS maxset FROM settlement");
		$maxset = mb_db_result($maxset0,"maxset");

	if($snum > $maxset OR $snum <= 0)	{
		echo"<font class=yellow><div align=center>Settlement $snum does not exist.</font></div><br><br>";
		die();
	}

	mysqli_query($db, "UPDATE user SET csnum =\"$snum\" WHERE email='$email' AND pw='$pw'");

	echo "
		<font class=inner2>You are viewing Settlement $snum</font><br><br>
		<select name=empvalue>
			<option selected value=ns>-Resource Attack-</option>";

	$query_string = "SELECT userid, ename FROM user WHERE setid = '$snum'";
	$result_id = mysqli_query($db, $query_string);
	while ($row = mysqli_fetch_row($result_id))	{
		echo "<option value=$row[0]>$row[1]\n</option>";
	}

	echo "
		</select><br><br>";
}
else	{
	
	echo "
		<font class=inner2>You are viewing Settlement $csnum</font><br><br>
		<select name=empvalue>
			<option selected value=ns>-Resource Attack-</option>";

	$query_string = "SELECT userid, ename FROM user WHERE setid = '$csnum'";
	$result_id = mysqli_query($db, $query_string);
	while ($row = mysqli_fetch_row($result_id))	{
		echo "<option value=$row[0]>$row[1]\n</option>";
    }

	echo "
		</select>
		<br><br>";
}
echo "</div>";
?>