<?php
/**
 * Prompt model archive (/prompts/model/{slug}/).
 *
 * Reuses the Prompt Library archive template, which treats the queried term
 * as its fixed model filter, so list, search, filters and pagination stay in
 * one place.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

locate_template('archive-prompt.php', true, false);
