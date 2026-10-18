<?php
/**
 * Sessions: how a request becomes a logged-in player, and how it stops being one.
 *
 * Replaces the PHP 4 session_register('login'|'email'|'pw') calls that used to
 * open every page. Every session in the game starts here, including the admin
 * area's (admin/include/auth.php requires this file) and the login itself.
 *
 * Include this from the app root (pages, and includes reached from them, all
 * run with app/ as the CWD). It is idempotent: including it twice re-reads the
 * same values, which is why the files that include both this and functions.php
 * are unaffected by the double load.
 *
 * $login/$email/$pw are the credential nearly every query filters on --
 * "WHERE email=? AND pw=?", about 1300 times. $pw is the kingdom's key, which
 * the round holds in place of the password hash it used to (see
 * include/kingdom.php); the hash is the account's, in the accounts database,
 * and the session keeps only a fingerprint of it. $mb_account_id says which
 * account is logged in, for the pages that act on the account rather than the
 * kingdom: preferences.php and the admin gate.
 *
 * None of it leaves the server: the session lives in a file under
 * session.save_path, and what the browser holds is the session id. So the
 * thing to defend is that id, and the 2003 code defended it with nothing --
 * no expiry, a cookie script could read, the same id before and after login,
 * a logout that left the session standing, and no protection against another
 * site making the request. Each of those is answered below.
 */

// mb_valid_email() and mb_valid_kingdom_key(); see "checked on the way out".
require_once __DIR__ . '/credentials.php';
// mb_account_get() and mb_account_fingerprint(), for the check against the account.
require_once __DIR__ . '/accounts.php';

if (!defined('MB_SESSION_IDLE')) {
	/** Seconds without a request before a session is ended. */
	define('MB_SESSION_IDLE', 2 * 3600);
	/** Seconds after logging in before a session is ended, however busy. */
	define('MB_SESSION_MAX', 24 * 3600);
}

if (!function_exists('mb_session_cross_site')) {
	/**
	 * Whether another site caused this request.
	 *
	 * Browsers send Sec-Fetch-Site on every request: "same-origin" when this
	 * site's own pages made it, "none" when the player typed the address or
	 * used a bookmark, and "cross-site" or "same-site" when another origin's
	 * link, form, image or script did. Almost every action in this game is a
	 * GET link -- buying, attacking, deleting a post -- so a token on each form
	 * would leave most of them open. Refusing the session to any request the
	 * player's own pages did not make closes all of them at once, login
	 * included. A browser too old to send the header gets the SameSite=Strict
	 * cookie below instead, which keeps the session id off cross-site requests
	 * by itself.
	 *
	 * @return bool
	 */
	function mb_session_cross_site()
	{
		if (!isset($_SERVER['HTTP_SEC_FETCH_SITE'])) {
			return false;
		}
		$site = strtolower($_SERVER['HTTP_SEC_FETCH_SITE']);
		return $site !== 'same-origin' && $site !== 'none';
	}
}

if (!function_exists('mb_session_start')) {
	/**
	 * Start the session with the cookie and id rules the game never had.
	 *
	 * Not started at all for a cross-site request: with no session, every page
	 * renders its logged-out branch, nothing can be written to the player's
	 * account, and nothing is written to the session either -- so another site
	 * cannot even log the player out.
	 *
	 * @return bool  whether a session is active
	 */
	function mb_session_start()
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			return true;
		}
		// Several includes emit stray whitespace, so a page past that point
		// cannot send a cookie and falls through as logged out, as it did. The
		// CLI -- the tick, the tests -- has no cookie to carry one in.
		if (PHP_SAPI === 'cli' || headers_sent() || mb_session_cross_site()) {
			return false;
		}

		// Refuse an id the server did not issue, so a planted cookie cannot
		// choose the id a victim will log in under.
		ini_set('session.use_strict_mode', '1');
		ini_set('session.use_only_cookies', '1');
		ini_set('session.use_trans_sid', '0');
		// PHP's own collector must not end a session before the idle limit
		// does; past it, this file ends the session itself.
		ini_set('session.gc_maxlifetime', (string) MB_SESSION_IDLE);

		$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off');
		if (PHP_VERSION_ID >= 70300) {
			session_set_cookie_params(array(
				'lifetime' => 0,
				'path'     => '/',
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Strict',
			));
		} else {
			// No samesite parameter before 7.3; the attribute rides on the path.
			session_set_cookie_params(0, '/; samesite=Strict', '', $secure, true);
		}

		session_start();
		return true;
	}
}

if (!function_exists('mb_session_login')) {
	/**
	 * Record a successful login, under a new session id.
	 *
	 * The id is replaced so that one obtained before logging in -- planted,
	 * or simply observed -- is worth nothing after.
	 *
	 * @param int    $account_id  who logged in
	 * @param string $hash        the account's password hash as it now stands
	 *                            -- after any rehash -- of which only the
	 *                            fingerprint is kept
	 * @param string $email       the address, which names the kingdom too
	 * @param string $key         the kingdom's key
	 */
	function mb_session_login($account_id, $hash, $email, $key)
	{
		session_regenerate_id(true);
		$_SESSION['login']       = 1;
		$_SESSION['mb_account']  = (int) $account_id;
		$_SESSION['mb_account_fp'] = mb_account_fingerprint($hash);
		$_SESSION['email']       = $email;
		$_SESSION['pw']          = $key;
		$_SESSION['mb_auth_at']  = time();
		$_SESSION['mb_seen']     = time();
	}
}

if (!function_exists('mb_session_rotate')) {
	/**
	 * Carry a changed credential, under a new session id.
	 *
	 * For the password and address changes in preferences.php. The player's
	 * other sessions hold the old value -- the old hash's fingerprint, or the
	 * old address -- which no longer matches the account, so the check below
	 * ends them on their next request.
	 *
	 * preferences.php has rendered its frame by the time it changes anything,
	 * and a new id needs a new cookie; past that point the id stays and only
	 * the values move. The other sessions end either way.
	 */
	function mb_session_rotate($email, $key, $hash)
	{
		if (!headers_sent()) {
			session_regenerate_id(true);
		}
		$_SESSION['email'] = $email;
		$_SESSION['pw']    = $key;
		$_SESSION['mb_account_fp'] = mb_account_fingerprint($hash);
	}
}

if (!function_exists('mb_session_end')) {
	/**
	 * End the session outright: its data, its file and its cookie.
	 *
	 * logout.php used to unset two keys and leave the session -- and the id
	 * that named it -- standing.
	 */
	function mb_session_end()
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			return;
		}
		$_SESSION = array();
		if (!headers_sent()) {
			$p = session_get_cookie_params();
			setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
		}
		session_destroy();
	}
}

mb_session_start();

/*
 * Expiry. A session that has been idle too long, or has lived too long since
 * its login, is ended here and the request carries on logged out. The admin
 * area's session is the same session and keeps the same clock.
 */
if (session_status() === PHP_SESSION_ACTIVE) {
	$mb_now = time();
	$mb_idle_out = isset($_SESSION['mb_seen']) && $mb_now - $_SESSION['mb_seen'] > MB_SESSION_IDLE;
	$mb_max_out  = isset($_SESSION['mb_auth_at']) && $mb_now - $_SESSION['mb_auth_at'] > MB_SESSION_MAX;
	if ($mb_idle_out || $mb_max_out) {
		$_SESSION = array();
		session_regenerate_id(true);
	} else {
		$_SESSION['mb_seen'] = $mb_now;
	}
}

$login = isset($_SESSION['login']) ? $_SESSION['login'] : '';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$pw    = isset($_SESSION['pw'])    ? $_SESSION['pw']    : '';
$mb_account_id = isset($_SESSION['mb_account']) ? (int) $_SESSION['mb_account'] : 0;

/*
 * The credential is checked on the way out, not just on the way in.
 *
 * $email and $pw are what ~1300 queries filter on. They are bound now, but
 * they were interpolated bare for twenty years, and constraining what the two
 * names can hold is what kept every one of those sites safe while it was
 * rewritten -- and keeps safe any that a future change writes the old way.
 * This is the one place every request passes through.
 *
 * A value that fails is not repaired, it is discarded: an address or a key
 * that is not one of those things is not a credential, and the page falls
 * through to its logged-out branch exactly as it does for an expired session.
 * checksignup.php and preferences.php apply the same rule at the point of
 * writing, so this only ever fires for a row that predates them -- or a
 * session from before the accounts moved, which held a password hash as $pw
 * and no account id.
 *
 * See include/credentials.php for the rule and what it costs.
 */
if (!mb_valid_email($email) || !mb_valid_kingdom_key($pw) || $mb_account_id < 1) {
	$login = $email = $pw = '';
	$mb_account_id = 0;
}

/*
 * And against the account and the kingdom, once per request.
 *
 * The account first, by id. A session whose account is gone, or whose password
 * hash is no longer the one it logged in against, is not a credential any
 * more. That last is what makes a password change end the account's other
 * sessions: the session keeps a fingerprint of the hash (include/accounts.php),
 * and the session that made the change is handed the new one.
 *
 * Then the kingdom, which must still be this account's and still answer to
 * the session's address and key. A key that no longer names a row -- the
 * kingdom was deleted by an administrator, by the tick for inactivity, or by
 * a wipe, or its address moved from another session -- used to stay "logged
 * in" and render every page empty, because every query matched nothing. It is
 * logged out instead.
 */
if ($email !== '' && empty($GLOBALS['mb_session_checked'])) {
	$GLOBALS['mb_session_checked'] = true;
	$mb_account = mb_account_get($mb_account_id);
	$mb_fp = isset($_SESSION['mb_account_fp']) ? (string) $_SESSION['mb_account_fp'] : '';
	$mb_ok = $mb_account
		&& hash_equals(mb_account_fingerprint($mb_account['pw']), $mb_fp);
	if ($mb_ok) {
		require_once __DIR__ . '/connect.php';
		$mb_r = mb_query($db, "SELECT userid FROM user WHERE account_id=? AND email=? AND pw=?", array($mb_account_id, $email, $pw));
		$mb_ok = $mb_r && mysqli_fetch_row($mb_r);
	}
	if (!$mb_ok) {
		unset($_SESSION['login'], $_SESSION['email'], $_SESSION['pw'], $_SESSION['mb_account'], $_SESSION['mb_account_fp']);
		$GLOBALS['mb_session_revoked'] = true;
	}
	unset($mb_account, $mb_fp, $mb_ok, $mb_r);
}
if (!empty($GLOBALS['mb_session_revoked'])) {
	$login = $email = $pw = '';
	$mb_account_id = 0;
}
