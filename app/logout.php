<?

include("include/connect.php");

// Was a bare $_SESSION read with no session_start(), so the unsets below hit an
// empty array and logging out never marked the player offline.
include("include/session.php");

mysqli_query($db, "UPDATE user SET online='0' WHERE email='$email' AND pw='$pw'");

unset($_SESSION['email']);
unset($_SESSION['pw']);

echo "You have successfully logged out. <a href=index.php>Click here</a> to log back in.";

?>