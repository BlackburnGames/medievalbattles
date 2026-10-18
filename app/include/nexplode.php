<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$cexploreland = mb_input('cexploreland');
$cexploremt   = mb_input('cexploremt');
$exploreland  = mb_input('exploreland');
$exploremt    = mb_input('exploremt');
$send         = mb_input('send');
$uarcher      = mb_input('uarcher');
$ubarrack     = mb_input('ubarrack');
$ucatapult    = mb_input('ucatapult');
$uexplorer    = mb_input('uexplorer');
$ufarm        = mb_input('ufarm');
$ugm          = mb_input('ugm');
$ugolem       = mb_input('ugolem');
$uhome        = mb_input('uhome');
$uim          = mb_input('uim');
$uirongolem   = mb_input('uirongolem');
$ulmill       = mb_input('ulmill');
$upriest      = mb_input('upriest');
$ur1          = mb_input('ur1');
$ur10         = mb_input('ur10');
$ur11         = mb_input('ur11');
$ur12         = mb_input('ur12');
$ur13         = mb_input('ur13');
$ur14         = mb_input('ur14');
$ur15         = mb_input('ur15');
$ur16         = mb_input('ur16');
$ur17         = mb_input('ur17');
$ur18         = mb_input('ur18');
$ur2          = mb_input('ur2');
$ur3          = mb_input('ur3');
$ur4          = mb_input('ur4');
$ur5          = mb_input('ur5');
$ur6          = mb_input('ur6');
$ur7          = mb_input('ur7');
$ur8          = mb_input('ur8');
$ur9          = mb_input('ur9');
$urecruit     = mb_input('urecruit');
$usuicide     = mb_input('usuicide');
$usage        = mb_input('usage');
$uthief       = mb_input('uthief');
$uwarrior     = mb_input('uwarrior');
$uwizard      = mb_input('uwizard');
$uwp          = mb_input('uwp');

//empire
$iron = implode("", explode(",", $iron));
$gp = implode("", explode(",", $gp));
$land = implode("", explode(",", $land));
$mts = implode("", explode(",", $mts));
$lumber = implode("", explode(",", $lumber));

//military
$maxciv = implode("", explode(",", $maxciv));
$priestc = implode("", explode(",", $priestc));
$wizardc = implode("", explode(",", $wizardc));
$warriorc = implode("", explode(",", $warriorc));
$archerc = implode("", explode(",", $archerc));
$civ = implode("", explode(",", $civ));
$warriors = implode("", explode(",", $warriors));
$wizards = implode("", explode(",", $wizards));
$priests = implode("", explode(",", $priests));
$archers = implode("", explode(",", $archers));
$dbwar = implode("", explode(",", $dbwar));
$dbwar2 = implode("", explode(",", $dbwar2));
$dbwiz = implode("", explode(",", $dbwiz));
$dbwiz2 = implode("", explode(",", $dbwiz2));
$dbpri = implode("", explode(",", $dbpri));
$dbpri2 = implode("", explode(",", $dbpri2));
$dbarch = implode("", explode(",", $dbarch));
$dbarch2 = implode("", explode(",", $dbarch2));
// functions.php formats the golem counts with the rest, and they were never
// stripped: at 1,000 an attack compared "2,000" with the number sent as a
// string, and the training pages added new golems to the "1" of "1,234".
$golem = implode("", explode(",", $golem));
$irongolem = implode("", explode(",", $irongolem));
$dbgolem = implode("", explode(",", $dbgolem));
$dbirongolem = implode("", explode(",", $dbirongolem));
$explorers = implode("", explode(",", $explorers));
$thieves = implode("", explode(",", $thieves));
$sages = implode("", explode(",", $sages));
$dbexplorer = implode("", explode(",", $dbexplorer));
$dbthief = implode("", explode(",", $dbthief));
$dbsage = implode("", explode(",", $dbsage));
$aexplorers = implode("", explode(",", $aexplorers));
$tsages = implode("", explode(",", $tsages));
$texplorers = implode("", explode(",", $texplorers));
$explorec = implode("", explode(",", $explorec));
$bm_cost = implode("", explode(",", $bm_cost));
$b_cost = implode("", explode(",", $b_cost));
$tdefense = implode("", explode(",", $tdefense));
// These five stay strings. equip.php sends weapon names under the four unit
// names, and intel.php and scattack.php give a blank $send its own message.
// The pages that count them cast them themselves.
$send = implode("", explode(",", $send));
$uwarrior = implode("", explode(",", $uwarrior));
$uwizard = implode("", explode(",", $uwizard));
$upriest = implode("", explode(",", $upriest));
$uarcher = implode("", explode(",", $uarcher));
$uexplorer = mb_count($uexplorer);
$uthief = mb_count($uthief);
$usage = mb_count($usage);
$urecruit = mb_count($urecruit);
$ucatapult = mb_count($ucatapult);
$usuicide = mb_count($usuicide);
$ugolem = mb_count($ugolem);
$uirongolem = mb_count($uirongolem);
$twarriors = implode("", explode(",", $twarriors));
$twizards = implode("", explode(",", $twizards));
$tpriests = implode("", explode(",", $tpriests));
$tarchers = implode("", explode(",", $tarchers));
$explorec = implode("", explode(",", $explorec));

//buildings
$home = implode("", explode(",", $home));
$barrack = implode("", explode(",", $barrack));
$farm = implode("", explode(",", $farm));
$wp = implode("", explode(",", $wp));
$lmill =  implode("", explode(",", $lmill));
$gm = implode("", explode(",", $gm));
$im = implode("", explode(",", $im));
$dhome = implode("", explode(",", $dhome));
$dbarrack = implode("", explode(",", $dbarrack));
$dfarm = implode("", explode(",", $dfarm));
$dwp = implode("", explode(",", $dwp));
$dlmill =  implode("", explode(",", $dlmill));
$dgm = implode("", explode(",", $dgm));
$dim = implode("", explode(",", $dim));

$uhome = mb_count($uhome);
$ubarrack = mb_count($ubarrack);
$ufarm = mb_count($ufarm);
$uwp = mb_count($uwp);
$ulmill = mb_count($ulmill);
$ugm = mb_count($ugm);
$uim = mb_count($uim);

//research
// No $r1-$r7 or $r1pts-$r7pts: functions.php reads research into $res, and the
// two pages that assign $r1-$r7 do it from $res after this file has run.
$ur1 = mb_count($ur1);
$ur2 = mb_count($ur2);
$ur3 = mb_count($ur3);
$ur4 = mb_count($ur4);
$ur5 = mb_count($ur5);
$ur6 = mb_count($ur6);
$ur7 = mb_count($ur7);
$ur8 = mb_count($ur8);
$ur9 = mb_count($ur9);
$ur10 = mb_count($ur10);
$ur11 = mb_count($ur11);
$ur12 = mb_count($ur12);
$ur13 = mb_count($ur13);
$ur14 = mb_count($ur14);
$ur15 = mb_count($ur15);
$ur16 = mb_count($ur16);
$ur17 = mb_count($ur17);
$ur18 = mb_count($ur18);
$asages = implode("", explode(",", $asages));

//explore
$expland = implode("", explode(",", $expland));
$expmt = implode("", explode(",", $expmt));
$cexploreland = mb_count($cexploreland);
$cexploremt = mb_count($cexploremt);
$exploreland = mb_count($exploreland);
$exploremt = mb_count($exploremt);

?>