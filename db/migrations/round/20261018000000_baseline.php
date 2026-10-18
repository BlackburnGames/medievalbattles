<?php
use Phinx\Migration\AbstractMigration;

require_once __DIR__ . '/../../phinx/sql.php';

/**
 * The round schema as it stood when migrations began (2026-10-18): a frozen copy
 * of db.sql, next to this file, so that later edits to that file
 * cannot rewrite history. A fresh install and a live database start from here.
 */
final class Baseline extends AbstractMigration
{
    public function up(): void
    {
        foreach (mb_sql_statements(__DIR__ . '/20261018000000_baseline.sql') as $stmt) {
            $this->execute($stmt);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The baseline is not reversible: it is the whole schema.');
    }
}
