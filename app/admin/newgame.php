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

mysqli_query($db, "DELETE FROM emailvalidate");
echo "<div align=center><b>All validation codes have been deleted from the database.</b></div>";

mysqli_query($db, "DELETE FROM messages");
echo "<div align=center><b>All messages have been deleted from the database.</b></div>";

mysqli_query($db, "DELETE FROM setnews");
echo "<div align=center><b>All settlement news have been deleted from the database.</b></div>";

mysqli_query($db, "DELETE FROM setforums");
mysqli_query($db, "DELETE FROM setforumsmsgs");
echo "<div align=center><b>All settlement forum posts have been deleted from the database.</b></div>";

// Every account but the administrators', who are player accounts and would
// otherwise delete themselves -- and lose this page -- by starting a new game.
mysqli_query($db, "DELETE FROM user WHERE admin = 0");
mysqli_query($db, "DELETE FROM returntbl WHERE userid NOT IN (SELECT userid FROM user)");
mysqli_query($db, "DELETE FROM research WHERE userid NOT IN (SELECT userid FROM user)");
mysqli_query($db, "DELETE FROM military WHERE userid NOT IN (SELECT userid FROM user)");
mysqli_query($db, "DELETE FROM explore WHERE userid NOT IN (SELECT userid FROM user)");
mysqli_query($db, "DELETE FROM buildings WHERE userid NOT IN (SELECT userid FROM user)");
mysqli_query($db, "DELETE FROM barter WHERE userid NOT IN (SELECT userid FROM user)");
mysqli_query($db, "DELETE FROM empnews WHERE yourid NOT IN (SELECT userid FROM user)");
echo "<div align=center><b>All accounts except administrators have been deleted from the database.</b></div>";

mysqli_query($db, "DELETE FROM guild");
mysqli_query($db, "DELETE FROM guildmsgs");
mysqli_query($db, "DELETE FROM guildthreads");
mysqli_query($db, "DELETE FROM guildnews");
mysqli_query($db, "DELETE FROM guildrequests");
echo "<div align=center><b>All guild-related items have been deleted from the database.</b></div>";

mysqli_query($db, "UPDATE settlement SET setname='None'") or die(mysqli_error($db));
mysqli_query($db, "UPDATE settlement SET setpic=''") or die(mysqli_error($db));
mysqli_query($db, "UPDATE settlement SET members='0'") or die(mysqli_error($db));
mysqli_query($db, "UPDATE settlement SET setnotice='Welcome to Medieval Battles'") or die(mysqli_error($db));
mysqli_query($db, "UPDATE settlement SET setstrength='0'") or die(mysqli_error($db));
// The administrators who are left belong to no guild now, hold no votes and
// lead nothing, and are still counted in their settlements.
mysqli_query($db, "UPDATE user SET guild='None', vote=0, votefor='None', sl=''") or die(mysqli_error($db));
mysqli_query($db, "UPDATE settlement s SET members=(SELECT count(*) FROM user u WHERE u.setid=s.setid)") or die(mysqli_error($db));
echo "<div align=center><b>All settlement-related items have been deleted from the database.</b></div>";
?>
