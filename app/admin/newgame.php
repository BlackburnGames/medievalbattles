<?
// Was mysql_connect() with placeholder credentials, so this script has never
// run as shipped. It now uses the shared connection like every other admin
// page -- note it still wipes the world with no authentication of any kind,
// which is a Phase 3 problem.
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

mysqli_query($db, "DELETE FROM user");
mysqli_query($db, "DELETE FROM returntbl");
mysqli_query($db, "DELETE FROM research");
mysqli_query($db, "DELETE FROM military");
mysqli_query($db, "DELETE FROM explore");
mysqli_query($db, "DELETE FROM buildings");
echo "<div align=center><b>All accounts have been deleted from the database.</b></div>";

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
echo "<div align=center><b>All settlement-related items have been deleted from the database.</b></div>";
?>
