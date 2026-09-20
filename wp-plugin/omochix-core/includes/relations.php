<?php
/**
 * Safe readers for AI tool v2 manual relations (Learn / News / Lab / Compare).
 *
 * Storage is a plain array of post IDs (see meta-schema.php's
 * `post_id_array` sanitize type). This file is the single place that turns
 * those IDs back into real, currently-published posts, so a later-trashed,
 * unpublished, or wrong-post-type entry never produces a broken link or a
 * PHP notice on the front end.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a stored relation meta value into real, published posts.
 *
 * @param int                  $tool_id   AI tool post ID.
 * @param string               $meta_key  One of the related_*_ids meta keys.
 * @param string|string[]      $post_type Allowed post type(s).
 * @param array<string, mixed> $query_args Extra WP_Query args (e.g. orderby).
 * @return WP_Post[]
 */
function omochix_core_get_related_content( $tool_id, $meta_key, $post_type, $query_args = array() ) {
	$ids = get_post_meta( $tool_id, $meta_key, true );
	$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	$ids = array_values( array_filter( $ids ) );

	if ( ! $ids ) {
		return array();
	}

	$args = array_merge(
		array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		),
		$query_args
	);

	$query = new WP_Query( $args );

	return $query->posts;
}

/**
 * Return published pages eligible as "related Learn" picks.
 *
 * Prefers the theme's Learn-descendant helper when available so the picker
 * only lists real Learn content; falls back to all published pages if the
 * active theme does not define it, so the admin field degrades gracefully
 * rather than being empty.
 *
 * @return WP_Post[]
 */
function omochix_core_get_learn_picker_pages() {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	if ( ! function_exists( 'omochix_page_is_learn_descendant' ) ) {
		return $pages;
	}

	$learn_pages = array_values( array_filter( $pages, 'omochix_page_is_learn_descendant' ) );

	// If the Learn tree is empty (e.g. on a fresh install), fall back to the
	// full page list rather than showing editors an empty picker.
	return $learn_pages ? $learn_pages : $pages;
}
