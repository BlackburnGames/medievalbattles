<?
include("include/act_igtop.php");

// The account's link: the code and the address are both the account's.
$verify_link = mb_account_verify_link(mb_account_get($mb_account_id));

$subject = "Account Activation Code";
$body = "
Welcome to Medieval Battles! Before you can do anything with your account, you must activate it first. To actiavte your account, click this link.

$verify_link

If you do not activate your account upon 24 hours of signing up, your account will be deleted.";

// include/mail.php: the sender and the link are the install's.
$mailsend = mb_mail($email, $subject, $body);

echo"<div align=center><font class=yellow size=2px><b>Your account activation code has been resent to the email you provided upon sign up.</b></font></div>";	

echo "
</td>
</tr>
</table>
</body>
</html>";
?>