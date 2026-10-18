-- The accounts database: who a player is, kept apart from what they played.
--
-- db.sql is a round -- one world, its kingdoms and everything hanging off
-- them. This is the database beside it that outlasts every round, so that
-- ending one never has to delete a login. See docs/rounds.md, "The model".
--
-- An account is linked to its kingdom in a round by `user.account_id`, not by
-- address: the address can change, and a round that has ended keeps the one it
-- had. The round keeps filtering on "email=? AND pw=?" -- the bridge
-- docs/rounds.md describes -- with `pw` holding a kingdom key rather than this
-- table's password hash.
--
-- InnoDB, unlike the round's MyISAM: nothing here was written in 2003, and an
-- account is the one row a crashed write must not leave half made.

CREATE TABLE `account` (
  `account_id` int unsigned NOT NULL AUTO_INCREMENT,
  -- 50, as in every round table, which keeps a copy of it for the game's
  -- "email=? AND pw=?" queries. mb_valid_email() refuses anything longer.
  `email` varchar(50) NOT NULL,
  -- bcrypt, or the legacy unsalted MD5 that checklogin.php rehashes on the
  -- way through. Bound in every query that touches it, never interpolated.
  `pw` varchar(255) NOT NULL,
  -- May use app/admin/. Granted from the command line: bin/admin.php.
  `admin` tinyint(1) NOT NULL DEFAULT 0,
  -- The browser id index.php issued when the account was made, as the
  -- round's `user.signup_comp_id` records it for that kingdom.
  `signup_comp_id` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
