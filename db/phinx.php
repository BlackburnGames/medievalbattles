<?php
/**
 * Phinx's config: the game's one database, on the app's connection.
 *
 *   php vendor/bin/phinx status -c db/phinx.php     (or: php bin/migrate.php status)
 *
 * The settings are the MB_DB_* ones mb_db() reads, and the session is the one
 * mb_db_session() asks for, because a migration that inserts a row runs under
 * the host's strict sql_mode otherwise (docs/deployment.md). What has run is
 * recorded in the database's phinxlog table.
 */
$env = function ($name, $default) {
    $v = getenv($name);
    return $v === false || $v === '' ? $default : $v;
};

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'app',
        'app' => [
            'adapter'   => 'mysql',
            'host'      => $env('MB_DB_HOST', '127.0.0.1'),
            'name'      => $env('MB_DB_NAME', 'mbv6'),
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
