<?php
/**
 * Industry Hub loader (docs/Industry-Hub-Design.md).
 *
 * The only line the Industry Hub adds to omochix-core.php is the require of
 * this file, so the bootstrap and its existing init order stay untouched.
 * Everything registers on init priority 11: after omochix_core_init() (10)
 * has registered the existing post types the new taxonomies attach to.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once OMOCHIX_CORE_DIR . 'includes/industry-taxonomies.php';
require_once OMOCHIX_CORE_DIR . 'includes/provider.php';

/**
 * Register the Industry Hub data model.
 *
 * @return void
 */
function omochix_core_industry_hub_init() {
	omochix_core_register_industry_taxonomies();
	omochix_core_industry_maybe_flush_rewrite_rules();
}
add_action( 'init', 'omochix_core_industry_hub_init', 11 );
