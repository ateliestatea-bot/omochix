<?php
/**
 * About page template.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_template_part(
    'template-parts/static-page',
    null,
    [
        'type'        => 'about',
        'description' => __('OmochiXの使命、編集方針、情報の信頼性への取り組みをご紹介します。', 'omochix'),
    ]
);
