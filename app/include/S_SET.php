<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$snum = mb_input('snum');


if($snum != "")  {  $N_NUM = $snum; }
if($snum == "") { $N_NUM = $csnum;  }

$ssettlementpic = mysqli_query($db, "SELECT setpic FROM settlement WHERE setid = " . mb_sql_int($N_NUM));
  $settlepic = mb_db_result($ssettlementpic, "setpic", 0);
$ssettlementname = mysqli_query($db, "SELECT setname FROM settlement WHERE setid = " . mb_sql_int($N_NUM));
  $settlename = mb_db_result($ssettlementname, "setname", 0);

echo "
<div align=center>
<table border=0 width=300 align=center>
  <tr>
    <td width=20% align=center colspan=2><b><font class=red>Name:</font></b> " . mb_h($settlename) . " <font class=red>[</font>" . mb_h($N_NUM) . "<font class=red>]</font></td>";
if($settlepic != "")  {
  echo "<tr>
    <td colspan=2><img src=" . mb_attr($settlepic) . " width=300 height=200></td>";
}
echo "
</table>
</div>

<center><br><br>
<font class=red>Empire is a settlement leader</font><br>
<font class=orange>Empire is in safe mode</font><br>
<font class=blue>Your empire</font></center><br><br>

<table border=1 bordercolor=#000000 align=center width=80% cellpadding = 0 cellspacing = 0>
  <tr>
    <td class=main colspan=8><b class=reg>Settlement: " . mb_h($N_NUM) . "</b></td>
  <tr>
    <td class=main2 width=><b class=reg>Empire Name</b></td>
    <td class=main2 width=><b class=reg>Race</b></td>
    <td class=main2 width=><b class=reg>Class</b></td>
    <td class=main2 width=><b class=reg>Mountains</b></td>
    <td class=main2 width=><b class=reg>Land</b></td>
    <td class=main2 width=><b class=reg>Experience</b></td>
    <td class=main2 width=><b class=reg>Guild</b></td>";

$query_string = "SELECT userid, ename, race, class, mts, land, exp, guild FROM user WHERE setid = " . mb_sql_int($N_NUM) . " ORDER BY userid ASC";
$result_id = mysqli_query($db, $query_string);
while ($row = mysqli_fetch_row($result_id))  {

  $ONLINE_NO = mysqli_query($db, "SELECT online FROM user WHERE userid='$row[0]'");
    $OLINE = mb_db_result($ONLINE_NO,"OLINE");

  if($OLINE == 1) { $O_line = "<font class=red>*</font>";  }
  else  { $O_line = ""; }

  //  check to see if sl is there
  $SL_result = mysqli_query($db, "SELECT sl FROM user WHERE userid='$row[0]'");
    $SLcheck = mysqli_fetch_array($SL_result);

  if($SLcheck[0] == 'yes')  {
    $SL_SELECT = mysqli_query($db, "SELECT ename FROM user WHERE sl='yes' AND setid=" . mb_sql_int($N_NUM));
      $SL_S = mb_db_result($SL_SELECT,"SL_S");
  }

  $SAFEMODECHECK = mysqli_query($db, "SELECT safemode FROM user WHERE  userid='$row[0]'");
    $S_M_CHECK = mb_db_result($SAFEMODECHECK,"S_M_CHECK");

  $color = "#404040";
  $yclass = "grey";

  if($S_M_CHECK > 0)  { $yclass= "orange";  }
  if($row[1] == "$SL_S")  { $yclass= "red"; }
  if($row[1] == "$ename") { $yclass = "blue"; }

  $row[4] = number_format($row[4]);
  $row[5] = number_format($row[5]);
  $row[6] = number_format($row[6]);

  echo "
  <tr align=center valign=top colspan=7>
    <td bgcolor=$color><a href=" . mb_attr("messaging.php?send_to=$row[0]&snum=$N_NUM&setchg=1") . "><font class=$yclass>$row[1]</a>$O_line</td>
    <td bgcolor=$color><font class=$yclass>$row[2]</td>
    <td bgcolor=$color><font class=$yclass>$row[3]</td>
    <td bgcolor=$color><font class=$yclass>$row[4]</td>
    <td bgcolor=$color><font class=$yclass>$row[5]</td>
    <td bgcolor=$color><font class=$yclass>$row[6]</td>
    <td bgcolor=$color><font class=$yclass>$row[7]</td>\n";
}

  $COUNT_MEMBERS = mysqli_query($db, "SELECT count(userid) FROM user WHERE setid = " . mb_sql_int($N_NUM));
    $C_MEM = mb_db_result($COUNT_MEMBERS,"C_MEM");

  $totT = $C_MEM;

while($C_MEM < 10 AND $totT < 10) {
  echo"
  <tr>
    <td class=inner2><b class=reg>--</b></td>
    <td class=inner2><b class=reg>--</b></td>
    <td class=inner2><b class=reg>--</b></td>
    <td class=inner2><b class=reg>--</b></td>
    <td class=inner2><b class=reg>--</b></td>
    <td class=inner2><b class=reg>--</b></td>
    <td class=inner2><b class=reg>--</b></td>";
  $totT = $totT + 1;
}
echo "
</table><br>";

$SET_STRENGTH = mysqli_query($db, "SELECT sum(exp) FROM user WHERE setid=" . mb_sql_int($N_NUM));
  $S_STRENGTH = mb_db_result($SET_STRENGTH,"S_STRENGTH");
  $S_STRENGTH = number_format($S_STRENGTH);

echo "<br><div align=center><b><font class=red>Settlement Strength:</font></b> $S_STRENGTH</div></td></table>";
?>