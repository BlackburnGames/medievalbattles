<?
echo "
<form type=get action=barter.php>
<table border=1 bordercolor=#000000 align=center width=80%>	
	<table border=1 bordercolor=#000000 align=center width=\"90%\" cellpadding=0>
		<tr>
			<td class=main colspan=7><b class=reg>Barter</b></td>
		<tr>
			<td class=main2></td>
			<td class=main2 width=20%><b class=reg>Seller</b></td>
			<td class=main2><b class=reg>Type</b></td>
			<td class=main2><b class=reg>Amount</b></td>
			<td class=main2><b class=reg>Method</b></td>
			<td class=main2><b class=reg>Cost</b></td>
			<td class=main2 width=20%><b class=reg>Barter</b></td>";

$query_string = "SELECT seller, type, amount, method, cost, barterid, userid FROM barter WHERE page != 'guild' ORDER BY type ASC";
$result_id = mysqli_query($db, $query_string);
while ($row = mysqli_fetch_row($result_id))	{

	// The seller's userid comes back from the listing row, so it is stored
	// data rather than request input and the first-order audit cannot see it.
	// It is still interpolated into two queries, one of which deletes.
	$q_seller = mb_sql_int($row[6]);

	$result = mysqli_query($db, "SELECT setid FROM user WHERE userid=$q_seller");
	$s_sel = mysqli_fetch_array($result);
		$s_sel = $s_sel[0];
		// A listing whose seller no longer exists is swept up here.
		if($s_sel == "")	 {	mysqli_query($db, "DELETE FROM barter WHERE userid=$q_seller");	}

	if($row[0] == $ename)	{	$endnow = "<br><a href=" . mb_attr("barter.php?end=true&bid=$row[5]") . ">End</a>";	}
	else	{	$endnow = "";	}

	if($row[3] == 'gp')	 {	$row[3] = "Gold";	}
	else	{	$row[3] = "Iron";	}
	
	$row[2] = number_format($row[2]);
	$row[4] = number_format($row[4]);
	$row_num = $row_num + 1;
echo "
		<tr align=center valign=top colspan=5>
			<td bgcolor=#404040>$row_num</td>
			<td bgcolor=#404040><a href=" . mb_attr("messaging.php?value=$row[0]&snum=$s_sel&setchg=1") . ">" . mb_h($row[0]) . "(" . mb_h($s_sel) . ")</a></td>
			<td bgcolor=#404040>" . mb_h($row[1]) . "</td>
			<td bgcolor=#404040>" . mb_h($row[2]) . "</td>
			<td bgcolor=#404040>" . mb_h($row[3]) . "</td>
			<td bgcolor=#404040>" . mb_h($row[4]) . "</td>
			<td bgcolor=#404040><a href=" . mb_attr("barter.php?barter=1&bid=$row[5]") . ">Barter</a>$endnow</td>\n";
}

echo "
	</table><br>
	</form>";

?>