<?php
// mb_valid_email(), shared with preferences.php and include/session.php.
require_once __DIR__ . '/include/credentials.php';

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
// As in checklogin.php: the cookie index.php issues, which signup_comp_id
// records and nothing had read since register_globals went.
$computer_id = isset($_COOKIE['computer_id']) ? (string) (int) $_COOKIE['computer_id'] : '';

if ($signup) {

  // The password stays as typed until the checks below have run.
  //
  // It used to be md5()d on these three lines, and the "passwords don't match"
  // check compared the two digests -- which worked only because an unsalted
  // digest of a given password is always the same string. include/password.php
  // salts per call, so two hashes of one password never match; the comparison
  // has to happen on the plaintext, and the hashing once, after it.
  $opw = $pw;

  echo "Signups for v6: Kupo Remix Edition will start February 19, 2007 @ 9am PST. Ticks will commence 24 hours later.";

  include("include/connect.php");

  // insert ip address into db
  $ipaddress = $_SERVER["REMOTE_ADDR"];
  //  select number of players
    $numberplayers = mb_query($db, "SELECT count(userid) FROM user");
    $noplayers = mb_db_result($numberplayers, "noplayers");
  //  parse weird chars out of vars
    $ename = trim($ename);
    $ename = strip_tags($ename);
    $msn = strip_tags($msn);
    $aim = strip_tags($aim);
    $email = strip_tags($email);
  // Every value below is bound, never interpolated. The address in particular
  // is queried before mb_valid_email() has looked at it, so nothing can lean on
  // the rule further down.
  //  email already in the database?
    $emresult = mb_query($db, "SELECT email FROM user WHERE email=?", array($email));
    $emnamecheck = mysqli_fetch_array($emresult);
  // empire name already in the database?
    $eresult = mb_query($db, "SELECT ename FROM user WHERE ename=?", array($ename));
    $enamecheck = mysqli_fetch_array($eresult);
  //  dropcase of empire name and email
    $ename1 = strtolower($ename);
    $enamecheck1 = strtolower($enamecheck[0]);
    $email1 = strtolower($email);
    $emnamecheck1 = strtolower($emnamecheck[0]);
  //  are they a multi?
    $ip_query = mb_query($db, "SELECT count(userid) FROM user WHERE ip=?", array($ipaddress));
    $check_ip = mb_db_result($ip_query, "check_ip");

  if($enamecheck1 == $ename1 AND $ename != "")  { echo mb_h($ename) . " is already being used!"; die();   }
  elseif($emnamecheck1 == $email1 AND $email != "") { echo mb_h($email) . " is already being used!";  die(); }
  // The address check was commented out in 2003, so anything at all could be
  // signed up with -- and the address is then written to six tables and
  // interpolated into every "WHERE email='$email'" the account ever loads.
  // include/credentials.php holds the rule, shared with preferences.php and
  // include/session.php so all three agree on what an address may be.
  elseif (!mb_valid_email($email)) { echo "Email address is invalid!";  die(); }
  elseif($class == 'ns')  { echo "You must have a class to play the game!"; die();   }
  elseif($race == 'ns') { echo "You must have a race to play the game!";  die();   }
  elseif($ename == "")  { echo "You must have an empire name to play the game!";  die();   }
  // include/credentials.php says why: the name is interpolated bare on every
  // page once it is stored, and this is the only place it is ever written.
  elseif (!mb_valid_ename($ename)) { echo "Empire names may use letters, numbers, spaces and . _ - only, up to 25 characters!";  die();   }
  // The two drop-downs, held to what they offer. Both are stored and then
  // compared against literals all over the engine, so a value the form never
  // offered makes an empire that no race or class rule recognises.
  elseif (!isset($GAMEDATA['races'][$race]))   { echo "That race doesn't exist!";  die();   }
  elseif (!isset($GAMEDATA['classes'][$class])) { echo "That class doesn't exist!";  die();   }
  elseif($noplayers >= 450) { echo "Game is full! Try again later!";  die();   }
  elseif (($email == "") and ($cemail == "")) { echo "You must have an email to play!"; die();   }
  elseif ($email != $cemail)  { echo "Emails don't match!"; die();   }
  elseif (($pw == "") and ($cpw == "")) { echo "You must have a password to play!"; die();   }
  elseif ($pw != $cpw)   {  echo "Passwords don't match!";  die();   }
  //elseif($class != Cleric AND $class != Fighter AND $class != Mage AND $class != Ranger)   {  echo "That class doesn't exist!";  die(); }
  elseif($check_ip[0] >= 1)  {  echo "You are only allowed one account per computer!";   die(); }

  //  select minimum amount of members
    $Sett_least = mb_query($db, "SELECT min(members) FROM settlement");
    $least_set = mb_db_result($Sett_least,"least_set");
  //  select a random settlement
    $maxset = mb_settlement_count($db);
    $sel_mem = rand(1,$maxset);
  //  extract members from settlement
    $Random_mem = mb_query($db, "SELECT members FROM settlement WHERE setid=?", array((int) $sel_mem));
    $R_Mem = mb_db_result($Random_mem,"R_Mem");

  if($least_set != $R_Mem)  {
     $Sett_least = mb_query($db, "SELECT min(members) FROM settlement");
     $least_set = mb_db_result($Sett_least,"least_set");

     $Sel_members = mb_query($db, "SELECT setid FROM settlement WHERE members=?", array((int) $least_set));
     $sel_mem = mb_db_result($Sel_members,"sel_mem");
  }

  if($least_set == $R_Mem)  {
    $least_set = $R_Mem;
  }

  $new_mem = $least_set + 1;
  mb_query($db, "UPDATE settlement SET members=? WHERE setid=?", array((int) $new_mem, (int) $sel_mem));

  $snum = $sel_mem;

  // create the account
  $buildingsuserid = mb_query($db, "SELECT max(userid) FROM user");
  $buserid = mb_db_result($buildingsuserid,"buserid");
  $newbuserid = $buserid + 1;

  //  create activation code - bypassing validation for now
  //
  // Its own random value. This used to store the password digest and then mail
  // it out as the activate_account.php link, so the activation URL carried the
  // exact string that "WHERE email=... AND pw=..." accepts as the credential --
  // and emailvalidate.code is varchar(50), which a bcrypt hash does not fit
  // into anyway.
  $act_code = md5(uniqid(rand(), true));
  mb_query($db, "INSERT INTO emailvalidate (userid, code, `check`) VALUES (?, ?, '2')", array((int) $newbuserid, $act_code));

  if (($email === $cemail) and ($pw === $cpw) and ($email != "") and ($cemail != "") and ($pw != "") and ($cpw != ""))  {
    // Hashed here, once, after every comparison that needed the plaintext. Six
    // INSERTs below write it, because `pw` is denormalized across six tables.
    $pw = mb_password_hash($pw);

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

  // No archers and no Archery unless the race or the class grants them. The
  // Ranger check used to reset Archery with an else, after the Night Elf had
  // been granted it, so a Night Elf of any other class got archers it could
  // not field.
    $arch = 0;
    $r13pts = 0;

  // race advantages and disadvantages
    if($race == 'Giant')   {  $wiz = 0; $pri = 0;  }
    if($race == 'Demon')   {  $pri = 0;  }
    if($race == 'Night Elf')   {  $arch = rand(4, 10);  $r13pts  = 125000;   }

  // class advantages and disadvantages
    if($class == 'Ranger')  { $arch = rand(4, 10);  $r13pts = 125000; $wiz = 0; }
    if($class == 'Warlock') { $r1pts = 50000; }
      else  { $r1pts = 0; }
    if($class == 'Insurrectionist') { $suicide = rand(10, 20);  $r14pts = 125000; $wiz = 0; }
      else  { $suicide  = 0;  $r14pts = 0;  }

    mb_query($db, "INSERT INTO user (email,  pw, ename, msn, aim, gp, iron, exp, food, land, mts, setid, class, userid, race, safemode, signup_comp_id)
      VALUES (?, ?, ?, ?, ?, ?, ?, '0', '1500', '250', '200', ?, ?, ?, ?, '48', ?)",
      array($email, $pw, $ename, $msn, $aim, $gp, $iron, (int) $snum, $class, (int) $newbuserid, $race, $computer_id));

    mb_query($db, "INSERT INTO buildings (email, pw, home, barrack, farm, wp, gm, im, aland, amts, userid)
      VALUES (?, ?, '50', '50', '50', '0', '50', '50', '100', '100', ?)", array($email, $pw, (int) $newbuserid));

    mb_query($db, "INSERT INTO military (email, pw, civ, recruits, warriors, wizards, priests, maxciv, userid, warpower, warspeedw, cweapon, wizpower, wizspeeds, cspell, pripower, prispeedw, cstaff, cbow, archspeedw, archpower, wararmor, wizarmor, priarmor, wardef, wizdef, pridef, warspeeda, wizspeeda, prispeeda, archers, suicide)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '2', '6','Dagger', '3', '4', 'Magic Missile', '2', '4', 'Quarterstaff', 'Bow', '4', '2', 'Studded Leather', 'Robe', 'Leather', '1', '1', '2', '0', '0', '1', ?, ?)",
      array($email, $pw, (int) $civ, (int) $recruits, (int) $war, (int) $wiz, (int) $pri, (int) $maxciv, (int) $newbuserid, (int) $arch, (int) $suicide));

    mb_query($db, "INSERT INTO research (email, pw, userid, r1pts, r13pts, r14pts)
      VALUES (?, ?, ?, ?, ?, ?)", array($email, $pw, (int) $newbuserid, (int) $r1pts, (int) $r13pts, (int) $r14pts));

    mb_query($db, "INSERT INTO explore (email, pw, userid)
      VALUES (?, ?, ?)", array($email, $pw, (int) $newbuserid));

    $selectempire = mb_query($db, "SELECT setid FROM user WHERE email=? AND pw=?", array($email, $pw));
    $semp = mb_db_result($selectempire,"semp");

    include("include/clock.php");

    mb_news_set($db, "$clock", 'red', "$ename has joined the settlement", $snum, 0);
    mb_query($db, "INSERT INTO returntbl (email, pw, userid) VALUES (?, ?, ?)", array($email, $pw, (int) $newbuserid));

    echo "Thank you for signing up for Medieval Battles. You are in settlement " . mb_h($snum) . ".<br>Your login information and activation code has been emailed to you.<br><br><a href=index.php>You can login now here</a>";

    $subject = "Welcome to Medieval Battles";
    $body = "
    Thank you for being apart of the online game, Medieval Battles.
    Here is your account information:

    Empire Name: [$ename]
    Email: [$email]
    Password: [$opw]

    You will need your email and password to login. But before you can login, you must activate your account. To activate your account, click here:
    http://www.medievalbattles.com/activate_account.php?activate=true&act_userid=$newbuserid&act_code=$act_code

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