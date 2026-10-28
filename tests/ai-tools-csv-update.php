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
	$GLOBALS['csv_meta']    = array( 12 => array( 'pricing_type' => 'free', 'rating_overall' => 4.2 ) );
	$GLOBALS['csv_terms']   = array( 12 => array( 'ai_tool_platform' => array( 42, 36 ) ) );
	$GLOBALS['csv_term_inserts'] = array();
	$GLOBALS['csv_missing_terms'] = array();
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
function get_post_status( $post_id ) { return $GLOBALS['csv_posts'][ $post_id ]['post_status'] ?? false; }
function get_post_meta( $post_id, $key, $single = false ) { return $GLOBALS['csv_meta'][ $post_id ][ $key ] ?? ''; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#', (string) $url ) ? (string) $url : ''; }

/** The rating rule of omochix_core_sanitize_meta_value() ("rating" in meta-schema.php). */
function csv_sanitize_rating( $value ) {
	if ( '' === $value || ! is_numeric( $value ) ) {
		return 0;
	}
	return round( min( 5, max( 1, (float) $value ) ), 1 );
}
function omochix_core_sanitize_meta_value( $value, $key ) { return 'rating_overall' === $key ? csv_sanitize_rating( $value ) : $value; }
/** Registered meta runs the same sanitizer inside update_post_meta(), so a stored 0 becomes 1.0. */
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['csv_meta'][ $post_id ][ $key ] = 'rating_overall' === $key ? csv_sanitize_rating( $value ) : $value;
}
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
function wp_insert_term( $name, $taxonomy, $args ) { $GLOBALS['csv_term_inserts'][] = array( $taxonomy, $name ); return array( 'term_id' => 23 ); }
/** Platform terms as seeded by omochix_core_insert_initial_terms() (IDs as on production). */
function get_term_by( $field, $value, $taxonomy ) {
	$ids = array( 'web' => 36, 'windows' => 37, 'macos' => 38, 'linux' => 39, 'ios' => 40, 'android' => 41, 'api' => 42, 'chrome-extension' => 43, 'slack' => 44, 'discord' => 45 );
	if ( 'slug' !== $field || 'ai_tool_platform' !== $taxonomy || ! isset( $ids[ $value ] ) || in_array( $value, $GLOBALS['csv_missing_terms'], true ) ) {
		return false;
	}
	return (object) array( 'term_id' => $ids[ $value ], 'slug' => $value );
}
function wp_set_object_terms( $post_id, $term_ids, $taxonomy, $append = false ) {
	$GLOBALS['csv_terms'][ $post_id ][ $taxonomy ] = $term_ids;
	return $term_ids;
}

class WP_Query {
	public $posts;
	public function __construct( $args ) {
		$statuses    = (array) ( $args['post_status'] ?? 'publish' );
		$this->posts = array_keys( array_filter( $GLOBALS['csv_posts'], static function ( $post ) use ( $args, $statuses ) {
			return $args['post_type'] === $post['post_type'] && in_array( $post['post_status'], $statuses, true );
		} ) );
	}
}

require __DIR__ . '/../wp-plugin/omochix-core/includes/taxonomies.php';

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

// rating_overall goes through the real row normalizer and the import, as the admin page does:
// rows with errors are reported and never executed.
function csv_raw_row( $slug, $title, $rating, $extra = array() ) {
	return $extra + array(
		'title'             => $title,
		'slug'              => $slug,
		'company_name'      => 'Example',
		'official_url'      => 'https://example.com',
		'short_description' => 'Text',
		'pricing_type'      => 'paid',
		'japanese_support'  => 'full',
		'tool_status'       => 'active',
		'rating_overall'    => $rating,
		'is_featured'       => '0',
		'display_order'     => '1',
		'category'          => '',
		'feature'           => '',
		'tag'               => '',
		'platform'          => '',
	);
}
function csv_import_raw( $raw_rows ) {
	$rows   = array();
	$errors = 0;
	foreach ( $raw_rows as $index => $raw ) {
		$normalized = omochix_core_normalize_csv_row( $raw, $index + 2 );
		if ( $normalized['errors'] ) {
			++$errors;
			continue;
		}
		$rows[] = $normalized['row'];
	}
	$result = $rows ? omochix_core_execute_csv_import( $rows, 'draft' ) : array( 'created' => 0, 'updated' => 0, 'errors' => array() );
	return array( $errors, $result );
}
function csv_has_rating( $post_id ) {
	return array_key_exists( 'rating_overall', $GLOBALS['csv_meta'][ $post_id ] ?? array() );
}

csv_reset_store();
list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'new-tool', 'New unrated', '' ) ) );
check_csv( 'blank rating: new tool is created unrated', 0 === $errors && 1 === $result['created'] && ! csv_has_rating( 100 ) );

csv_reset_store();
list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'existing-tool', 'Rated tool', '' ) ) );
check_csv( 'blank rating: existing rating 4.2 is kept', 0 === $errors && 1 === $result['updated'] && 4.2 === $GLOBALS['csv_meta'][12]['rating_overall'] && 'Rated tool' === $GLOBALS['csv_posts'][12]['post_title'] );

csv_reset_store();
list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'draft-tool', 'Unrated tool', '' ) ) );
check_csv( 'blank rating: existing unrated tool stays unrated', 0 === $errors && 1 === $result['updated'] && ! csv_has_rating( 20 ) );

csv_reset_store();
csv_import_raw( array( csv_raw_row( 'existing-tool', 'Rated tool', '1.0' ), csv_raw_row( 'draft-tool', 'Unrated tool', '5' ) ) );
check_csv( 'ratings 1.0 and 5.0 are saved', 1.0 === $GLOBALS['csv_meta'][12]['rating_overall'] && 5.0 === $GLOBALS['csv_meta'][20]['rating_overall'] );

csv_reset_store();
csv_import_raw( array( csv_raw_row( 'existing-tool', 'Rated tool', '4.55' ) ) );
check_csv( 'rating 4.55 is rounded to 4.6', 4.6 === $GLOBALS['csv_meta'][12]['rating_overall'] );

foreach ( array( '0', '5.1', 'abc' ) as $bad_rating ) {
	csv_reset_store();
	$before      = $GLOBALS['csv_posts'];
	$meta_before = $GLOBALS['csv_meta'];
	list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'existing-tool', 'Should not change', $bad_rating ) ) );
	check_csv( "rating \"{$bad_rating}\" is a row error and changes nothing", 1 === $errors && 0 === $result['updated'] && $before === $GLOBALS['csv_posts'] && $meta_before === $GLOBALS['csv_meta'] && array() === $GLOBALS['csv_calls'] );
}

// An invalid row next to a valid one: only the valid row is applied.
csv_reset_store();
list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'existing-tool', 'Should not change', '9' ), csv_raw_row( 'draft-tool', 'Valid row', '3.5' ) ) );
check_csv( 'invalid row leaves its tool untouched while the valid row applies', 1 === $errors && 1 === $result['updated'] && 'Original title' === $GLOBALS['csv_posts'][12]['post_title'] && 4.2 === $GLOBALS['csv_meta'][12]['rating_overall'] && 3.5 === $GLOBALS['csv_meta'][20]['rating_overall'] );

// ---- P5C: platform values ------------------------------------------------------------
function csv_platform_terms( $post_id ) { return $GLOBALS['csv_terms'][ $post_id ]['ai_tool_platform'] ?? null; }
function csv_platform_inserts() { return array_filter( $GLOBALS['csv_term_inserts'], static function ( $insert ) { return 'ai_tool_platform' === $insert[0]; } ); }

csv_reset_store();
list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'existing-tool', 'Rated tool', '', array( 'platform' => 'Web, iOS, android, MACOS, Windows, linux' ) ) ) );
check_csv( 'platform: the six environments are accepted by slug or name, any case', 0 === $errors && 1 === $result['updated'] && array( 36, 40, 41, 38, 37, 39 ) === csv_platform_terms( 12 ) );

csv_reset_store();
csv_import_raw( array( csv_raw_row( 'existing-tool', 'Rated tool', '', array( 'platform' => 'web、Web, ios' ) ) ) );
check_csv( 'platform: duplicates collapse and legacy terms (API) are replaced', array( 36, 40 ) === csv_platform_terms( 12 ) );

foreach ( array( 'Mac', 'API', 'Slack', 'ウェブ', 'Web / iOS', 'chrome-extension', 'web, Mac' ) as $bad_platform ) {
	csv_reset_store();
	$before      = $GLOBALS['csv_posts'];
	$meta_before = $GLOBALS['csv_meta'];
	$term_before = $GLOBALS['csv_terms'];
	list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'existing-tool', 'Should not change', '4.9', array( 'platform' => $bad_platform, 'company_name' => 'Changed Co' ) ) ) );
	check_csv( "platform \"{$bad_platform}\" is a row error and changes nothing", 1 === $errors && 0 === $result['updated'] && $before === $GLOBALS['csv_posts'] && $meta_before === $GLOBALS['csv_meta'] && $term_before === $GLOBALS['csv_terms'] && array() === $GLOBALS['csv_calls'] && array() === $GLOBALS['csv_term_inserts'] );
}

csv_reset_store();
list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'existing-tool', 'Kept platform', '', array( 'platform' => '' ) ) ) );
check_csv( 'platform: blank keeps the current terms and still updates the other fields', 0 === $errors && 1 === $result['updated'] && array( 42, 36 ) === csv_platform_terms( 12 ) && 'Kept platform' === $GLOBALS['csv_posts'][12]['post_title'] );

csv_reset_store();
csv_import_raw( array( csv_raw_row( 'new-tool', 'New, no platform', '', array( 'platform' => '' ) ) ) );
check_csv( 'platform: blank on a new tool sets no platform terms', isset( $GLOBALS['csv_posts'][100] ) && null === csv_platform_terms( 100 ) );

csv_reset_store();
$GLOBALS['csv_meta'][12]['supported_devices'] = array( 'Web', 'iOS', 'Android' );
csv_import_raw( array( csv_raw_row( 'existing-tool', 'Devices kept', '', array( 'platform' => 'windows' ) ) ) );
check_csv( 'platform: supported_devices is never changed', array( 'Web', 'iOS', 'Android' ) === $GLOBALS['csv_meta'][12]['supported_devices'] && array( 37 ) === csv_platform_terms( 12 ) );

csv_reset_store();
csv_import_raw( array( csv_raw_row( 'existing-tool', 'A', '', array( 'platform' => 'web, ios' ) ), csv_raw_row( 'new-tool', 'B', '', array( 'platform' => 'linux' ) ) ) );
check_csv( 'platform: no platform term is ever created from a CSV', array() === csv_platform_inserts() && array( 39 ) === csv_platform_terms( 100 ) );

// A preview saved by an older version may hold names, or a missing term: refused before any write.
csv_reset_store();
$before = $GLOBALS['csv_posts'];
$stale  = omochix_core_normalize_csv_row( csv_raw_row( 'existing-tool', 'Stale', '', array( 'platform' => 'web' ) ), 2 )['row'];
$stale['ai_tool_platform'] = array( 'Web', 'Mac' );
$result = omochix_core_execute_csv_import( array( $stale ), 'draft' );
check_csv( 'platform: an old preview holding "Mac" is refused at execution and changes nothing', 1 === count( $result['errors'] ) && $before === $GLOBALS['csv_posts'] && array( 42, 36 ) === csv_platform_terms( 12 ) && array() === $GLOBALS['csv_calls'] );
csv_reset_store();
$GLOBALS['csv_missing_terms'] = array( 'linux' );
list( $errors, $result ) = csv_import_raw( array( csv_raw_row( 'existing-tool', 'Missing term', '', array( 'platform' => 'linux' ) ) ) );
check_csv( 'platform: a missing environment term is an error, not created', 1 === count( $result['errors'] ) && array() === csv_platform_inserts() && 'Original title' === $GLOBALS['csv_posts'][12]['post_title'] );

// ---- P5C: tools in the trash -------------------------------------------------------------
function csv_add_trashed( $id, $name, $desired ) {
	$GLOBALS['csv_posts'][ $id ] = array( 'ID' => $id, 'post_type' => 'ai_tool', 'post_name' => $name, 'post_title' => 'Trashed', 'post_status' => 'trash', 'post_content' => '<p>Trashed body.</p>', 'post_excerpt' => 'Trashed excerpt.' );
	if ( $desired ) {
		$GLOBALS['csv_meta'][ $id ]['_wp_desired_post_slug'] = $desired;
	}
}

foreach ( array( 'with _wp_desired_post_slug' => 'gone-tool', 'name suffix only' => '' ) as $label => $desired ) {
	csv_reset_store();
	csv_add_trashed( 30, 'gone-tool__trashed', $desired );
	$before = $GLOBALS['csv_posts'];
	$result = omochix_core_execute_csv_import( array( omochix_core_normalize_csv_row( csv_raw_row( 'gone-tool', 'Gone tool', '' ), 2 )['row'] ), 'publish' );
	check_csv( "trash ({$label}): same slug is a row error; nothing is published, created or restored", 1 === count( $result['errors'] ) && 0 === $result['created'] && 0 === $result['updated'] && $before === $GLOBALS['csv_posts'] && 100 === $GLOBALS['csv_next_id'] && array() === $GLOBALS['csv_calls'] && 'trash' === $GLOBALS['csv_posts'][30]['post_status'] );
}

csv_reset_store();
csv_add_trashed( 31, 'old-tool', '' ); // trashed before WordPress added the __trashed suffix
$before = $GLOBALS['csv_posts'];
$result = omochix_core_execute_csv_import( array( omochix_core_normalize_csv_row( csv_raw_row( 'old-tool', 'Old tool', '' ), 2 )['row'] ), 'publish' );
check_csv( 'trash: a trashed post that kept its slug is not edited either', 1 === count( $result['errors'] ) && $before === $GLOBALS['csv_posts'] && array() === $GLOBALS['csv_calls'] );

$normalized = omochix_core_normalize_csv_row( csv_raw_row( 'gone-tool__trashed', 'X', '' ), 2 );
check_csv( 'trash: a "__trashed" slug in the CSV is a row error', null === $normalized['row'] && 1 === count( $normalized['errors'] ) );

csv_reset_store();
csv_add_trashed( 30, 'existing-tool__trashed', 'existing-tool' );
$result = omochix_core_execute_csv_import( array( omochix_core_normalize_csv_row( csv_raw_row( 'existing-tool', 'Live tool', '' ), 2 )['row'] ), 'draft' );
check_csv( 'trash: a live tool with the same slug is still updated; the trashed copy is untouched', 1 === $result['updated'] && 'Live tool' === $GLOBALS['csv_posts'][12]['post_title'] && 'trash' === $GLOBALS['csv_posts'][30]['post_status'] && 'Trashed' === $GLOBALS['csv_posts'][30]['post_title'] );

csv_reset_store();
csv_add_trashed( 30, 'draft-tool__trashed', 'draft-tool' );
$result = omochix_core_execute_csv_import( array( omochix_core_normalize_csv_row( csv_raw_row( 'draft-tool', 'Draft retitled', '' ), 2 )['row'] ), 'publish' );
check_csv( 'trash: a draft tool with the same slug is updated and stays draft; the trashed copy is untouched', 1 === $result['updated'] && 0 === $result['created'] && 'Draft retitled' === $GLOBALS['csv_posts'][20]['post_title'] && 'draft' === $GLOBALS['csv_posts'][20]['post_status'] && 'trash' === $GLOBALS['csv_posts'][30]['post_status'] && 100 === $GLOBALS['csv_next_id'] );

csv_reset_store();
csv_add_trashed( 30, 'gone-tool__trashed', 'gone-tool' );
$result = omochix_core_execute_csv_import( array( omochix_core_normalize_csv_row( csv_raw_row( 'brand-new', 'Brand new', '' ), 2 )['row'] ), 'publish' );
check_csv( 'trash: unrelated new slugs are still created as before', 1 === $result['created'] && 'publish' === $GLOBALS['csv_posts'][100]['post_status'] );

if ( $GLOBALS['csv_failures'] ) {
	fwrite( STDERR, "AI Tools CSV update regression tests failed: {$GLOBALS['csv_failures']} check(s).\n" );
	exit( 1 );
}
echo "AI Tools CSV update regression tests passed.\n";
