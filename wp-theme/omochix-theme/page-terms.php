<?php
/**
 * Terms page template.
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
        'type'        => 'terms',
        'description' => __('OmochiXをご利用いただく際の条件についてご案内します。', 'omochix'),
    ]
);
