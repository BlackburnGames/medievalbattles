<?php
/**
 * Login throttling: how many wrong passwords an address, or an IP, may try.
 *
 * The game never limited them, so a password could be guessed for as long as
 * anyone cared to. checklogin.php asks mb_login_throttled() before it looks
 * at the password at all, records each failure, and clears an address's on
 * success.
 *
 * Two counts, because they stop different things: the address's stops a
 * guesser working on one account from anywhere; the IP's stops one machine
 * working down a list of accounts. Both are over a sliding window. The
 * refusal is the same whether or not the address has an account.
 */

require_once __DIR__ . '/db.php';

if (!defined('MB_LOGIN_WINDOW')) {
	/** Minutes a failure counts against an address and an IP. */
	define('MB_LOGIN_WINDOW', 15);
	/** Failures for one address within the window before it is refused. */
	define('MB_LOGIN_MAX_EMAIL', 5);
	/** Failures from one IP within the window before it is refused. */
	define('MB_LOGIN_MAX_IP', 20);
}

if (!function_exists('mb_login_throttled')) {
	/**
	 * @return bool  whether this address or this IP must wait
	 */
	function mb_login_throttled($db, $email, $ip)
	{
		$since = "NOW() - INTERVAL " . (int) MB_LOGIN_WINDOW . " MINUTE";
		$by_email = mysqli_fetch_row(mb_query($db, "SELECT count(*) FROM login_failure WHERE email=? AND at > $since", // mb-unbound-ok: a constant interval
			array(strtolower((string) $email))));
		$by_ip = mysqli_fetch_row(mb_query($db, "SELECT count(*) FROM login_failure WHERE ip=? AND at > $since", // mb-unbound-ok: a constant interval
			array((string) $ip)));
		return (int) $by_email[0] >= MB_LOGIN_MAX_EMAIL || (int) $by_ip[0] >= MB_LOGIN_MAX_IP;
	}
}

if (!function_exists('mb_login_failed')) {
	/** Record a failed login, and sweep the ones too old to count for anything. */
	function mb_login_failed($db, $email, $ip)
	{
		mb_query($db, "INSERT INTO login_failure (email, ip, at) VALUES (?, ?, NOW())",
			array(substr(strtolower((string) $email), 0, 254), substr((string) $ip, 0, 45)));
		mb_query($db, "DELETE FROM login_failure WHERE at < NOW() - INTERVAL 1 DAY");
	}
}

if (!function_exists('mb_login_succeeded')) {
	/** A right password clears the address's failures, not the IP's. */
	function mb_login_succeeded($db, $email)
	{
		mb_query($db, "DELETE FROM login_failure WHERE email=?", array(strtolower((string) $email)));
	}
}
