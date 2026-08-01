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

  mysqli_query($db, "UPDATE game_info SET last_comp_id='$id'");
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
          <td><input type="text" name="email" maxlength=50 size=15></td>
        </tr>
        <tr>
          <td>Password:</td>
          <td><input type="password" name="pw" maxlength=15 size=15></td>
        </tr>
        <tr>
          <td align="right" colspan=2><input class=button type=submit value=Login></td>
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
        // let any local .php file be pulled in via a traversal path. basename()
        // strips directory components and the file must actually exist here.
        $page = isset($_GET['page']) ? basename((string) $_GET['page']) : '';
        if ($page === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $page) || !file_exists(__DIR__ . '/' . $page . '.php')) {
          $page = "main-site";
        }
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