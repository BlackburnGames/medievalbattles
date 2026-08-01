<?php

// Request input, formerly supplied by register_globals.
include("include/request.php");
$empvalue = mb_input('empvalue');

include("include/igtop.php");

$change = $_POST['change'];

echo "<br><br><br><br>";

//  select max amount of votes in your settlement
$_themaxvote = mysqli_query($db, "SELECT max(vote) FROM user WHERE setid='$setid'");
  $_maxvoteno = mb_db_result($_themaxvote, "_maxvoteno", 0);
//  select empire wtih most votes
$MV_empire = mysqli_query($db, "SELECT ename FROM user WHERE setid='$setid' AND vote='$_maxvoteno'");
  $MV_emp = mb_db_result($MV_empire, "MV_emp", 0);

mysqli_query($db, "UPDATE user SET sl='no' WHERE setid='$setid'");
if($_maxvoteno != 0)  {
  mysqli_query($db, "UPDATE user SET sl='no' WHERE setid='$setid'");
  mysqli_query($db, "UPDATE user SET sl='yes' WHERE ename='$MV_emp' AND setid='$setid'");
}

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

  $query_string = "SELECT userid, ename FROM user WHERE setid='$setid' ORDER BY userid ASC";
  $result_id = mysqli_query($db, $query_string);
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

  //  select max amount of votes in your settlement
  $themaxvote = mysqli_query($db, "SELECT max(vote) FROM user WHERE setid='$setid'");
    $maxvoteno = mb_db_result($themaxvote, "maxvoteno", 0);

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

      //  select votes from old empire
      $theoldempv = mysqli_query($db, "SELECT vote FROM user WHERE ename='$votefor'");
        $oldempv = mb_db_result($theoldempv, "oldempv");

      //  subtract a vote form old empire and set votefor to none
      $oldempirevotes = $oldempv - 1;
      mysqli_query($db, "UPDATE user SET vote='$oldempirevotes' WHERE ename='$votefor'");
      mysqli_query($db, "UPDATE user SET votefor='None' WHERE ename='$ename'");

      //  select max amount of votes in your set
      $_themaxvote = mysqli_query($db, "SELECT max(vote) FROM user WHERE setid='$setid'");
        $_maxvoteno = mb_db_result($_themaxvote, "_maxvoteno");

      //  select empire with most votes
      $MV_empire = mysqli_query($db, "SELECT ename FROM user WHERE setid='$setid' AND vote='_$maxvoteno'");
          $MV_emp = mb_db_result($MV_empire, "MV_emp");

      mysqli_query($db, "UPDATE user SET sl='no' WHERE setid='$setid'");
      if($_maxvoteno != 0)  {
        mysqli_query($db, "UPDATE user SET sl='no' WHERE setid='$setid'");
        mysqli_query($db, "UPDATE user SET sl='yes' WHERE ename='$MV_emp' AND setid='$setid'");
      }

      echo"<div align=center><font class=yellow>You are now voting for no one.</font></div><br>";
      include ("include/S_GOVT.php");
      die();
    }
  }
  elseif($votefor == 'None')  {

    //  ename just voted for
    $selename = mysqli_query($db, "SELECT ename FROM user WHERE userid=" . mb_sql_int($empvalue));
      $fename = mb_db_result($selename, "fename", 0);

    //  select setid
    $SELECT_SETID = mysqli_query($db, "SELECT setid FROM user WHERE userid=" . mb_sql_int($empvalue));
      $SEL_SID = mb_db_result($SELECT_SETID, "SEL_SID", 0);

    if($SEL_SID != $setid)  {
      echo"<div align=center><font class=yellow>This empire is not in your settlement.</font></div>";
      include("include/S_GOVT.php");
      die();
    }

    //  set your votefor to empire voted for
    mysqli_query($db, "UPDATE user SET votefor='$fename' WHERE email='$email' AND pw='$pw'");

    //  select vote count for empire you voted for
    $selectedempvotes = mysqli_query($db, "SELECT vote FROM user WHERE userid=" . mb_sql_int($empvalue));
      $selempvotes = mb_db_result($selectedempvotes, "selempvotes");

    //  new vote count for empire voted for
    $newvotecount = $selempvotes + 1;

    //  set  number of votes for empire you voted for
    mysqli_query($db, "UPDATE user SET vote='$newvotecount' WHERE userid=" . mb_sql_int($empvalue));

    //  select max amount of votes in your settlement
    $themaxvote = mysqli_query($db, "SELECT max(vote) FROM user WHERE setid='$setid'");
      $maxvoteno = mb_db_result($themaxvote, "maxvoteno");

    //  select empire with most votes
    $MV_empire = mysqli_query($db, "SELECT ename FROM user WHERE setid='$setid' AND vote='$maxvoteno'");
      $MV_emp = mb_db_result($MV_empire, "MV_emp");

    if($maxvoteno != 0) {
      mysqli_query($db, "UPDATE user SET sl='no' WHERE setid='$setid'");
      mysqli_query($db, "UPDATE user SET sl='yes' WHERE ename = '$MV_emp' AND setid='$setid'");
    }

    echo"<div align=center><font class=yellow>You have voted for " . mb_h($fename) . ".</font></div><br>";
    include ("include/S_GOVT.php");
    die();
  }
  else  {

    //  ename voted for
    $selename = mysqli_query($db, "SELECT ename FROM user WHERE userid=" . mb_sql_int($empvalue));
      $fename = mb_db_result($selename, "fename", 0);

    //  select setid
    $SELECT_SETID = mysqli_query($db, "SELECT setid FROM user WHERE userid=" . mb_sql_int($empvalue));
      $SEL_SID = mb_db_result($SELECT_SETID, "SEL_SID", 0);

    if($SEL_SID != $setid)  {
      echo"<div align=center><font class=yellow>This empire is not in your settlement.</font></div><br>";
      include("include/S_GOVT.php");
      die();
    }

    //  subtract old votes from old empire
    $theoldempv = mysqli_query($db, "SELECT vote FROM user WHERE ename='$votefor'");
      $oldempv = mb_db_result($theoldempv, "oldempv");

    //  add to new empires votes
    $oldempirevotes = $oldempv - 1;
    mysqli_query($db, "UPDATE user SET vote='$oldempirevotes' WHERE ename='$votefor'");

    //  set votefor
    mysqli_query($db, "UPDATE user SET votefor='$fename' WHERE email='$email' AND pw='$pw'");

    //  select vote where empire selected
    $selectedempvotes = mysqli_query($db, "SELECT vote FROM user WHERE ename='$fename'");
      $selempvotes = mb_db_result($selectedempvotes,"selempvotes");

    //  set vote+1 where empire selected
    $newvotecount = $selempvotes + 1;
    mysqli_query($db, "UPDATE user SET vote='$newvotecount' WHERE ename='$fename'");

    //  select max amount of votes in your set
    $themaxvote = mysqli_query($db, "SELECT max(vote) FROM user WHERE setid='$setid'");
      $maxvoteno = mb_db_result($themaxvote, "maxvoteno");

    //  select empire with most votes
    $MV_empire = mysqli_query($db, "SELECT ename FROM user WHERE setid='$setid' AND vote='$maxvoteno'");
      $MV_emp = mb_db_result($MV_empire, "MV_emp");

    if($maxvoteno != 0) {
      mysqli_query($db, "UPDATE user SET sl='no' WHERE setid='$setid'");
      mysqli_query($db, "UPDATE user SET sl='yes' WHERE ename='$MV_emp' AND setid='$setid'");
    }

    echo"<div align=center><font class=yellow>You have voted for " . mb_h($fename) . ".</font></div><br>";
  }
}

include("include/S_GOVT.php");

?>

</td>
</tr>
</table>
</body>
</html>