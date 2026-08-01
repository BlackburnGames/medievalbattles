<?

include("include/connect.php");

$email = $_SESSION['email'];
$pw = $_SESSION['pw'];

mysqli_query($db, "UPDATE user SET online='0' WHERE email='$email' AND pw='$pw'");

unset($_SESSION['email']);
unset($_SESSION['pw']);

echo "You have successfully logged out. <a href=index.php>Click here</a> to log back in.";

?>