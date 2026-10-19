<?php
use Phinx\Migration\AbstractMigration;

/**
 * Password reset links (app/include/password_reset.php). A new table, so the
 * previous commit's code never sees it.
 */
final class PasswordReset extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("CREATE TABLE `password_reset` (
  `reset_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `fp` char(64) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `created` datetime NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`reset_id`),
  KEY `account_created` (`account_id`, `created`),
  KEY `ip_created` (`ip`, `created`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE `password_reset`");
    }
}
