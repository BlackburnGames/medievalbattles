<?php
use Phinx\Migration\AbstractMigration;

/**
 * Round results: each kingdom as it stood when its round ended
 * (docs/rounds.md, "The model"). Written by mb_round_end(), read by the
 * history. A new table, so the previous commit's code never sees it.
 */
final class RoundResult extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("CREATE TABLE `round_result` (
  `round_id` int unsigned NOT NULL,
  `userid` bigint NOT NULL,
  `account_id` int unsigned NOT NULL,
  `ename` varchar(255) NOT NULL,
  `race` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `setid` bigint NOT NULL,
  `setname` varchar(255) NOT NULL,
  `guild` varchar(255) NOT NULL,
  `land` bigint NOT NULL,
  `mts` bigint NOT NULL,
  `exp` bigint NOT NULL,
  `place` int unsigned NOT NULL,
  PRIMARY KEY (`round_id`, `userid`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE `round_result`");
    }
}
