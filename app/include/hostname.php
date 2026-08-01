<?php
/**
 * Reverse-lookup of the client address, for the forum posts' "posted from".
 *
 * common.php and commong.php each declared their own gethostname() to do this.
 * That became a PHP built-in in 5.3, so loading either file was an instant
 * fatal, and loading both would have been a second one -- hence a single
 * definition here rather than a copy in each.
 *
 * It was parked in the container's compat layer during Phase 2 alongside
 * mb_db_result(); like that function it is app code, not a shim for something
 * PHP removed, so it belongs in the app.
 *
 * getenv() rather than $_SERVER because that is what the 2003 code did, and
 * the two differ under some SAPIs. gethostbyaddr() is silenced: it returns the
 * address unchanged on failure, but warns on a malformed one.
 */

if (!function_exists('mb_client_hostname')) {
    /**
     * @return string the resolved hostname, the raw address if it does not
     *                resolve, or "" when the SAPI reports no client address
     */
    function mb_client_hostname()
    {
        $address = getenv('REMOTE_ADDR');
        if (!$address) {
            $address = getenv('REMOTE_HOST');
        }
        if (!$address) {
            return '';
        }
        return @gethostbyaddr($address);
    }
}
