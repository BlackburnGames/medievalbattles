<?php
include("include/session.php");

function callback($buffer)
{
  return ($buffer);
}

// ob_start("callback");

include("functions.php");

//$version_query = $db->query("SELECT version FROM game_info") or die(mysqli_error($db));
//  $version = mb_db_result($version_query, "version");
?>
<html>
<head>
<title>Medieval Battles .:::. v6: Kupo Remix Edition</title>
  <link rel=stylesheet type="text/css" href="css/ingame.css">
</head>

<body>
<table class=outer border="0" cellpadding="1" cellspacing="0"  width="100%">
 <TR>
  <TD valign="top" colspan="2">
   <table border="0" width="100%" cellpadding=0 cellspacing=0>
  <tr>
</TD><table border=1 width=100% align=center bordercolor=#212121 cellspacing=0 cellpadding=0><tr><td>
<table border=1 width=100% align=center bordercolor=#2a2929 cellspacing=0 cellpadding=0><tr><td>
<table border=1 width=100% align=center bordercolor=#323131 cellspacing=0 cellpadding=0><tr><td>
   <table border="1" cellpadding="2" cellspacing="0" bgcolor="#336600" bordercolor="#3a3838" width="100%">
  <tr>
   <td class=top width=20%><b class=rtop><font class=red>Gold Pieces: </font></b><?php echo "$gp"; ?></font></td>
   <td class=top width=20%><b class=rtop><font class=red>Civilians: </font></b><?php echo "$civ"; ?></font></td>
   <td class=top width=20%><b class=rtop><font class=red>Land: </font></b> <?php echo "$land"; ?></font></td>
   <td class=top width=20%><b class=rtop><font class=red>Mountains: </font></b><?php echo "$mts"; ?></font></td>
   <td class=top width=20%><b class=rtop><font class=red>Experience: </font></b><?php echo "$exp"; ?></font></td>
  </table></td></table></td></table></td></table>
</TD>
</TR>
<TR valign="top">
 <TD width="19%">
  <?php
    include("include/ignavbar.php");
  ?>
 </TD>
 <TD width="85%">

<div align=center><font class=yellow size=2px>There are going to be bugs obviously as this is deprecated code. If you come across a bug, go ahead and report it to <a href="mailto:shoxfusion@gmail.com?subject=v6+Bug">shoxfusion@gmail.com</a> and we'll see what we can do to fix it. Don't get your hopes up, however.</font></div><br>

<?php
// safe mode notice
if($safemode > 0) {
  echo"<div align=center><font class=blue><b>You are in Safe Mode for $safemode more ticks</b></font></div>";
}

// are ticks running?
$tickk = $db->query("SELECT tick FROM game_info");
$tick = mb_db_result($tickk, "tick");
if($tick == 'yes')    {
  echo"<font class=yellow size=4px><center><br>Tick in progress.</center>";
  die();
}

//  is their account activated?
if($validate_checker != 2)  {
  echo"<div align=center><font class=yellow size=4px><br>You must activate your account.</font></div>";
  echo"<div align=center><font class=yellow size=2px><a href=activate_code.php>Resend activation code.</a></font></div>";
  die();
}

echo "<br><br>";
?>