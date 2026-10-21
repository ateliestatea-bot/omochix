<?php
/**
 * Learn Practice relation readers (Learn Phase 4).
 *
 * Relations are stored once, on the practice item (post ID arrays, plus
 * catalog slugs for Motion samples). The forward direction goes through the
 * shared omochix_core_get_related_content() reader; the reverse direction
 * (which practice items use this prompt / tool / page / motion sample) is
 * resolved here with a serialized LIKE narrowing followed by an exact check,
 * the same technique as prompt-relations.php.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forward relation: published posts of a type listed on a practice item.
 *
 * @param int    $practice_id Practice post ID.
 * @param string $meta_key    related_*_ids meta key.
 * @param string $post_type   Allowed post type.
 * @param int    $limit       Maximum posts.
 * @return WP_Post[]
 */
function omochix_core_get_practice_related( $practice_id, $meta_key, $post_type, $limit = 6 ) {
	return omochix_core_get_related_content( $practice_id, $meta_key, $post_type, array( 'posts_per_page' => max( 1, absint( $limit ) ) ) );
}

/**
 * IDs of published practice items whose meta array contains a value,
 * newest first, verified exactly after the LIKE narrowing.
 *
 * @param string          $meta_key Meta key holding an array.
 * @param int|string      $needle   Post ID (for related_*_ids) or slug.
 * @return int[]
 */
function omochix_core_get_practice_ids_referencing( $meta_key, $needle ) {
	if ( ! post_type_exists( OMOCHIX_CORE_PRACTICE_POST_TYPE ) ) {
		return array();
	}
	$is_id = is_int( $needle ) || ctype_digit( (string) $needle );
	if ( $is_id ) {
		$needle = absint( $needle );
		if ( ! $needle ) {
			return array();
		}
		$like = ';i:' . $needle . ';';
	} else {
		$needle = sanitize_title( (string) $needle );
		if ( '' === $needle ) {
			return array();
		}
		$like = ':"' . $needle . '";';
	}

	$query = new WP_Query(
		array(
			'post_type'              => OMOCHIX_CORE_PRACTICE_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'     => $meta_key,
					'value'   => $like,
					'compare' => 'LIKE',
				),
			),
		)
	);

	$verified = array();
	foreach ( $query->posts as $post_id ) {
		$values = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_array( $values ) ) {
			continue;
		}
		$values = $is_id ? array_map( 'absint', $values ) : array_map( 'strval', $values );
		if ( in_array( $needle, $values, true ) ) {
			$verified[] = (int) $post_id;
		}
	}
	return $verified;
}

/**
 * Published practice posts for a list of IDs, in the given order, capped.
 *
 * @param int[] $ids   Post IDs.
 * @param int   $limit Maximum posts.
 * @return WP_Post[]
 */
function omochix_core_get_practice_posts_by_ids( $ids, $limit = 6 ) {
	$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	if ( ! $ids ) {
		return array();
	}
	$ids   = array_slice( $ids, 0, max( 1, absint( $limit ) ) );
	$query = new WP_Query(
		array(
			'post_type'      => OMOCHIX_CORE_PRACTICE_POST_TYPE,
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $ids ),
			'no_found_rows'  => true,
		)
	);
	return $query->posts;
}

/** Practice items that use a prompt. */
function omochix_core_get_practice_for_prompt( $prompt_id, $limit = 6 ) {
	return omochix_core_get_practice_posts_by_ids( omochix_core_get_practice_ids_referencing( 'related_prompt_ids', (int) $prompt_id ), $limit );
}

/** Practice items that use an AI tool. */
function omochix_core_get_practice_for_tool( $tool_id, $limit = 6 ) {
	return omochix_core_get_practice_posts_by_ids( omochix_core_get_practice_ids_referencing( 'related_tool_ids', (int) $tool_id ), $limit );
}

/** Practice items that reference a Learn page. */
function omochix_core_get_practice_for_page( $page_id, $limit = 6 ) {
	return omochix_core_get_practice_posts_by_ids( omochix_core_get_practice_ids_referencing( 'related_learn_ids', (int) $page_id ), $limit );
}

/** Practice items that use a Motion Gallery sample (catalog slug). */
function omochix_core_get_practice_for_motion( $slug, $limit = 6 ) {
	return omochix_core_get_practice_posts_by_ids( omochix_core_get_practice_ids_referencing( 'related_motion_slugs', (string) $slug ), $limit );
}

/**
 * Published practice items in a term of one of the practice taxonomies.
 *
 * @param string               $taxonomy Taxonomy name.
 * @param string               $slug     Term slug.
 * @param array<string, mixed> $args     Extra WP_Query args (posts_per_page, tax_query additions…).
 * @return WP_Post[]
 */
function omochix_core_get_practice_by_term( $taxonomy, $slug, $args = array() ) {
	if ( ! post_type_exists( OMOCHIX_CORE_PRACTICE_POST_TYPE ) || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}
	$query = new WP_Query(
		array_merge(
			array(
				'post_type'           => OMOCHIX_CORE_PRACTICE_POST_TYPE,
				'post_status'         => 'publish',
				'posts_per_page'      => 12,
				'orderby'             => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'tax_query'           => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => sanitize_title( $slug ),
					),
				),
			),
			$args
		)
	);
	return $query->posts;
}

/**
 * Number of published practice items (cached per request).
 *
 * @return int
 */
function omochix_core_count_published_practice() {
	static $count = null;
	if ( null === $count ) {
		$count = post_type_exists( OMOCHIX_CORE_PRACTICE_POST_TYPE ) ? (int) ( wp_count_posts( OMOCHIX_CORE_PRACTICE_POST_TYPE )->publish ?? 0 ) : 0;
	}
	return $count;
}
