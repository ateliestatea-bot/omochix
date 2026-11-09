<?php
/**
 * Industry Hub read side (Industry Hub Phase 1): what a hub or a workflow
 * shows, assembled from references only (taxonomy terms, curated IDs, Motion
 * slugs, ledger IDs). Nothing is copied or migrated. Phase 2 templates call
 * these; Phase 1 renders nothing.
 *
 * Every getter returns published, non-password-protected content only and
 * caps its result size.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_INDUSTRY_LIST_MAX = 12;

/**
 * Post types a hub can list.
 *
 * @return string[]
 */
function omochix_core_get_industry_listable_types() {
	return array( 'ai_tool', 'prompt', 'learn_practice', 'page', 'post' );
}

/**
 * Resolve an industry term from an ID, slug or term object.
 *
 * @param int|string|WP_Term $term Term.
 * @return WP_Term|null
 */
function omochix_core_resolve_industry( $term ) {
	if ( $term instanceof WP_Term ) {
		return OMOCHIX_CORE_INDUSTRY_TAXONOMY === $term->taxonomy ? $term : null;
	}
	$found = is_int( $term ) || ( is_string( $term ) && ctype_digit( $term ) ) ? get_term( (int) $term, OMOCHIX_CORE_INDUSTRY_TAXONOMY ) : get_term_by( 'slug', sanitize_title( (string) $term ), OMOCHIX_CORE_INDUSTRY_TAXONOMY );
	return $found instanceof WP_Term ? $found : null;
}

/**
 * Published content of one type tagged with an industry (sub-industries
 * included), optionally narrowed by business purpose and provider type.
 *
 * @param int|string|WP_Term   $industry  Industry.
 * @param string               $post_type One of omochix_core_get_industry_listable_types().
 * @param array<string, mixed> $args      purpose (slug), provider (slug), limit (int), exclude (int[]).
 * @return WP_Post[]
 */
function omochix_core_get_industry_posts( $industry, $post_type, $args = array() ) {
	$term = omochix_core_resolve_industry( $industry );
	if ( ! $term || ! in_array( $post_type, omochix_core_get_industry_listable_types(), true ) || ! post_type_exists( $post_type ) ) {
		return array();
	}
	$limit     = max( 1, min( OMOCHIX_CORE_INDUSTRY_LIST_MAX, (int) ( $args['limit'] ?? 6 ) ) );
	$tax_query = array(
		'relation' => 'AND',
		array( 'taxonomy' => OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'field' => 'term_id', 'terms' => array( (int) $term->term_id ), 'include_children' => true ),
	);
	$raw_purpose = isset( $args['purpose'] ) ? ( is_scalar( $args['purpose'] ) ? trim( (string) $args['purpose'] ) : '-' ) : '';
	if ( '' !== $raw_purpose ) {
		$purpose = sanitize_title( $raw_purpose );
		if ( '' === $purpose || ! term_exists( $purpose, OMOCHIX_CORE_PURPOSE_TAXONOMY ) ) {
			return array(); // an unknown purpose narrows to nothing rather than being ignored
		}
		$tax_query[] = array( 'taxonomy' => OMOCHIX_CORE_PURPOSE_TAXONOMY, 'field' => 'slug', 'terms' => array( $purpose ) );
	}
	$raw_provider = isset( $args['provider'] ) ? ( is_scalar( $args['provider'] ) ? trim( (string) $args['provider'] ) : '-' ) : '';
	if ( '' !== $raw_provider ) {
		$provider = sanitize_key( $raw_provider );
		if ( ! isset( omochix_core_get_provider_vocabulary()[ $provider ] ) || ! in_array( $post_type, omochix_core_get_provider_object_types(), true ) ) {
			return array(); // "unclassified" or an unknown value is never a filter match
		}
		$tax_query[] = array( 'taxonomy' => OMOCHIX_CORE_PROVIDER_TAXONOMY, 'field' => 'slug', 'terms' => array( $provider ) );
	}
	$query = array(
		'post_type'           => $post_type,
		'post_status'         => 'publish',
		'has_password'        => false,
		'posts_per_page'      => min( OMOCHIX_CORE_INDUSTRY_LIST_MAX * 2, $limit * 2 ), // headroom for the read-side re-checks below
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'post__not_in'        => array_map( 'absint', (array) ( $args['exclude'] ?? array() ) ),
		'tax_query'           => $tax_query,
		'orderby'             => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	);
	if ( 'ai_tool' === $post_type && function_exists( 'omochix_get_active_ai_tool_meta_query' ) ) {
		$query['meta_query'] = omochix_get_active_ai_tool_meta_query(); // discontinued tools are never recommended
	}
	$posts = get_posts( $query );
	$posts = is_array( $posts ) ? array_values( array_filter( $posts, static fn( $p ) => $p instanceof WP_Post ) ) : array();
	if ( 'learn_practice' === $post_type && function_exists( 'omochix_core_workflow_stored_blockers' ) ) {
		omochix_core_prime_workflow_tools( $posts );
		// A live workflow that no longer meets its rules is not listed.
		$posts = array_values( array_filter( $posts, static fn( $p ) => ! omochix_core_workflow_stored_blockers( $p->ID ) ) );
	}
	if ( '' !== $raw_provider ) {
		// Re-check: a post with two providers is unclassified, never a match.
		$posts = array_values( array_filter( $posts, static fn( $p ) => $provider === omochix_core_get_provider_type( $p ) ) );
	}
	return array_slice( $posts, 0, $limit );
}

/**
 * Curated posts of a hub (industry_tool_ids / industry_featured_ids), in the
 * editor's order, published only.
 *
 * @param int|string|WP_Term $industry Industry.
 * @param string             $key      industry_tool_ids or industry_featured_ids.
 * @return WP_Post[]
 */
function omochix_core_get_industry_curated( $industry, $key ) {
	$term = omochix_core_resolve_industry( $industry );
	if ( ! $term || ! in_array( $key, array( 'industry_tool_ids', 'industry_featured_ids' ), true ) ) {
		return array();
	}
	$ids = omochix_core_get_industry_meta( $term->term_id )[ $key ];
	if ( ! $ids ) {
		return array();
	}
	$schema = omochix_core_get_industry_meta_schema()[ $key ];
	$query  = array(
		'post_type'      => $schema['post_types'],
		'post_status'    => 'publish',
		'has_password'   => false,
		'post__in'       => $ids,
		'orderby'        => 'post__in',
		'posts_per_page' => count( $ids ),
		'no_found_rows'  => true,
	);
	if ( 'industry_tool_ids' === $key && function_exists( 'omochix_get_active_ai_tool_meta_query' ) ) {
		$query['meta_query'] = omochix_get_active_ai_tool_meta_query();
	}
	$posts = get_posts( $query );
	return is_array( $posts ) ? array_values( $posts ) : array();
}

/**
 * Motion samples of a hub that exist in the theme's catalog.
 *
 * @param int|string|WP_Term $industry Industry.
 * @return string[]
 */
function omochix_core_get_industry_motion_slugs( $industry ) {
	$term = omochix_core_resolve_industry( $industry );
	return $term ? omochix_core_filter_known_motion_slugs( omochix_core_get_industry_meta( $term->term_id )['industry_motion_slugs'] ) : array();
}

/**
 * Top-level industries of a post (a sub-industry counts as its parent),
 * unique, in term order.
 *
 * @param WP_Post|int $post Post.
 * @return WP_Term[]
 */
function omochix_core_get_post_industries( $post ) {
	$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
	$terms   = $post_id ? get_the_terms( $post_id, OMOCHIX_CORE_INDUSTRY_TAXONOMY ) : false;
	$out     = array();
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$top = (int) $term->parent ? get_term( (int) $term->parent, OMOCHIX_CORE_INDUSTRY_TAXONOMY ) : $term;
		if ( $top instanceof WP_Term && ! isset( $out[ $top->term_id ] ) ) {
			$out[ $top->term_id ] = $top;
		}
	}
	return array_values( $out );
}

/**
 * Published ledger cards a workflow references, in the stored order.
 *
 * @param WP_Post|int $post Workflow.
 * @return array<int, array<string, mixed>>
 */
function omochix_core_get_workflow_resource_cards( $post ) {
	$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
	$ids     = omochix_core_sanitize_workflow_meta_value( get_post_meta( $post_id, 'workflow_resource_ids', true ), 'workflow_resource_ids' );
	$cards   = array();
	foreach ( (array) $ids as $id ) {
		$card = omochix_core_get_resource_card( (int) $id );
		if ( $card ) {
			$cards[] = $card;
		}
	}
	return $cards;
}

/**
 * Published ledger cards tagged with an industry.
 *
 * @param int|string|WP_Term $industry Industry.
 * @param int                $limit    Maximum.
 * @return array<int, array<string, mixed>>
 */
function omochix_core_get_industry_resource_cards( $industry, $limit = 6 ) {
	$term = omochix_core_resolve_industry( $industry );
	if ( ! $term ) {
		return array();
	}
	$ids = get_posts(
		array(
			'post_type'      => OMOCHIX_CORE_RESOURCE_POST_TYPE,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => max( 1, min( OMOCHIX_CORE_INDUSTRY_LIST_MAX, (int) $limit ) ),
			'no_found_rows'  => true,
			'tax_query'      => array( array( 'taxonomy' => OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'field' => 'term_id', 'terms' => array( (int) $term->term_id ), 'include_children' => true ) ),
		)
	);
	return array_values( array_filter( array_map( 'omochix_core_get_resource_card', is_array( $ids ) ? $ids : array() ) ) );
}

/**
 * Load the AI tools that practice items reference in one query, so the
 * workflow re-check (which checks each tool's post type) does not query
 * once per item.
 *
 * @param WP_Post[] $posts Practice items (meta already cached).
 * @return void
 */
function omochix_core_prime_workflow_tools( array $posts ) {
	if ( ! function_exists( '_prime_post_caches' ) ) {
		return;
	}
	$ids = array();
	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post && 'learn_practice' === $post->post_type ) {
			$ids = array_merge( $ids, array_map( 'absint', (array) get_post_meta( $post->ID, 'related_tool_ids', true ) ) );
		}
	}
	$ids = array_values( array_unique( array_filter( $ids ) ) );
	if ( $ids ) {
		_prime_post_caches( $ids, false, false );
	}
}
