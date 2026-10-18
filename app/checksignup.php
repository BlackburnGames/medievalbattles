<?php
// mb_valid_email(), shared with preferences.php and include/session.php.
require_once __DIR__ . '/include/credentials.php';
// mb_kingdom_start() and mb_kingdom_seed(): what a new empire starts with.
require_once __DIR__ . '/include/kingdom.php';

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
  // empire name already in the database?
    $eresult = mb_query($db, "SELECT ename FROM user WHERE ename=?", array($ename));
    $enamecheck = mysqli_fetch_array($eresult);
  //  dropcase of empire name and email
    $ename1 = strtolower($ename);
    $enamecheck1 = strtolower($enamecheck[0]);
  //  are they a multi?
    $ip_query = mb_query($db, "SELECT count(userid) FROM user WHERE ip=?", array($ipaddress));
    $check_ip = mb_db_result($ip_query, "check_ip");

  if($enamecheck1 == $ename1 AND $ename != "")  { echo mb_h($ename) . " is already being used!"; die();   }
  // The address check was commented out in 2003, so anything at all could be
  // signed up with -- and the address was then written to six tables and
  // interpolated into every "WHERE email='$email'" the account ever loaded.
  // It is the account's alone now, and only ever bound.
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

  // The account. Accounts outlast their kingdoms (include/accounts.php), so
  // a player whose kingdom was deleted comes back this way. With the account's password, this signup makes the
  // account a new kingdom. With any other, the address is taken, as it always
  // was -- and says no more than that.
  //
  // And an account plays one kingdom a round, which is asked by account id:
  // the round holds no address.
  $account = mb_account_find($email);
  if ($account && !mb_password_verify($pw, $account['pw'])) { echo mb_h($email) . " is already being used!";  die(); }
  if ($account && mb_kingdom_find($db, $account['account_id'])) { echo mb_h($email) . " is already being used!";  die(); }

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

  // The activation code is the account's now (include/accounts.php), made
  // with it below, and confirming an address is no longer once per round. It
  // was a row in the round's emailvalidate table, written with check='2' --
  // already activated -- since 2007, "bypassing validation for now". A new
  // account is unverified until its link is followed.

  if (($email === $cemail) and ($pw === $cpw) and ($email != "") and ($cemail != "") and ($pw != "") and ($cpw != ""))  {
    // Hashed here, once, after every comparison that needed the plaintext, and
    // stored on the account and nowhere else. An account that already exists
    // keeps the hash it has; the address is the one it holds, should the case
    // typed differ.
    if ($account) {
      $email = $account['email'];
      $account_id = (int) $account['account_id'];
    } elseif (!($account_id = mb_account_create($email, mb_password_hash($pw), $computer_id))) {
      // Another signup took the address between the check and here; the
      // accounts table's unique key refused this one.
      echo mb_h($email) . " is already being used!";  die();
    }

  // What the empire starts with is include/kingdom.php's, shared with the
  // reset a new game runs on the administrators' kingdoms.
    $start = mb_kingdom_start($race, $class);
    $u = $start['user'];

    mb_query($db, "INSERT INTO user (ename, msn, aim, gp, iron, exp, food, land, mts, setid, class, userid, race, safemode, signup_comp_id, account_id)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
      array($ename, $msn, $aim, $u['gp'], $u['iron'], $u['exp'], $u['food'], $u['land'], $u['mts'], (int) $snum, $class, (int) $newbuserid, $race, $u['safemode'], $computer_id, $account_id));

    mb_kingdom_seed($db, $newbuserid, $start);

    $selectempire = mb_query($db, "SELECT setid FROM user WHERE userid=?", array((int) $newbuserid));
    $semp = mb_db_result($selectempire,"semp");

    include("include/clock.php");

    mb_news_set($db, "$clock", 'red', "$ename has joined the settlement", $snum, 0);

    echo "Thank you for signing up for Medieval Battles. You are in settlement " . mb_h($snum) . ".<br>Your login information and activation code has been emailed to you.<br><br><a href=index.php>You can login now here</a>";

    // An account that already existed keeps the code it was made with; if it
    // is verified already, the link says so harmlessly.
    $verify_link = mb_account_verify_link(mb_account_get($account_id));

    $subject = "Welcome to Medieval Battles";
    $body = "
    Thank you for being apart of the online game, Medieval Battles.
    Here is your account information:

    Empire Name: [$ename]
    Email: [$email]
    Password: [$opw]

    You will need your email and password to login. But before you can play, you must activate your account. To activate your account, click here:
    $verify_link

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