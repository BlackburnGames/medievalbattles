<?php
// Was mysql_connect() with placeholder credentials, so this script has never
// run as shipped. It uses the shared connection like every other admin page.
//
// Nothing links here and it takes no parameters: a bare GET wipes the entire
// world. It is behind the gate now -- it does not include include/igtop.php,
// so it asks for itself.
include("include/auth.php");
mb_admin_require();

include("include/connect.php");
// mb_kingdom_reset_admins(). This page runs from app/admin/, so the shared
// include is reached by path rather than by the CWD.
require_once __DIR__ . '/../include/kingdom.php';

// A new game is a new start for the current round: nothing below reaches an
// ended one (docs/rounds.md). Ending a round and opening the next is to
// replace this page.
$mb_round = (int) mb_round_current($db);


mb_query($db, "DELETE FROM messages WHERE round_id=?", array($mb_round));
echo "<div align=center><b>All messages have been deleted from the database.</b></div>";

mb_query($db, "DELETE FROM setnews WHERE round_id=?", array($mb_round));
echo "<div align=center><b>All settlement news have been deleted from the database.</b></div>";

mb_query($db, "DELETE FROM setforums WHERE round_id=?", array($mb_round));
mb_query($db, "DELETE FROM setforumsmsgs WHERE round_id=?", array($mb_round));
echo "<div align=center><b>All settlement forum posts have been deleted from the database.</b></div>";

// Every kingdom but the administrators', who are player accounts and would
// otherwise delete themselves -- and lose this page -- by starting a new game.
// The accounts themselves are kept: they live in the `account` table and
// outlast the round, and a player comes back by signing up again with the
// same address and password. See include/kingdom.php.
mb_kingdom_delete_players($db, $mb_round);
mb_query($db, "DELETE FROM returntbl WHERE round_id=? AND userid NOT IN (SELECT userid FROM user)", array($mb_round));
mb_query($db, "DELETE FROM research WHERE round_id=? AND userid NOT IN (SELECT userid FROM user)", array($mb_round));
mb_query($db, "DELETE FROM military WHERE round_id=? AND userid NOT IN (SELECT userid FROM user)", array($mb_round));
mb_query($db, "DELETE FROM explore WHERE round_id=? AND userid NOT IN (SELECT userid FROM user)", array($mb_round));
mb_query($db, "DELETE FROM buildings WHERE round_id=? AND userid NOT IN (SELECT userid FROM user)", array($mb_round));
mb_query($db, "DELETE FROM barter WHERE round_id=? AND userid NOT IN (SELECT userid FROM user)", array($mb_round));
mb_query($db, "DELETE FROM empnews WHERE round_id=? AND yourid NOT IN (SELECT userid FROM user)", array($mb_round));
echo "<div align=center><b>All kingdoms except administrators' have been deleted from the database. Accounts are kept.</b></div>";

mb_query($db, "DELETE FROM guild WHERE round_id=?", array($mb_round));
mb_query($db, "DELETE FROM guildmsgs WHERE round_id=?", array($mb_round));
mb_query($db, "DELETE FROM guildthreads WHERE round_id=?", array($mb_round));
mb_query($db, "DELETE FROM guildnews WHERE round_id=?", array($mb_round));
mb_query($db, "DELETE FROM guildrequests WHERE round_id=?", array($mb_round));
echo "<div align=center><b>All guild-related items have been deleted from the database.</b></div>";

mb_query($db, "UPDATE settlement SET setname='None' WHERE round_id=?", array($mb_round)) or die(mysqli_error($db));
mb_query($db, "UPDATE settlement SET setpic='' WHERE round_id=?", array($mb_round)) or die(mysqli_error($db));
mb_query($db, "UPDATE settlement SET members='0' WHERE round_id=?", array($mb_round)) or die(mysqli_error($db));
mb_query($db, "UPDATE settlement SET setnotice='Welcome to Medieval Battles' WHERE round_id=?", array($mb_round)) or die(mysqli_error($db));
mb_query($db, "UPDATE settlement SET setstrength='0' WHERE round_id=?", array($mb_round)) or die(mysqli_error($db));
// The administrators' kingdoms start the new game as everyone else's will:
// as signup would make them, in the settlement they were in -- which is still
// counted. They used to keep everything but their guild, votes and
// leadership, because every table above is cleared only of rows whose
// account is gone.
$mb_reset = mb_kingdom_reset_admins($db, $mb_round);
mb_query($db, "UPDATE settlement s SET members=(SELECT count(*) FROM user u WHERE u.setid=s.setid) WHERE s.round_id=?", array($mb_round)) or die(mysqli_error($db));
echo "<div align=center><b>All settlement-related items have been deleted from the database.</b></div>";
echo "<div align=center><b>" . (int) $mb_reset . " administrator kingdom(s) have been reset to their starting state.</b></div>";
?>
