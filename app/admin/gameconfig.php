<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$daccount = mb_input('daccount');
$deletea  = mb_input('deletea');
$deletef  = mb_input('deletef');
$deleteg  = mb_input('deleteg');
$deletem  = mb_input('deletem');
$deleten  = mb_input('deleten');
$deletes  = mb_input('deletes');
$empre    = mb_input('empre');
$reseta   = mb_input('reseta');

function callback($buffer) {

  return ($buffer);

}

ob_start("callback");

/*
 * The page's only authentication was here and was already commented out in
 * 2003: it restored $pw from the session and compared it to a hardcoded
 * password, redirecting to index.php on a mismatch. As it stands anyone who
 * can reach this file can drop every account, message and news table in the
 * game. Restoring a real check on the admin pages is Phase 3.
 *
 * if ($pw == "melvin") { } else { header("Location: index.php"); exit; }
 */
?>

<?php
if($deletem)	{

	include("include/connect.php");

	mysqli_query($db, "DELETE FROM messages"); 
	echo "<center>All of the messages have been deleted.<br><a href=gameconfig.php>Return</a></center>";
}
else	{	
?>

<form type="post" action="gameconfig.php">
<table border=1 bordercolor=#ffffff align=center bgcolor=#630000 cellpadding=0 cellspacing=0> 
	<tr>
		<td bgcolor=#404040 colspan=3><font color=#ffffff><b>You can delete all of the messages on the message table</b></font></td>
	<tr>
		<td><font color="#ffffff">DELETE all messages</font></td>
		<td><center><input type="submit" name="deletem" value="DELETE"></center></td>
</table>
</form>

<?php
}
if(!IsSet($deleten))
	{
?>
	
<br>

<form type="post" action="gameconfig.php">
	<table border=1 bordercolor=#ffffff align=center bgcolor=#630000 cellpadding=0 cellspacing=0>
		<tr>
		  <td bgcolor=#404040 colspan=2><font color=#ffffff><b>You can delete all of the news on the setnews tables</b></font></td>
		<tr>
			<td><font color="#ffffff">DELETE all news</font></td>
			<td><center><input type="submit" name="deleten" value="DELETE"></center></td>
				<input type="hidden" name="deleten" value="2"></td>
			
	

</table>
</form>
	<?php
		}
		else
		{	


	include("include/connect.php");


			mysqli_query($db, "DELETE FROM setnews1"); 
			mysqli_query($db, "DELETE FROM setnews2");
			mysqli_query($db, "DELETE FROM setnews3"); 
			mysqli_query($db, "DELETE FROM setnews4");
			mysqli_query($db, "DELETE FROM setnews5"); 
			mysqli_query($db, "DELETE FROM setnews6");
			mysqli_query($db, "DELETE FROM setnews7"); 
			mysqli_query($db, "DELETE FROM setnews8");
			mysqli_query($db, "DELETE FROM setnews9"); 
			mysqli_query($db, "DELETE FROM setnews10");
			echo"<center>All settlement news have been deleted.<br>
				<a href=http://www.medievalbattles.com/gameconfig.php>Return</a></center>";
		}
?>
<br>



<?php
if(!IsSet($deletea))
	{
?>
	
<br>

<form type="post" action="gameconfig.php">
	<table border=1 bordercolor=#ffffff align=center bgcolor=#630000 cellpadding=0 cellspacing=0>
		<tr>
		  <td bgcolor=#404040 colspan=2><font color=#ffffff><b>You can delete all accounts</b></font></td>
		<tr>
			<td><font color="#ffffff">DELETE all accounts</font></td>
			<td><center><input type="submit" name="deletea" value="DELETE"></center></td>
				<input type="hidden" name="deletea" value="3"></td>
			
	

</table>
</form>
	<?php
		}
		else
		{	


	include("include/connect.php");


			mysqli_query($db, "DELETE FROM buildings"); 
			mysqli_query($db, "DELETE FROM military");
			mysqli_query($db, "DELETE FROM return"); 
			mysqli_query($db, "DELETE FROM research");
			mysqli_query($db, "DELETE FROM explore"); 
			mysqli_query($db, "DELETE FROM user");
		
			echo"<center>All accounts have been deleted.<br>
				<a href=http://www.medievalbattles.com/gameconfig.php>Return</a></center>";
		}
?>


<br><br>



<?php
if(!IsSet($deleteg))
	{
?>
	
<br><br>

<form type="post" action="gameconfig.php">
	<table border=1 bordercolor=#ffffff align=center bgcolor=#630000 cellpadding=0 cellspacing=0>
		<tr>
		  <td bgcolor=#404040 colspan=2><font color=#ffffff><b>You can delete all of the guilds from the guild table</b></font></td>
		<tr>
			<td><font color="#ffffff">DELETE all guilds</font></td>
			<td><center><input type="submit" name="deleteg" value="DELETE"></center></td>
				<input type="hidden" name="deleteg" value="4"></td>
			
	

</table>
</form>
	<?php
		}
		else
		{	


		include("include/connect.php");

			mysqli_query($db, "DELETE FROM guild"); 
			echo"<center>All guilds have been deleted.<br>
				<a href=http://www.medievalbattles.com/gameconfig.php>Return</a></center>";
		}
?>

<br>


<?php
if(!IsSet($deletef))
	{
?>
	
<br><br>

<form type="post" action="gameconfig.php">
	<table border=1 bordercolor=#ffffff align=center bgcolor=#630000 cellpadding=0 cellspacing=0>
		<tr>
		  <td bgcolor=#404040 colspan=2><font color=#ffffff><b>You can delete all fields from the forums</b></font></td>
		<tr>
			<td><font color="#ffffff">DELETE all forums</font></td>
			<td><center><input type="submit" name="deletef" value="DELETE"></center></td>
				<input type="hidden" name="deletef" value="5"></td>
			
	

</table>
</form>
	<?php
		}
		else
		{	


include("include/connect.php");


			mysqli_query($db, "DELETE FROM setmain1"); 
			mysqli_query($db, "DELETE FROM setmsgs1");
			mysqli_query($db, "DELETE FROM setmain2"); 
			mysqli_query($db, "DELETE FROM setmsgs2");
			mysqli_query($db, "DELETE FROM setmain3"); 
			mysqli_query($db, "DELETE FROM setmsgs3");
			mysqli_query($db, "DELETE FROM setmain4"); 
			mysqli_query($db, "DELETE FROM setmsgs4");
			mysqli_query($db, "DELETE FROM setmain5"); 
			mysqli_query($db, "DELETE FROM setmsgs5");
			mysqli_query($db, "DELETE FROM setmain6"); 
			mysqli_query($db, "DELETE FROM setmsgs6");
			mysqli_query($db, "DELETE FROM setmain7"); 
			mysqli_query($db, "DELETE FROM setmsgs7");
			mysqli_query($db, "DELETE FROM setmain8"); 
			mysqli_query($db, "DELETE FROM setmsgs8");
			mysqli_query($db, "DELETE FROM setmain9"); 
			mysqli_query($db, "DELETE FROM setmsgs9");
			mysqli_query($db, "DELETE FROM setmain10"); 
			mysqli_query($db, "DELETE FROM setmsgs10");
			echo"<center>All forums have been deleted.<br>
				<a href=http://www.medievalbattles.com/gameconfig.php>Return</a></center>";
		}
?>

<br><br>


<?php
if(!IsSet($deletes))
	{
?>
	
<br>

<form type="post" action="gameconfig.php">
	<table border=1 bordercolor=#ffffff align=center bgcolor=#630000 cellpadding=0 cellspacing=0>
		<tr>
		  <td bgcolor=#404040 colspan=2><font color=#ffffff><b>You can change settlement table back to normal</b></font></td>
		<tr>
			<td><font color="#ffffff">RETURN all fields to normal</font></td>
			<td><center><input type="submit" name="deletes" value="RETURN"></center></td>
				<input type="hidden" name="deletes" value="5"></td>
			
	

</table>
</form>
	<?php
		}
		else
		{	


		include("include/connect.php");


			mysqli_query($db, "UPDATE settlement SET setname = \"None\"") or die(mysqli_error($db));
			mysqli_query($db, "UPDATE settlement SET setpic = \"http://www.medievalbattles.com/setpic.gif\"") or die(mysqli_error($db));
			mysqli_query($db, "UPDATE settlement SET setguild = \"None\"") or die(mysqli_error($db));

			mysqli_query($db, "UPDATE settlement SET setstrength = \"0\"") or die(mysqli_error($db));
			mysqli_query($db, "UPDATE settlement SET userid = \"0\"") or die(mysqli_error($db));
			mysqli_query($db, "UPDATE settlement SET fgold = \"0\"") or die(mysqli_error($db));
			mysqli_query($db, "UPDATE settlement SET firon = \"0\"") or die(mysqli_error($db));
			mysqli_query($db, "UPDATE settlement SET nap = \"None\"") or die(mysqli_error($db));
echo"<center>Settlement table has been restored to normal.<br>
				<a href=http://www.medievalbattles.com/gameconfig.php>Return</a></center>";
		}
?>




<?php
if(!IsSet($daccount))
	{
?>
	
<br>

<form type="post" action="gameconfig.php">
	<table border=1 bordercolor=#ffffff align=center bgcolor=#630000 cellpadding=0 cellspacing=0>
		<tr>
		  <td bgcolor=#404040 colspan=2><font color=#ffffff><b>Delete Account</b></font></td>
		<tr>
			<td><input type="text" name="empre" maxlength="50"></td>
			<td><center><input type="submit" name="daccount" value="DELETE ACCOUNT"></center></td>
				<input type="hidden" name="daccount" value="6"></td>
			
	

</table>
</form>
	<?php
		}
		else
		{	


							$hourdiff = "0"; 

							$timeadjust = ($hourdiff * 60 * 60);

							$clock = date(" d F h:i:s a",time() + $timeadjust);
		$dbnam= "medievalbattles_com";

include("include/connect.php");

		//SELECTING SETID
			$empreset = mysqli_query($db, "SELECT setid FROM user WHERE ename='$empre'");
			$esetid = mb_db_result($empreset,"esetid");
		//SELECTING EMAIL
			$theiremail = mysqli_query($db, "SELECT email FROM user WHERE ename='$empre'");
			$tmail = mb_db_result($theiremail,"tmail");
		//UPDATE NEWS
			$settable = "setnews" . "$esetid";
		mysqli_query($db, "INSERT INTO $settable (date, news) 
			VALUES	('$clock', '<font class=red><b>$empre</b> has been deleted by an administrator</font>') ");			


			mysqli_query($db, "DELETE FROM buildings WHERE email='$tmail'"); 
			mysqli_query($db, "DELETE FROM military WHERE email='$tmail'");
			mysqli_query($db, "DELETE FROM returntbl WHERE email='$tmail'"); 
			mysqli_query($db, "DELETE FROM research WHERE email='$tmail'");
			mysqli_query($db, "DELETE FROM explore WHERE email='$tmail'"); 
			mysqli_query($db, "DELETE FROM user WHERE email='$tmail'");

echo"<center>$empre has been deleted.<br>
				<a href=http://www.medievalbattles.com/gameconfig.php>Return</a></center>";die();
		}
?>


<?php
if(!IsSet($reseta))
	{
?>
	
<br>

<form type="post" action="gameconfig.php">
	<table border=1 bordercolor=#ffffff align=center bgcolor=#630000 cellpadding=0 cellspacing=0>
		<tr>
		  <td bgcolor=#404040 colspan=2><font color=#ffffff><b>You can reset all accounts</b></font></td>
		<tr>
			<td><font color="#ffffff">RESET all accounts</font></td>
			<td><center><input type="submit" name="reseta" value="RESET"></center></td>
				<input type="hidden" name="reseta" value="7"></td>
			
	

</table>
</form>
	<?php
		}
		else
		{	

include("include/connect.php");

// Reset User Table
mysqli_query($db, "UPDATE user SET gp = \"500000\"");
mysqli_query($db, "UPDATE user SET iron = \"10000\"");
mysqli_query($db, "UPDATE user SET exp = \"10000\"");
mysqli_query($db, "UPDATE user SET food = \"500\"");
mysqli_query($db, "UPDATE user SET land = \"300\"");
mysqli_query($db, "UPDATE user SET mts = \"200\"");
mysqli_query($db, "UPDATE user SET vote = \"0\"");
mysqli_query($db, "UPDATE user SET votefor = \"None\"");
mysqli_query($db, "UPDATE user SET sl = \"no\"");
mysqli_query($db, "UPDATE user SET exp2 = \"0\"");
mysqli_query($db, "UPDATE user SET fleets = \"4\"");
mysqli_query($db, "UPDATE user SET rectime = \"0\"");
mysqli_query($db, "UPDATE user SET mno = \"0\"");

// Reset Buildings Table
mysqli_query($db, "UPDATE buildings SET home = \"50\"");
mysqli_query($db, "UPDATE buildings SET barrack = \"75\"");
mysqli_query($db, "UPDATE buildings SET farm = \"50\"");
mysqli_query($db, "UPDATE buildings SET lab = \"75\"");
mysqli_query($db, "UPDATE buildings SET gm = \"50\"");
mysqli_query($db, "UPDATE buildings SET im = \"50\"");
mysqli_query($db, "UPDATE buildings SET aland = \"50\"");
mysqli_query($db, "UPDATE buildings SET amts = \"100\"");
mysqli_query($db, "UPDATE buildings SET dhome = \"0\"");
mysqli_query($db, "UPDATE buildings SET dbarrack = \"0\"");
mysqli_query($db, "UPDATE buildings SET dfarm = \"0\"");
mysqli_query($db, "UPDATE buildings SET dlab = \"0\"");
mysqli_query($db, "UPDATE buildings SET dgm = \"0\"");
mysqli_query($db, "UPDATE buildings SET dim = \"0\"");
mysqli_query($db, "UPDATE buildings SET Hhrs = \"0\"");
mysqli_query($db, "UPDATE buildings SET Bhrs = \"0\"");
mysqli_query($db, "UPDATE buildings SET Lhrs = \"0\"");
mysqli_query($db, "UPDATE buildings SET Fhrs = \"0\"");
mysqli_query($db, "UPDATE buildings SET Ghrs = \"0\"");
mysqli_query($db, "UPDATE buildings SET Ihrs = \"0\"");

// Reset Military Table
mysqli_query($db, "UPDATE military SET civ = \"5000\"");
mysqli_query($db, "UPDATE military SET recruits = \"250\"");
mysqli_query($db, "UPDATE military SET wizards = \"10\"");
mysqli_query($db, "UPDATE military SET warriors = \"10\"");
mysqli_query($db, "UPDATE military SET priests = \"10\"");
mysqli_query($db, "UPDATE military SET scientists = \"0\"");
mysqli_query($db, "UPDATE military SET thieves = \"0\"");
mysqli_query($db, "UPDATE military SET explorers = \"0\"");
mysqli_query($db, "UPDATE military SET maxciv = \"350\"");
mysqli_query($db, "UPDATE military SET ssword = \"0\"");
mysqli_query($db, "UPDATE military SET lsword = \"0\"");
mysqli_query($db, "UPDATE military SET axe = \"0\"");
mysqli_query($db, "UPDATE military SET gaxe = \"0\"");
mysqli_query($db, "UPDATE military SET club = \"0\"");
mysqli_query($db, "UPDATE military SET cweapon = \"Dagger\"");
mysqli_query($db, "UPDATE military SET cspell = \"Magic Missile\"");
mysqli_query($db, "UPDATE military SET cstaff = \"Quarter Staff\"");
mysqli_query($db, "UPDATE military SET icesword = \"0\"");
mysqli_query($db, "UPDATE military SET wararmor = \"Studded Leather\"");
mysqli_query($db, "UPDATE military SET wizarmor = \"Robe\"");
mysqli_query($db, "UPDATE military SET priarmor = \"Leather\"");
mysqli_query($db, "UPDATE military SET cs = \"0\"");
mysqli_query($db, "UPDATE military SET cm = \"0\"");
mysqli_query($db, "UPDATE military SET bp = \"0\"");
mysqli_query($db, "UPDATE military SET fp = \"0\"");
mysqli_query($db, "UPDATE military SET fullp = \"0\"");
mysqli_query($db, "UPDATE military SET sm = \"0\"");
mysqli_query($db, "UPDATE military SET warspeedw = \"0\"");
mysqli_query($db, "UPDATE military SET wizspeeds = \"0\"");
mysqli_query($db, "UPDATE military SET prispeedw = \"0\"");
mysqli_query($db, "UPDATE military SET warpower = \"3\"");
mysqli_query($db, "UPDATE military SET wizpower = \"2\"");
mysqli_query($db, "UPDATE military SET pripower = \"2\"");
mysqli_query($db, "UPDATE military SET priout = \"0\"");
mysqli_query($db, "UPDATE military SET warspeeda = \"0\"");
mysqli_query($db, "UPDATE military SET wizspeeda = \"0\"");
mysqli_query($db, "UPDATE military SET prispeeda = \"0\"");
mysqli_query($db, "UPDATE military SET wardef = \"6\"");
mysqli_query($db, "UPDATE military SET wizdef = \"10\"");
mysqli_query($db, "UPDATE military SET prideef = \"8\"");
mysqli_query($db, "UPDATE military SET dbwar = \"0\"");
mysqli_query($db, "UPDATE military SET dbwiz = \"0\"");
mysqli_query($db, "UPDATE military SET dbpri = \"0\"");
mysqli_query($db, "UPDATE military SET dbwar2 = \"0\"");
mysqli_query($db, "UPDATE military SET dbwiz2 = \"0\"");
mysqli_query($db, "UPDATE military SET dbpri2 = \"0\"");
mysqli_query($db, "UPDATE military SET dbscientist = \"0\"");
mysqli_query($db, "UPDATE military SET dbexplorer = \"0\"");
mysqli_query($db, "UPDATE military SET dbthief = \"0\"");

// Reset Return Table
mysqli_query($db, "UPDATE returntbl SET war1 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET war2 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET war3 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET war4 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET wiz1 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET wiz2 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET wiz3 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET wiz4 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET pri1 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET pri2 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET pri3 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET pri4 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET time1 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET time2 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET time3 = \"0\"");
mysqli_query($db, "UPDATE returntbl SET time4 = \"0\"");

// Reset Research Table
mysqli_query($db, "UPDATE research SET r1 = \"0\"");
mysqli_query($db, "UPDATE research SET r2 = \"0\"");
mysqli_query($db, "UPDATE research SET r3 = \"0\"");
mysqli_query($db, "UPDATE research SET r4 = \"0\"");
mysqli_query($db, "UPDATE research SET r5 = \"0\"");
mysqli_query($db, "UPDATE research SET r1pts = \"0\"");
mysqli_query($db, "UPDATE research SET r2pts = \"0\"");
mysqli_query($db, "UPDATE research SET r3pts = \"0\"");
mysqli_query($db, "UPDATE research SET r4pts = \"0\"");
mysqli_query($db, "UPDATE research SET r5pts = \"0\"");

// Reset Explore Table
mysqli_query($db, "UPDATE explore SET expland = \"0\"");
mysqli_query($db, "UPDATE explore SET expmt = \"0\"");
mysqli_query($db, "UPDATE explore SET landhrs = \"0\"");
mysqli_query($db, "UPDATE explore SET mthrs= \"0\"");


			echo"<center>All accounts have been reset.<br>
				<a href=http://www.medievalbattles.com/gameconfig.php>Return</a></center>";
		}
?>

<br>
<br>
<br>
<?php

include("include/connect.php");
	$tablename = "user";

echo "
	<table border=1 align=center width=\"100%\">
	 <tr>
	  <td>Empire Name</td>
	  <td>Password</td>
	  <td>Email</td>
	  <td>AIM</td>
	  <td>MSN</td>
	  <td>Host Name</td>
";




		$query_string = "SELECT ename, pw, email, aim, msn, ip FROM user ORDER BY ip";
		$result_id = mysqli_query($db, $query_string);
		while ($row = mysqli_fetch_row($result_id))
		    {

			$ONLINE_NO = mysqli_query($db, "SELECT online FROM user WHERE ename='$row[0]'");	
				$OLINE = mb_db_result($ONLINE_NO,"OLINE");
	
			if($OLINE == 1)
				{$O_line = "<font color=red>*</font>";}
			else{$O_line = "";}


		    print("<TR ALIGN=center VALIGN=TOP colspan=7>
				<td>$row[0] $O_line</td>
				<td>$row[1]</td>
				<td>$row[2]</td>
				<td>$row[3]</td>
				<td>$row[4]</td>
				<td>$row[5]</td>\n");
		    }

		echo "</table>"; 

?>

<?php

ob_end_flush();

?>	