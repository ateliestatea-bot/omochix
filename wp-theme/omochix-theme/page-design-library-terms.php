<?php
/**
 * Design Library usage terms page template ("design-library-terms", G13).
 *
 * The terms text is the page's own editor content (entered and reviewed in the WordPress admin);
 * this template only adds the shared static-page layout, like page-terms.php. With no content yet it
 * shows the shared "content in preparation" notice. It does not open the Design Library publish gate:
 * the gate depends on the "design" page only (inc/design-library.php, omochix_design_page_id()).
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
        'type'        => 'design-library-terms',
        'description' => __('Design Library の作品のコードとプロンプトを、ご自身の制作物に利用するときの条件です。', 'omochix'),
    ]
);
