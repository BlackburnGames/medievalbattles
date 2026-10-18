-- The accounts database: who a player is, kept apart from what they played.
--
-- db.sql is a round -- one world, its kingdoms and everything hanging off
-- them. This is the database beside it that outlasts every round, so that
-- ending one never has to delete a login. See docs/rounds.md, "The model".
--
-- An account is linked to its kingdom in a round by `user.account_id`. The
-- round holds neither the address nor the password: the game finds a kingdom
-- by its userid, and each of these exists once, here.
--
-- InnoDB, unlike the round's MyISAM: nothing here was written in 2003, and an
-- account is the one row a crashed write must not leave half made.

CREATE TABLE `account` (
  `account_id` int unsigned NOT NULL AUTO_INCREMENT,
  -- mb_valid_email() refuses anything longer than this column.
  `email` varchar(254) NOT NULL,
  -- bcrypt, or the legacy unsalted MD5 that checklogin.php rehashes on the
  -- way through. Bound in every query that touches it, never interpolated.
  `pw` varchar(255) NOT NULL,
  -- May use app/admin/. Granted from the command line: bin/admin.php.
  `admin` tinyint(1) NOT NULL DEFAULT 0,
  -- The browser id index.php issued when the account was made, as the
  -- round's `user.signup_comp_id` records it for that kingdom.
  `signup_comp_id` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  -- When the address was confirmed by following the mailed link; NULL until
  -- then, and the game's pages refuse an unverified account. It lived in the
  -- round's `emailvalidate` table, keyed by userid, which meant confirming an
  -- address once per round and losing the confirmation with the kingdom.
  `verified_at` datetime NULL DEFAULT NULL,
  -- The code the link carries: 32 hex characters, compared with hash_equals().
  `verify_code` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
