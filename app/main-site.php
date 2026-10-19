<?php
// The front page's body, which index.php prints. Its numbers are the game's
// own, from include/gamedata.php, so the page cannot drift from the rules.
//
// It used to be the 2007 relaunch's post: signups opening February 19th,
// 2007, ticks "at the Sabin server speed of every five minutes", bug reports
// to a 2007 address, and "4 different races ... 4 different classes" in a
// game that has seven of each.
require_once __DIR__ . '/include/gamedata.php';

$mb_races   = count($GAMEDATA['races']);
$mb_classes = count($GAMEDATA['classes']);
$mb_safe    = (int) $GAMEDATA['timers']['safemode_ticks'];
$mb_idle    = (int) $GAMEDATA['timers']['inactivity_ticks'];
// One tick every half hour: the cron schedule (docs/deployment.md).
$mb_safe_hours = $mb_safe / 2;
$mb_idle_days  = $mb_idle / 48;

$data = "
<table width=100% border=1 bordercolor=#990000 cellspacing=3 cellpadding=8>
	<tr>
		<td width=15% bgcolor=#000000 valign=top>
			<table>
				<tr>
					<td>
						<table>
							<tr>
								<td valign=top>
									<hr color=#660000 width=45%>
									<center><b>What is Medieval Battles?</b></center>
									<hr color=#660000 width=45%>
									&nbsp; &nbsp; Medieval Battles is a text-based online game that is based off of AD&amp;D. You can choose from $mb_races different races and be one of $mb_classes different classes. Each has its own set of advantages and disadvantages. Train your civilians into recruits. Train your recruits into your army. You can train wizards, warriors, priests and archers then go out and conquer more land or mountains to expand your empire. You can even explore for land and mountains too.<br>
									<br>
									&nbsp; &nbsp; Join guilds, or make your own guild and go up against other guilds. Or you can just sit back and watch your guild grow. Build better and stronger weapons to equip your troops with. Research new spells for your wizards to cast in battle. Make friends or enemies and show everyone else what you are really made of!
								</td>
							</tr>
							<tr>
								<td valign=top>
									<hr color=#660000 width=45%>
									<center><b>Medieval Battles is back</b></center>
									<hr color=#660000 width=45%>
									&nbsp; &nbsp; This is Medieval Battles v6, the 2003 game, running again on a modern server. It plays as it always did, with a good many old bugs fixed along the way. If you find one that is left, there is a link to report it inside the game.
								</td>
							</tr>
							<tr>
								<td valign=top>
									<hr color=#660000 width=45%>
									<center><b>How to play</b></center>
									<hr color=#660000 width=45%>
									&nbsp; &nbsp; <a href=index.php?page=signup>Sign up</a> for an account, follow the activation link you are emailed, then log in and join the current round with a kingdom of your own. Your account carries over from round to round; each round you found a new kingdom.<br><br>
									&nbsp; &nbsp; The game ticks every 30 minutes. A new kingdom is in safe mode for its first $mb_safe ticks ($mb_safe_hours hours), and a kingdom whose owner has not logged in for $mb_idle ticks ($mb_idle_days days) is deleted. Your account is kept, and you can join again.
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>";
?>
