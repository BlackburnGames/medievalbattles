<?php
/**
 * The guild-only barter board.
 *
 * Same handler as barter.php, switched to the guild board: it lists and sells
 * only rows with page='guild', and refuses a listing posted by someone outside
 * the caller's guild.
 */

define('MB_BARTER_BOARD', 'guild');
include("include/barter_handler.php");
