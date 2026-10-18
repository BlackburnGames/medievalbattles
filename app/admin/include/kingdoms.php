<?php
/**
 * Every kingdom in the round, for the admin area's listings, with its owner's
 * address in place of the account id.
 *
 * The listings used to select `user.email` and let MySQL sort by it. The round
 * holds no address now -- it is the account's, in the accounts database -- so
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
        $r = mysqli_query($db, 'SELECT ' . implode(', ', $select) . ' FROM user'); // mb-unbound-ok: column names from the caller's literal list

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
