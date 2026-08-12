<?php
/**
 * Operator information page template.
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
        'type'        => 'company',
        'description' => __('OmochiXの運営情報とお問い合わせ先をご案内します。', 'omochix'),
    ]
);
