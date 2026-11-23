<?php
// omochix-glossary-version: 1
/**
 * AI glossary: publish guard.
 *
 * A term that fails glossary-validate.php (missing reading or summary, no
 * primary source, a future or missing check date, a URL the rules refuse,
 * forbidden HTML, something that looks like a credential, not exactly one
 * category ...) is never published or scheduled, whatever the path: the
 * classic editor, Quick Edit, Bulk Edit, XML-RPC and wp_insert_post
 * (wp_insert_post_data), the block editor and REST (rest_pre_insert), and
 * scheduled publishing by cron (publish_future_post). A term that is already
 * live is not demoted here; gate G-B hides it while it fails the rules.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Why a term may not go live, or an empty list.
 *
 * @param WP_Post|object $post Post as it will be saved.
 * @param array          $over Values of the save in progress.
 * @return string[]
 */
function omochix_core_glossary_publish_problems( $post, array $over = array() ) {
	$res = omochix_core_glossary_validate_term( omochix_core_glossary_term_data( $post, $over ) );
	return $res['errors'];
}

/**
 * Remember the problems for the editor's notice (IDs and messages only).
 *
 * @param int      $post_id  Post ID.
 * @param string[] $problems Problems.
 * @return void
 */
function omochix_core_glossary_remember_hold( $post_id, array $problems ) {
	if ( $post_id ) {
		set_transient( 'omochix_core_glossary_hold_' . (int) $post_id, array_slice( $problems, 0, 20 ), 10 * MINUTE_IN_SECONDS );
	}
}

/**
 * Whether a post is live already (publish or future): never demoted here.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function omochix_core_glossary_is_live( $post_id ) {
	return $post_id && in_array( (string) get_post_field( 'post_status', (int) $post_id ), array( 'publish', 'future' ), true );
}

/**
 * Classic editor, Quick Edit, Bulk Edit, XML-RPC, wp_insert_post.
 *
 * @param array $data    Slashed post data.
 * @param array $postarr Raw post array.
 * @return array
 */
function omochix_core_glossary_guard_insert_data( $data, $postarr ) {
	if ( 'omx_glossary' !== ( $data['post_type'] ?? '' ) || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future' ), true ) ) {
		return $data;
	}
	$post_id = (int) ( $postarr['ID'] ?? 0 );
	if ( omochix_core_glossary_is_live( $post_id ) ) {
		return $data;
	}
	$post     = (object) array(
		'ID'           => $post_id,
		'post_type'    => 'omx_glossary',
		'post_name'    => (string) wp_unslash( $data['post_name'] ?? '' ),
		'post_title'   => (string) wp_unslash( $data['post_title'] ?? '' ),
		'post_content' => (string) wp_unslash( $data['post_content'] ?? '' ),
	);
	$over     = array();
	$tax      = isset( $postarr['tax_input']['omx_glossary_category'] ) ? $postarr['tax_input']['omx_glossary_category'] : null;
	if ( null !== $tax ) {
		$over['category'] = omochix_core_glossary_category_slugs( $tax );
	}
	$problems = omochix_core_glossary_publish_problems( $post, $over );
	if ( $problems ) {
		$data['post_status'] = 'draft';
		omochix_core_glossary_remember_hold( $post_id, $problems );
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'omochix_core_glossary_guard_insert_data', 13, 2 );

/**
 * Category slugs from submitted terms (IDs, slugs or names; array or list).
 *
 * @param mixed $value Submitted terms.
 * @return string[]
 */
function omochix_core_glossary_category_slugs( $value ) {
	$out = array();
	foreach ( is_array( $value ) ? $value : explode( ',', (string) $value ) as $v ) {
		$v = is_scalar( $v ) ? trim( (string) $v ) : '';
		if ( '' === $v || '0' === $v ) {
			continue;
		}
		$term = ctype_digit( $v ) ? get_term( (int) $v, 'omx_glossary_category' ) : ( get_term_by( 'slug', $v, 'omx_glossary_category' ) ?: get_term_by( 'name', $v, 'omx_glossary_category' ) );
		$out[] = $term && ! is_wp_error( $term ) ? (string) $term->slug : '(不明なカテゴリ)';
	}
	return array_values( array_unique( $out ) );
}

/**
 * Block editor and REST: refuse to publish or schedule a term that fails.
 *
 * @param stdClass|WP_Error $prepared Prepared post.
 * @param mixed             $request  Request.
 * @return stdClass|WP_Error
 */
function omochix_core_glossary_guard_rest( $prepared, $request ) {
	if ( $prepared instanceof WP_Error || ! in_array( (string) ( $prepared->post_status ?? '' ), array( 'publish', 'future' ), true ) ) {
		return $prepared;
	}
	$post_id = (int) ( $prepared->ID ?? 0 );
	if ( omochix_core_glossary_is_live( $post_id ) ) {
		return $prepared;
	}
	$stored = $post_id ? get_post( $post_id ) : null;
	$post   = (object) array(
		'ID'           => $post_id,
		'post_type'    => 'omx_glossary',
		'post_name'    => (string) ( $prepared->post_name ?? ( $stored->post_name ?? '' ) ),
		'post_title'   => (string) ( $prepared->post_title ?? ( $stored->post_title ?? '' ) ),
		'post_content' => (string) ( $prepared->post_content ?? ( $stored->post_content ?? '' ) ),
	);
	$over   = array();
	$meta   = is_object( $request ) && method_exists( $request, 'get_param' ) ? $request->get_param( 'meta' ) : null;
	foreach ( is_array( $meta ) ? $meta : array() as $k => $v ) {
		if ( array_key_exists( $k, omochix_core_glossary_meta_fields() ) ) {
			$over[ $k ] = omochix_core_glossary_sanitize_meta( $v, $k );
		}
	}
	$cats = is_object( $request ) && method_exists( $request, 'get_param' ) ? $request->get_param( 'glossary-categories' ) : null;
	if ( null !== $cats ) {
		$over['category'] = omochix_core_glossary_category_slugs( $cats );
	}
	$problems = omochix_core_glossary_publish_problems( $post, $over );
	if ( ! $problems ) {
		return $prepared;
	}
	return new WP_Error(
		'omochix_glossary_not_ready',
		__( 'この用語は公開の条件を満たしていないため、公開・予約できません。下書きとして保存し、内容を確認してください。', 'omochix-core' ),
		array(
			'status'   => 400,
			'problems' => array_slice( $problems, 0, 20 ),
		)
	);
}
add_filter( 'rest_pre_insert_omx_glossary', 'omochix_core_glossary_guard_rest', 13, 2 );

/**
 * Scheduled publishing (cron bypasses the filters above): a due term that
 * fails is not published; it is kept as a draft.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_glossary_guard_future( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post || 'omx_glossary' !== $post->post_type ) {
		return;
	}
	$problems = omochix_core_glossary_publish_problems( $post );
	if ( $problems ) {
		remove_action( 'publish_future_post', 'check_and_publish_future_post', 10 );
		wp_update_post(
			array(
				'ID'          => (int) $post_id,
				'post_status' => 'draft',
			)
		);
		omochix_core_glossary_remember_hold( (int) $post_id, $problems );
	}
}
add_action( 'publish_future_post', 'omochix_core_glossary_guard_future', 9 );

/** Put WordPress's own scheduled publishing back for the next event. */
function omochix_core_glossary_guard_future_restore() {
	if ( false === has_action( 'publish_future_post', 'check_and_publish_future_post' ) ) {
		add_action( 'publish_future_post', 'check_and_publish_future_post', 10, 1 );
	}
}
add_action( 'publish_future_post', 'omochix_core_glossary_guard_future_restore', 11 );

/** Tell the editor why a save stayed a draft (escaped). */
function omochix_core_glossary_hold_notice() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$problems = get_transient( 'omochix_core_glossary_hold_' . $post_id );
	if ( ! is_array( $problems ) || ! $problems ) {
		return;
	}
	delete_transient( 'omochix_core_glossary_hold_' . $post_id );
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'この用語は公開の条件を満たしていないため、下書きのまま保存しました。', 'omochix-core' ) . '</p><ul>';
	foreach ( $problems as $p ) {
		echo '<li>' . esc_html( (string) $p ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'omochix_core_glossary_hold_notice' );
// omochix-glossary-version: 1 (end)
