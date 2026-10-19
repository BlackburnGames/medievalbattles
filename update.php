<?php
//include("www/include/connect.php");
//include("www/include/clock.php");
include("app/include/connect.php");
include("app/include/clock.php");

/*
 * The manual's numbers, read by the engine rather than transcribed from it.
 *
 * include/gamedata.php has been a one-way copy since it was written: every
 * value carries a file:line citation back to the engine, and keeping the two in
 * step was a review rule. A rule is not a mechanism, so this inverts the
 * dependency one block at a time -- the economy rates first, because the tick
 * golden master computes every one of them and will not let a transcription
 * error through.
 *
 * An inverted value's citation changes direction with it: gamedata.php is the
 * definition now and the comment says who reads it.
 *
 * $GAMEDATA arrives with connect.php above, which is the one include every
 * reader of it already has.
 */
$ECON = $GAMEDATA['economy'];
$XP   = $GAMEDATA['exp_values'];

echo "<p>Starting update process</p>";

/*
 * One round at a time: the running one. Every statement below is confined to
 * it -- by a userid, a setid or a gid of its own, or by round_id -- so an
 * ended round is never advanced (docs/rounds.md). With none running there is
 * nothing to do.
 */
$round_id = mb_round_running($db);
if (!$round_id) {
  echo "<p>No round is running.</p>";
  return;
}

  mysqli_query($db, "UPDATE game_info SET tick='yes'");

/*
 * Every kingdom's lock, in userid order, before anything is read. A page holds
 * its kingdom's from before it reads to the end of the request and writes back
 * totals from that read (mb_kingdom_lock(), include/db.php), so a request in
 * flight across the tick wrote the tick's gains away, or delivered a finished
 * build twice. With tick='yes' set first no new page gets past igtop.php; the
 * tick waits here for the ones already running.
 *
 * An attack is the one page that holds two -- its own, then its target's
 * (mb_kingdom_lock_target()) -- so it can meet the tick holding the second and
 * waiting for the first. MySQL breaks such a cycle by refusing one GET_LOCK;
 * if that is the tick's, it lets go of everything and starts over, and the
 * attack, which needs no more locks, finishes in the meantime.
 */
$lock_ids = array();
$lock_kingdoms = mb_query($db, "SELECT userid FROM user WHERE round_id=? ORDER BY userid", array($round_id));
while ($lock_row = mysqli_fetch_row($lock_kingdoms)) {
  $lock_ids[] = (int) $lock_row[0];
}
$lock_held = false;
for ($lock_try = 1; $lock_try <= 5 && !$lock_held; $lock_try++) {
  $lock_held = true;
  foreach ($lock_ids as $lock_id) {
    if (!@mb_kingdom_lock($db, $lock_id, 60)) {
      $lock_held = false;
      mysqli_query($db, "SELECT RELEASE_ALL_LOCKS()");
      break;
    }
  }
}
if (!$lock_held) {
  mysqli_query($db, "UPDATE game_info SET tick = 'no'");
  fwrite(STDERR, "update.php: could not hold every kingdom after 5 tries; tick skipped\n");
  exit(1);
}
  $mb_ids = mysqli_fetch_row(mb_query($db, "SELECT min(userid), max(userid) FROM user WHERE round_id=?", array($round_id)));
  $max_UID = (int) $mb_ids[1];

/*
 * Walks userids rather than rows, so it visits every id from the round's
 * first to its highest -- including ids that never existed and ids whose owner
 * was deleted. $INC_ID was not initialised, so the first pass was always for
 * userid 0, which no player has ever had.
 *
 * Every write in this loop is scoped by userid and matched nothing on those
 * passes, except the two resource UPDATEs below: with no row to read, they
 * interpolated empty strings into their arithmetic and MySQL rejected them as
 * syntax errors. Harmless, but the errors are noise in tests/broken-queries.txt
 * and they hid the two real failures next to them for the whole port.
 *
 * Skipping an id with no user row is behaviour-preserving: the only writes not
 * scoped by userid are inside the two deletion branches, and both are guarded
 * on a column value that is null when there is no row.
 */
$INC_ID = max(1, (int) $mb_ids[0]);

while($INC_ID < $max_UID + 1) {
      $result1 = mb_query($db, "SELECT * FROM user WHERE userid=? AND round_id=?", array($INC_ID, $round_id)) or die("Error in query! " . mysqli_error($db));
      $user = mysqli_fetch_array($result1);

      // A gap in the id sequence -- a deleted player, or an id never issued.
      if (!$user) {
        $INC_ID = $INC_ID + 1;
        continue;
      }

      $result2 = mb_query($db, "SELECT * FROM military WHERE userid=?", array($INC_ID)) or die("Error in query! " . mysqli_error($db));
      $mil = mysqli_fetch_array($result2);

      $result3 = mb_query($db, "SELECT * FROM buildings WHERE userid=?", array($INC_ID)) or die("Error in query! " . mysqli_error($db));
      $build = mysqli_fetch_array($result3);

      $result4 = mb_query($db, "SELECT * FROM research WHERE userid=?", array($INC_ID)) or die("Error in query! " . mysqli_error($db));
      $res = mysqli_fetch_array($result4);

      $result5 = mb_query($db, "SELECT * FROM explore WHERE userid=?", array($INC_ID)) or die("Error in query! " . mysqli_error($db));
      $explore = mysqli_fetch_array($result5);

      $result6 = mb_query($db, "SELECT * FROM returntbl WHERE userid=?", array($INC_ID)) or die("Error in query! " . mysqli_error($db));
      $ret = mysqli_fetch_array($result6);

        // The per-tick base rates. The switches below adjust these copies for
        // race, class and research; the values themselves are gamedata's.
        $foodmod = $ECON['food_per_farm'];
        $gpmod   = $ECON['gpmod'];
        $ironmod = $ECON['iron_per_mine'];
        $lumbmod = $ECON['lumber_per_mill'];
        $civmod  = $ECON['civ_growth_mod'];
        $base = $build['home'];
        $civ = $mil['civ'];
        $food = $user['food'];
        $fbase = $build['farm'];
        $civexp = 0;

##################
### Update Recruits & Resources
##################
  echo "<p>Updating Recruits and Resources...</p>";

      switch($user['class'])  {
        case "Fighter";
        $gpmod = .95;
        break;

        case"Mage";
        $gpmod = 1.1;
        break;

        case"Ranger";
        $lumbmod = $lumbmod + .1;
        break;

        case"Savant";
        $gpmod = 1.5;
        break;

        case"Insurrectionist";
        $gpmod = .90;
        break;
      }
      switch($user['race']) {
        case"Angel";
        $civmod = $civmod + .10;
        break;

        case"Dwarf";
        $gpmod = $gpmod + .15;
        $ironmod = $ironmod + .15;
        break;

        // The Demon's gold and iron deltas are gamedata's; every other modifier
        // in these two switches is still a literal. The fixture is a Human
        // "Warrior" -- not one of the seven classes -- a Demon Cleric, whose
        // class has no case here, and a Giant Ranger with one farm and no
        // mills, and those are the only rows the golden master computes.
        // Even the Demon's civilian delta is unobserved: the Demon is
        // overcrowded, and the decay below replaces $civmod outright.
        case"Demon";
        $gpmod = $gpmod + $GAMEDATA['races']['Demon']['gpmod_delta'];
        $ironmod = $ironmod + $GAMEDATA['races']['Demon']['ironmod_delta'];
        $civmod = $civmod - .10;
        break;

        case"Elf";
        $civmod = $civmod - .15;
        break;

        case"Giant";
        $foodmod = $foodmod + .1;
        break;

        case"Night Elf";
        $foodmod = $foodmod + .25;
        $lumbmod = $lumbmod + .1;
        $civmod = $civmod - .25;
        break;
      }
      switch($res['r11pts'])   {
        case"100000";
        $gpmod = $gpmod + .1;
        break;
      }
      switch($res['r12pts'])   {
        case"100000";
        $ironmod = $ironmod + .1;
        break;
      }
      switch($res['r15pts'])   {
        case"100000";
        $foodmod = $foodmod + 2;
        break;
      }

      // Overcrowded: more civilians than the homes hold.
      if($mil['civ'] > $build['home'] * $ECON['civ_per_home']) {
        $base = $mil['civ'];
        $civmod = $ECON['decay_factor'];
      }

      // Starving: more civilians than the food supply feeds.
      if($mil['civ'] > $user['food']) {
        $base = $mil['civ'];
        $civmod = $ECON['decay_factor'];
      }

      // Overflowing: more food than the farms can store.
      if($user['food'] > $build['farm'] * $ECON['food_cap_per_farm']) {
        $fbase = $user['food'];
        $foodmod = $ECON['decay_factor'];
        $food = 0;
      }

      // Pulled into locals because these two are interpolated into SQL, where
      // $ECON['x'] would need braces and $ECON[x] is the bare-key form the
      // port removed.
      $recruit_rate  = $ECON['recruit_pool_rate'];
      $gold_per_mine = $ECON['gold_per_mine'];

      mb_query($db, "UPDATE military SET maxciv = round(? + (? * ?)), civ=round(? * ?) WHERE userid=?", array(mb_num($mil['maxciv']), mb_num($recruit_rate), mb_num($mil['civ']), mb_num($base), mb_num($civmod), $INC_ID));
      mb_query($db, "UPDATE user SET gp = round((? * ?) * ? + ?), iron = round(? * ? + ?), food = round(? * ? + ?), lumber = round(? * ? + ?) WHERE userid=?", array(mb_num($build['gm']), mb_num($gold_per_mine), mb_num($gpmod), mb_num($user['gp']), mb_num($build['im']), mb_num($ironmod), mb_num($user['iron']), mb_num($foodmod), mb_num($fbase), mb_num($food), mb_num($build['lmill']), mb_num($lumbmod), mb_num($user['lumber']), $INC_ID));

##################
### Update Land & Mountains
##################
  echo "<p>Updating Land and Mountains...</p>";

      $found = mb_explore($user['race'], $user['land'], $user['mts'], $explore['expland'], $explore['expmt']);
      $land_gain = $found['land_gain'];
      $mt_gain = $found['mt_gain'];

      mb_query($db, "UPDATE buildings SET aland = aland + ?, amts = amts + ? WHERE userid=?", array(mb_num($land_gain), mb_num($mt_gain), $INC_ID));
      mb_query($db, "UPDATE user SET land = land + ?, mts = mts + ? WHERE userid=?", array(mb_num($land_gain), mb_num($mt_gain), $INC_ID));

##################
### Return Parties
##################
  echo "<p>Updating Return Parties...</p>";

      if($ret['time1'] == 1)  {
        mb_query($db, "UPDATE military SET warriors = warriors + ?, wizards = wizards + ?, priests = priests + ?, archers = archers + ?, golem = golem + ?, irongolem = irongolem + ?  WHERE userid=?", array(mb_num($ret['war1']), mb_num($ret['wiz1']), mb_num($ret['pri1']), mb_num($ret['arch1']), mb_num($ret['golem1']), mb_num($ret['irongolem1']), $INC_ID));
        mb_query($db, "UPDATE user SET fleets = fleets + 1 WHERE userid=?", array($INC_ID));
      }
      if($ret['time2'] == 1)   {
        mb_query($db, "UPDATE military SET warriors = warriors + ?, wizards = wizards + ?, priests = priests + ?, archers = archers + ?, golem = golem + ?, irongolem = irongolem + ? WHERE userid=?", array(mb_num($ret['war2']), mb_num($ret['wiz2']), mb_num($ret['pri2']), mb_num($ret['arch2']), mb_num($ret['golem2']), mb_num($ret['irongolem2']), $INC_ID));
        mb_query($db, "UPDATE user SET fleets = fleets + 1 WHERE userid=?", array($INC_ID));
      }
      if($ret['time3'] == 1)  {
        mb_query($db, "UPDATE military SET warriors = warriors + ?, wizards = wizards + ?, priests = priests + ?, archers = archers + ?, golem = golem + ?, irongolem = irongolem + ? WHERE userid=?", array(mb_num($ret['war3']), mb_num($ret['wiz3']), mb_num($ret['pri3']), mb_num($ret['arch3']), mb_num($ret['golem3']), mb_num($ret['irongolem3']), $INC_ID));
        mb_query($db, "UPDATE user SET fleets = fleets + 1 WHERE userid=?", array($INC_ID));
      }
      if($ret['time4'] == 1)  {
        mb_query($db, "UPDATE military SET warriors = warriors + ?, wizards = wizards + ?, priests = priests + ?, archers = archers + ?, golem = golem + ?, irongolem = irongolem + ? WHERE userid=?", array(mb_num($ret['war4']), mb_num($ret['wiz4']), mb_num($ret['pri4']), mb_num($ret['arch4']), mb_num($ret['golem4']), mb_num($ret['irongolem4']), $INC_ID));
        mb_query($db, "UPDATE user SET fleets = fleets + 1 WHERE userid=?", array($INC_ID));
      }
##################
### Research Updater
##################
  echo "<p>Updating Research...</p>";

  if($user['class'] == 'Savant')  { $research_mod = 1.10; }
  elseif($user['class'] == 'Warlock') { $research_mod = .75;  }
  else  { $research_mod = 1;  }

  mb_query($db, "UPDATE research SET r1pts = r1pts + (r1 * ?), r2pts = r2pts + (r2 * ?), r3pts = r3pts + (r3 * ?), r4pts = r4pts + (r4 * ?), r5pts = r5pts + (r5 * ?), r6pts = r6pts + (r6 * ?), r7pts = r7pts + (r7 * ?), r8pts = r8pts + (r8 * ?), r9pts = r9pts + (r9 * ?), r10pts = r10pts + (r10 * ?), r11pts = r11pts + (r11 * ?), r12pts = r12pts + (r12 * ?), r13pts = r13pts + (r13 * ?), r14pts = r14pts + (r14 * ?), r15pts= r15pts + (r15 * ?), r16pts= r16pts + (r16 * ?), r17pts= r17pts + (r17 * ?), r18pts= r18pts + (r18 * ?) WHERE userid=?", array(mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), mb_num($research_mod), $INC_ID));

##################
### Activation System (ASS)
##################
  echo "<p>Updating Activation System...</p>";

  // A kingdom whose account never confirmed its address, within
  // activation_hours of the account being made. The account is kept -- it is
  // not the round's to delete -- and can still confirm and sign up again.
  $mb_acct = mb_account_get($user['account_id']);
  $mb_unverified = $mb_acct && $mb_acct['verified_at'] === null
    && strtotime($mb_acct['created']) < time() - $GAMEDATA['timers']['activation_hours'] * 3600;

  if($mb_unverified)  {
    mb_news_set($db, "$clock", 'red', "$user[ename] has been deleted for inactivity.", $user['setid'], 0);

    // The address is the account's; the round holds none.
    $email = $mb_acct['email'];
    $subject = "Your Medieval Battles Account Deleted";
    $body = "
Since you did not activate your account within two days of signing up, your kingdom was deleted. Activate your account with the link you were sent, then log in at " . mb_site_url() . " and join the round again.

This email is automated. Your reply will not be recieved.";
    $mailsend = mb_mail($email, $subject, $body);

    mb_query($db, "DELETE FROM user WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM military WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM buildings WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM research WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM returntbl WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM explore WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "UPDATE user SET votefor='None' WHERE round_id=? AND votefor=?", array($round_id, $user['ename']));
  }

##################
### Automatic Inactive Deletion System (AIDS)
##################
  echo "<p>Updating Inactive Deletion System...</p>";

  mb_query($db, "UPDATE user SET countdown= countdown - 1 WHERE userid =?", array($INC_ID));

  if($user['countdown'] == 1)  {
    mb_news_set($db, "$clock", 'red', "$user[ename] has been deleted for inactivity.", $user['setid'], 0);

    // The address is the account's; the round holds none.
    $mb_acct = mb_account_get($user['account_id']);
    $email = $mb_acct ? $mb_acct['email'] : '';
    $subject = "Your Medieval Battles Account Deleted";
    $body = "
Since you have not logged in for a week, your kingdom was deleted for inactivity. Your account is kept: log in at " . mb_site_url() . " to join the round again.

This email is automated. Your reply will not be recieved.";
    $mailsend = mb_mail($email, $subject, $body);

    mb_query($db, "DELETE FROM user WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM military WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM buildings WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM research WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM returntbl WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "DELETE FROM explore WHERE userid=?", array((int) $user['userid']));
    mb_query($db, "UPDATE user SET votefor='None' WHERE round_id=? AND votefor=?", array($round_id, $user['ename']));
  }

##################
### Land & Mountain Recalculator (LMRC)
##################
  echo "<p>Updating Land and Mountain Calculations...</p>";

  $built_land_rc = $build['home'] + $build['barrack'] + $build['farm'] + $build['wp'] + $build['lmill'];
  $built_mtn_rc = $build['gm'] + $build['im'];

  $constructing_land_rc = $build['dhome'] + $build['dbarrack'] + $build['dfarm'] + $build['dwp'] + $build['dlmill'];
  $constructing_mtn_rc = $build['dgm'] + $build['dim'];

  $land_rc = $built_land_rc + $constructing_land_rc + $build['aland'];
  $mtn_rc = $built_mtn_rc + $constructing_mtn_rc + $build['amts'];

  mb_query($db, "UPDATE user SET land=? WHERE userid=?", array($land_rc, $INC_ID)) or die("Error in query! " . mysqli_error($db));
  mb_query($db, "UPDATE user SET mts=? WHERE userid=?", array($mtn_rc, $INC_ID)) or die("Error in query! " . mysqli_error($db));

##################
### Experience Recalculator (XPRC)
##################
  echo "<p>Updating Experience Calculations...</p>";

  //  calculate all units
  //
  // The score weights. Eight are gamedata's, and they are the eight the
  // fixture holds, so the golden master multiplies each by a non-zero count.
  // The other six are still literals: no fixture empire owns a thief, an
  // explorer, a suicide civilian, a catapult or either golem, so the tick
  // multiplies those weights by zero and a wrong one would pass.
  $warrior_exp = (($mil['warriors'] + $ret['war1'] + $ret['war2'] + $ret['war3'] + $ret['war4']) * $XP['Warrior']);
  $wizard_exp = (($mil['wizards'] + $ret['wiz1'] + $ret['wiz2'] + $ret['wiz3'] + $ret['wiz4']) * $XP['Wizard']);
  $priest_exp = (($mil['priests'] + $ret['pri1'] + $ret['pri2'] + $ret['pri3'] + $ret['pri4']) * $XP['Priest']);
  $archer_exp = (($mil['archers'] + $ret['arch1'] + $ret['arch2'] + $ret['arch3'] + $ret['arch4']) * $XP['Archer']);
  // Not the sages you hold: military.scientists is the unit's pre-rename
  // column and nothing writes it, so only sages assigned to the first fourteen
  // projects score. See docs/modernization.md.
  $sage_exp = (($mil['sages'] + $res['r1'] + $res['r2'] + $res['r3'] + $res['r4'] + $res['r5'] + $res['r6'] + $res['r7'] + $res['r8'] + $res['r9'] + $res['r10'] + $res['r11'] + $res['r12'] + $res['r13'] + $res['r14'] + $res['r15'] + $res['r16'] + $res['r17'] + $res['r18']) * $XP['Sage']);
  $explorer_exp = (($mil['explorers'] + $explore['expland'] + $explore['expmt']) * 10);
  $thief_exp = $mil['thieves'] * 10;
  $land_exp = $user['land'] * $XP['Land'];
  $mountain_exp = $user['mts'] * $XP['Mountain'];
  $suicide_exp = $mil['suicide'] * 28;
  $catapult_exp = $mil['catapult'] * 33;
  $recruits_exp = $mil['recruits'] * $XP['Recruit'];
  $golem_exp = $mil['golem'] * 30;
  $irongolem_exp = $mil['irongolem'] * 35;

  $xprc_units = $warrior_exp + $wizard_exp + $priest_exp + $archer_exp + $thief_exp + $explorer_exp + $sage_exp + $land_exp + $mountain_exp + $suicide_exp + $catapult_exp + $recruits_exp + $golem_exp + $irongolem_exp;

  //  calculate all weapons
  if($mil['warriorwep'] == 0) { $warrior_weapon = 0;  } // dagger
  if($mil['warriorwep'] == 1) { $warrior_weapon = 5000; } // short sword
  if($mil['warriorwep'] == 2) { $warrior_weapon = 13000;  } //  long sword
  if($mil['warriorwep'] == 3) { $warrior_weapon = 29000;  } //  bastard sword
  if($mil['warriorwep'] == 4) { $warrior_weapon = 53000;  } //  scourge
  if($mil['warriorwep'] == 5) { $warrior_weapon = 100000; } //  scimitar
  if($mil['warriorwep'] == 6) { $warrior_weapon = 168000; } //  roms fury
  if($mil['warriorwep'] == 7) { $warrior_weapon = 257000; } //  toledos broad sword
  if($mil['warriorwep'] == 8) { $warrior_weapon = 362000; } //  sword of gandalara

  if($mil['priestwep'] == 0)  { $priest_weapon = 0; } // quarterstaff
  if($mil['priestwep'] == 1)  { $priest_weapon = 5000;  } // mace
  if($mil['priestwep'] == 2)  { $priest_weapon = 15000; } //  flail
  if($mil['priestwep'] == 3)  { $priest_weapon = 35000; } //  sword of zakarum
  if($mil['priestwep'] == 4)  { $priest_weapon = 70000; } //  footman flail
  if($mil['priestwep'] == 5)  { $priest_weapon = 120000;  } //  morning star
  if($mil['priestwep'] == 6)  { $priest_weapon = 190000;  } //  thyoras tear
  if($mil['priestwep'] == 7)  { $priest_weapon = 275000;  } //  flail of isidole
  if($mil['priestwep'] == 8)  { $priest_weapon = 375000;  } //  eldamars star

  if($mil['archerwep'] == 0)  { $archer_weapon = 0; } // bow
  if($mil['archerwep'] == 1)  { $archer_weapon = 5000;  } // short bow
  if($mil['archerwep'] == 2)  { $archer_weapon = 20000; } //  ferric bow
  if($mil['archerwep'] == 3)  { $archer_weapon = 50000; } //  keldars arms
  if($mil['archerwep'] == 4)  { $archer_weapon = 90000; } //  splens sight
  if($mil['archerwep'] == 5)  { $archer_weapon = 150000;  } //  bow of tion
  if($mil['archerwep'] == 6)  { $archer_weapon = 225000;  } //  the dynefian
  if($mil['archerwep'] == 7)  { $archer_weapon = 315000;  } //  heartsong
  if($mil['archerwep'] == 8)  { $archer_weapon = 415000;  } //  shyrscreams bow

  //  calculate all armors
  if($mil['warriorarm'] == 0) { $warrior_armor = 0; } // studded leather
  if($mil['warriorarm'] == 1) { $warrior_armor = 10000; } // chain shirt
  if($mil['warriorarm'] == 2) { $warrior_armor = 30000; } //  chain mail
  if($mil['warriorarm'] == 3) { $warrior_armor = 70000; } //  breast plate
  if($mil['warriorarm'] == 4) { $warrior_armor = 130000;  } //  medieval armor

  if($mil['wizardarm'] == 0)  { $wizard_armor = 0;  } //  robe
  if($mil['wizardarm'] == 1)  { $wizard_armor = 20000;  } //  travellers robe
  if($mil['wizardarm'] == 2)  { $wizard_armor = 55000;  } //  magicians robe
  if($mil['wizardarm'] == 3)  { $wizard_armor = 105000; } //  mythril armor

  if($mil['priestarm'] == 0)  { $priest_armor = 0;  } //  leather
  if($mil['priestarm'] == 1)  { $priest_armor = 30000;  } //  golden armor
  if($mil['priestarm'] == 2)  { $priest_armor = 90000;  } //  blessed armor

  $xprc_equipment = $priest_armor + $wizard_armor + $warrior_armor + $archer_weapon + $priest_weapon + $warrior_weapon;
  $xprc = $xprc_units + $xprc_equipment;

  mb_query($db, "UPDATE user SET exp=? WHERE userid=?", array($xprc, $INC_ID));


  $INC_ID = $INC_ID + 1;
}

##################
### Misc.
##################

  echo "<p>Updating Misc...</p>";
  mb_query($db, "UPDATE user SET barterclock= barterclock - 1 WHERE round_id=? AND barterclock > 0", array($round_id));
  mb_query($db, "UPDATE user SET online = 0 WHERE round_id=?", array($round_id));
  mb_query($db, "UPDATE user SET safemode = safemode - 1 WHERE round_id=? AND safemode > 0", array($round_id));

##################
### Update Return Times
##################

  echo "<p>Updating Return Times...</p>";
  mb_query($db, "UPDATE returntbl SET war1 =0, wiz1=0, pri1=0, arch1=0, golem1=0, irongolem1=0 WHERE round_id=? AND time1 = 1", array($round_id));
  mb_query($db, "UPDATE returntbl SET war2 =0, wiz2=0, pri2=0, arch2=0, golem2=0, irongolem2=0 WHERE round_id=? AND time2 = 1", array($round_id));
  mb_query($db, "UPDATE returntbl SET war3 =0, wiz3=0, pri3=0, arch3=0, golem3=0, irongolem3=0 WHERE round_id=? AND time3 = 1", array($round_id));
  mb_query($db, "UPDATE returntbl SET war4 =0, wiz4=0, pri4=0, arch4=0, golem4=0, irongolem4=0 WHERE round_id=? AND time4 = 1", array($round_id));
  mb_query($db, "UPDATE returntbl SET time1 = time1 - 1 WHERE round_id=? AND time1 > 0", array($round_id));
  mb_query($db, "UPDATE returntbl SET time2 = time2 - 1 WHERE round_id=? AND time2 > 0", array($round_id));
  mb_query($db, "UPDATE returntbl SET time3 = time3 - 1 WHERE round_id=? AND time3 > 0", array($round_id));
  mb_query($db, "UPDATE returntbl SET time4 = time4 - 1 WHERE round_id=? AND time4 > 0", array($round_id));

##################
### Update Building Construction
##################
  echo "<p>Updating Building Construction...</p>";
  mb_query($db, "UPDATE buildings SET home    = home      + round(dhome / Hhrs)    WHERE round_id=? AND Hhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET barrack = barrack   + round(dbarrack / Bhrs) WHERE round_id=? AND Bhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET farm    = farm      + round(dfarm / Fhrs)    WHERE round_id=? AND Fhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET wp      = wp        + round(dwp / Whrs)      WHERE round_id=? AND Whrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET lmill   = lmill     + round(dlmill / Lhrs)   WHERE round_id=? AND Lhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET gm      = gm        + round(dgm / Ghrs)      WHERE round_id=? AND Ghrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET im      = im        + round(dim / Ihrs)      WHERE round_id=? AND Ihrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET dhome    = dhome    - round(dhome / Hhrs)    WHERE round_id=? AND Hhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET dbarrack = dbarrack - round(dbarrack / Bhrs) WHERE round_id=? AND Bhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET dfarm    = dfarm    - round(dfarm / Fhrs)    WHERE round_id=? AND Fhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET dwp      = dwp      - round(dwp / Whrs)      WHERE round_id=? AND Whrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET dlmill   = dlmill   - round(dlmill / Lhrs)   WHERE round_id=? AND Lhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET dgm      = dgm      - round(dgm / Ghrs)      WHERE round_id=? AND Ghrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET dim      = dim      - round(dim / Ihrs)      WHERE round_id=? AND Ihrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET Hhrs = Hhrs - 1 WHERE round_id=? AND Hhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET Bhrs = Bhrs - 1 WHERE round_id=? AND Bhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET Fhrs = Fhrs - 1 WHERE round_id=? AND Fhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET Whrs = Whrs - 1 WHERE round_id=? AND Whrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET Lhrs = Lhrs - 1 WHERE round_id=? AND Lhrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET Ghrs = Ghrs - 1 WHERE round_id=? AND Ghrs > 0", array($round_id));
  mb_query($db, "UPDATE buildings SET Ihrs = Ihrs - 1 WHERE round_id=? AND Ihrs > 0", array($round_id));

##################
### Update Weapon Construction
##################
  echo "<p>Updating Weapon Construction...</p>";
// warrior weapons
  mb_query($db, "UPDATE military SET shortsword = shortsword - 1 WHERE round_id=? AND  shortsword > 1", array($round_id));
  mb_query($db, "UPDATE military SET longsword = longsword - 1 WHERE round_id=? AND  longsword > 1", array($round_id));
  mb_query($db, "UPDATE military SET bastardsword = bastardsword - 1 WHERE round_id=? AND bastardsword > 1", array($round_id));
  mb_query($db, "UPDATE military SET scourge = scourge - 1 WHERE round_id=? AND  scourge > 1", array($round_id));
  mb_query($db, "UPDATE military SET scimitar = scimitar - 1 WHERE round_id=? AND  scimitar > 1", array($round_id));
  mb_query($db, "UPDATE military SET romsfury = romsfury - 1 WHERE round_id=? AND romsfury > 1", array($round_id));
  mb_query($db, "UPDATE military SET broadsword = broadsword - 1 WHERE round_id=? AND broadsword > 1", array($round_id));
  mb_query($db, "UPDATE military SET gandalara = gandalara - 1 WHERE round_id=? AND gandalara > 1", array($round_id));
// priest weapons
  mb_query($db, "UPDATE military SET mace = mace - 1 WHERE round_id=? AND mace > 1", array($round_id));
  mb_query($db, "UPDATE military SET flail = flail - 1 WHERE round_id=? AND flail > 1", array($round_id));
  mb_query($db, "UPDATE military SET zakarum = zakarum - 1 WHERE round_id=? AND zakarum > 1", array($round_id));
  mb_query($db, "UPDATE military SET footmanflail = footmanflail - 1 WHERE round_id=? AND footmanflail > 1", array($round_id));
  mb_query($db, "UPDATE military SET morningstar = morningstar - 1 WHERE round_id=? AND morningstar > 1", array($round_id));
  mb_query($db, "UPDATE military SET thyorastear = thyorastear - 1 WHERE round_id=? AND thyorastear > 1", array($round_id));
  mb_query($db, "UPDATE military SET isidole = isidole - 1 WHERE round_id=? AND isidole> 1", array($round_id));
  mb_query($db, "UPDATE military SET eldamarstar = eldamarstar - 1 WHERE round_id=? AND eldamarstar > 1", array($round_id));
// archer weapons
  mb_query($db, "UPDATE military SET shortbow = shortbow - 1 WHERE round_id=? AND shortbow > 1", array($round_id));
  mb_query($db, "UPDATE military SET ferricbow = ferricbow - 1 WHERE round_id=? AND ferricbow > 1", array($round_id));
  mb_query($db, "UPDATE military SET keldarsarms = keldarsarms - 1 WHERE round_id=? AND keldarsarms > 1", array($round_id));
  mb_query($db, "UPDATE military SET splensight = splensight - 1 WHERE round_id=? AND splensight > 1", array($round_id));
  mb_query($db, "UPDATE military SET bowoftion = bowoftion - 1 WHERE round_id=? AND bowoftion > 1", array($round_id));
  mb_query($db, "UPDATE military SET dynefian = dynefian - 1 WHERE round_id=? AND dynefian > 1", array($round_id));
  mb_query($db, "UPDATE military SET heartsong = heartsong - 1 WHERE round_id=? AND heartsong > 1", array($round_id));
  mb_query($db, "UPDATE military SET shyrscreamsbow = shyrscreamsbow - 1 WHERE round_id=? AND shyrscreamsbow > 1", array($round_id));
##################
### Update Armor Construction
##################
  echo "<p>Updating Armor Construction...</p>";
  mb_query($db, "UPDATE military SET cs = cs - 1 WHERE round_id=? AND cs > 1", array($round_id));
  mb_query($db, "UPDATE military SET cm = cm - 1 WHERE round_id=? AND cm > 1", array($round_id));
  mb_query($db, "UPDATE military SET bp = bp - 1 WHERE round_id=? AND bp > 1", array($round_id));
  mb_query($db, "UPDATE military SET fp = fp - 1 WHERE round_id=? AND fp > 1", array($round_id));
  mb_query($db, "UPDATE military SET ma = ma - 1 WHERE round_id=? AND ma > 1", array($round_id));
  mb_query($db, "UPDATE military SET ga = ga - 1 WHERE round_id=? AND ga > 1", array($round_id));
  mb_query($db, "UPDATE military SET ba = ba - 1 WHERE round_id=? AND ba > 1", array($round_id));
  mb_query($db, "UPDATE military SET tr = tr - 1 WHERE round_id=? AND tr > 1", array($round_id));
  mb_query($db, "UPDATE military SET mr = mr - 1 WHERE round_id=? AND mr > 1", array($round_id));
##################
### Create Units
##################
  echo "<p>Updating Units...</p>";
  mb_query($db, "UPDATE military SET thieves = thieves + dbthief, explorers = explorers + dbexplorer, sages = sages + dbsage, dbthief = 0, dbexplorer = 0, dbsage = 0, warriors = warriors + dbwar, wizards = wizards + dbwiz, priests = priests + dbpri, archers = archers + dbarch, dbwar = 0, dbwiz = 0, dbarch = 0, dbwar = dbwar2, dbwiz = dbwiz2, dbpri = dbpri2, dbarch = dbarch2, dbwar2 = 0, dbwiz2 = 0, dbpri2 = 0, dbarch2 = 0, catapult = catapult + dbcatapult, dbcatapult = dbcatapult2, dbcatapult2 = dbcatapult3, dbcatapult3 = 0, suicide = suicide + dbsuicide, dbsuicide = dbsuicide2, dbsuicide2 = dbsuicide3, dbsuicide3 = 0 WHERE round_id=?", array($round_id)) or die("Error in query! " . mysqli_error($db));
  mb_query($db, "UPDATE military SET golem = golem + dbgolem, irongolem = irongolem + dbirongolem, dbgolem = 0, dbirongolem = 0 WHERE round_id=?", array($round_id)) or die("Error in query! " . mysqli_error($db));
##################
### Update Research
##################
  echo "<p>Updating Research...</p>";
  mb_query($db, "UPDATE research SET r1pts = 50000 WHERE round_id=? AND r1pts > 50000", array($round_id)); // fireball
  mb_query($db, "UPDATE research SET r2pts = 100000 WHERE round_id=? AND r2pts > 100000", array($round_id)); // ice storm
  mb_query($db, "UPDATE research SET r3pts = 200000 WHERE round_id=? AND r3pts > 200000", array($round_id)); // wall of fire
  mb_query($db, "UPDATE research SET r4pts = 300000 WHERE round_id=? AND r4pts > 300000", array($round_id)); // wall of ice
  mb_query($db, "UPDATE research SET r5pts = 400000 WHERE round_id=? AND r5pts > 400000", array($round_id)); // chain lightning
  mb_query($db, "UPDATE research SET r6pts = 500000 WHERE round_id=? AND r6pts > 500000", array($round_id)); // gust of wind
  mb_query($db, "UPDATE research SET r7pts = 600000 WHERE round_id=? AND r7pts > 600000", array($round_id)); // flaming sphere
  mb_query($db, "UPDATE research SET r8pts = 700000 WHERE round_id=? AND r8pts > 700000", array($round_id)); // cloud kill
  mb_query($db, "UPDATE research SET r9pts = 800000 WHERE round_id=? AND r9pts > 800000", array($round_id)); // lighting bolt
  mb_query($db, "UPDATE research SET r10pts = 900000 WHERE round_id=? AND r10pts > 900000", array($round_id)); // meteor swarm
  mb_query($db, "UPDATE research SET r11pts = 100000 WHERE round_id=? AND r11pts > 100000", array($round_id)); // adv gold mining
  mb_query($db, "UPDATE research SET r12pts = 100000 WHERE round_id=? AND r12pts > 100000", array($round_id)); // adv iron mining
  mb_query($db, "UPDATE research SET r13pts = 125000 WHERE round_id=? AND r13pts > 125000", array($round_id)); // archery
  mb_query($db, "UPDATE research SET r14pts = 125000 WHERE round_id=? AND r14pts > 125000", array($round_id)); // pyrotechnics
  mb_query($db, "UPDATE research SET r15pts = 100000 WHERE round_id=? AND r15pts > 100000", array($round_id)); // adv farming
  mb_query($db, "UPDATE research SET r16pts = 100000 WHERE round_id=? AND r16pts > 100000", array($round_id)); // adv construction
##################
### Update Messages
##################
  echo "<p>Updating Messages...</p>";
  mb_query($db, "UPDATE messages SET age = age + 1 WHERE round_id=?", array($round_id));
  mb_query($db, "UPDATE setnews SET age = age + 1 WHERE round_id=?", array($round_id));
  mb_query($db, "UPDATE guildnews SET age = age + 1 WHERE round_id=?", array($round_id));
  // Housekeeping constants, read rather than repeated. The message and news
  // tables share one lifetime, which is why it is one value.
  $msg_ttl   = $GAMEDATA['timers']['message_ttl_ticks'];
  $exp_floor = $GAMEDATA['timers']['exp_floor'];

  mb_query($db, "DELETE FROM messages WHERE round_id=? AND age=?", array($round_id, $msg_ttl));
  mb_query($db, "DELETE FROM setnews WHERE round_id=? AND age=?", array($round_id, $msg_ttl));
  mb_query($db, "DELETE FROM guildnews WHERE round_id=? AND age=?", array($round_id, $msg_ttl));

  mb_query($db, "UPDATE user SET exp = ? WHERE round_id=? AND exp < ?", array(mb_num($exp_floor), $round_id, $exp_floor));
  mysqli_query($db, "UPDATE game_info SET tick = 'no'");

  // The strength recalculation walks settlement ids rather than rows, so it is
  // bounded by the round's range -- the table's, see include/settlements.php.
  // It used to walk to 30 over a table of ten.
  list($SET_INC, $SET_MAX) = mb_settlement_range($db, $round_id);
  while($SET_INC < $SET_MAX + 1)  {
    $SET_STRENGTH = mb_query($db, "SELECT sum(exp) FROM user WHERE setid = ?", array($SET_INC));
      $S_STRENGTH = mb_db_result($SET_STRENGTH, 0, 0);
    mb_query($db, "UPDATE settlement SET setstrength = ? WHERE setid=?", array($S_STRENGTH, $SET_INC));
    $SET_INC = $SET_INC + 1;
  }

  $MAX_GUILDID = mysqli_fetch_row(mb_query($db, "SELECT min(gid), max(gid) FROM guild WHERE round_id=?", array($round_id)));
    $GUILD_INC = (int) $MAX_GUILDID[0];
    $MAX_GID = (int) $MAX_GUILDID[1];

  while($GUILD_INC < $MAX_GID + 1)  {
    $guild_name_query = mb_query($db, "SELECT gname FROM guild WHERE gid=?", array($GUILD_INC));
      $guild_name = mysqli_fetch_array($guild_name_query);
    // An id with no guild: a deleted one, or none at all in a round with no
    // guilds yet, when min and max are both empty. There is nothing to sum.
    if (!$guild_name) {
      $GUILD_INC = $GUILD_INC + 1;
      continue;
    }
    $guild_user_exp = mb_query($db, "SELECT sum(exp) FROM user WHERE round_id=? AND guild=?", array($round_id, $guild_name['gname']));
      $user_exp = mb_db_result($guild_user_exp, 0, 0);
    mb_query($db, "UPDATE guild SET strength=? WHERE gid=?", array($user_exp, $GUILD_INC));

    $GUILD_INC = $GUILD_INC + 1;
  }

// The tick used to mail the original author's address on every run, from
// every install. Whether a tick ran is the cron log's to say.
echo "<p>Tick complete.</p>";
