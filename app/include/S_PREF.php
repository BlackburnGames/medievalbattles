<form method=post action="preferences.php">
<table border="0" bordercolor="#000000" align=center width="50%">
	<tr>
		<td class=main colspan=2><b>Update Preferences</b></td>
    <tr>
		<td class=main2><b>Email:</b></td>
		<td class=inner2><input type="text" name="newemail" maxlength=254 value="<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$msn = mb_input('msn');
 // Escaped though the address is validated: an apostrophe, an ampersand
 // and the rest of what an address may hold are text here, not markup.
 echo mb_h($email); ?>"></td>
	<tr>
		<td class=main2><b>AIM:</b></td>
		<td class=inner2><input type="text" name="newaim" maxlength=20 value="<? echo mb_h($aim); ?>"></td>
	<tr>
		<td class=main2><b>MSN:</b></td>
		<td class=inner2><input type="text" name="newmsn" maxlength=50 value="<? echo mb_h($msn); ?>"></td>
</table>

<br>
<center><input class=button type="submit" name="update" value="Update Account"></center>
<input type="hidden" name="update" value="1">
</form>