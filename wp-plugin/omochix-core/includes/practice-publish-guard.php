<?php
/**
 * Learn Practice publish guard (Phase 6A-S3).
 *
 * A practice item cannot go from an unpublished state to publish / future
 * while a required field is missing or a secret-looking string sits in its
 * title, excerpt, content or meta. Two paths, one rule:
 *
 * - Block editor (REST): rest_pre_insert_learn_practice returns a WP_Error,
 *   which the editor shows inline. The post stays as it was. Meta boxes are
 *   saved by the editor only after a successful post save, so the message
 *   asks for 下書き保存 first when the required fields were just filled in.
 * - Classic requests (post.php, quick edit, bulk edit): wp_insert_post_data
 *   turns the status back to draft and records the reasons for an admin
 *   notice. The values submitted in the same request count (stored meta
 *   overlaid with the meta-box and quick-edit fields).
 *
 * An item that is already published is never demoted: the reasons are
 * recorded as a warning and the list's 必須 column flags it.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_PRACTICE_GUARD_TRANSIENT = 'omochix_core_practice_guard_';

/** Statuses that expose the item on the public site (now or scheduled). */
function omochix_core_practice_is_public_status( $status ) {
	return in_array( (string) $status, array( 'publish', 'future' ), true );
}

/**
 * The item's meta as the request would leave it: stored values overlaid
 * with the classic meta-box fields and the quick-edit fields of this request.
 *
 * @param int                       $post_id   Post ID (0 for a brand-new post).
 * @param array<string, mixed>|null $submitted Optional submitted meta (unslashed, keyed by meta key); null reads $_POST.
 * @return array<string, mixed> Sanitized values keyed by schema key.
 */
function omochix_core_practice_effective_meta( $post_id, $submitted = null ) {
	$schema = omochix_core_get_practice_meta_schema();
	$meta   = array();
	foreach ( array_keys( $schema ) as $key ) {
		$meta[ $key ] = $post_id ? get_post_meta( $post_id, $key, true ) : '';
	}
	if ( null === $submitted ) {
		$submitted = array();
		if ( isset( $_POST['omochix_core_practice_meta'] ) && is_array( $_POST['omochix_core_practice_meta'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only overlay; the save handlers verify their nonces.
			$submitted = wp_unslash( $_POST['omochix_core_practice_meta'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.
		}
		if ( isset( $_POST['omochix_practice_quick'] ) && is_array( $_POST['omochix_practice_quick'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$submitted = array_merge( $submitted, wp_unslash( $_POST['omochix_practice_quick'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
	}
	foreach ( $submitted as $key => $value ) {
		if ( ! isset( $schema[ $key ] ) || 'post_id_array' === $schema[ $key ]['sanitize'] ) {
			continue;
		}
		if ( 'json_rows' === $schema[ $key ]['sanitize'] && ! omochix_core_practice_rows_input_is_valid( $value ) ) {
			continue; // the save handler keeps the stored rows for unparsable JSON
		}
		$meta[ $key ] = omochix_core_sanitize_practice_meta_value( $value, $key );
	}
	return $meta;
}

/**
 * Why an item may not be published: missing required fields and
 * secret-looking strings.
 *
 * @param array<string, mixed>  $meta   Effective meta (see omochix_core_practice_effective_meta()).
 * @param array<string, string> $fields Post fields to lint: title, excerpt, content.
 * @return array{missing: string[], secrets: array<int, array{path: string, label: string, sample: string}>}
 */
function omochix_core_practice_publish_blockers( array $meta, array $fields = array() ) {
	$missing = array();
	foreach ( omochix_core_get_practice_required_meta() as $key ) {
		$value = $meta[ $key ] ?? '';
		if ( '' === $value || null === $value || array() === $value || 0 === $value || '0' === $value ) {
			$missing[] = $key;
		}
	}
	$secrets = array();
	omochix_core_practice_import_lint_secrets( array_intersect_key( $fields, array_flip( array( 'title', 'excerpt', 'content' ) ) ), '', $secrets );
	omochix_core_practice_import_lint_secrets( $meta, '', $secrets );
	return array( 'missing' => $missing, 'secrets' => $secrets );
}

/** Human-readable lines for a blockers result. */
function omochix_core_practice_blocker_messages( array $blockers ) {
	$lines = array();
	if ( $blockers['missing'] ) {
		$lines[] = sprintf( __( '必須項目が未入力です：%s', 'omochix-core' ), implode( ', ', $blockers['missing'] ) );
	}
	foreach ( $blockers['secrets'] as $finding ) {
		$lines[] = sprintf( __( '秘密情報らしき文字列：%1$s（%2$s）', 'omochix-core' ), $finding['path'], $finding['label'] );
	}
	return $lines;
}

/** Remember why a post was held back, for the admin notice. */
function omochix_core_practice_guard_remember( $post_id, $type, array $lines ) {
	set_transient( OMOCHIX_CORE_PRACTICE_GUARD_TRANSIENT . (int) $post_id, array( 'type' => $type, 'lines' => $lines ), 10 * MINUTE_IN_SECONDS );
}

/**
 * Classic path: hold an unpublished item back from publish / future when it
 * is blocked. Already-public items keep their status and get a warning.
 *
 * @param array<string, mixed> $data    Slashed post data about to be written.
 * @param array<string, mixed> $postarr Raw post array (ID, etc.).
 * @return array<string, mixed>
 */
function omochix_core_practice_guard_insert_data( $data, $postarr ) {
	if ( OMOCHIX_CORE_PRACTICE_POST_TYPE !== ( $data['post_type'] ?? '' ) || ! omochix_core_practice_is_public_status( $data['post_status'] ?? '' ) ) {
		return $data;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return $data; // the REST path answers with a WP_Error instead (omochix_core_practice_guard_rest)
	}
	$post_id = (int) ( $postarr['ID'] ?? 0 );
	$stored  = $post_id ? (string) get_post_field( 'post_status', $post_id ) : '';
	$fields  = array(
		'title'   => wp_unslash( (string) ( $data['post_title'] ?? '' ) ),
		'excerpt' => wp_unslash( (string) ( $data['post_excerpt'] ?? '' ) ),
		'content' => wp_unslash( (string) ( $data['post_content'] ?? '' ) ),
	);
	$blockers = omochix_core_practice_publish_blockers( omochix_core_practice_effective_meta( $post_id ), $fields );
	$lines    = omochix_core_practice_blocker_messages( $blockers );
	if ( ! $lines ) {
		return $data;
	}
	if ( omochix_core_practice_is_public_status( $stored ) ) {
		omochix_core_practice_guard_remember( $post_id, 'warn', $lines ); // never demote a live item
		return $data;
	}
	$data['post_status'] = 'draft';
	omochix_core_practice_guard_remember( $post_id, 'blocked', $lines );
	return $data;
}
add_filter( 'wp_insert_post_data', 'omochix_core_practice_guard_insert_data', 10, 2 );

/**
 * REST path (block editor): refuse the publish with a message the editor
 * shows inline; the post and its meta stay untouched.
 *
 * @param stdClass        $prepared Post object about to be inserted.
 * @param WP_REST_Request $request  Request.
 * @return stdClass|WP_Error
 */
function omochix_core_practice_guard_rest( $prepared, $request ) {
	$status = isset( $prepared->post_status ) ? (string) $prepared->post_status : '';
	if ( ! omochix_core_practice_is_public_status( $status ) ) {
		return $prepared;
	}
	$post_id = isset( $prepared->ID ) ? (int) $prepared->ID : 0;
	$stored  = $post_id ? (string) get_post_field( 'post_status', $post_id ) : '';
	if ( omochix_core_practice_is_public_status( $stored ) ) {
		return $prepared; // already live: edits go through, the list flags gaps
	}
	$submitted = is_array( $request['meta'] ?? null ) ? $request['meta'] : array();
	$fields    = array(
		'title'   => isset( $prepared->post_title ) ? (string) $prepared->post_title : ( $post_id ? (string) get_post_field( 'post_title', $post_id ) : '' ),
		'excerpt' => isset( $prepared->post_excerpt ) ? (string) $prepared->post_excerpt : ( $post_id ? (string) get_post_field( 'post_excerpt', $post_id ) : '' ),
		'content' => isset( $prepared->post_content ) ? (string) $prepared->post_content : ( $post_id ? (string) get_post_field( 'post_content', $post_id ) : '' ),
	);
	$meta     = $submitted ? array_replace( omochix_core_practice_effective_meta( $post_id, array() ), omochix_core_practice_effective_meta( $post_id, $submitted ) ) : omochix_core_practice_effective_meta( $post_id, array() );
	$blockers = omochix_core_practice_publish_blockers( $meta, $fields );
	$lines    = omochix_core_practice_blocker_messages( $blockers );
	if ( ! $lines ) {
		return $prepared;
	}
	$hint = $blockers['missing'] ? __( '入力した項目は「下書き保存」で保存してから、もう一度「公開」してください。', 'omochix-core' ) : __( '該当する値をプレースホルダー（＜ここに貼る：…＞）に置き換えてください。', 'omochix-core' );
	return new WP_Error( 'omochix_practice_blocked', __( '公開できません。', 'omochix-core' ) . ' ' . implode( ' / ', $lines ) . ' ' . $hint, array( 'status' => 400 ) );
}
add_filter( 'rest_pre_insert_' . OMOCHIX_CORE_PRACTICE_POST_TYPE, 'omochix_core_practice_guard_rest', 10, 2 );

/** Admin notice on the item's edit screen after a classic save. */
function omochix_core_practice_guard_notices() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || OMOCHIX_CORE_PRACTICE_POST_TYPE !== $screen->post_type ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id ) {
		return;
	}
	$held = get_transient( OMOCHIX_CORE_PRACTICE_GUARD_TRANSIENT . $post_id );
	if ( ! is_array( $held ) || empty( $held['lines'] ) ) {
		return;
	}
	delete_transient( OMOCHIX_CORE_PRACTICE_GUARD_TRANSIENT . $post_id );
	$blocked = 'blocked' === ( $held['type'] ?? '' );
	$lead    = $blocked ? __( '公開できないため下書きのまま保存しました。', 'omochix-core' ) : __( '公開中の教材に不足があります（公開状態は変えていません）。', 'omochix-core' );
	echo '<div class="notice ' . ( $blocked ? 'notice-error' : 'notice-warning' ) . ' is-dismissible"><p><strong>' . esc_html( $lead ) . '</strong></p><ul>';
	foreach ( (array) $held['lines'] as $line ) {
		echo '<li>' . esc_html( (string) $line ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'omochix_core_practice_guard_notices' );
