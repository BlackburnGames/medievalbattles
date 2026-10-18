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
				<td colspan=2><b>Delete Kingdom</b></td>
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
	// below, and the last of them removes the kingdom. Not the account: that
	// lives in the `account` table and outlasts the round, and its owner can
	// sign up again for a new kingdom with the same address and password.

	//SELECTING SETID
		$empreset = mb_query($db, "SELECT setid FROM user WHERE ename=?", array($empre));
		$esetid = mb_db_result($empreset,"esetid");
	//SELECTING THE KINGDOM
		$theirid = mb_query($db, "SELECT userid FROM user WHERE ename=?", array($empre));
		$tuid = mb_db_result($theirid,"tuid");

	// No such empire: say so rather than running six DELETEs on nothing. When
	// they were keyed by address, an unset one read `email=''`, which matched
	// any row whose address was blank.
	if ($tuid === null) {
		echo "<center>No empire called " . htmlspecialchars($empre) . ".<br>";
		die();
	}

	$news    = "[b]{$empre}[/b] has been deleted by an administrator";

	mb_news_set($db, "$clock", 'red', $news, $esetid, 0);

	mb_query($db, "DELETE FROM buildings WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM military WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM returntbl WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM research WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM explore WHERE userid=?", array((int) $tuid));
	mb_query($db, "DELETE FROM user WHERE userid=?", array((int) $tuid));

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