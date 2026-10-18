<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$empvalue = mb_input('empvalue');

include("include/igtop.php");
require_once __DIR__ . '/include/election.php';

$change = $_POST['change'];

echo "<br><br><br><br>";

// The election used to run here, on every view of the page: it cleared every
// member's sl flag and then crowned the top vote-holder only if somebody had a
// vote -- so in a settlement where nobody had voted, looking at this page
// deposed the leader. It runs when a vote changes now; see include/election.php.

echo "
<table border=1 bordercolor=#000000 align=center width=80%>
  <tr>
    <td colspan=6>
<form method=post action=govt.php>
      <center>";

if (!IsSet($change)) {

  echo "
        <select name=empvalue>
          <option selected value=ns>-- Select an Empire --</option>
            <option select value=NOemp>-None-</option>";

  $result_id = mb_query($db, "SELECT userid, ename FROM user WHERE setid=? ORDER BY userid ASC", array($setid));
  while ($row = mysqli_fetch_row($result_id))  {
    echo "<option value=" . mb_attr($row[0]) . ">" . mb_h($row[1]) . "\n</option>";
  }


  echo "
        </select><br><br>
        <input type=submit name=change value=Vote class=button>
        <input type=hidden name=change value=1>
        </center></td>";

} else {

  include("include/connect.php");

  // The counts move relative to what is stored -- vote = vote - 1 -- where they
  // used to be read, adjusted and written back, which loses a vote whenever two
  // members change theirs at once.
  if($empvalue == 'ns') {
    echo"<div align=center><font class=yellow>You did not specify anyone to vote for.</font></div><br>";
    include("include/S_GOVT.php");
    die();
  }
  elseif($empvalue == 'NOemp')  {

    if($votefor == 'None') {
      echo"<font class=yellow><div align=center>You are not voting for anyone.</font></div><br>";
      include("include/S_GOVT.php");
      die();
    } else {

      //  take the vote back from the old empire and set votefor to none
      mb_query($db, "UPDATE user SET vote = vote - 1 WHERE round_id=? AND ename=? AND vote > 0", array((int) $round_id, $votefor));
      mb_query($db, "UPDATE user SET votefor='None' WHERE userid=?", array($userid));

      mb_settlement_elect($db, $setid);

      echo"<div align=center><font class=yellow>You are now voting for no one.</font></div><br>";
      include ("include/S_GOVT.php");
      die();
    }
  }
  else {

    //  the empire voted for, which has to be in your settlement
    $selected = mb_query($db, "SELECT ename, setid FROM user WHERE userid=?", array((int) $empvalue));
      $target = $selected ? mysqli_fetch_array($selected) : null;

    if(!$target OR $target['setid'] != $setid)  {
      echo"<div align=center><font class=yellow>This empire is not in your settlement.</font></div><br>";
      include("include/S_GOVT.php");
      die();
    }
    $fename = $target['ename'];

    //  move the vote: off the old empire, if there was one, and onto this one
    if($votefor != 'None')  {
      mb_query($db, "UPDATE user SET vote = vote - 1 WHERE round_id=? AND ename=? AND vote > 0", array((int) $round_id, $votefor));
    }
    mb_query($db, "UPDATE user SET votefor=? WHERE userid=?", array($fename, $userid));
    mb_query($db, "UPDATE user SET vote = vote + 1 WHERE userid=?", array((int) $empvalue));

    mb_settlement_elect($db, $setid);

    echo"<div align=center><font class=yellow>You have voted for " . mb_h($fename) . ".</font></div><br>";
    if($votefor == 'None')  {
      include ("include/S_GOVT.php");
      die();
    }
  }
}

include("include/S_GOVT.php");

?>

</td>
</tr>
</table>
</body>
</html>
