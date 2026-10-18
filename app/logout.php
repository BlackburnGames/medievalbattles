<?

include("include/connect.php");

// Was a bare $_SESSION read with no session_start(), so the unsets below hit an
// empty array and logging out never marked the player offline.
include("include/session.php");

mb_query($db, "UPDATE user SET online='0' WHERE email=? AND pw=?", array($email, $pw));

// The whole session, not the two keys it used to unset: the session file, and
// the id that named it, outlived every logout. A request another site made
// has no session to end -- include/session.php never started one -- so a
// cross-site link here cannot log the player out.
mb_session_end();

echo "You have successfully logged out. <a href=index.php>Click here</a> to log back in.";

?>
