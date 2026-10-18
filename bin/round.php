<?php
/**
 * Open, start and end rounds (docs/rounds.md).
 *
 *   docker compose exec web php /repo/bin/round.php list
 *   docker compose exec web php /repo/bin/round.php open "Round 2"
 *   docker compose exec web php /repo/bin/round.php start
 *   docker compose exec web php /repo/bin/round.php end
 *
 * open makes a round that takes signups but does not tick; start sets the
 * tick on it; end writes its results and closes it to play. One round is
 * not ended at a time. The work is app/include/rounds.php's.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/include/connect.php';

$usage = "usage: php bin/round.php list | open <name> | start | end\n";
$command = isset($argv[1]) ? $argv[1] : '';

try {
    if ($command === 'list') {
        $r = mysqli_query($db, "SELECT r.round_id, r.name, r.status, r.started_at, r.ended_at,
            (SELECT count(*) FROM user u WHERE u.round_id = r.round_id) AS kingdoms
            FROM `round` r ORDER BY r.round_id");
        while ($row = mysqli_fetch_assoc($r)) {
            echo "{$row['round_id']}\t{$row['status']}\t{$row['kingdoms']} kingdoms\t"
                . ($row['started_at'] ?: '-') . "\t" . ($row['ended_at'] ?: '-') . "\t{$row['name']}\n";
        }
    } elseif ($command === 'open' && isset($argv[2])) {
        $id = mb_round_open($db, $argv[2]);
        list($first, $last) = mb_settlement_range($db, $id);
        echo "Opened round $id, \"{$argv[2]}\", with settlements $first-$last. Signups are open; `start` begins the tick.\n";
    } elseif ($command === 'start') {
        echo "Started round " . mb_round_start($db) . ".\n";
    } elseif ($command === 'end') {
        $id = mb_round_end($db);
        $n = mysqli_fetch_row(mb_query($db, "SELECT count(*) FROM round_result WHERE round_id = ?", array($id)));
        echo "Ended round $id: {$n[0]} kingdom results written.\n";
    } else {
        fwrite(STDERR, $usage);
        exit(2);
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
