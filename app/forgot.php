<?php
/**
 * Asking for a password reset link: index.php?page=forgot, which puts the
 * page in $data as it does the others.
 *
 * The answer to a request is the same whether the address has an account,
 * has asked too often, or was mailed -- see include/password_reset.php.
 */
require_once __DIR__ . '/include/credentials.php';
require_once __DIR__ . '/include/html.php';
require_once __DIR__ . '/include/password_reset.php';

$mb_said = '';
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $mb_addr = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
  $mb_account = mb_valid_email($mb_addr) ? mb_account_find($mb_addr) : null;
  if ($mb_account) {
    $mb_link = mb_password_reset_issue($mb_account, isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
    if ($mb_link !== null) {
      require_once __DIR__ . '/include/mail.php';
      mb_mail($mb_account['email'], "Reset your Medieval Battles password", "
Someone asked to reset the password of the Medieval Battles account for this address. To choose a new one, follow this link:

$mb_link

It works once, for " . (int) MB_RESET_TTL . " minutes. If you did not ask, ignore this mail: your password has not changed.
");
    }
  }
  $mb_said = "<tr><td colspan='2'><b>If " . mb_h($mb_addr) . " has an account, a link to reset its password has been mailed to it.</b><br><br></td></tr>";
}

$data = "
<table width='100%' border='1' bordercolor='#990000' cellspacing='3' cellpadding='8'>
  <tr>
    <td width='15%' bgcolor='#000000' valign='top'>
      <table align='center'>
        <tr>
          <td align='center'>
            <hr color='#660000' width='45%'>
            <center><b>Forgot Your Password?</b></center>
            <hr color='#660000' width='45%'>
            <form method='post' action='index.php?page=forgot'>
              <table width='100%'>
                $mb_said
                <tr><td colspan='2'>Enter the address you signed up with, and we will mail you a link to choose a new password.</td></tr>
                <tr><td>Email Address:</td><td><input type='text' name='email' size='30' maxlength='254'></td></tr>
                <tr align='center'><td colspan='2'><input class='button' type='submit' value='Mail me a link'></td></tr>
              </table>
            </form>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>";
