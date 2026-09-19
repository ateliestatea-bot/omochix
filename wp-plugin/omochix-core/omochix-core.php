<?php
/**
 * Plugin Name: OmochiX Core
 * Description: Provides the AI tool data model and editorial interface for OmochiX.
 * Version: 1.4.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: OmochiX
 * Text Domain: omochix-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OMOCHIX_CORE_VERSION', '1.4.0' );
define( 'OMOCHIX_CORE_FILE', __FILE__ );
define( 'OMOCHIX_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'OMOCHIX_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once OMOCHIX_CORE_DIR . 'includes/post-types.php';
require_once OMOCHIX_CORE_DIR . 'includes/taxonomies.php';
require_once OMOCHIX_CORE_DIR . 'includes/meta-schema.php';
require_once OMOCHIX_CORE_DIR . 'includes/compatibility.php';
require_once OMOCHIX_CORE_DIR . 'includes/software-application-schema.php';
require_once OMOCHIX_CORE_DIR . 'includes/relations.php';
require_once OMOCHIX_CORE_DIR . 'includes/product-timeline.php';
require_once OMOCHIX_CORE_DIR . 'includes/prompt-post-type.php';
require_once OMOCHIX_CORE_DIR . 'includes/prompt-taxonomies.php';
require_once OMOCHIX_CORE_DIR . 'includes/prompt-meta-schema.php';
require_once OMOCHIX_CORE_DIR . 'admin/meta-boxes.php';
require_once OMOCHIX_CORE_DIR . 'admin/relations-meta-box.php';
require_once OMOCHIX_CORE_DIR . 'admin/timeline-meta-box.php';
require_once OMOCHIX_CORE_DIR . 'admin/save-meta.php';
require_once OMOCHIX_CORE_DIR . 'admin/list-table.php';
require_once OMOCHIX_CORE_DIR . 'admin/csv-importer.php';
require_once OMOCHIX_CORE_DIR . 'admin/prompt-meta-boxes.php';
require_once OMOCHIX_CORE_DIR . 'admin/save-prompt-meta.php';

/**
 * Register plugin data structures.
 *
 * @return void
 */
function omochix_core_init() {
	omochix_core_register_post_types();
	omochix_core_register_taxonomies();
	omochix_core_register_post_meta();
	omochix_core_register_prompt_post_type();
	omochix_core_register_prompt_taxonomies();
	omochix_core_register_prompt_post_meta();
}
add_action( 'init', 'omochix_core_init' );

/**
 * Prepare rewrite rules and starter terms once on activation.
 *
 * @return void
 */
function omochix_core_activate() {
	omochix_core_register_post_types();
	omochix_core_register_taxonomies();
	omochix_core_register_prompt_post_type();
	omochix_core_register_prompt_taxonomies();
	omochix_core_insert_initial_terms();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'omochix_core_activate' );

/**
 * Clear plugin rewrite rules on deactivation.
 *
 * @return void
 */
function omochix_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'omochix_core_deactivate' );
