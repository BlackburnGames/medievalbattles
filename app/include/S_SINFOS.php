<?

// Request input, formerly supplied by register_globals.
include("include/request.php");
$flag       = mb_input('flag');
$info       = mb_input('info');
$newaim     = mb_input('newaim');
$newmsn     = mb_input('newmsn');
$thesetname = mb_input('thesetname');
$theseturl  = mb_input('theseturl');

// Stored as typed. This block used to be seven strip_tags() calls, six
// htmlspecialchars() calls and ninety str_replace() calls -- 2003 standing in
// for output escaping, and doing it wrong in both directions.
//
// The str_replace() list could not have worked: it looked for "<script>" and
// "onmouseover" in a string htmlspecialchars() had already turned into
// "&lt;script&gt;". What it did do was corrupt legitimate input, because it
// stripped its needles out of the middle of the value -- a settlement named
// "Wheightsville" was stored as "Wsville", since "height" was on the list.
//
// Every page that renders these six escapes them now. The two URLs get
// mb_safe_url(), which checks the scheme -- the one thing the old list was
// reaching for and never achieved, since href="javascript:..." runs however
// well the value is escaped.
$theseturl = mb_safe_url($theseturl);
$flag      = mb_safe_url($flag);

?>