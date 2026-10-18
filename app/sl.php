<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$thesetname   = mb_input('thesetname');
$thesetnotice = mb_input('thesetnotice');
$theseturl    = mb_input('theseturl');
$update       = mb_input('update');

include("include/igtop.php");

// `== 'no'` failed open. user.sl defaults to '' and signup never sets it, so
// only govt.php ever writes 'no' -- and until somebody in the settlement had
// opened that page, any member could rename the settlement and replace its
// picture and notice. The forum pages already ask the right question.
include("include/forum_guard.php");
mb_forum_require_sl($sl);

if(!IsSet($update))	{
	include("include/S_SINFO.php");
}
else	{
	
	include("include/S_SINFOS.php");

	mb_query($db, "UPDATE settlement SET setpic =? WHERE setid=?", array($theseturl, $setid));
	mb_query($db, "UPDATE settlement SET setname =? WHERE  setid=?", array($thesetname, $setid));
	mb_query($db, "UPDATE settlement SET setnotice =? WHERE  setid=?", array($thesetnotice, $setid));

	echo "<div align=center>Your settlement information has been updated.</div>";
	include("include/S_SINFO.php");
	die();

}
?>

</td>
</tr>
</table>
</body>
</html>	