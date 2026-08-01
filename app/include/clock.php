<?php
  $clock = date("n/j/y, g:ia");

// Deliberately no closing tag: this is a logic-only include, and the blank
// lines that used to follow it were emitted as page output. checklogin.php
// includes this before calling header(), so that stray whitespace broke the
// post-login redirect with "headers already sent".
