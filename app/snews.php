<?php include("include/igtop.php");?>

<?	
	$SN_query = ("SELECT setid FROM setnews WHERE setid='$setid'");
	$SN_result = mysqli_query($db, $SN_query);
	$SN_check = mysqli_fetch_array($SN_result); 
		if($SN_check[0] == "" OR $SN_check[0] == 0)
			{echo"<div align=center><font class=yellow>Your settlement does not have any news to display.</font></div>";die();}
?>	   

<div align=center>		
	<font class=red>Empire Joining/Deleting</font><br>
	<!-- <font class=green>Donating/Receiving from Funds</font><br> -->
	<font class=blue>Leaving/Joining a Guild</font><br>
	<font class=yellow>Attacking (successful/unsuccessful)</font><br>
	<font class=orange>Successfully defended empire</font><br>
	<font class=lg>Unsuccessfully defended empire</font><br>
</div>


<?php
	include("include/connect.php");
	$tablename = 'user';
	// $tablename was always ignored -- the query below hardcodes setnews -- and
	// the second parameter used to be the legacy mysql link, which mysqli takes
	// from the shared $db handle instead.
	function display_db_table($tablename)
	{
			Global $setid, $db;

			$query_string = "SELECT date, news, class FROM setnews  WHERE setid='$setid' ORDER BY date DESC";
			$result_id = mysqli_query($db, $query_string);

			 
			while ($row = mysqli_fetch_row($result_id))
		
			{
				// Was a generic loop over mysqli_num_fields(), printing every
				// selected column raw. The columns are named now because they
				// render differently: the date is text, and the news is text
				// plus its category. See include/news.php.
				print("<TR ALIGN=center VALIGN=TOP>");
				print("<TD bgcolor=#404040 align=left>" . mb_h($row[0]) . "</TD>\n");
				print("<TD bgcolor=#404040 align=left>" . mb_news_html($row[2], $row[1]) . "</TD>\n");
		
				print("</TR>\n");
			}
		
		print("</TABLE>\n");
	}

	?>
	

		
	<table border=0 bordercolor="#404040" width="95%" align=center cellspacing=1>
	  <tr>
	    <td colspan=4 class=main><b class=reg>News for Settlement <? echo"$setid"; ?></b></td>
	  <tr align=left>
		<td class=main2 width="20%" align=left><b class=reg>Date/Time</b></td>
		<td class=main2 align=left><b class=reg width="80%">News</b></td>
		
	  <?php display_db_table("user");?>

<!-- body ends here -->
</TD>
</TR>
</TABLE>
</BODY>
</HTML>