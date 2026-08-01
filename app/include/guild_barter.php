<?
echo "
<form type=get action=guildbarter.php>
<table border=1 bordercolor=#000000 align=center width=80%>
	<table border=1 bordercolor=#000000 align=center width=\"90%\" cellpadding=0>
		<tr>
			<td class=main colspan=7><b class=reg>Guild Barter</b></td>
		<tr>
			<td class=main2></td>
			<td class=main2 width=20%><b class=reg>Seller</b></td>
			<td class=main2><b class=reg>Type</b></td>
			<td class=main2><b class=reg>Amount</b></td>
			<td class=main2><b class=reg>Method</b></td>
			<td class=main2><b class=reg>Cost</b></td>
			<td class=main2 width=20%><b class=reg>Barter</b></td>";

// The guild name is the player's own, but it reaches the WHERE clause from the
// `user` row rather than from a literal, so it is escaped like any other value.
$query_string = "SELECT seller, type, amount, method, cost, barterid, userid FROM barter WHERE page='guild' AND guild=" . mb_sql_str($db, $empireguild) . " ORDER BY type ASC";
$result_id = mysqli_query($db, $query_string);
while ($row = mysqli_fetch_row($result_id))	{

	$q_seller = mb_sql_int($row[6]);

	$result = mysqli_query($db, "SELECT setid FROM user WHERE userid=$q_seller");
	$s_sel = mysqli_fetch_array($result);
		$s_sel = $s_sel[0];
		if($s_sel == "")	 {	mysqli_query($db, "DELETE FROM barter WHERE userid=$q_seller");	}

	// Both links pointed at barter.php, which lists only the open board. So
	// every guild listing's Barter link went to the handler that refuses to
	// sell it -- and before that handler was scoped to its own board, it sold
	// it anyway, without ever checking that the buyer was in the guild.
	if($row[0] == $ename)	{	$endnow = "<br><a href=guildbarter.php?end=true&bid=$row[5]>End</a>";	}
	else	{	$endnow = "";	}

	if($row[3] == 'gp')	 {	$row[3] = "Gold";	}
	else	{	$row[3] = "Iron";	}
	
	$row[2] = number_format($row[2]);
	$row[4] = number_format($row[4]);
	$row_num = $row_num + 1;
echo "
		<tr align=center valign=top colspan=5>
			<td bgcolor=#404040>$row_num</td>
			<td bgcolor=#404040><a href=\"messaging.php?value=$row[0]&snum=$s_sel&setchg=1\">$row[0]($s_sel)</a></td>
			<td bgcolor=#404040>$row[1]</td>
			<td bgcolor=#404040>$row[2]</td>
			<td bgcolor=#404040>$row[3]</td>
			<td bgcolor=#404040>$row[4]</td>
			<td bgcolor=#404040><a href=guildbarter.php?barter=1&bid=$row[5]>Barter</a>$endnow</td>\n";
}

echo "
	</table><br>
	</form>";

?>