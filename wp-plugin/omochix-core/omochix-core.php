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

// Web 2.0 security layer (docs/web2/sec/README.md). Not a module: always
// loaded, before the Web 2.0 block and also with OMOCHIX_WEB2_DISABLED, because
// it only narrows what WordPress renders (it opens nothing). Fail closed: when
// the file is missing, cut short, throws or does not define its guards, every
// navigation block renders nothing, REST returns no menu blocks to non-editors
// and searches no password-protected menu for them, the comments screen hides
// rows of unregistered post types from non-editors (P3-1),
// and the problem is logged and shown to administrators. The rest of the
// plugin keeps working.
/**
 * Loads the security layer; true when its navigation guard is in place.
 *
 * @return bool
 */
function omochix_core_sec_layer_load() {
	$file = OMOCHIX_CORE_DIR . 'includes/web2/security.php';
	$why  = '';
	if ( ! is_readable( $file ) ) {
		$why = 'missing';
	} else {
		try {
			require_once $file;
		} catch ( Throwable $omochix_core_error ) {
			$why = 'failed to load (' . get_class( $omochix_core_error ) . ')';
			unset( $omochix_core_error );
		}
	}
	// Each group: the filters (hook => callback) the layer must have registered, and the functions those
	// callbacks call. Navigation (review N-1) and the comments screen (P3-1).
	$groups = array(
		'navigation' => array(
			array(
				'register_block_type_args'              => 'omochix_core_security_nav_block_args',
				'block_core_navigation_render_fallback' => 'omochix_core_security_nav_fallback',
				'rest_prepare_wp_navigation'            => 'omochix_core_security_nav_rest',
				'rest_wp_navigation_query'              => 'omochix_core_security_nav_rest_query',
			),
			array( 'omochix_core_security_nav_ref_id', 'omochix_core_security_nav_ref_allowed', 'omochix_core_security_nav_render_allowed' ),
		),
		'comments'   => array(
			array(
				'comments_list_table_query_args' => 'omochix_core_security_comments_args',
				'the_title'                      => 'omochix_core_security_comments_title',
				'wp_count_comments'              => 'omochix_core_security_comments_count',
			),
			array( 'omochix_core_security_orphan_row_masked', 'omochix_core_security_comments_masked', 'omochix_core_security_comments_screen' ),
		),
	);
	if ( '' === $why ) {
		foreach ( $groups as $group ) {
			foreach ( $group[0] as $hook => $callback ) {
				if ( ! function_exists( $callback ) || false === has_filter( $hook, $callback ) ) {
					$why = 'incomplete';
				}
			}
			foreach ( $group[1] as $helper ) {
				if ( ! function_exists( $helper ) ) {
					$why = 'incomplete';
				}
			}
		}
	}
	if ( '' === $why ) {
		return true;
	}
	// A broken copy may have registered a filter whose function, or a function it calls, is missing:
	// running it would be a fatal error, so that group's filters are taken off. Working groups stay.
	foreach ( $groups as $group ) {
		$whole = true;
		foreach ( array_merge( array_values( $group[0] ), $group[1] ) as $fn ) {
			if ( ! function_exists( $fn ) ) {
				$whole = false;
			}
		}
		foreach ( $group[0] as $hook => $callback ) {
			$priority = has_filter( $hook, $callback );
			if ( false !== $priority && ( ! $whole || ! function_exists( $callback ) ) ) {
				remove_filter( $hook, $callback, $priority );
			}
		}
	}
	omochix_core_sec_layer_problem( $why );
	add_filter( 'register_block_type_args', 'omochix_core_nav_render_closed', 9999, 2 );
	add_filter( 'rest_prepare_wp_navigation', 'omochix_core_nav_rest_closed', 9999, 2 );
	add_filter( 'rest_wp_navigation_query', 'omochix_core_nav_query_closed', 9999 );
	add_filter( 'comments_list_table_query_args', 'omochix_core_comments_args_closed', 10 );
	add_filter( 'the_title', 'omochix_core_comments_title_closed', 9999, 2 );
	add_filter( 'wp_count_comments', 'omochix_core_comments_count_closed', 9999, 2 );
	add_action( 'admin_notices', 'omochix_core_sec_layer_notice' );
	error_log( 'OmochiX Core: the security layer (includes/web2/security.php) is ' . $why . '; navigation blocks render nothing and the comments screen hides rows of unregistered post types until it is restored.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	return false;
}

/**
 * Fail closed on the comments screen (P3-1) while the security layer is unavailable: the same rule as
 * the layer, kept here so it does not depend on that file. For a user who cannot edit others' posts
 * now, a row of a post type nobody registered (and a revision of one) is answered like an ID that does
 * not exist. Authorship never counts. Registered post types are left alone.
 *
 * @param mixed $post A post ID or WP_Post.
 * @return bool
 */
function omochix_core_comments_row_closed( $post ) {
	if ( ! $post instanceof WP_Post ) {
		// get_post( 0 | '' | null ) would fall back to the global post: only a positive ID is looked up.
		$post = ( is_int( $post ) || ( is_string( $post ) && ctype_digit( $post ) ) ) && (int) $post > 0 ? get_post( (int) $post ) : null;
	}
	if ( ! $post instanceof WP_Post || ! function_exists( 'post_type_exists' ) ) {
		return false;
	}
	$owner = $post;
	if ( 'revision' === (string) $post->post_type ) {
		$owner = (int) $post->post_parent > 0 ? get_post( (int) $post->post_parent ) : null;
		if ( ! $owner instanceof WP_Post ) {
			return false;
		}
	}
	$type = (string) $owner->post_type;
	if ( '' === $type || post_type_exists( $type ) ) {
		return false;
	}
	return ! current_user_can( 'edit_others_posts' );
}

/**
 * State of the fail-closed comments screen: whether a comments list was built on this request, and the
 * rows hidden on it.
 *
 * @param int  $add    An ID to remember (0: none).
 * @param bool $screen True to mark the screen.
 * @return array{screen: bool, ids: array<int, true>}
 */
function omochix_core_comments_closed_state( $add = 0, $screen = false ) {
	static $state = array(
		'screen' => false,
		'ids'    => array(),
	);
	if ( true === $screen ) {
		$state['screen'] = true;
	}
	if ( is_int( $add ) && $add > 0 ) {
		$state['ids'][ $add ] = true;
	}
	return $state;
}

/**
 * Fail closed: the comments list of a hidden row lists nothing (post_id is kept).
 *
 * @param mixed $args WP_Comment_Query arguments of the comments list table.
 * @return mixed
 */
function omochix_core_comments_args_closed( $args ) {
	omochix_core_comments_closed_state( 0, true );
	if ( ! is_array( $args ) || empty( $args['post_id'] ) || ! is_numeric( $args['post_id'] ) ) {
		return $args;
	}
	$id = (int) $args['post_id'];
	if ( $id <= 0 || ! omochix_core_comments_row_closed( $id ) ) {
		return $args;
	}
	omochix_core_comments_closed_state( $id );
	$not                  = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array();
	$not[]                = $id;
	$args['post__not_in'] = $not;
	return $args;
}

/**
 * Fail closed: on the comments screen a hidden row has no title ("(no title)", as for an unknown ID).
 *
 * @param mixed $title   Title.
 * @param mixed $post_id Post ID.
 * @return mixed
 */
function omochix_core_comments_title_closed( $title, $post_id = 0 ) {
	if ( ! omochix_core_comments_closed_state()['screen'] || ! is_numeric( $post_id ) || (int) $post_id <= 0 ) {
		return $title;
	}
	return omochix_core_comments_row_closed( (int) $post_id ) ? '' : $title;
}

/**
 * Fail closed: a hidden row counts zero comments, as for an unknown ID.
 *
 * @param mixed $count   Counts so far.
 * @param mixed $post_id Post ID (0: the whole site).
 * @return mixed
 */
function omochix_core_comments_count_closed( $count, $post_id = 0 ) {
	if ( ! is_numeric( $post_id ) || ! isset( omochix_core_comments_closed_state()['ids'][ (int) $post_id ] ) ) {
		return $count;
	}
	return (object) array(
		'approved'       => 0,
		'moderated'      => 0,
		'spam'           => 0,
		'trash'          => 0,
		'post-trashed'   => 0,
		'total_comments' => 0,
		'all'            => 0,
	);
}

/**
 * Why the security layer is unavailable ('' when it is in place).
 *
 * @param string|null $set Reason to record (loader only).
 * @return string
 */
function omochix_core_sec_layer_problem( $set = null ) {
	static $why = '';
	if ( is_string( $set ) ) {
		$why = $set;
	}
	return $why;
}

/**
 * Fail closed: core/navigation renders nothing while the security layer is unavailable.
 *
 * @param mixed $args Block type arguments.
 * @param mixed $name Block type name.
 * @return mixed
 */
function omochix_core_nav_render_closed( $args, $name = '' ) {
	if ( 'core/navigation' === $name && is_array( $args ) ) {
		$args['render_callback'] = '__return_empty_string';
	}
	return $args;
}

/**
 * Fail closed in REST: while the security layer is unavailable, /wp/v2/navigation returns no menu
 * blocks (content.raw, content.rendered, block_version) to a user who may not edit that menu.
 *
 * @param mixed $response WP_REST_Response.
 * @param mixed $post     The menu post.
 * @return mixed
 */
function omochix_core_nav_rest_closed( $response, $post = null ) {
	if ( ! $post instanceof WP_Post || 'wp_navigation' !== $post->post_type || current_user_can( 'edit_post', (int) $post->ID ) ) {
		return $response;
	}
	if ( is_object( $response ) && method_exists( $response, 'get_data' ) && method_exists( $response, 'set_data' ) ) {
		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data['content'] ) && is_array( $data['content'] ) ) {
			unset( $data['content']['raw'], $data['content']['block_version'] );
			$data['content']['rendered']  = '';
			$data['content']['protected'] = true;
			$response->set_data( $data );
		}
	}
	return $response;
}

/**
 * Fail closed in REST search: while the security layer is unavailable, a user who may not edit menus
 * searches /wp/v2/navigation without password-protected menus (no guessing at their content).
 *
 * @param mixed $args Query arguments.
 * @return mixed
 */
function omochix_core_nav_query_closed( $args ) {
	if ( is_array( $args ) && isset( $args['s'] ) && '' !== (string) $args['s'] && ! current_user_can( 'edit_theme_options' ) ) {
		$args['has_password'] = false;
	}
	return $args;
}

/** Tells administrators that the security layer is unavailable (the reason only, no path). */
function omochix_core_sec_layer_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html(
		sprintf(
			/* translators: %s: reason */
			__( 'OmochiX Core：セキュリティ層（includes/web2/security.php）を使えないため、ナビゲーションのブロックを表示せず、コメント画面では登録されていない投稿タイプの行を隠しています（%s）。プラグインのファイルをすべてアップロードし直してください。', 'omochix-core' ),
			omochix_core_sec_layer_problem()
		)
	) . '</p></div>';
}
omochix_core_sec_layer_load();

// Web 2.0 modules (docs/web2/hooks/README.md). Loaded last and guarded: with
// the loader missing or broken the plugin works as before. The loader loads
// only modules on its fixed list (empty, default off) and checks each file
// before loading it; OMOCHIX_WEB2_DISABLED in wp-config.php stops them all.
if ( is_readable( OMOCHIX_CORE_DIR . 'includes/web2-loader.php' ) ) {
	try {
		require_once OMOCHIX_CORE_DIR . 'includes/web2-loader.php';
	} catch ( Throwable $omochix_core_error ) {
		unset( $omochix_core_error );
	}
}
