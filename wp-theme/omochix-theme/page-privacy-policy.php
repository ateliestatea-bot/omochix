<?php
/**
 * Privacy policy page template.
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
        'type'        => 'privacy',
        'description' => __('OmochiXにおける個人情報と利用者情報の取り扱いについてご案内します。', 'omochix'),
    ]
);
