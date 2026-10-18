<?php
$data = "
<table width='100%' border='1' bordercolor='#990000' cellspacing='3' cellpadding='8'>
  <tr>
    <td width='15%' bgcolor='#000000' valign='top'>
      <table align='center'>
        <tr>
          <td align='center'>
            <hr color='#660000' width='45%'>
            <center><b>Sign Up</b></center>
            <hr color='#660000' width='45%'>
              <table width='100%'>
              <form method='post' action='checksignup.php'>
                <tr><td>Email Address:</td><td><input type='text' name='email' size='30' maxlength='254'></td></tr>
                <tr><td>Confirm Email:</td><td><input type='text' name='cemail' size='30' maxlength='254'></td></tr>
                <tr><td>Password:</td><td><input type='password' name='pw' size='30' maxlength='15'></td></tr>
                <tr><td>Confirm Password:</td><td><input type='password' size='30' name='cpw' maxlength='15'></td></tr>
                <tr><td>Player Name:</td><td><input type='text' name='handle' size='30' maxlength='25'></td></tr>
                <tr><td colspan='2'>Your kingdom -- its name, race and class -- is chosen when you join a round, after you log in.</td></tr>
                <tr><td></td></tr>
                <tr align='center'><td colspan='2'><input class='button' type='submit' name='signup' value='Sign up (click only once)'></td></tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</form>";
?>