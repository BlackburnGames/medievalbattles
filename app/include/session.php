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
 * "WHERE email='$email' AND pw='$pw'", about 1300 times. $pw is the stored
 * password hash. It never leaves the server: the session lives in a file under
 * session.save_path, and what the browser holds is the session id. So the
 * thing to defend is that id, and the 2003 code defended it with nothing --
 * no expiry, a cookie script could read, the same id before and after login,
 * a logout that left the session standing, and no protection against another
 * site making the request. Each of those is answered below; the 1300 queries
 * are untouched, because what they interpolate has not changed.
 */

// mb_valid_email() and mb_valid_pw_hash(); see "checked on the way out" below.
require_once __DIR__ . '/credentials.php';

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
	 */
	function mb_session_login($email, $pw)
	{
		session_regenerate_id(true);
		$_SESSION['login']       = 1;
		$_SESSION['email']       = $email;
		$_SESSION['pw']          = $pw;
		$_SESSION['mb_auth_at']  = time();
		$_SESSION['mb_seen']     = time();
	}
}

if (!function_exists('mb_session_rotate')) {
	/**
	 * Carry a changed credential, under a new session id.
	 *
	 * For the password and address changes in preferences.php. The player's
	 * other sessions hold the old value, which no longer matches the row, so
	 * the check below ends them on their next request.
	 *
	 * preferences.php has rendered its frame by the time it changes anything,
	 * and a new id needs a new cookie; past that point the id stays and only
	 * the values move. The other sessions end either way.
	 */
	function mb_session_rotate($email, $pw)
	{
		if (!headers_sent()) {
			session_regenerate_id(true);
		}
		$_SESSION['email'] = $email;
		$_SESSION['pw']    = $pw;
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

/*
 * The credential is checked on the way out, not just on the way in.
 *
 * $email and $pw are what ~1300 queries filter on, interpolated straight into
 * "WHERE email='$email' AND pw='$pw'" with no escaping. Fixing that at the call
 * sites is not on the table -- there are 1300 of them, and most are in files
 * that never see where the value came from. Constraining what the two names can
 * hold makes every one of those interpolations safe by construction, and this
 * is the one place every request passes through.
 *
 * A value that fails is not repaired, it is discarded: an address or a digest
 * that is not one of those things is not a credential, and the page falls
 * through to its logged-out branch exactly as it does for an expired session.
 * checksignup.php and preferences.php apply the same rule at the point of
 * writing, so this only ever fires for a row that predates them.
 *
 * See include/credentials.php for the rule and what it costs.
 */
if (!mb_valid_email($email) || !mb_valid_pw_hash($pw)) {
	$login = $email = $pw = '';
}

/*
 * And against the account, once per request. A session whose address and hash
 * no longer name a row -- the password was changed from another session, the
 * account was deleted or destroyed -- is not a credential any more. It used to
 * stay "logged in" and render every page empty, because every query matched
 * nothing; it is logged out now, which is also what makes a password change
 * end the player's other sessions.
 */
if ($email !== '' && empty($GLOBALS['mb_session_checked'])) {
	$GLOBALS['mb_session_checked'] = true;
	require_once __DIR__ . '/connect.php';
	$mb_r = mysqli_query($db, "SELECT userid FROM user WHERE email=" . mb_sql_str($db, $email)
		. " AND pw=" . mb_sql_str($db, $pw));
	if (!$mb_r || !mysqli_fetch_row($mb_r)) {
		unset($_SESSION['login'], $_SESSION['email'], $_SESSION['pw']);
		$GLOBALS['mb_session_revoked'] = true;
	}
}
if (!empty($GLOBALS['mb_session_revoked'])) {
	$login = $email = $pw = '';
}
