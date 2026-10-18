<?php
/**
 * Join the current round: make the logged-in account a kingdom in it, and
 * log it into that kingdom (docs/rounds.md, "The player's path").
 *
 * The rules are include/kingdom.php's mb_kingdom_join() -- signup's, before
 * signup made only the account. A refusal says why and goes back to
 * rounds.php.
 */

include("include/session.php");
include("include/connect.php");
require_once __DIR__ . '/include/kingdom.php';

if (!$mb_account_id) {
	header("Location: index.php");
	exit();
}
if ($userid !== '') {
	header("Location: main.php?pageid=news");
	exit();
}

$round = mb_round_current($db);
$computer_id = isset($_COOKIE['computer_id']) ? (string) (int) $_COOKIE['computer_id'] : '';
$field = function ($name) {
	return isset($_POST[$name]) ? (string) $_POST[$name] : '';
};

$result = !$round ? "No round is open to join." : mb_kingdom_join($db, $round, $mb_account_id, array(
	'ename' => $field('ename'), 'race' => $field('race'), 'class' => $field('class'),
	'msn' => $field('msn'), 'aim' => $field('aim'),
	'ip' => $_SERVER["REMOTE_ADDR"], 'computer_id' => $computer_id,
));

if (is_string($result)) {
	echo mb_h($result) . "<br><br><a href=rounds.php>Back</a>";
	exit();
}

mb_kingdom_enter($db, $result, $_SERVER["REMOTE_ADDR"], $computer_id);
mb_session_login(mb_account_get($mb_account_id), $result);
header("Location: main.php?pageid=news");
exit();
