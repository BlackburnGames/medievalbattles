<?php
/**
 * Signup makes an account, and only an account (docs/rounds.md, "The player's
 * path"): an address, a password and a player name, which outlast every
 * round. A kingdom is made by joining a round, after logging in -- rounds.php
 * and join.php, through include/kingdom.php's mb_kingdom_join().
 *
 * It used to make both at once, and an account whose kingdom was gone came
 * back by signing up again with its own password. That way back is logging
 * in now, so an address already in use is simply taken.
 *
 * The welcome mail used to carry the password, in plain text. It does not.
 */
// mb_valid_email(), shared with preferences.php and include/session.php.
require_once __DIR__ . '/include/credentials.php';

$signup = isset($_POST['signup']) ? $_POST['signup'] : '';

$email  = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$cemail = isset($_POST['cemail']) ? trim((string) $_POST['cemail']) : '';
$pw     = isset($_POST['pw']) ? (string) $_POST['pw'] : '';
$cpw    = isset($_POST['cpw']) ? (string) $_POST['cpw'] : '';
// The account's public name, the same in every round. A kingdom's name is
// chosen when it joins one.
$handle = isset($_POST['handle']) ? trim((string) $_POST['handle']) : '';
// As in checklogin.php: the cookie index.php issues, which signup_comp_id
// records.
$computer_id = isset($_COOKIE['computer_id']) ? (string) (int) $_COOKIE['computer_id'] : '';

if (!$signup) {
  echo 'If you want to make an account, <a href="index.php?page=signup">click here</a>';
  exit();
}

include("include/connect.php");

// The password stays as typed until every check has run: include/password.php
// salts per call, so two hashes of one password never match, and the
// comparison has to be of the plaintext. It is hashed once, below.
//
// The address check was commented out in 2003, so anything at all could be
// signed up with. include/credentials.php holds the rule, shared with
// preferences.php and include/session.php so all three agree.
if ($email === '' && $cemail === '')       { echo "You must have an email to play!"; die(); }
elseif (!mb_valid_email($email))           { echo "Email address is invalid!"; die(); }
elseif ($email != $cemail)                 { echo "Emails don't match!"; die(); }
elseif ($pw === '' && $cpw === '')         { echo "You must have a password to play!"; die(); }
elseif ($pw != $cpw)                       { echo "Passwords don't match!"; die(); }
elseif (!mb_valid_handle($handle))         { echo "Player names may use letters, numbers, spaces and . _ - only, up to 25 characters!"; die(); }
elseif (mb_account_find($email))           { echo mb_h($email) . " is already being used!"; die(); }
elseif (mb_account_handle_taken($handle))  { echo mb_h($handle) . " is already being used!"; die(); }

// Another signup may take the address or the name between the checks and
// here; the account table's unique keys refuse this one if so.
$account_id = mb_account_create($email, $handle, mb_password_hash($pw), $computer_id);
if (!$account_id) {
  echo mb_h($email) . " is already being used!";
  die();
}

echo "Thank you for signing up for Medieval Battles. An activation link has been emailed to you.<br><br>"
  . "<a href=index.php>Log in</a> to join a round and found your kingdom.";

$verify_link = mb_account_verify_link(mb_account_get($account_id));

$subject = "Welcome to Medieval Battles";
$body = "
Thank you for being apart of the online game, Medieval Battles.
Here is your account information:

Player Name: [$handle]
Email: [$email]

You will need your email and password to login. But before you can play, you must activate your account. To activate your account, click here:
$verify_link

";

// include/mail.php: the sender and the link are the install's.
mb_mail($email, $subject, $body);
