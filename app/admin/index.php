<?php
// The admin area has no login of its own: an administrator logs into the game,
// and the account's `admin` flag opens this area. See include/auth.php.
include("include/auth.php");
mb_admin_require();
header("Location: main.php");
exit;
