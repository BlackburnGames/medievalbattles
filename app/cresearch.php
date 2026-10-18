<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$first = mb_input('first');
$ur1   = mb_input('ur1');
$ur10  = mb_input('ur10');
$ur11  = mb_input('ur11');
$ur12  = mb_input('ur12');
$ur13  = mb_input('ur13');
$ur14  = mb_input('ur14');
$ur15  = mb_input('ur15');
$ur16  = mb_input('ur16');
$ur17  = mb_input('ur17');
$ur18  = mb_input('ur18');
$ur2   = mb_input('ur2');
$ur3   = mb_input('ur3');
$ur4   = mb_input('ur4');
$ur5   = mb_input('ur5');
$ur6   = mb_input('ur6');
$ur7   = mb_input('ur7');
$ur8   = mb_input('ur8');
$ur9   = mb_input('ur9');


include("include/igtop.php");
 		
echo "<div align=center><b class=reg>[ <a href=research.php>Send Sages</a> ]</b></center><br>";
		
if(!IsSet($first))	
{
	include("include/S_CRES.php");
}
else	
{
	include("include/nexplode.php");

	if($res['r1'] < $ur1 OR $res['r2'] < $ur2 OR $res['r3'] < $ur3 OR $res['r4'] < $ur4 OR $res['r5'] < $ur5 OR $res['r6'] < $ur6 OR $res['r7'] < $ur7 OR $res['r8'] < $ur8 OR $res['r9'] < $ur9 OR $res['r10'] < $ur10 OR $res['r11'] < $ur11 OR $res['r12'] < $ur12 OR $res['r13'] < $ur13 OR $res['r14'] < $ur14 OR $res['r15'] < $ur15 OR $res['r16'] < $ur16 OR $res['r17'] < $ur17 OR $res['r18'] < $ur18)	 
	{
		echo"<div align=center><font class=yellow>You cannot cancel that many sages.</font></div>"; 
		include("include/S_CRES.php"); 
		die();
	}
	elseif($ur1 < 0 OR $ur2 < 0 OR $ur3 < 0 OR $ur4 < 0 OR $ur5 < 0 OR $ur6 < 0 OR $ur7 < 0 OR $ur8 < 0 OR $ur9 < 0 OR $ur10 < 0 OR $ur11 < 0 OR $ur12 < 0 OR $ur13 < 0 OR $ur14 < 0 OR $ur15 < 0 OR $ur16 < 0 OR $ur17 < 0 OR $ur18 < 0)	
	{
		echo"<div align=center><font class=yellow>You cannot cancel negative or 0 sages.</font></div>";  
		include("include/S_CRES.php");
		die();
	}
	elseif($ur1 > 0  AND $race == 'Giant')	
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur2 > 0 AND $race == 'Giant')	 
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur3 > 0 AND $race == 'Giant')	 
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur4 > 0 AND $race == 'Giant')	 
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur5 > 0 AND $race == 'Giant')	 
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur6 > 0 AND $race == 'Giant')	 
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur7 > 0 AND $race == 'Giant')	 
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur8 > 0 AND $race == 'Giant')	 
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur9 > 0 AND $race == 'Giant')	 
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	elseif($ur10 > 0 AND $race == 'Giant')	
	{
		echo"<div align=center><font class=yellow>You cannot research these items being that you are a Giant.</font></div>";
		include("include/S_CRES.php");
		die();
	}
	else	{
			 
		$asages = $asages + ($ur1 + $ur2 + $ur3 + $ur4 + $ur5 + $ur6 + $ur7 + $ur8 + $ur9 + $ur10 + $ur11 + $ur12 + $ur13 + $ur14 + $ur15 + $ur16 + $ur17 + $ur18);
		$sages = $sages + ($ur1 + $ur2 + $ur3 + $ur4 + $ur5 + $ur6 + $ur7 + $ur8 + $ur9 + $ur10 + $ur11 + $ur12 + $ur13 + $ur14 + $ur15 + $ur16 + $ur17 + $ur18);
		$r1 = $res['r1'] - $ur1;
		$r2 = $res['r2'] - $ur2;
    	$r3 = $res['r3'] - $ur3;
		$r4 = $res['r4'] - $ur4;
    	$r5 = $res['r5'] - $ur5;
		$r6 = $res['r6'] - $ur6;
		$r7 = $res['r7'] - $ur7;
	 	$r8 = $res['r8'] - $ur8;
	 	$r9 = $res['r9'] - $ur9;
     	$r10 = $res['r10'] - $ur10;
	 	$r11 = $res['r11'] - $ur11;
	 	$r12 = $res['r12'] - $ur12;	
		$r13 = $res['r13'] - $ur13;
		$r14 = $res['r14'] - $ur14;
		$r15 = $res['r15'] - $ur15;
		$r16 = $res['r16'] - $ur16;
		$r17 = $res['r17'] - $ur17;
		$r18 = $res['r18'] - $ur18;

	 	mb_query($db, "UPDATE military SET sages=? WHERE email=? AND pw=?", array($sages, $email, $pw));
	  	mb_query($db, "UPDATE research SET r1=? WHERE email=? AND pw=?", array($r1, $email, $pw));
	  	mb_query($db, "UPDATE research SET r2=? WHERE email=? AND pw=?", array($r2, $email, $pw));
      	mb_query($db, "UPDATE research SET r3=? WHERE email=? AND pw=?", array($r3, $email, $pw));
      	mb_query($db, "UPDATE research SET r4=? WHERE email=? AND pw=?", array($r4, $email, $pw));
      	mb_query($db, "UPDATE research SET r5=? WHERE email=? AND pw=?", array($r5, $email, $pw));
		mb_query($db, "UPDATE research SET r6=? WHERE email=? AND pw=?", array($r6, $email, $pw));
		mb_query($db, "UPDATE research SET r7=? WHERE email=? AND pw=?", array($r7, $email, $pw));
	  	mb_query($db, "UPDATE research SET r8=? WHERE email=? AND pw=?", array($r8, $email, $pw));
	  	mb_query($db, "UPDATE research SET r9=? WHERE email=? AND pw=?", array($r9, $email, $pw));
      	mb_query($db, "UPDATE research SET r10=? WHERE email=? AND pw=?", array($r10, $email, $pw));
      	mb_query($db, "UPDATE research SET r11=? WHERE email=? AND pw=?", array($r11, $email, $pw));
      	mb_query($db, "UPDATE research SET r12=? WHERE email=? AND pw=?", array($r12, $email, $pw));
		mb_query($db, "UPDATE research SET r13=? WHERE email=? AND pw=?", array($r13, $email, $pw));
		mb_query($db, "UPDATE research SET r14=? WHERE email=? AND pw=?", array($r14, $email, $pw));
		mb_query($db, "UPDATE research SET r15=? WHERE email=? AND pw=?", array($r15, $email, $pw));
		mb_query($db, "UPDATE research SET r16=? WHERE email=? AND pw=?", array($r16, $email, $pw));
		mb_query($db, "UPDATE research SET r17=? WHERE email=? AND pw=?", array($r17, $email, $pw));
		mb_query($db, "UPDATE research SET r18=? WHERE email=? AND pw=?", array($r18, $email, $pw));

		echo "<div align=center><font class=yellow>You have successfully cancelled your sages.</font></div>";
		include("include/S_CRES.php");
		die();
	}
}
?>

</td>
</tr>
</table>
</body>
</html>