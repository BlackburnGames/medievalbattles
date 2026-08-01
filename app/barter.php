<?php
/**
 * The open barter board.
 *
 * Everything below the board selector lives in include/barter_handler.php,
 * which guildbarter.php also uses -- the two pages were 430-line copies of one
 * another that had quietly drifted apart. See that file for what differed.
 */

define('MB_BARTER_BOARD', 'open');
include("include/barter_handler.php");
