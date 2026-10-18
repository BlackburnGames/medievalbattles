<?php
/**
 * What both Phinx configs share: one database each, on the app's connection.
 *
 * Phinx keeps one migrations path per config, and the game has two databases
 * that change separately -- the round (MB_DB_NAME) and the accounts beside it
 * (MB_ACCOUNTS_DB_NAME, docs/rounds.md) -- so each has a config of its own,
 * round.php and accounts.php, and bin/migrate.php runs both. Each database
 * keeps its own phinxlog table of what has run.
 *
 * The connection is the app's: the same MB_DB_* settings connect.php reads,
 * and the same session mb_db_session() asks for, because a migration that
 * inserts a row runs under the host's strict sql_mode otherwise
 * (docs/deployment.md).
 */
function mb_phinx_config($which, $dbEnv, $dbDefault)
{
    $env = function ($name, $default) {
        $v = getenv($name);
        return $v === false || $v === '' ? $default : $v;
    };

    return [
        'paths' => [
            'migrations' => dirname(__DIR__) . '/migrations/' . $which,
        ],
        'environments' => [
            'default_migration_table' => 'phinxlog',
            'default_environment'     => 'app',
            'app' => [
                'adapter'   => 'mysql',
                'host'      => $env('MB_DB_HOST', '127.0.0.1'),
                'name'      => $env($dbEnv, $dbDefault),
                'user'      => $env('MB_DB_USER', 'mb'),
                'pass'      => $env('MB_DB_PASS', 'mb'),
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'mysql_attr_init_command' =>
                    "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'",
            ],
        ],
        'version_order' => 'creation',
    ];
}
