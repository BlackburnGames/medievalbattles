<?php
/**
 * Data-access helper.
 *
 * Lived in the container's compat layer while Phase 2 was in flight, because
 * that layer was auto-prepended and so needed no include line at ~47 call
 * sites. It is not a shim for a removed function though -- it is app code, and
 * the app has to keep working outside this repo's Docker image -- so it now
 * lives here and is pulled in by connect.php, which every query path already
 * reaches.
 */

if (!function_exists('mb_db_result')) {
    /**
     * Fetch a single field from a mysqli result, mirroring mysql_result().
     *
     * Note the argument order is NOT mysql_result()'s: field comes second,
     * row third. That is deliberate, and the column-name fallback below is
     * load-bearing rather than defensive. Every original call was the
     * two-argument form, e.g.
     *
     *     mysql_result($r, "noplayers")
     *
     * where mysql_result()'s second parameter is the ROW, not the field. PHP
     * cast "noplayers" to int 0, so it returned row 0 / column 0 and the name
     * was never looked up. Most of those names are fiction: the query behind
     * "noplayers" is SELECT count(userid) FROM user, whose column is actually
     * named "count(userid)". Resolving these names strictly would return null
     * at nearly every call site and break signup, login and the guild code --
     * which is exactly how the abandoned PHP 7 port disabled the game tick.
     *
     * So: use the name when it genuinely resolves, otherwise fall back to
     * column 0, which is what the 2003 code effectively did. Both paths agree
     * at every current call site, and the name path starts doing real work as
     * queries gain proper aliases during the port.
     *
     * @param mysqli_result $result
     * @param int|string    $field column name, or offset
     * @param int|null      $row   row offset; defaults to 0
     * @return mixed null when the row is absent
     */
    function mb_db_result($result, $field = 0, $row = null)
    {
        if (!($result instanceof mysqli_result)) {
            return null;
        }
        if ($row !== null && $row > 0 && !mysqli_data_seek($result, $row)) {
            return null;
        }

        $data = mysqli_fetch_array($result, MYSQLI_BOTH);
        if (!is_array($data)) {
            return null;
        }
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }
        return array_key_exists(0, $data) ? $data[0] : null;
    }
}
