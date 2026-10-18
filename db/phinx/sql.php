<?php
/**
 * The statements in a .sql file, for a migration to run one at a time.
 *
 * One at a time because PDO's multi-statement exec() reports an error only in
 * the first statement: a baseline handed whole to execute() could stop halfway
 * and still be recorded as run. Full-line comments (# and --) are dropped, and
 * a statement ends at a ; that ends a line, which is how db.sql and
 * db/accounts.sql are written; tests/migrations.sh proves the result matches.
 *
 * @return string[]
 */
function mb_sql_statements($path)
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("cannot read $path");
    }
    $sql = preg_replace('/^\s*(#|--).*$/m', '', str_replace("\r\n", "\n", $sql));
    $out = [];
    foreach (preg_split('/;[ \t]*$/m', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $out[] = trim($stmt);
        }
    }
    return $out;
}
