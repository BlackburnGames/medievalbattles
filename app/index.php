<?php

$computer_id = isset($_COOKIE['computer_id']) ? $_COOKIE['computer_id'] : '';

if ($computer_id === '') {
  include("include/connect.php");

  $query_get = mysqli_query($db, "SELECT last_comp_id FROM game_info");
  $last_id = mysqli_fetch_array($query_get);

  $id = $last_id['last_comp_id'] + 1;

  // Was '9999999999999999999', which overflows the expiry and made the cookie
  // expire immediately, so a new id was minted on every request.
  setcookie('computer_id', $id, time() + (10 * 365 * 24 * 60 * 60));

  mb_query($db, "UPDATE game_info SET last_comp_id=?", array((int) $id));
}
?>
<html>
<head>
  <title>Medieval Battles</title>
  <link rel=stylesheet type="text/css" href="css/main-site.css">
</head>

<body>

<form method="POST" action="checklogin.php">
<table width="100%" border="0">
  <tr>
    <td valign="center">
      <table>
        <tr>
          <td colspan=2 align=center>
            <?php
              include("include/clock.php");
              echo "<b>$clock</b>";
            ?>
          </td>
        </tr>
        <tr>
          <td>Email:</td>
          <td><input type="text" name="email" maxlength=254 size=15></td>
        </tr>
        <tr>
          <td>Password:</td>
          <td><input type="password" name="pw" maxlength=15 size=15></td>
        </tr>
        <tr>
          <td align="right" colspan=2><input class=button type=submit value=Login></td>
        </tr>
        <tr>
          <td align="right" colspan=2><a href="index.php?page=forgot">Forgot your password?</a></td>
        </tr>
      </table>
    </td>
    <td><img src="images/meb.gif" align=right></td>
  </tr>
</table>
</form>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td width="15%" valign="top">
      <table width="100%" border="1" bordercolor="#990000" cellspacing=3 cellpadding=5>
        <tr>
          <td bgcolor="#000000" valign="top">
            <table>
              <tr>
                <td>
                  <a href="index.php">Main</a><br>
                  <a href="index.php?page=signup">Sign Up</a><br>
                  <a href="index.php?page=forgot">Forgot Password</a><br>
                  <!-- <a href="index.php?page=game_scores">Scores</a><br> -->
                  <a href="manual.php" target="_blank">Manual</a><br>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
    <td valign="top">
      <?php
        // Was include($_GET['page'] . ".php") with no filtering at all, which
        // let any local .php file be pulled in via a traversal path. It then
        // admitted any .php file in app/ -- including index.php itself, so
        // ?page=index recursed until PHP gave out, one counter write per
        // level for a visitor with no computer_id. Only the pages meant to
        // be shown here are; each puts its body in $data.
        $pages = array('main-site', 'signup', 'forgot', 'reset', 'about_us', 'game_scores');
        $page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : '';
        if (!in_array($page, $pages, true)) {
          $page = "main-site";
        }
        $data = '';
        include($page . ".php");
        echo $data;
      ?>
    </td>
  </tr>
</table>
<br>
<center><font face="tahoma" size="2">Copyright  2003 - <?=date("Y")?> Medieval Battles</font></center>
</body>

</html>