<?php
/**
 * Choosing a new password from a mailed link: index.php?page=reset&id=..&token=..,
 * which puts the page in $data as it does the others.
 *
 * The link's id and token ride along in the form, so the POST is checked the
 * same way the GET was. See include/password_reset.php.
 */
require_once __DIR__ . '/include/html.php';
require_once __DIR__ . '/include/password_reset.php';

$mb_post = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
$mb_src = $mb_post ? $_POST : $_GET;
$mb_reset_id = isset($mb_src['id']) ? (int) $mb_src['id'] : 0;
$mb_token = isset($mb_src['token']) && is_string($mb_src['token']) ? $mb_src['token'] : '';
$mb_account = mb_password_reset_find($mb_reset_id, $mb_token);

$mb_said = '';
$mb_form = true;
if (!$mb_account) {
  $mb_said = "This link has expired or has already been used. You can <a href='index.php?page=forgot'>ask for another</a>.";
  $mb_form = false;
} elseif ($mb_post) {
  $mb_pw  = isset($_POST['pw']) ? (string) $_POST['pw'] : '';
  $mb_cpw = isset($_POST['cpw']) ? (string) $_POST['cpw'] : '';
  if ($mb_pw === '') {
    $mb_said = "You must choose a password.";
  } elseif ($mb_pw !== $mb_cpw) {
    $mb_said = "Those passwords do not match.";
  } else {
    mb_password_reset_complete($mb_account, $mb_pw);
    $mb_said = "Your password has been changed. <a href='index.php'>Log in</a> with it above.";
    $mb_form = false;
  }
}

$mb_body = $mb_said === '' ? '' : "<tr><td colspan='2'><b>$mb_said</b><br><br></td></tr>";
if ($mb_form) {
  $mb_body .= "
                <tr><td colspan='2'>Choose a new password for " . mb_h($mb_account['email']) . ".</td></tr>
                <tr><td>New Password:</td><td><input type='password' name='pw' size='30' maxlength='15'></td></tr>
                <tr><td>Confirm Password:</td><td><input type='password' name='cpw' size='30' maxlength='15'></td></tr>
                <tr align='center'><td colspan='2'>
                  <input type='hidden' name='id' value=" . mb_attr($mb_reset_id) . ">
                  <input type='hidden' name='token' value=" . mb_attr($mb_token) . ">
                  <input class='button' type='submit' value='Set password'>
                </td></tr>";
}

$data = "
<table width='100%' border='1' bordercolor='#990000' cellspacing='3' cellpadding='8'>
  <tr>
    <td width='15%' bgcolor='#000000' valign='top'>
      <table align='center'>
        <tr>
          <td align='center'>
            <hr color='#660000' width='45%'>
            <center><b>Reset Your Password</b></center>
            <hr color='#660000' width='45%'>
            <form method='post' action='index.php?page=reset'>
              <table width='100%'>
                $mb_body
              </table>
            </form>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>";
