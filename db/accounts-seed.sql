-- The fixture's three accounts, beside the kingdoms db/seed.sql gives them.
--
--   tester@example.com / test1234   (an administrator)
--   idle@example.com   / pass2
--   poor@example.com   / pass3
--
-- Two hash formats on purpose, because there are two live code paths. idle@
-- carries a bcrypt hash, which is what include/password.php writes now.
-- tester@ and poor@ keep the unsalted MD5 the game stored until Phase 3, so
-- every crawl exercises checklogin.php's legacy verify and the rehash that
-- follows it. Deleting the MD5 rows would delete the only coverage that path
-- has.
--
-- Account ids match the kingdoms' userids only because both were made in the
-- same order. Nothing relies on it: the link is `user.account_id`, which
-- db/seed.sql sets to these ids.
--
-- Re-runnable: each load clears its own rows first.

DELETE FROM `account` WHERE account_id IN (1, 2, 3);

-- All three verified, as the round's emailvalidate rows used to say.
INSERT INTO `account` (account_id, email, pw, admin, signup_comp_id, created, verified_at, verify_code) VALUES
  (1, 'tester@example.com', '16d7a4fca7442dda3ad93c9a726597e4', 1, 'seed', '2003-01-01 00:00:00', '2003-01-01 00:00:00', '16d7a4fca7442dda3ad93c9a726597e4'),
  (2, 'idle@example.com',   '$2y$10$zwzsLaBoYCYRMC8wR2Df7eJT1lrq6IjnsIHKElvgu9rGzrSxwyoEW', 0, 'seed', '2003-01-01 00:00:00', '2003-01-01 00:00:00', 'c1572d05424d0ecb2a65ec6a82aeacbf'),
  (3, 'poor@example.com',   '3afc79b597f88a72528e864cf81856d2', 0, 'seed', '2003-01-01 00:00:00', '2003-01-01 00:00:00', '3afc79b597f88a72528e864cf81856d2');
