<?php
/**
 * Every kingdom in the current round, for the admin area's listings, with its
 * owner's address in place of the account id. An ended round's are history,
 * and in round_result (docs/rounds.md).
 *
 * The listings used to select `user.email` and let MySQL sort by it. The round
 * holds no address now -- it is the account's, in the `account` table -- so
 * the address is filled in from there and the rows are sorted here instead,
 * case-insensitively, as the old ORDER BY's collation did.
 *
 * @param mysqli   $db
 * @param string[] $columns  user columns from the caller's literal list;
 *                           'email' names the owner's address
 * @param string   $sort     one of $columns
 * @return array[]  one row per kingdom, numerically indexed in $columns' order
 */

require_once __DIR__ . '/../../include/accounts.php';

if (!function_exists('mb_admin_kingdoms')) {
    function mb_admin_kingdoms($db, array $columns, $sort)
    {
        $select = array();
        foreach ($columns as $c) {
            if (!preg_match('/^[a-z_]+$/', $c)) {
                return array();
            }
            $select[] = $c === 'email' ? 'account_id' : $c;
        }
        $r = mb_query($db, 'SELECT ' . implode(', ', $select) . ' FROM user WHERE round_id=?', array((int) mb_round_current($db))); // mb-unbound-ok: column names from the caller's literal list

        $rows = array();
        $ids = array();
        $at = array_search('email', $columns, true);
        while ($r && ($row = mysqli_fetch_row($r))) {
            $rows[] = $row;
            if ($at !== false) {
                $ids[] = (int) $row[$at];
            }
        }
        if ($at !== false) {
            $emails = mb_account_emails($ids);
            foreach ($rows as $i => $row) {
                $rows[$i][$at] = isset($emails[(int) $row[$at]]) ? $emails[(int) $row[$at]] : '';
            }
        }

        $by = array_search($sort, $columns, true);
        if ($by !== false) {
            usort($rows, function ($a, $b) use ($by) {
                return strcasecmp((string) $a[$by], (string) $b[$by]);
            });
        }
        return $rows;
    }
}

if (!function_exists('mb_admin_delete_kingdom')) {
    /**
     * Delete a kingdom of the current round, by its name: what dempire.php and
     * gameconfig.php's delete form both do.
     *
     * They did it twice, each deleting six tables by hand -- leaving the
     * kingdom's votes cast, any guild it led leaderless and its settlement's
     * count one too high -- and only gameconfig.php refused an
     * administrator's. Both come here: an administrator's kingdom is refused,
     * the settlement is told, and include/empire.php's mb_empire_remove()
     * does the rest, as for any other end of an empire. The account is kept.
     *
     * @return string  what happened, as plain text
     */
    function mb_admin_delete_kingdom($db, $ename)
    {
        require_once __DIR__ . '/../../include/empire.php';
        $r = mb_query($db, "SELECT userid, setid, account_id FROM user WHERE round_id=? AND ename=?",
            array((int) mb_round_current($db), (string) $ename));
        $row = $r ? mysqli_fetch_assoc($r) : null;
        if (!$row) {
            return "No empire called $ename.";
        }
        // The flag is the account's; revoke it first, with bin/admin.php.
        $account = mb_account_get($row['account_id']);
        if ($account && (int) $account['admin'] === 1) {
            return "$ename is an administrator. Revoke that first, with bin/admin.php.";
        }
        include __DIR__ . '/../../include/clock.php';
        mb_news_set($db, "$clock", 'red', "[b]{$ename}[/b] has been deleted by an administrator", $row['setid'], 0);
        mb_empire_remove($db, $row['userid']);
        return "$ename has been deleted.";
    }
}
