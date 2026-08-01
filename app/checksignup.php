<?php
$signup = $_POST['signup'];

$email = $_POST['email'];
$cemail = $_POST['cemail'];
$pw = $_POST['pw'];
$cpw = $_POST['cpw'];
$ename = $_POST['ename'];
$race = $_POST['race'];
$class = $_POST['class'];
$msn = $_POST['msn'];
$aim = $_POST['aim'];

if ($signup) {

  $opw = $pw;
  $cpw = md5($cpw);
  $pw = md5($pw);

  echo "Signups for v6: Kupo Remix Edition will start February 19, 2007 @ 9am PST. Ticks will commence 24 hours later.";

  include("include/connect.php");

  // insert ip address into db
  $ipaddress = $_SERVER["REMOTE_ADDR"];
  //  select number of players
    $numberplayers = $db->query("SELECT count(userid) FROM user");
    $noplayers = mb_db_result($numberplayers, "noplayers");
  //  parse weird chars out of vars
    $ename = trim($ename);
    $ename = strip_tags($ename);
    $msn = strip_tags($msn);
    $aim = strip_tags($aim);
    $email = strip_tags($email);
  //  email already in the database?
    $emresult = $db->query("SELECT email FROM user WHERE email='$email'");
    $emnamecheck = mysqli_fetch_array($emresult);
  // empire name already in the database?
    $eresult = $db->query("SELECT ename FROM user WHERE ename='$ename'");
    $enamecheck = mysqli_fetch_array($eresult);
  //  dropcase of empire name and email
    $ename1 = strtolower($ename);
    $enamecheck1 = strtolower($enamecheck[0]);
    $email1 = strtolower($email);
    $emnamecheck1 = strtolower($emnamecheck[0]);
  //  are they a multi?
    $ip_query = $db->query("SELECT count(userid) FROM user WHERE ip='$ipaddress'");
    $check_ip = mb_db_result($ip_query, "check_ip");

  if($enamecheck1 == $ename1 AND $ename != "")  { echo "$ename is already being used!"; die();   }
  elseif($emnamecheck1 == $email1 AND $email != "") { echo "$email is already being used!";  die(); }
  // elseif (!preg_match("([[:alnum:]\.\-]+)(\@[[:alnum:]\.\-]+\.+)", $email)) { echo "Email address is invalid!";  die(); }
  elseif($class == ns)  { echo "You must have a class to play the game!"; die();   }
  elseif($race == ns) { echo "You must have a race to play the game!";  die();   }
  elseif($ename == "")  { echo "You must have an empire name to play the game!";  die();   }
  elseif($noplayers >= 450) { echo "Game is full! Try again later!";  die();   }
  elseif (($email == "") and ($cemail == "")) { echo "You must have an email to play!"; die();   }
  elseif ($email != $cemail)  { echo "Emails don't match!"; die();   }
  elseif (($pw == "") and ($cpw == "")) { echo "You must have a password to play!"; die();   }
  elseif ($pw != $cpw)   {  echo "Passwords don't match!";  die();   }
  //elseif($class != Cleric AND $class != Fighter AND $class != Mage AND $class != Ranger)   {  echo "That class doesn't exist!";  die(); }
  elseif($check_ip[0] >= 1)  {  echo "You are only allowed one account per computer!";   die(); }

  //  select minimum amount of members
    $Sett_least = $db->query("SELECT min(members) FROM settlement");
    $least_set = mb_db_result($Sett_least,"least_set");
  //  select a random settlement
    $maxset0 = $db->query("SELECT max(setid) AS maxset FROM settlement");
    $maxset = mb_db_result($maxset0,"maxset");
    $sel_mem = rand(1,$maxset);
  //  extract members from settlement
    $Random_mem = $db->query("SELECT members FROM settlement WHERE setid='$sel_mem'");
    $R_Mem = mb_db_result($Random_mem,"R_Mem");

  if($least_set != $R_Mem)  {
     $Sett_least = $db->query("SELECT min(members) FROM settlement");
     $least_set = mb_db_result($Sett_least,"least_set");

     $Sel_members = $db->query("SELECT setid FROM settlement WHERE members='$least_set'");
     $sel_mem = mb_db_result($Sel_members,"sel_mem");
  }

  if($least_set == $R_Mem)  {
    $least_set = $R_Mem;
  }

  $new_mem = $least_set + 1;
  $db->query("UPDATE settlement SET members='$new_mem' WHERE setid='$sel_mem'");

  $snum = $sel_mem;

  // create the account
  $buildingsuserid = $db->query("SELECT max(userid) FROM user");
  $buserid = mb_db_result($buildingsuserid,"buserid");
  $newbuserid = $buserid + 1;

  //  create activation code - bypassing validation for now
  $db->query("INSERT INTO emailvalidate (userid, code, `check`) VALUES ('$newbuserid', '$pw', '2') ");

  if (($email === $cemail) and ($pw === $cpw) and ($email != "") and ($cemail != "") and ($pw != "") and ($cpw != ""))  {
    $part1 = rand(250, 350);
    $part2 = rand(000, 999);

    $gp = "$part1" . "$part2";
    $iron = rand(4000, 6000);
    $civ = rand(1000, 1250);
    $recruits = rand(150, 250);
    $war = rand(1,5);
    $wiz = rand(1,5);
    $pri = rand(1,5);
    $maxciv = rand(100,250);

    if($class == "Cleric")  {
      $part1 = rand(350, 450);
      $part2 = rand(000, 999);

      $gp = "$part1" . "$part2";
      $iron = rand(6000, 7500);
      $civ = rand(1200, 1400);
      $recruits = rand(200, 300);
      $war = rand(4,10);
      $wiz = rand(4,10);
      $pri = rand(4,10);
      $maxciv = rand(250,350);
    }

  // race advantages and disadvantages
    if($race == 'Giant')   {  $wiz = 0; $pri = 0;  }
    if($race == 'Demon')   {  $pri = 0;  }
    if($race == 'Night Elf')   {  $arch = rand(4, 10);  $r13pts  = 125000;   }

  // class advantages and disadvantages
    if($class == 'Ranger')  { $arch = rand(4, 10);  $r13pts = 125000; $wiz = 0; }
      else  { $r13pts = 0;  }
    if($class == 'Warlock') { $r1pts = 50000; }
      else  { $r1pts = 0; }
    if($class == 'Insurrectionist') { $suicide = rand(10, 20);  $r14pts = 125000; $wiz = 0; }
      else  { $suicide  = 0;  $r14pts = 0;  }

    $db->query("INSERT INTO user (email,  pw, ename, msn, aim, gp, iron, exp, food, land, mts, setid, class, userid, race, safemode, signup_comp_id)
      VALUES ('$email', '$pw', '$ename', '$msn', '$aim', '$gp', '$iron', '0', '1500', '250', '200', '$snum', '$class', '$newbuserid', '$race', '48', '$computer_id') ");

    $db->query("INSERT INTO buildings (email, pw, home, barrack, farm, wp, gm, im, aland, amts, userid)
      VALUES ('$email', '$pw', '50', '50', '50', '0', '50', '50', '100', '100', '$newbuserid') ");

    $db->query("INSERT INTO military (email, pw, civ, recruits, warriors, wizards, priests, maxciv, userid, warpower, warspeedw, cweapon, wizpower, wizspeeds, cspell, pripower, prispeedw, cstaff, cbow, archspeedw, archpower, wararmor, wizarmor, priarmor, wardef, wizdef, pridef, warspeeda, wizspeeda, prispeeda, archers, suicide)
      VALUES ('$email', '$pw', '$civ', '$recruits', '$war', '$wiz', '$pri', '$maxciv', '$newbuserid', '2', '6','Dagger', '3', '4', 'Magic Missile', '2', '4', 'Quarterstaff', 'Bow', '4', '2', 'Studded Leather', 'Robe', 'Leather', '1', '1', '2', '0', '0', '1', '$arch', '$suicide') ");

    $db->query("INSERT INTO research (email, pw, userid, r1pts, r13pts, r14pts)
      VALUES ('$email', '$pw', '$newbuserid', '$r1pts', '$r13pts', '$r14pts') ");

    $db->query("INSERT INTO explore (email, pw, userid)
      VALUES ('$email', '$pw', '$newbuserid') ");

    $selectempire = $db->query("SELECT setid FROM user WHERE email='$email' AND pw='$pw'");
    $semp = mb_db_result($selectempire,"semp");

    include("include/clock.php");

    $db->query("INSERT INTO setnews (date, news, setid)   VALUES ('$clock', '<font class=red>$ename has joined the settlement</font>', '$snum') ");
    $db->query("INSERT INTO returntbl (email, pw, userid)   VALUES ('$email', '$pw', '$newbuserid') ");

    echo "Thank you for signing up for Medieval Battles. You are in settlement $snum.<br>Your login information and activation code has been emailed to you.<br><br><a href=index.php>You can login now here</a>";

    $subject = "Welcome to Medieval Battles";
    $body = "
    Thank you for being apart of the online game, Medieval Battles.
    Here is your account information:

    Empire Name: [$ename]
    Email: [$email]
    Password: [$opw]

    You will need your email and password to login. But before you can login, you must activate your account. To activate your account, click here:
    http://www.medievalbattles.com/activate_account.php?activate=true&act_userid=$newbuserid&act_code=$pw

    If you have any questions you can email us at support@medievalbattles.com";

    $from = "From: support@medievalbattles.com\r\nbcc: phb@sendhost\r\nContent-type: text/plain\r\nX-mailer: PHP/" . phpversion();
    $mailsend = mail("$email","$subject","$body","$from");

    die();
  } else {
    echo 'Something broke';
  }
} else {
  echo 'If you want to make an account, <a href="index.php?page=signup">click here</a>';
}
?>