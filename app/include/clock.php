<?php
  // The game's time, shown to every player and stored as text in the news,
  // forums and messages. It is one zone for everybody -- US Central unless
  // MB_TIMEZONE names another -- and only this formatting uses it: PHP's
  // own zone stays UTC, as MySQL's is, so time() and strtotime() still agree
  // with the database (the tick's activation clock compares the two).
  $clock = (new DateTime('now', new DateTimeZone(getenv('MB_TIMEZONE') ? getenv('MB_TIMEZONE') : 'America/Chicago')))->format("n/j/y, g:ia");

// Deliberately no closing tag: this is a logic-only include, and the blank
// lines that used to follow it were emitted as page output. checklogin.php
// includes this before calling header(), so that stray whitespace broke the
// post-login redirect with "headers already sent".
