<?php
use Phinx\Migration\AbstractMigration;

/**
 * Rounds: the `round` table, and a `round_id` on every table that belongs to
 * one (docs/rounds.md, "Where a round lives").
 *
 * The world already in the database becomes round 1, running, so a live
 * install carries on as it was. A fresh one gets the same round 1 from the
 * baseline's ten settlements, which is what db.sql now builds directly.
 *
 * The previous commit's code keeps working on the result: it never names
 * round_id, and the column has no default, so its inserts land in round 0
 * under the app's non-strict session -- rows no round claims, which is what
 * a missing filter would otherwise read.
 */
final class Rounds extends AbstractMigration
{
    /** Every table whose rows belong to one round. */
    const TABLES = [
        'user', 'military', 'research', 'buildings', 'explore', 'returntbl',
        'barter', 'empnews', 'messages',
        'settlement', 'setnews', 'setforums', 'setforumsmsgs',
        'guild', 'guildmsgs', 'guildnews', 'guildrequests', 'guildthreads',
    ];

    public function up(): void
    {
        $this->execute("CREATE TABLE `round` (
  `round_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `ruleset` varchar(32) NOT NULL DEFAULT 'classic',
  `status` enum('open','running','ended') NOT NULL DEFAULT 'open',
  `started_at` datetime NULL DEFAULT NULL,
  `ended_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("INSERT INTO `round` (round_id, name, status) VALUES (1, 'Round 1', 'running')");

        foreach (self::TABLES as $t) {
            $this->execute("ALTER TABLE `$t` ADD COLUMN `round_id` int unsigned NOT NULL, ADD KEY `round_id` (`round_id`)");
            $this->execute("UPDATE `$t` SET round_id = 1");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            $this->execute("ALTER TABLE `$t` DROP KEY `round_id`, DROP COLUMN `round_id`");
        }
        $this->execute("DROP TABLE `round`");
    }
}
