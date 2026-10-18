<?php
use Phinx\Migration\AbstractMigration;

/**
 * Failed logins, for throttling (app/include/throttle.php). A new table, so
 * the previous commit's code never sees it.
 */
final class LoginFailure extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("CREATE TABLE `login_failure` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(254) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `email_at` (`email`, `at`),
  KEY `ip_at` (`ip`, `at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE `login_failure`");
    }
}
