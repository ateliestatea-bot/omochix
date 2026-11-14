<?php
/**
 * Plugin Name: OmochiX Core
 * Description: Provides the AI tool data model and editorial interface for OmochiX.
 * Version: 1.10.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: OmochiX
 * Text Domain: omochix-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OMOCHIX_CORE_VERSION', '1.10.0' );
define( 'OMOCHIX_CORE_FILE', __FILE__ );
define( 'OMOCHIX_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'OMOCHIX_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once OMOCHIX_CORE_DIR . 'includes/post-types.php';
require_once OMOCHIX_CORE_DIR . 'includes/taxonomies.php';
require_once OMOCHIX_CORE_DIR . 'includes/meta-schema.php';
require_once OMOCHIX_CORE_DIR . 'includes/compatibility.php';
// Guarded so an FTP upload that writes omochix-core.php before this new file
// cannot break the site; every caller checks function_exists().
if ( is_readable( OMOCHIX_CORE_DIR . 'includes/tool-display-facts.php' ) ) {
	require_once OMOCHIX_CORE_DIR . 'includes/tool-display-facts.php';
}
require_once OMOCHIX_CORE_DIR . 'includes/software-application-schema.php';
require_once OMOCHIX_CORE_DIR . 'includes/relations.php';
require_once OMOCHIX_CORE_DIR . 'includes/product-timeline.php';
require_once OMOCHIX_CORE_DIR . 'includes/post-content-role.php';
require_once OMOCHIX_CORE_DIR . 'includes/prompt-post-type.php';
require_once OMOCHIX_CORE_DIR . 'includes/prompt-taxonomies.php';
require_once OMOCHIX_CORE_DIR . 'includes/prompt-meta-schema.php';
require_once OMOCHIX_CORE_DIR . 'includes/prompt-relations.php';
require_once OMOCHIX_CORE_DIR . 'includes/practice-post-type.php';
require_once OMOCHIX_CORE_DIR . 'includes/practice-taxonomies.php';
require_once OMOCHIX_CORE_DIR . 'includes/practice-meta-schema.php';
require_once OMOCHIX_CORE_DIR . 'includes/practice-relations.php';
require_once OMOCHIX_CORE_DIR . 'includes/practice-exporter.php';
require_once OMOCHIX_CORE_DIR . 'includes/practice-importer.php';
require_once OMOCHIX_CORE_DIR . 'includes/practice-publish-guard.php';
require_once OMOCHIX_CORE_DIR . 'admin/meta-boxes.php';
require_once OMOCHIX_CORE_DIR . 'admin/relations-meta-box.php';
require_once OMOCHIX_CORE_DIR . 'admin/timeline-meta-box.php';
require_once OMOCHIX_CORE_DIR . 'admin/save-meta.php';
require_once OMOCHIX_CORE_DIR . 'admin/list-table.php';
require_once OMOCHIX_CORE_DIR . 'admin/csv-importer.php';
require_once OMOCHIX_CORE_DIR . 'admin/ai-tool-content-updater.php';
require_once OMOCHIX_CORE_DIR . 'admin/prompt-meta-boxes.php';
require_once OMOCHIX_CORE_DIR . 'admin/save-prompt-meta.php';
require_once OMOCHIX_CORE_DIR . 'admin/prompt-csv-importer.php';
require_once OMOCHIX_CORE_DIR . 'admin/practice-meta-boxes.php';
require_once OMOCHIX_CORE_DIR . 'admin/save-practice-meta.php';
require_once OMOCHIX_CORE_DIR . 'admin/practice-json-importer.php';
require_once OMOCHIX_CORE_DIR . 'admin/practice-json-exporter.php';
require_once OMOCHIX_CORE_DIR . 'admin/practice-list-table.php';
// Industry Hub. A half-done FTP upload cannot break the site: the loader is
// optional (missing, or half written: the error is caught) and loads its
// module all or nothing. While the module is not loaded its workflow publish
// guard is not running either, so the fail-closed hold below stops workflows
// from going public until it is back.
if ( is_readable( OMOCHIX_CORE_DIR . 'includes/industry-hub.php' ) ) {
	try {
		require_once OMOCHIX_CORE_DIR . 'includes/industry-hub.php';
	} catch ( Throwable $omochix_core_error ) {
		unset( $omochix_core_error );
	}
}

/**
 * Whether workflows must be held back: the Industry Hub module (and with it
 * the workflow publish guard) is not loaded.
 *
 * @return bool
 */
function omochix_core_workflow_hold_active() {
	return ! function_exists( 'omochix_core_industry_hub_loaded' ) || ! omochix_core_industry_hub_loaded();
}

/**
 * Whether a practice item is, or is being made, a workflow (learn_format
 * "workflow"): its stored terms, or the terms submitted with the save.
 *
 * @param int        $post_id   Post ID (0 for a new post).
 * @param mixed|null $submitted Submitted learn_format terms (IDs, slugs, names; array or comma list).
 * @return bool
 */
function omochix_core_workflow_hold_applies( $post_id, $submitted = null ) {
	$term = get_term_by( 'slug', 'workflow', 'learn_format' );
	if ( ! $term ) {
		return false;
	}
	if ( $post_id && has_term( (int) $term->term_id, 'learn_format', (int) $post_id ) ) {
		return true;
	}
	$values = is_array( $submitted ) ? $submitted : ( is_scalar( $submitted ) ? explode( ',', (string) $submitted ) : array() );
	foreach ( $values as $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' !== $value && ( (string) $term->term_id === $value || $term->slug === $value || $term->name === $value ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Hold (classic editor, Quick Edit, Bulk Edit, XML-RPC, wp_insert_post): a
 * workflow that is not live yet stays a draft. Live items are never demoted;
 * other content is not affected.
 *
 * @param array<string, mixed> $data    Slashed post data.
 * @param array<string, mixed> $postarr Raw post array.
 * @return array<string, mixed>
 */
function omochix_core_workflow_hold_insert_data( $data, $postarr ) {
	if ( 'learn_practice' !== ( $data['post_type'] ?? '' ) || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future' ), true ) || ! omochix_core_workflow_hold_active() ) {
		return $data;
	}
	$post_id = (int) ( $postarr['ID'] ?? 0 );
	if ( $post_id && in_array( (string) get_post_field( 'post_status', $post_id ), array( 'publish', 'future' ), true ) ) {
		return $data;
	}
	$tax_input = isset( $postarr['tax_input'] ) && is_array( $postarr['tax_input'] ) ? ( $postarr['tax_input']['learn_format'] ?? null ) : null;
	if ( omochix_core_workflow_hold_applies( $post_id, $tax_input ) ) {
		$data['post_status'] = 'draft';
		if ( $post_id ) {
			set_transient( 'omochix_core_workflow_hold_' . $post_id, 1, 10 * MINUTE_IN_SECONDS );
		}
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'omochix_core_workflow_hold_insert_data', 12, 2 );

/**
 * Hold (block editor / REST): refuse to publish or schedule a workflow.
 *
 * @param stdClass|WP_Error $prepared Prepared post or an earlier error.
 * @param mixed             $request  Request.
 * @return stdClass|WP_Error
 */
function omochix_core_workflow_hold_rest( $prepared, $request ) {
	if ( $prepared instanceof WP_Error || ! in_array( isset( $prepared->post_status ) ? (string) $prepared->post_status : '', array( 'publish', 'future' ), true ) || ! omochix_core_workflow_hold_active() ) {
		return $prepared;
	}
	$post_id = isset( $prepared->ID ) ? (int) $prepared->ID : 0;
	if ( $post_id && in_array( (string) get_post_field( 'post_status', $post_id ), array( 'publish', 'future' ), true ) ) {
		return $prepared;
	}
	$submitted = is_object( $request ) && method_exists( $request, 'get_param' ) ? $request->get_param( 'learn-formats' ) : null;
	if ( ! omochix_core_workflow_hold_applies( $post_id, $submitted ) ) {
		return $prepared;
	}
	return new WP_Error( 'omochix_workflow_on_hold', __( 'OmochiX Core の更新中のため、ワークフローは公開・予約できません。下書きとして保存し、更新の完了後にもう一度公開してください。', 'omochix-core' ), array( 'status' => 503 ) );
}
add_filter( 'rest_pre_insert_learn_practice', 'omochix_core_workflow_hold_rest', 12, 2 );

/**
 * Hold (scheduled publishing by cron, which bypasses the filters above): a
 * due workflow is not published now; it is checked again in 15 minutes.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_workflow_hold_future( $post_id ) {
	if ( 'learn_practice' === get_post_type( (int) $post_id ) && omochix_core_workflow_hold_active() && omochix_core_workflow_hold_applies( (int) $post_id ) ) {
		remove_action( 'publish_future_post', 'check_and_publish_future_post', 10 );
		wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'publish_future_post', array( (int) $post_id ) );
	}
}
add_action( 'publish_future_post', 'omochix_core_workflow_hold_future', 9 );

/** Put WordPress's own scheduled publishing back for the next event. */
function omochix_core_workflow_hold_future_restore() {
	if ( false === has_action( 'publish_future_post', 'check_and_publish_future_post' ) ) {
		add_action( 'publish_future_post', 'check_and_publish_future_post', 10, 1 );
	}
}
add_action( 'publish_future_post', 'omochix_core_workflow_hold_future_restore', 11 );

/** Tell the editor why a classic save stayed a draft. */
function omochix_core_workflow_hold_notice() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id || ! get_transient( 'omochix_core_workflow_hold_' . $post_id ) ) {
		return;
	}
	delete_transient( 'omochix_core_workflow_hold_' . $post_id );
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'OmochiX Core の更新中のため、ワークフローを下書きのまま保存しました。更新の完了後にもう一度公開してください。', 'omochix-core' ) . '</p></div>';
}
add_action( 'admin_notices', 'omochix_core_workflow_hold_notice' );

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
	omochix_core_register_practice_post_type();
	omochix_core_register_practice_taxonomies();
	omochix_core_register_practice_post_meta();
	omochix_core_practice_maybe_flush_rewrite_rules();
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
	omochix_core_register_practice_post_type();
	omochix_core_register_practice_taxonomies();
	omochix_core_insert_initial_terms();
	omochix_core_insert_practice_initial_terms();
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
