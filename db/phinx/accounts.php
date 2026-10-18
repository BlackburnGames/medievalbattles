<?php
// The accounts' schema (MB_ACCOUNTS_DB_NAME): db/migrations/accounts. See config.php.
require_once __DIR__ . '/config.php';
return mb_phinx_config('accounts', 'MB_ACCOUNTS_DB_NAME', 'mb_accounts');
