<?php
/**
 * Regression checks for updates through the AI Tools CSV importer.
 *
 * Run: php tests/ai-tools-csv-update.php
 *
 * The post API stubs follow WordPress core semantics, so a wrong API choice
 * fails here the way it fails on a real site:
 * - wp_insert_post() fills every field it is not given with core defaults
 *   (post_status "draft", empty post_content / post_excerpt), also when the
 *   array carries an existing ID;
 * - wp_update_post() loads the stored post, merges the given fields over it
 *   and then calls wp_insert_post().
 *
 * OMOCHIX_CSV_IMPORTER may point at another copy of csv-importer.php, e.g. the
 * pre-fix version, to show that these checks catch the original bug.
 */

define( 'ABSPATH', __DIR__ );
define( 'MB_IN_BYTES', 1048576 );

class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

function csv_reset_store() {
	$GLOBALS['csv_posts'] = array(
		12 => array(
			'ID'           => 12,
			'post_type'    => 'ai_tool',
			'post_name'    => 'existing-tool',
			'post_title'   => 'Original title',
			'post_status'  => 'publish',
			'post_content' => '<p>Keep published body.</p>',
			'post_excerpt' => 'Keep excerpt.',
		),
		20 => array(
			'ID'           => 20,
			'post_type'    => 'ai_tool',
			'post_name'    => 'draft-tool',
			'post_title'   => 'Draft title',
			'post_status'  => 'draft',
			'post_content' => '<p>Draft body.</p>',
			'post_excerpt' => 'Draft excerpt.',
		),
	);
	$GLOBALS['csv_next_id'] = 100;
	$GLOBALS['csv_meta']    = array( 12 => array( 'pricing_type' => 'free' ) );
	$GLOBALS['csv_terms']   = array();
	$GLOBALS['csv_calls']   = array();
	$GLOBALS['csv_depth']   = 0;
	$GLOBALS['csv_caps']    = array( 'edit_post' => true, 'publish_posts' => true );
}

function add_action() {}
function __( $message ) { return $message; }
function sanitize_key( $value ) { return strtolower( (string) $value ); }
function current_user_can( $cap, $id = null ) { return ! empty( $GLOBALS['csv_caps'][ $cap ] ); }
function get_post_type_object( $type ) { return (object) array( 'cap' => (object) array( 'publish_posts' => 'publish_posts' ) ); }
function wp_slash( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_reset_postdata() {}
function get_post_field( $field, $post_id ) { return $GLOBALS['csv_posts'][ $post_id ][ $field ] ?? null; }
function omochix_core_sanitize_meta_value( $value, $key ) { return $value; }
function update_post_meta( $post_id, $key, $value ) { $GLOBALS['csv_meta'][ $post_id ][ $key ] = $value; }
function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['csv_meta'][ $post_id ][ $key ] ); }

/** Mirrors wp_insert_post(): unspecified fields get core defaults, even on update. */
function wp_insert_post( $postarr, $wp_error = false ) {
	if ( 0 === $GLOBALS['csv_depth'] ) {
		$GLOBALS['csv_calls'][] = array( 'wp_insert_post', $postarr );
	}
	$defaults = array(
		'post_content' => '',
		'post_title'   => '',
		'post_excerpt' => '',
		'post_status'  => 'draft',
		'post_type'    => 'post',
		'post_name'    => '',
	);
	$postarr = array_merge( $defaults, $postarr );
	$id      = empty( $postarr['ID'] ) ? 0 : (int) $postarr['ID'];
	if ( $id && ! isset( $GLOBALS['csv_posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post ID.' ) : 0;
	}
	if ( ! $id ) {
		$id = $GLOBALS['csv_next_id']++;
	}
	$postarr['ID']                = $id;
	$GLOBALS['csv_posts'][ $id ] = $postarr;
	return $id;
}

/** Mirrors wp_update_post(): merge the given fields over the stored post. */
function wp_update_post( $postarr = array(), $wp_error = false ) {
	$GLOBALS['csv_calls'][] = array( 'wp_update_post', $postarr );
	$id                     = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
	if ( ! $id || ! isset( $GLOBALS['csv_posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post ID.' ) : 0;
	}
	++$GLOBALS['csv_depth'];
	$result = wp_insert_post( array_merge( $GLOBALS['csv_posts'][ $id ], $postarr ), $wp_error );
	--$GLOBALS['csv_depth'];
	return $result;
}

function term_exists( $name, $taxonomy ) { return false; }
function sanitize_title( $value ) { return strtolower( (string) $value ); }
function wp_insert_term( $name, $taxonomy, $args ) { return array( 'term_id' => 23 ); }
function wp_set_object_terms( $post_id, $term_ids, $taxonomy, $append = false ) {
	$GLOBALS['csv_terms'][ $post_id ][ $taxonomy ] = $term_ids;
	return $term_ids;
}

class WP_Query {
	public $posts;
	public function __construct( $args ) { $this->posts = array_keys( $GLOBALS['csv_posts'] ); }
}

require getenv( 'OMOCHIX_CSV_IMPORTER' ) ?: __DIR__ . '/../wp-plugin/omochix-core/admin/csv-importer.php';

$GLOBALS['csv_failures'] = 0;
function check_csv( $message, $passed ) {
	if ( ! $passed ) {
		++$GLOBALS['csv_failures'];
		fwrite( STDERR, "FAIL: {$message}\n" );
		return;
	}
	echo "PASS: {$message}\n";
}

function csv_row( $slug, $title ) {
	return array(
		'line'              => 2,
		'title'             => $title,
		'slug'              => $slug,
		'company_name'      => 'Example',
		'official_url'      => 'https://example.com',
		'short_description' => 'Updated short text',
		'pricing_type'      => 'paid',
		'japanese_support'  => 'full',
		'tool_status'       => 'active',
		'rating_overall'    => 4.5,
		'is_featured'       => true,
		'display_order'     => 10,
		'ai_tool_category'  => array( 'Writing' ),
		'ai_tool_feature'   => array(),
		'ai_tool_tag'       => array(),
		'ai_tool_platform'  => array(),
	);
}

function csv_top_level_calls( $function ) {
	return array_values( array_filter( $GLOBALS['csv_calls'], static function ( $call ) use ( $function ) { return $function === $call[0]; } ) );
}

// 1-5, 9: updating a published tool.
csv_reset_store();
$result = omochix_core_execute_csv_import( array( csv_row( 'existing-tool', 'Updated title' ) ), 'draft' );
$post   = $GLOBALS['csv_posts'][12];
check_csv( 'existing tool counts as updated without errors', 1 === $result['updated'] && 0 === $result['created'] && empty( $result['errors'] ) );
check_csv( 'published status is preserved', 'publish' === $post['post_status'] );
check_csv( 'body is preserved', '<p>Keep published body.</p>' === $post['post_content'] );
check_csv( 'excerpt is preserved', 'Keep excerpt.' === $post['post_excerpt'] );
check_csv( 'post ID is kept and no duplicate is created', 12 === $post['ID'] && 2 === count( $GLOBALS['csv_posts'] ) && 100 === $GLOBALS['csv_next_id'] );
check_csv( 'title and metadata are updated', 'Updated title' === $post['post_title'] && 'paid' === $GLOBALS['csv_meta'][12]['pricing_type'] && 'Example' === $GLOBALS['csv_meta'][12]['company_name'] );
check_csv( 'post type and slug stay the same', 'ai_tool' === $post['post_type'] && 'existing-tool' === $post['post_name'] );
$updates = csv_top_level_calls( 'wp_update_post' );
check_csv( 'existing tool goes through wp_update_post() only', 1 === count( $updates ) && 12 === (int) $updates[0][1]['ID'] && array() === csv_top_level_calls( 'wp_insert_post' ) );
check_csv( 'update does not send post_status, post_content or post_excerpt', isset( $updates[0] ) && ! array_intersect( array( 'post_status', 'post_content', 'post_excerpt' ), array_keys( $updates[0][1] ) ) );

// Existing drafts keep their state even when the import creates as published.
csv_reset_store();
omochix_core_execute_csv_import( array( csv_row( 'draft-tool', 'Draft retitled' ) ), 'publish' );
check_csv( 'existing draft stays draft when new posts are published', 'draft' === $GLOBALS['csv_posts'][20]['post_status'] && '<p>Draft body.</p>' === $GLOBALS['csv_posts'][20]['post_content'] );

// 6, 9: new tool, default draft.
csv_reset_store();
$result  = omochix_core_execute_csv_import( array( csv_row( 'new-tool', 'New draft' ) ), 'draft' );
$inserts = csv_top_level_calls( 'wp_insert_post' );
check_csv( 'new tool is created once', 1 === $result['created'] && 0 === $result['updated'] && isset( $GLOBALS['csv_posts'][100] ) && 3 === count( $GLOBALS['csv_posts'] ) );
check_csv( 'new tool defaults to draft', 'draft' === $GLOBALS['csv_posts'][100]['post_status'] );
check_csv( 'new tool goes through wp_insert_post() as ai_tool without an ID', 1 === count( $inserts ) && empty( $inserts[0][1]['ID'] ) && 'ai_tool' === $inserts[0][1]['post_type'] && array() === csv_top_level_calls( 'wp_update_post' ) );
check_csv( 'new tool keeps its slug, title and metadata', 'new-tool' === $GLOBALS['csv_posts'][100]['post_name'] && 'New draft' === $GLOBALS['csv_posts'][100]['post_title'] && 'paid' === $GLOBALS['csv_meta'][100]['pricing_type'] );

// 7: new tool with publish requested.
csv_reset_store();
omochix_core_execute_csv_import( array( csv_row( 'new-tool', 'New published' ) ), 'publish' );
check_csv( 'new tool is published when publish is requested by a publisher', 'publish' === $GLOBALS['csv_posts'][100]['post_status'] );
csv_reset_store();
$GLOBALS['csv_caps']['publish_posts'] = false;
omochix_core_execute_csv_import( array( csv_row( 'new-tool', 'New without publish cap' ) ), 'publish' );
check_csv( 'new tool falls back to draft without publish capability', 'draft' === $GLOBALS['csv_posts'][100]['post_status'] );

// 8: no permission to edit the existing tool.
csv_reset_store();
$before                           = $GLOBALS['csv_posts'];
$meta_before                      = $GLOBALS['csv_meta'];
$GLOBALS['csv_caps']['edit_post'] = false;
$result                           = omochix_core_execute_csv_import( array( csv_row( 'existing-tool', 'Disallowed update' ) ), 'draft' );
check_csv( 'insufficient permission reports an error and changes nothing', 1 === count( $result['errors'] ) && 0 === $result['updated'] && $before === $GLOBALS['csv_posts'] && $meta_before === $GLOBALS['csv_meta'] && array() === $GLOBALS['csv_calls'] );

if ( $GLOBALS['csv_failures'] ) {
	fwrite( STDERR, "AI Tools CSV update regression tests failed: {$GLOBALS['csv_failures']} check(s).\n" );
	exit( 1 );
}
echo "AI Tools CSV update regression tests passed.\n";
