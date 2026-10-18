<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$cexploreland = mb_input('cexploreland');
$cexploremt   = mb_input('cexploremt');
$exploreland  = mb_input('exploreland');
$exploremt    = mb_input('exploremt');


echo "
<form method='POST' action='explore.php'>
<table border='0' bordercolor='#808080' align='center' width='40%'>
 <tr><td colspan='5' class='main'><b class='top'>Explore</b></td>
 <tr><td colspan='5' class='main2'>You have $aexplorers explorers you can send.</td>
 <tr>
   <td class='main2'><b class='reg'>Type</b></td>
   <td class='main2'><b class='reg'>Explorers Exploring</b></td>
   <td class='main2'><b class='reg'>Explorers to send</b></td>
 <tr>
   <td class='inner2'>Land</td>
   <td class='inner2'>$expland</td>
   <td class='inner2'><input type='number' name='exploreland' size='10'></td>
 <tr>
   <td class='inner2'>Mountain</td>
   <td class='inner2'>$expmt</td>
   <td class='inner2'><input type='number' name='exploremt' size='10'></td>
</table>
<br>";

$land = implode("", explode(",", $land));
$mts = implode("", explode(",", $mts));
$expland = implode("", explode(",", $expland));
$expmt = implode("", explode(",", $expmt));
$cexploreland = implode("", explode(",", $cexploreland));
$cexploremt = implode("", explode(",", $cexploremt));
$exploreland = implode("", explode(",", $exploreland));
$exploremt = implode("", explode(",", $exploremt));

// what the explorers out will find, as the tick works it out
$found = mb_explore($race, $land, $mts, $expland, $expmt);
$max_land = $found['max_land'];
$max_mts = $found['max_mts'];
$per_land = $found['per_land'];
$per_mt = $found['per_mt'];
$land_gain = $found['land_gain'];
$mt_gain = $found['mt_gain'];

echo "
<center>
  <br><font class='orange'>Explorers needed for max land gain:</font> $max_land
  <br><font class='orange'>Explorers needed for max mt gain:</font> $max_mts
  <br>
  <br><font class='orange'>Explorers for 1 land:</font> $per_land
  <br><font class='orange'>Explorers for 1 mt:</font> $per_mt
  <br>
  <br><font class='orange'>Land per tick:</font> $land_gain
  <br><font class='orange'>Mountains per tick:</font> $mt_gain
<br><br>
<input class='button' type='submit' name='explore' value='Send'>
<input type='hidden' name='explore' value='1'>
</center>";
?>