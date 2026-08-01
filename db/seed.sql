-- Deterministic test world for Medieval Battles v6.
--
-- Runs after db.sql (which supplies the schema, the game_info row and ten
-- empty settlements). Everything here is fixed-value on purpose: signup
-- derives starting resources from rand(), and PHP 7.1 rewired rand() to
-- mt_rand and fixed its modulo bias, so the same seed produces different
-- numbers on the 5.6 baseline than on 8.x. Seeding users directly keeps the
-- golden-master comparisons valid across PHP versions.
--
--   tester@example.com / test1234
--   idle@example.com   / pass2
--   poor@example.com   / pass3
--
-- Two hash formats on purpose, because there are two live code paths. idle@
-- carries a bcrypt hash, which is what include/password.php writes now.
-- tester@ and poor@ keep the unsalted MD5 the game stored until this change,
-- so that every crawl exercises checklogin.php's legacy verify and the rehash
-- that follows it -- six UPDATEs across six tables, and a session token that
-- has to become the new value or nothing after the redirect matches a row.
-- Deleting the MD5 rows would delete the only coverage that path has.
--
-- The `emailvalidate` codes below are unchanged: they are activation codes and
-- happen to look like the digests because signup used to store the password
-- hash as the code. checksignup.php generates a distinct one now.
--
-- User ids are deliberately 1-3. The tick loops from 0 to max(userid) rather
-- than over the rows that exist, so its cost is driven by the highest id, not
-- the player count -- high fixture ids would make every test run crawl.
--
-- Re-runnable: each load clears its own rows first.

DELETE FROM `barter`        WHERE barterid IN (1, 2, 3);
DELETE FROM `user`          WHERE userid IN (1, 2, 3);
DELETE FROM `buildings`     WHERE userid IN (1, 2, 3);
DELETE FROM `military`      WHERE userid IN (1, 2, 3);
DELETE FROM `research`      WHERE userid IN (1, 2, 3);
DELETE FROM `explore`       WHERE userid IN (1, 2, 3);
DELETE FROM `emailvalidate` WHERE userid IN (1, 2, 3);

-- check=2 means "activated"; the app refuses to render game pages otherwise.
INSERT INTO `emailvalidate` (userid, code, `check`, clock) VALUES
  (1, '16d7a4fca7442dda3ad93c9a726597e4', 2, 48),
  (2, 'c1572d05424d0ecb2a65ec6a82aeacbf', 2, 48),
  (3, '3afc79b597f88a72528e864cf81856d2', 2, 48);

-- safemode 0 on the primary tester so combat and tick paths are reachable;
-- user 2 keeps a low countdown to exercise the inactivity branch of the tick.
INSERT INTO `user`
  (email, pw, ename, race, class, gp, iron, lumber, exp, food, land, mts,
   setid, csnum, userid, online, safemode, countdown, guild, lastlogin, signup_comp_id)
VALUES
  ('tester@example.com', '16d7a4fca7442dda3ad93c9a726597e4', 'TestLord',  'Human', 'Warrior', 300000, 5000, 4000, 1000, 1500, 250, 200, 1, 1, 1, 0, 0,  336, 'None', '1/1/03, 12:00am', 'seed'),  -- fleets set below
  ('idle@example.com',   '$2y$10$zwzsLaBoYCYRMC8wR2Df7eJT1lrq6IjnsIHKElvgu9rGzrSxwyoEW', 'IdleBaron', 'Demon', 'Cleric',  120000, 6200, 3100,  400, 1500, 250, 200, 1, 1, 2, 0, 0,    2, 'None', '1/1/03, 12:00am', 'seed'),
  ('poor@example.com',   '3afc79b597f88a72528e864cf81856d2', 'PoorSerf',  'Giant', 'Ranger',       0,    0,    0,    0,    0, 250, 200, 2, 2, 3, 0, 0,  336, 'None', '1/1/03, 12:00am', 'seed');

INSERT INTO `buildings` (email, pw, home, barrack, farm, wp, gm, im, aland, amts, userid) VALUES
  ('tester@example.com', '16d7a4fca7442dda3ad93c9a726597e4', 50, 50, 50, 0, 50, 50, 100, 100, 1),
  ('idle@example.com',   '$2y$10$zwzsLaBoYCYRMC8wR2Df7eJT1lrq6IjnsIHKElvgu9rGzrSxwyoEW', 50, 50, 50, 0, 50, 50, 100, 100, 2),
  ('poor@example.com',   '3afc79b597f88a72528e864cf81856d2',  1,  1,  1, 0,  1,  1, 100, 100, 3);

INSERT INTO `military`
  (email, pw, civ, recruits, warriors, wizards, priests, maxciv, userid, warpower,
   warspeedw, cweapon, wizpower, wizspeeds, cspell, pripower, prispeedw, cstaff,
   cbow, archspeedw, archpower, wararmor, wizarmor, priarmor, wardef, wizdef,
-- `sages` on the primary tester, so the barter board can trade one. The unit
-- is called a Sage everywhere current -- functions.php reads military.sages
-- into $sages -- while `military.scientists` is what it was called before the
-- rename and is now written by nothing.
   pridef, warspeeda, wizspeeda, prispeeda, archers, suicide, sages)
VALUES
  ('tester@example.com', '16d7a4fca7442dda3ad93c9a726597e4', 1200, 200, 3, 3, 3, 200, 1, 2, 6, 'Dagger', 3, 4, 'Magic Missile', 2, 4, 'Quarterstaff', 'Bow', 4, 2, 'Studded Leather', 'Robe', 'Leather', 1, 1, 2, 0, 0, 1, 0, 0, 8),
  ('idle@example.com',   '$2y$10$zwzsLaBoYCYRMC8wR2Df7eJT1lrq6IjnsIHKElvgu9rGzrSxwyoEW', 1300, 250, 5, 0, 5, 300, 2, 2, 6, 'Dagger', 3, 4, 'Magic Missile', 2, 4, 'Quarterstaff', 'Bow', 4, 2, 'Studded Leather', 'Robe', 'Leather', 1, 1, 2, 0, 0, 1, 0, 0, 0),
  ('poor@example.com',   '3afc79b597f88a72528e864cf81856d2',    0,   0, 0, 0, 0, 100, 3, 2, 6, 'Dagger', 3, 4, 'Magic Missile', 2, 4, 'Quarterstaff', 'Bow', 4, 2, 'Studded Leather', 'Robe', 'Leather', 1, 1, 2, 0, 0, 1, 5, 0, 0);

-- r1/r15 are sages ASSIGNED to a project; r1pts/r15pts are the points they have
-- accumulated. The primary tester has both kinds so the tick's research step is
-- covered: without an assigned sage the UPDATE writes nothing observable, which
-- is how the schema being four columns short went unnoticed for the whole port.
INSERT INTO `research` (email, pw, userid, r1, r15, r1pts, r13pts, r14pts) VALUES
  ('tester@example.com', '16d7a4fca7442dda3ad93c9a726597e4', 1, 5, 2, 0, 0, 0),
  ('idle@example.com',   '$2y$10$zwzsLaBoYCYRMC8wR2Df7eJT1lrq6IjnsIHKElvgu9rGzrSxwyoEW', 2, 0, 0, 0, 0, 0),
  ('poor@example.com',   '3afc79b597f88a72528e864cf81856d2', 3, 0, 0, 0, 125000, 0);

-- An army in the field, so the tick's return path is exercised.
--
-- Slot 1 lands this tick (time1 = 1): the units should move into `military` and
-- the slot should clear. Slot 2 is still travelling (time2 = 3) and should only
-- have its timer decremented. Golems are included deliberately -- they are the
-- reason both of those statements used to fail.
DELETE FROM `returntbl` WHERE userid IN (1, 2, 3);
INSERT INTO `returntbl` (email, pw, userid, war1, wiz1, pri1, arch1, golem1, irongolem1, time1, war2, golem2, time2) VALUES
  ('tester@example.com', '16d7a4fca7442dda3ad93c9a726597e4', 1, 40, 2, 1, 3, 2, 1, 1, 25, 1, 3),
  ('idle@example.com',   '$2y$10$zwzsLaBoYCYRMC8wR2Df7eJT1lrq6IjnsIHKElvgu9rGzrSxwyoEW', 2,  0, 0, 0, 0, 0, 0, 0,  0, 0, 0),
  ('poor@example.com',   '3afc79b597f88a72528e864cf81856d2', 3,  0, 0, 0, 0, 0, 0, 0,  0, 0, 0);

-- user.fleets counts generals still AVAILABLE, not armies in the field: sending
-- an army decrements it and update.php:180 gives one back per army that lands.
-- The two armies above therefore have to be paid for here, or the tick hands
-- the tester a fifth general on a roster of four.
UPDATE `user` SET fleets = 2 WHERE userid = 1;

INSERT INTO `explore` (email, pw, userid) VALUES
  ('tester@example.com', '16d7a4fca7442dda3ad93c9a726597e4', 1),
  ('idle@example.com',   '$2y$10$zwzsLaBoYCYRMC8wR2Df7eJT1lrq6IjnsIHKElvgu9rGzrSxwyoEW', 2),
  ('poor@example.com',   '3afc79b597f88a72528e864cf81856d2', 3);

-- A guild exists so the guild and guild-forum pages are reachable. Without
-- membership the crawler cannot enter roughly ten scripts at all, and they
-- would silently sit outside the regression net.
--
-- `owner` holds the owner's USERID, not their empire name. It looks like a
-- name column and every screen renders it next to one, but gc.php:210 inserts
-- '$userid' and every reader compares it to '$userid' -- so seeding the ename
-- here (as this fixture did until now) leaves the guild with no recognised
-- leader, and silently hid gl-forum, gl-topic, gl-inputposts, gl-delposts and
-- guildconfig from the crawl.
DELETE FROM `guild` WHERE gid = 1;
INSERT INTO `guild` (gname, cpw, strength, gid, datemade, info, owner, mem, notice) VALUES
  ('Testguild', '', 0, 1, '1/1/03, 12:00am', 'A guild used by the test fixtures.', '1', 2, 'None');

UPDATE `user` SET guild = 'Testguild' WHERE userid IN (1, 2);

-- The primary tester leads settlement 1, which is what gates sl.php and the
-- sl-* settlement forum moderation pages. govt.php elects a leader by setting
-- this column; there is no separate leader column on `settlement`.
UPDATE `user` SET sl = 'yes' WHERE userid = 1;
UPDATE `user` SET sl = 'no'  WHERE userid IN (2, 3);

-- Seeded forum threads. Both forums are empty until someone posts, and an
-- empty forum renders no topic links at all -- so topic.php, topicg.php and
-- the moderation pages that hang off them were unreachable from the crawl no
-- matter which user it logged in as.
--
-- topicid/messageid are given explicitly so the crawl visits stable URLs.
DELETE FROM `setforums`      WHERE setid = 1;
DELETE FROM `setforumsmsgs`  WHERE setid = 1;
DELETE FROM `guildthreads`   WHERE guildname = 'Testguild';
DELETE FROM `guildmsgs`      WHERE topicid IN (1, 2);

INSERT INTO `setforums` (setid, topicid, name, topic, lastpost, lastposter, replies, message, datestamp) VALUES
  (1, 1, 'TestLord',  'Settlement muster',  '1/1/03, 12:00am', 'IdleBaron', 1, 'Who is holding the north wall?', '1/1/03, 12:00am'),
  (1, 2, 'IdleBaron', 'Grain prices again', '1/1/03, 12:00am', 'IdleBaron', 0, 'The mill is charging double.',   '1/1/03, 12:00am');

INSERT INTO `setforumsmsgs` (setid, messageid, name, topic, topicid, message, datestamp) VALUES
  (1, 1, 'IdleBaron', 'Settlement muster', 1, 'I will take the watch.', '1/1/03, 12:00am');

INSERT INTO `guildthreads` (topicid, name, host, topic, lastpost, lastposter, replies, message, datestamp, guildname) VALUES
  (1, 'TestLord',  'localhost', 'Guild orders',   '1/1/03, 12:00am', 'IdleBaron', 1, 'Muster at dawn.',        '1/1/03, 12:00am', 'Testguild'),
  (2, 'IdleBaron', 'localhost', 'Recruiting', '1/1/03, 12:00am', 'IdleBaron', 0, 'We have two open slots.', '1/1/03, 12:00am', 'Testguild');

INSERT INTO `guildmsgs` (messageid, name, host, topic, topicid, message, datestamp) VALUES
  (1, 'IdleBaron', 'localhost', 'Guild orders', 1, 'Understood.', '1/1/03, 12:00am');

-- A message in the primary tester's inbox. vmessages.php was reachable before
-- this but rendered an empty table: `messages` starts empty, so the loop that
-- draws each message -- and runs two more queries per row on the sender's name
-- -- never executed once in the whole crawl.
DELETE FROM `messages` WHERE yourid = 1;
INSERT INTO `messages` (origin, datesent, message, yourid, mid, age) VALUES
  ('IdleBaron', '1/1/03, 12:00am', 'Care to trade iron for gold?', 1, 1, 0);

-- The barter boards.
--
-- `user.barterclock` counts down from 336 ticks -- a week -- and barter.php
-- refuses to open until it is at or below 1. Left at the schema default the
-- fixture could never reach either board, so the primary tester and the guild
-- member start with it spent. PoorSerf keeps the default so the "your empire
-- is too young" branch is still represented in the fixture.
UPDATE `user` SET barterclock = 0   WHERE userid IN (1, 2);
UPDATE `user` SET barterclock = 336 WHERE userid = 3;

-- Three listings, one per path the crawl drives.
--
-- The tester buys 1 and 2, and cancels 3. Two sellers are needed because you
-- cannot buy your own listing, and the guild row has to come from a guild-mate
-- or guildbarter.php refuses it -- which is the check that was never reachable
-- before, since include/guild_barter.php sent every Barter link to the open
-- board instead.
--
-- barterid 1-3 are fixed so the crawl visits stable URLs; the add form derives
-- the next id from max(barterid), so anything it posts lands at 4 and up.
INSERT INTO `barter` (seller, cost, type, amount, barterid, userid, method, page, guild) VALUES
  ('PoorSerf',  2000, 'Land',    10, 1, 3, 'gp',   '',      ''),
  ('IdleBaron',  150, 'Recruit', 25, 2, 2, 'iron', 'guild', 'Testguild'),
  ('TestLord',   500, 'Warrior',  1, 3, 1, 'gp',   '',      '');

-- Settlements 1 and 2 now hold the seeded users.
UPDATE `settlement` SET members = 2, setname = 'Testhold'  WHERE setid = 1;
UPDATE `settlement` SET members = 1, setname = 'Poorhaven' WHERE setid = 2;

-- Make sure the game is not stuck mid-tick; update.php sets this to 'yes' and
-- never resets it, which makes every page render "Tick in progress" and die.
UPDATE `game_info` SET tick = 'no';
