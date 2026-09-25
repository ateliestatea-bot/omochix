<?php
/**
 * AI tool category archive (/ai-tools/category/{slug}/).
 *
 * Reuses the AI tool archive template, which treats the queried term as its
 * category filter, so list, search, filters and pagination stay in one place.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

locate_template('archive-ai_tool.php', true, false);
