<?php
/**
 * Mail the game sends, and the address it sends people to.
 *
 * Six places mailed players, each building its own headers: a sender at
 * medievalbattles.com, which this install does not serve and whose mail a
 * receiving server checking SPF will refuse; a "bcc: phb@sendhost" to a
 * host that does not exist; and links to www.medievalbattles.com -- so the
 * activation link every new account needs pointed at someone else's domain.
 *
 * Both are the install's to say, through the environment as the database
 * settings are (docs/deployment.md):
 *
 *   MB_SITE_URL   where the game is served, e.g. https://example.com
 *   MB_MAIL_FROM  the sender, e.g. noreply@example.com
 *
 * Unset, the site is the request's own host (or the local stack's, from the
 * command line), and the sender is noreply@ that host.
 */

require_once __DIR__ . '/credentials.php';

if (!function_exists('mb_site_url')) {
	/**
	 * The game's address, with no trailing slash.
	 *
	 * @return string
	 */
	function mb_site_url()
	{
		$url = getenv('MB_SITE_URL');
		if ($url !== false && $url !== '') {
			return rtrim($url, '/');
		}
		if (!empty($_SERVER['HTTP_HOST']) && preg_match('/^[A-Za-z0-9.-]+(:[0-9]+)?$/', $_SERVER['HTTP_HOST'])) {
			$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
			return ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
		}
		return 'http://127.0.0.1:8080';
	}
}

if (!function_exists('mb_mail')) {
	/**
	 * Send a plain-text mail to a player.
	 *
	 * The address is held to mb_valid_email() first: it is the one value in
	 * the headers a player chose, and a line break in it would be a header of
	 * their own.
	 *
	 * @return bool  whether mail() accepted it
	 */
	function mb_mail($to, $subject, $body)
	{
		if (!mb_valid_email((string) $to)) {
			return false;
		}
		$from = getenv('MB_MAIL_FROM');
		if ($from === false || !mb_valid_email($from)) {
			$from = 'noreply@' . preg_replace('/:[0-9]+$/', '', (string) parse_url(mb_site_url(), PHP_URL_HOST));
		}
		$headers = "From: Medieval Battles <$from>\r\n"
			. "Content-Type: text/plain; charset=UTF-8\r\n";
		// The envelope sender too, which is what SPF checks.
		return mail((string) $to, (string) $subject, (string) $body, $headers, '-f' . $from);
	}
}
