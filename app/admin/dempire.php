<?php

// A deletion, so from a POST body alone: the form used to say type=post,
// which is not an attribute, and so submitted by GET -- the deletion was a
// link anyone could be sent.
$daccount = isset($_POST['daccount']) ? (string) $_POST['daccount'] : null;
$empre    = isset($_POST['empre']) ? (string) $_POST['empre'] : '';

include("include/igtop.php");

if(!IsSet($daccount))	{
	echo "<form method=post action=dempire.php>
		<table border=1 bordercolor=#000000 align=center cellpadding=0 cellspacing=0>
			<tr>
				<td colspan=2><b>Delete Kingdom</b></td>
			<tr>
				<td><input type=\"text\" name=\"empre\" maxlength=\"50\"></td>
				<td><input type=\"submit\" name=\"daccount\" value=\"Delete\"></td>
		</table>
	</form>";
}
else	{
	// include/kingdoms.php, shared with gameconfig.php. The account is kept:
	// it outlasts the round, and its owner can join again.
	require_once __DIR__ . '/include/kingdoms.php';
	echo "<center>" . htmlspecialchars(mb_admin_delete_kingdom($db, $empre)) . "<br>";
	die();
}

echo "
</td>
</tr>
</table>
</body>
</html>";
?>