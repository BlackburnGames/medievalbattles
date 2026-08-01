<?php
include("include/igtop.php");

$uhome = $_POST['uhome'];
$ubarrack = $_POST['ubarrack'];
$ufarm = $_POST['ufarm'];
$uwp = $_POST['uwp'];
$ulmill = $_POST['ulmill'];
$ugm = $_POST['ugm'];
$uim = $_POST['uim'];
$update = $_POST['update'];

echo "<center><b class=reg>| <a href=buildings.php> -Construct- </a> |</b></center><br>";

if(!IsSet($update)) {
  include("include/S_DBUILD.php");
}
else  {
  include("include/nexplode.php");

  if($ulmill > 0 && $res['r13pts'] < 125000)  {
    echo"<div align=center><font class=yellow>You have to research Archery before this building becomes available to demolish.</font></div>";
    include("include/S_BUILD.php");
    die();
  }
  elseif($gm < $ugm || $im < $uim)  {
    echo"<div align=center><font class=yellow>You cannot demolish that many mines.</font></div>";
    include("include/S_DBUILD.php");
    die();
  }
  elseif($uhome < 0 || $ubarrack < 0 || $ufarm < 0 || $uwp < 0 || $ugm < 0 || $uim < 0 || $ulmill < 0)  {
    echo"<div align=center><font class=yellow>You cannot demolish negative or 0 buildings.</font></div>";
    include("include/S_DBUILD.php");
    die();
  }
  elseif ($uhome > $home || $ufarm > $farm || $uwp > $wp || $ubarrack > $barrack || $ulmill > $lmill) {
    echo"<div align=center><font class=yellow>You cannot demolish that many buildings.</font></div>";
    include("include/S_DBUILD.php");
    die();
  }
  elseif($gp < ($ugm + $uim + $uhome + $ubarrack + $ufarm + $uwp) * 75) {
    echo"<div align=center><font class=yellow>You do not have enough gold pieces to carry out your orders.</div></font>";
    include("include/S_DBUILD.php");
    die();
  }
  elseif($uwp > 0 && $race == 'Orc') {
    echo"<div align=center><font class=yellow>Being that you are an orc, you cannot construct the wooden platform.</font></div>";
    include("include/S_BUILD.php");
    die();
  }
  else  {

    include("include/connect.php");

    $gp = $gp - (($ugm + $uim + $uhome + $ubarrack + $ufarm + $uwp + $ulmill) * 75);
    $gp = floor($gp);

    $amts = $amts + ($ugm + $uim);
    $aland = $aland + ($uhome + $ubarrack + $ufarm + $uwp + $ulmill);

    $forexp2 = $exp2 - (($uhome + $ubarrack + $ufarm + $uwp + $ulmill) * $landexp) + (($ugm + $uim) * $mtexp);

    $home = $home - $uhome;
    $barrack = $barrack - $ubarrack;
    $farm = $farm - $ufarm;
    $wp = $wp - $uwp;
    $lmill = $lmill - $ulmill;
    $gm = $gm - $ugm;
    $im = $im - $uim;

    mysqli_query($db, "UPDATE buildings SET amts='$amts' WHERE email='$email' AND pw='$pw'");
      mysqli_query($db, "UPDATE buildings SET aland='$aland' WHERE email='$email' AND pw='$pw'");

    mysqli_query($db, "UPDATE buildings SET home='$home' WHERE email='$email' AND pw='$pw'");
      mysqli_query($db, "UPDATE buildings SET barrack='$barrack' WHERE email='$email' AND pw='$pw'");
      mysqli_query($db, "UPDATE buildings SET farm='$farm' WHERE email='$email' AND pw='$pw'");
      mysqli_query($db, "UPDATE buildings SET wp='$wp' WHERE email='$email' AND pw='$pw'");
    mysqli_query($db, "UPDATE buildings SET lmill='$lmill' WHERE email='$email' AND pw='$pw'");

    mysqli_query($db, "UPDATE buildings SET gm='$gm' WHERE email='$email' AND pw='$pw'");
      mysqli_query($db, "UPDATE buildings SET im='$im' WHERE email='$email' AND pw='$pw'");

    echo"<div align=center><font class=yellow>Your orders have been carried out.</font></div>";
    include("include/S_DBUILD.php");
    die();
  }
}

?>
</td>
</tr>
</table>
</body>
</html>