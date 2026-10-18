<?php
/**
 * News rows: written as data, rendered as markup.
 *
 * The three news tables -- empnews, setnews, guildnews -- used to hold finished
 * HTML. Every one of the 51 sites that files a news line built a string like
 *
 *     "<font class=yellow>$ename ($setid) successfully attacked
 *      $evu[ename] ($evu[setid]) and gained $landgain land</font>"
 *
 * and stored it. That is markup in the database, and worse, it is markup with
 * two player-chosen empire names interpolated into it and nothing escaping
 * them -- the last stored-XSS vector in the game, and one no amount of output
 * escaping could have closed, because the column genuinely did contain HTML.
 *
 * The colour was never decoration. snews.php prints a legend for it:
 *
 *     red     empire joining or deleting
 *     blue    leaving or joining a guild
 *     yellow  attacking, successful or not
 *     orange  successfully defended
 *     lg      unsuccessfully defended
 *
 * So it is a category, and it now lives in its own `class` column while the
 * `news` column holds plain text. mb_news_html() puts them back together at
 * render time, escaping the text on the way through.
 */

require_once __DIR__ . '/html.php';

/**
 * The five categories, and the legend snews.php shows for them.
 *
 * Kept here rather than in gamedata.php because these are presentation, not
 * game values -- nothing in the engine branches on them.
 */
$MB_NEWS_CLASSES = array(
    'red'    => 'Empire Joining/Deleting',
    'blue'   => 'Leaving/Joining a Guild',
    'yellow' => 'Attacking (successful/unsuccessful)',
    'orange' => 'Successfully defended empire',
    'lg'     => 'Unsuccessfully defended empire',
);

if (!function_exists('mb_news_html')) {
    /**
     * One news row, as markup.
     *
     * The text goes through mb_rich() rather than mb_h() for one reason: two of
     * the attack lines end in "Medieval style" and want it italic. They carry
     * [i]...[/i] now, the same markup a player writes in a forum post, so there
     * is one renderer rather than a second special case.
     *
     * An unknown class falls back to yellow rather than being interpolated,
     * because this ends up inside an unquoted class= attribute and a row
     * written before the column existed would otherwise land there empty.
     *
     * @param mixed $class one of $MB_NEWS_CLASSES
     * @param mixed $text  plain text, as stored
     * @return string
     */
    function mb_news_html($class, $text)
    {
        global $MB_NEWS_CLASSES;

        $class = (string) $class;
        if (!isset($MB_NEWS_CLASSES[$class])) {
            $class = 'yellow';
        }

        return '<font class=' . $class . '>' . mb_rich($text) . '</font>';
    }
}

if (!function_exists('mb_news_emp')) {
    /**
     * File a line of empire news.
     *
     * @param mysqli $db
     * @param mixed  $date   the clock string
     * @param string $class  category
     * @param mixed  $text   plain text
     * @param mixed  $userid whose empire news this is
     */
    function mb_news_emp($db, $date, $class, $text, $userid)
    {
        // In the kingdom's round; a kingdom that no longer exists gets none.
        mb_query($db, "INSERT INTO empnews (date, news, class, yourid, round_id) SELECT ?, ?, ?, userid, round_id FROM user WHERE userid=?",
            array($date, $text, $class, (int) $userid));
    }
}

if (!function_exists('mb_news_set')) {
    /**
     * File a line of settlement news.
     *
     * $sid is the caller's own running counter -- every call site computes
     * max(sid)+1 for itself, and that stays where it is rather than moving in
     * here, because several sites file two rows from one max() and depend on
     * the offsets they choose.
     */
    function mb_news_set($db, $date, $class, $text, $setid, $sid)
    {
        mb_query($db, "INSERT INTO setnews (date, news, class, setid, sid, round_id) SELECT ?, ?, ?, setid, ?, round_id FROM settlement WHERE setid=?",
            array($date, $text, $class, (int) $sid, (int) $setid));
    }
}

if (!function_exists('mb_news_guild')) {
    /** File a line of guild news. $gid is a varchar column, so it is bound as a string. */
    function mb_news_guild($db, $date, $class, $text, $gid, $gnid)
    {
        mb_query($db, "INSERT INTO guildnews (date, news, class, gid, gnid, round_id) SELECT ?, ?, ?, ?, ?, round_id FROM guild WHERE gid=?",
            array($date, $text, $class, (string) $gid, (int) $gnid, (string) $gid));
    }
}
