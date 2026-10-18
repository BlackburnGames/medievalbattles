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

echo "<div align=center><b class=reg>[ <a href=dbuildings.php>Demolish</a> ]</b></div>";

if(!IsSet($update)) {
  include("include/S_BUILD.php");
} else {

  include("include/nexplode.php");

  if($ulmill > 0 AND $res['r13pts'] < 125000) {
    echo"<div align=center><font class=yellow>You can't construct Lumber Mills, because you haven't researched Archery yet!</font></div>";
    include("include/S_BUILD.php");
    die();
  }
  elseif($amts < $ugm + $uim) {
    echo"<div align=center><font class=yellow>Not enough mountains!.</font></div>";
    include("include/S_BUILD.php");
    die();
  }
  elseif($uhome < 0 OR $ubarrack < 0 OR $ufarm < 0 OR $ulmill < 0 OR $uwp < 0 OR $ugm < 0 OR $uim < 0)  {
    echo"<div align=center><font class=yellow>You can't build negative or 0 buildings.</font></div>";
    include("include/S_BUILD.php");
    die();
  }
  elseif($aland < $uhome + $ubarrack + $ufarm + $uwp + $ulmill) {
    echo"<div align=center><font class=yellow>Not enough land!</font></div>";
    include("include/S_BUILD.php");
    die();
  }
  elseif($gp < ($bm_cost * ($ugm + $uim)) + ($b_cost * ($ufarm + $ubarrack + $uhome + $uwp + $ulmill))) {
    echo"<div align=center><font class=yellow>Not enough gold!</font></div>";
    include("include/S_BUILD.php");
    die();
  }

  $gp = $gp -  (($bm_cost * ($ugm + $uim)) + ($b_cost * ($ufarm + $ubarrack + $uhome + $uwp + $ulmill)));
  $gp = round($gp);

  $amts = $amts - ($ugm + $uim);
  $aland = $aland - ($uhome + $ubarrack + $ufarm + $uwp + $ulmill);

  $forexp2 = $exp2 + (($uhome + $ubarrack + $ufarm + $uwp + $ulmill) * $landexp) + (($ugm + $uim) * $mtexp);

  $dhome = $dhome + $uhome;
  $dbarrack = $dbarrack + $ubarrack;
  $dfarm = $dfarm + $ufarm;
  $dwp = $dwp + $uwp;
  $dlmill = $dlmill + $ulmill;
  $dgm = $dgm + $ugm;
  $dim = $dim + $uim;
  $max_land = $max_land - ($uhome + $ubarrack + $ufarm + $ulmill + $uwp);
  $max_mt = $max_mt - ($ugm + $uim);

  mysqli_query($db, "UPDATE user SET gp ='$gp' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE user SET exp2 ='$forexp2' WHERE email='$email' AND pw='$pw'");

  mysqli_query($db, "UPDATE buildings SET amts='$amts' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET dgm='$dgm' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET dim='$dim' WHERE email='$email' AND pw='$pw'");

  mysqli_query($db, "UPDATE buildings SET aland='$aland' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET dhome='$dhome' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET dbarrack='$dbarrack' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET dfarm= '$dfarm' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET dwp='$dwp' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET dlmill='$dlmill' WHERE email='$email' AND pw='$pw'");

//  determines how long building are under development: a tick per building,
//  at most 20, both scaled by the modifiers. A queue already running keeps the
//  ticks it has and the order adds its own, so the modifiers apply only to what
//  this order builds -- and a timer for a building it does not build is left
//  alone. They used to be applied to every timer on every order.

  if($res['r16pts'] >= 100000)  { $adv_constr = 0.5;  }
  else  { $adv_constr = 1;  }
  if($race == 'Night Elf')  { $constr_mod = 2;  }
  else  { $constr_mod = 1;  }
  $constr_mult = $adv_constr * $constr_mod;
  $constr_cap = 20 * $constr_mult;

  // Rounded up: half a tick still has to be waited for, and a timer of 0 is
  // one the tick never builds.
  if($uhome > 0)    { $Hhrs = ceil(min($Hhrs + $uhome * $constr_mult, $constr_cap));  }
  if($ubarrack > 0) { $Bhrs = ceil(min($Bhrs + $ubarrack * $constr_mult, $constr_cap));  }
  if($ufarm > 0)    { $Fhrs = ceil(min($Fhrs + $ufarm * $constr_mult, $constr_cap));  }
  if($uwp > 0)      { $Whrs = ceil(min($Whrs + $uwp * $constr_mult, $constr_cap));  }
  if($ulmill > 0)   { $Lhrs = ceil(min($Lhrs + $ulmill * $constr_mult, $constr_cap));  }
  if($ugm > 0)      { $Ghrs = ceil(min($Ghrs + $ugm * $constr_mult, $constr_cap));  }
  if($uim > 0)      { $Ihrs = ceil(min($Ihrs + $uim * $constr_mult, $constr_cap));  }

  mysqli_query($db, "UPDATE buildings SET Hhrs ='$Hhrs' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET Bhrs ='$Bhrs' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET Fhrs ='$Fhrs' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET Whrs ='$Whrs' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET Lhrs ='$Lhrs' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET Ghrs ='$Ghrs' WHERE email='$email' AND pw='$pw'");
  mysqli_query($db, "UPDATE buildings SET Ihrs ='$Ihrs' WHERE email='$email' AND pw='$pw'");

  echo"<div align=center><font class=yellow>Your orders have been carried out.</font></div>";
  include("include/S_BUILD.php");
  die();
}
?>
</td>
</tr>
</table>
</body>
</html>