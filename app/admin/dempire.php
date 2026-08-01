<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$daccount = mb_input('daccount');
$empre    = mb_input('empre');
 

include("include/igtop.php");

if(!IsSet($daccount))	{
	echo "<form type=post action=dempire.php>
		<table border=1 bordercolor=#000000 align=center cellpadding=0 cellspacing=0>
			<tr>
				<td colspan=2><b>Delete Account</b></td>
			<tr>
				<td><input type=\"text\" name=\"empre\" maxlength=\"50\"></td>
				<td><input type=\"submit\" name=\"daccount\" value=\"Delete\"></td>
				<input type=\"hidden\" name=\"daccount\" value=\"6\"></td>
		</table>
	</form>";
}
else	{	

	$clock = date("m/d/y, H:ia");

	// The empire name is typed into the form, and the address and settlement id
	// are read back out of the row it names -- so all three reach the DELETEs
	// below, and the last of them removes an account.
	$q_empre = mb_sql_str($db, $empre);

	//SELECTING SETID
		$empreset = mysqli_query($db, "SELECT setid FROM user WHERE ename=$q_empre");
		$esetid = mb_db_result($empreset,"esetid");
	//SELECTING EMAIL
		$theiremail = mysqli_query($db, "SELECT email FROM user WHERE ename=$q_empre");
		$tmail = mb_db_result($theiremail,"tmail");

	// No such empire: say so rather than running six unfiltered DELETEs. With
	// $tmail unset every WHERE below read `email=''`, which matches any row
	// whose address is blank.
	if ($tmail === null) {
		echo "<center>No empire called " . htmlspecialchars($empre) . ".<br>";
		die();
	}

	$q_tmail = mb_sql_str($db, $tmail);
	$news    = "[b]{$empre}[/b] has been deleted by an administrator";

	mb_news_set($db, "$clock", 'red', $news, $esetid, 0);

	mysqli_query($db, "DELETE FROM buildings WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM military WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM returntbl WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM research WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM explore WHERE email=$q_tmail");
	mysqli_query($db, "DELETE FROM user WHERE email=$q_tmail");

	echo "<center>" . htmlspecialchars($empre) . " has been deleted.<br>";
	die();
}

echo "
</td>
</tr>
</table>
</body>
</html>";
?>