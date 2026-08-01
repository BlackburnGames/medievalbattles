<?php 

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

	//SELECTING SETID
		$empreset = mysqli_query($db, "SELECT setid FROM user WHERE ename='$empre'");
		$esetid = mb_db_result($empreset,"esetid");
	//SELECTING EMAIL
		$theiremail = mysqli_query($db, "SELECT email FROM user WHERE ename='$empre'");
		$tmail = mb_db_result($theiremail,"tmail");
	mysqli_query($db, "INSERT INTO setnews (date, news, setid) 	VALUES	('$clock', '<font class=red><b>$empre</b> has been deleted by an administrator</font>', '$esetid') ");			

	mysqli_query($db, "DELETE FROM buildings WHERE email='$tmail'"); 
	mysqli_query($db, "DELETE FROM military WHERE email='$tmail'");
	mysqli_query($db, "DELETE FROM returntbl WHERE email='$tmail'"); 
	mysqli_query($db, "DELETE FROM research WHERE email='$tmail'");
	mysqli_query($db, "DELETE FROM explore WHERE email='$tmail'"); 
	mysqli_query($db, "DELETE FROM user WHERE email='$tmail'");

	echo "<center>$empre has been deleted.<br>";
	die();
}

echo "
</td>
</tr>
</table>
</body>
</html>";
?>