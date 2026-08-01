<html>
<head>
<title>Medieval Battles</title>
<link rel=stylesheet type="text/css" href="css/main-site.css">
</head>

<body>

<form type=get action="checklogin.php">
<table width="100%" border="0">
	<tr>
		<td><img src="images/logo.gif" height="130" width="577"></td>
		<td valign="center">
			<table>
				<tr>
					<td colspan=2 align=center>
						<?php
							include("include/clock.php");
							echo $clock;
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
									<a href="http://forums.medievalbattles.com">Public Forums</a><br>
									<a href="manual.html">Manual</a><br>
									<a href="index.php?page=game_scores">Scores</a><br>
									<a href="index.php?page=about_us">About Us</a><br>
									<a href="index.php?page=agn">Announcements</a><br>
									<a href="index.php?page=agn">Game News</a><br>
									<a href="http://sabin.medievalbattles.com">Sabin</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		<td>
		<td valign="top">
			<table width=100% border=1 bordercolor=#990000 cellspacing=3 cellpadding=8>
				<tr>
					<td width=15% bgcolor=#000000 valign=top>
						<table align=center>
							<tr>
								<td align=center>
									<table width=100%>
<?php
	if(!IsSet($_GET['activate']))	{
		echo "not activated";
	}	else	{
		include("include/connect.php");

		$validate_result = $db->query("SELECT userid, code, check FROM emailvalidate WHERE userid='$act_userid'");
		$validate = mysqli_fetch_array($validate_result);

		if($act_code != $validate[1])	{
			echo "<center><b>Validation code is incorrect.</b></center><br>";
		}
		else	{
			$db->query("UPDATE emailvalidate SET check='2' WHERE userid='$act_userid' AND code='$act_code'");
			echo "<center><b>Your account has been activated!</b></center>";
		}
	}
?>
									</table>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<br>
<center><font face=tahoma size=2>Copyright &copy; <?php echo date("Y"); ?> Medieval Battles</font></center>
</body>
</html>