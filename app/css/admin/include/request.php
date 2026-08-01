<?php
/**
 * Forwarder to the app's request helper.
 *
 * The admin area is served from app/css/admin/, so its working directory is
 * that folder and "include/..." resolves here rather than to app/include/.
 * mb_input() itself lives with the rest of the app; this only makes it
 * reachable under the admin include root, so there is one definition.
 */

include(__DIR__ . '/../../../include/request.php');
