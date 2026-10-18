<?php
// The round's schema (MB_DB_NAME): db/migrations/round. See config.php.
require_once __DIR__ . '/config.php';
return mb_phinx_config('round', 'MB_DB_NAME', 'mbv6');
