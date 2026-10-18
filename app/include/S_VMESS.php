<font class='orange'><div align='center'><b>All messages get deleted after 4 days</b></div></font><br>

<?php

echo "  <br><br>
	<table border='1' bordercolor='#000000' align='center' width='90%' cellpadding='3' cellspacing='0'>
		<tr>
			<td class='main' colspan='5'><b class='reg'>View Messages</b></td>
		<tr>
			<td class='main2'><b class=reg>Origin</b></td>
			<td class='main2' width='80%'><b class=reg>Message</b></td>";

$result_id = mb_query($db, "SELECT origin, datesent, message, mid FROM messages WHERE yourid=? ORDER BY datesent DESC", array($userid));
while ($row = mysqli_fetch_array($result_id))	{
	$origin = $row['origin']; $message = $row['message']; $datesent = $row['datesent'];
	// $link is markup in one branch and a bare name in the other, so it is
	// escaped where it is built rather than where it is echoed -- escaping it at
	// the echo would double-escape the anchor below.
	$link = mb_h($origin);

	// check to see if he/shes allready a GL
	$sresult = mb_query($db, "SELECT setid FROM user WHERE round_id=? AND ename=?", array((int) $round_id, $origin));
	$setcheck = mysqli_fetch_array($sresult);

	if($setcheck[0] != "")	{
		$their_set = mb_query($db, "SELECT setid FROM user WHERE round_id=? AND ename=?", array((int) $round_id, $origin));
			$snum = mb_db_result($their_set,"snum");
		$sender = urlencode($origin);
		$link = "<a href=" . mb_attr("messaging.php?value=$sender&snum=$snum&setchg=1") . ">"
			. mb_h($origin) . "(" . mb_h($snum) . ")</a>";
	}
	echo "
		<tr align='center' valign='top' colspan='6'>
			<td bgcolor='#404040'>$link</td>
			<td bgcolor='#404040' align='left' width='75%'>" . mb_rich($message) . "<br><br><font size='1px' class='orange'><i>Recieved " . mb_h($datesent) . "</i></font></td>\n";
}
echo "
	</table><br>";
?>