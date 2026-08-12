<?php
/**
 * Contact page template.
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
        'type'        => 'contact',
        'description' => __('OmochiXへのお問い合わせ窓口です。内容をご確認のうえご連絡ください。', 'omochix'),
    ]
);
