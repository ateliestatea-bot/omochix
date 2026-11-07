<?php
/**
 * Industry Hub visibility, gate cache and leak protection (Industry Hub Phase 2).
 *
 * - Visible  = G1 (editor flag) + G2..G5 (written content and an official
 *   workflow). Only visible hubs are shown to visitors.
 * - Indexable = visible + G6 (freshness) + G7 (sources).
 * - Gate results are cached per term (term meta `_industry_gate_cache`) and
 *   invalidated by bumping one option whenever hub content, workflows or
 *   their terms change; G6 depends on time, so entries also expire daily.
 * - Once hubs are public (the theme turns on omochix_core_industry_public),
 *   industries that are not visible are hidden from visitors everywhere:
 *   REST (list, single, post fields, collection filters), post_class, feeds,
 *   embeds and site search.
 *
 * Everything here is inert while hubs are not public.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_GATE_CACHE_KEY     = '_industry_gate_cache';
const OMOCHIX_CORE_GATE_VERSION_OPT   = 'omochix_core_industry_gate_version';
const OMOCHIX_CORE_GATE_TTL           = DAY_IN_SECONDS;
const OMOCHIX_CORE_GATE_VISIBLE_CODES = array( 'G1_editor_ready', 'G2_lead', 'G3_challenges', 'G4_usecases', 'G5_official_workflow' );

/** Current gate cache version. */
function omochix_core_gate_version() {
	return (int) get_option( OMOCHIX_CORE_GATE_VERSION_OPT, 1 );
}

/**
 * Invalidate every cached gate (one option write). Bumped on every change,
 * not once per request, so a recompute later in the same request can never
 * keep a result that an even later change made stale.
 */
function omochix_core_gate_invalidate() {
	update_option( OMOCHIX_CORE_GATE_VERSION_OPT, omochix_core_gate_version() + 1, true );
}

/**
 * Gate results for several top-level industries, from the cache where it is
 * fresh, computed together (one workflow query) where it is not.
 *
 * @param int[] $term_ids Term IDs.
 * @param int   $now      Unix time (tests).
 * @return array<int, string[]> term_id => unmet codes.
 */
function omochix_core_industry_gates( array $term_ids, $now = 0 ) {
	$now     = $now ? (int) $now : time();
	$version = omochix_core_gate_version();
	$out     = array();
	$stale   = array();
	foreach ( array_values( array_unique( array_map( 'intval', $term_ids ) ) ) as $id ) {
		$cached = get_term_meta( $id, OMOCHIX_CORE_GATE_CACHE_KEY, true );
		if ( is_array( $cached ) && ( $cached['v'] ?? 0 ) === $version && ( $now - (int) ( $cached['t'] ?? 0 ) ) < OMOCHIX_CORE_GATE_TTL && isset( $cached['unmet'] ) && is_array( $cached['unmet'] ) ) {
			$out[ $id ] = $cached['unmet'];
		} else {
			$stale[] = $id;
		}
	}
	if ( $stale ) {
		$counts = omochix_core_count_official_workflows_batch( $stale );
		foreach ( $stale as $id ) {
			$gate       = omochix_core_industry_hub_gate( $id, $now, $counts[ $id ] ?? 0 );
			$out[ $id ] = $gate['unmet'];
			// Only real top-level industries are cached (unknown IDs stay uncached).
			if ( ! in_array( 'missing_term', $gate['unmet'], true ) ) {
				update_term_meta( $id, OMOCHIX_CORE_GATE_CACHE_KEY, array( 'v' => $version, 't' => $now, 'unmet' => $gate['unmet'] ) );
			}
		}
	}
	return $out;
}

/**
 * Visibility of one industry.
 *
 * @param int|WP_Term $term Term or ID.
 * @param int         $now  Unix time (tests).
 * @return array{visible: bool, indexable: bool, unmet: string[]}
 */
function omochix_core_industry_visibility( $term, $now = 0 ) {
	$id    = $term instanceof WP_Term ? (int) $term->term_id : (int) $term;
	$unmet = omochix_core_industry_gates( array( $id ), $now )[ $id ] ?? array( 'missing_term' );
	$visible = ! array_intersect( $unmet, array_merge( OMOCHIX_CORE_GATE_VISIBLE_CODES, array( 'missing_term', 'not_top_level' ) ) );
	return array( 'visible' => $visible, 'indexable' => $visible && ! $unmet, 'unmet' => $unmet );
}

/**
 * Top-level industries in display order with their visibility.
 *
 * @param bool $only_visible Return only visible ones.
 * @param int  $now          Unix time (tests).
 * @return array<int, array{term: WP_Term, visible: bool, indexable: bool, unmet: string[]}>
 */
function omochix_core_get_industries_with_visibility( $only_visible = false, $now = 0 ) {
	$terms = get_terms(
		array(
			'taxonomy'   => OMOCHIX_CORE_INDUSTRY_TAXONOMY,
			'parent'     => 0,
			'hide_empty' => false,
		)
	);
	if ( ! is_array( $terms ) || ! $terms ) {
		return array();
	}
	$terms = array_values( array_filter( $terms, static fn( $t ) => $t instanceof WP_Term ) );
	$gates = omochix_core_industry_gates( wp_list_pluck( $terms, 'term_id' ), $now );
	$order = array();
	$list  = array();
	foreach ( $terms as $term ) {
		$unmet   = $gates[ (int) $term->term_id ] ?? array( 'missing_term' );
		$visible = ! array_intersect( $unmet, array_merge( OMOCHIX_CORE_GATE_VISIBLE_CODES, array( 'missing_term', 'not_top_level' ) ) );
		if ( $only_visible && ! $visible ) {
			continue;
		}
		$list[] = array( 'term' => $term, 'visible' => $visible, 'indexable' => $visible && ! $unmet, 'unmet' => $unmet );
		$order[] = array( (int) get_term_meta( $term->term_id, 'industry_order', true ), (string) $term->name );
	}
	array_multisort( $order, $list );
	return $list;
}

/** IDs of visible top-level industries. */
function omochix_core_get_visible_industry_ids( $now = 0 ) {
	return array_map( static fn( $row ) => (int) $row['term']->term_id, omochix_core_get_industries_with_visibility( true, $now ) );
}

/**
 * Whether an industry term (top level or sub-industry) belongs to a visible
 * hub. Sub-industries follow their parent.
 *
 * @param int|WP_Term $term Term.
 * @return bool
 */
function omochix_core_industry_term_is_visible( $term ) {
	$term = $term instanceof WP_Term ? $term : get_term( (int) $term, OMOCHIX_CORE_INDUSTRY_TAXONOMY );
	if ( ! $term instanceof WP_Term || OMOCHIX_CORE_INDUSTRY_TAXONOMY !== $term->taxonomy ) {
		return false;
	}
	$top = (int) $term->parent ? get_term( (int) $term->parent, OMOCHIX_CORE_INDUSTRY_TAXONOMY ) : $term;
	return $top instanceof WP_Term && omochix_core_industry_visibility( $top )['visible'];
}

/* -------------------------------------------------------------------------
 * Invalidation
 * ---------------------------------------------------------------------- */

/** Term meta changes on industries (except the cache itself). */
function omochix_core_gate_on_term_meta( $meta_id, $object_id, $meta_key ) {
	if ( OMOCHIX_CORE_GATE_CACHE_KEY === $meta_key || 0 !== strpos( (string) $meta_key, 'industry_' ) ) {
		return;
	}
	$term = get_term( (int) $object_id );
	if ( $term instanceof WP_Term && OMOCHIX_CORE_INDUSTRY_TAXONOMY === $term->taxonomy ) {
		omochix_core_gate_invalidate();
	}
}
add_action( 'added_term_meta', 'omochix_core_gate_on_term_meta', 10, 3 );
add_action( 'updated_term_meta', 'omochix_core_gate_on_term_meta', 10, 3 );
add_action( 'deleted_term_meta', 'omochix_core_gate_on_term_meta', 10, 3 );

/** Industries created, edited, deleted. */
function omochix_core_gate_on_term_change( $term_id = 0, $tt_id = 0, $taxonomy = '' ) {
	if ( '' === $taxonomy || OMOCHIX_CORE_INDUSTRY_TAXONOMY === $taxonomy ) {
		omochix_core_gate_invalidate();
	}
}
add_action( 'created_' . OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'omochix_core_gate_on_term_change' );
add_action( 'edited_' . OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'omochix_core_gate_on_term_change' );
add_action( 'delete_' . OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'omochix_core_gate_on_term_change' );

/** A workflow (or any practice item) changed status. */
function omochix_core_gate_on_status( $new, $old, $post ) {
	if ( $post instanceof WP_Post && 'learn_practice' === $post->post_type && $new !== $old ) {
		omochix_core_gate_invalidate();
	}
}
add_action( 'transition_post_status', 'omochix_core_gate_on_status', 10, 3 );

/** Terms that feed the gate were set on a post. */
function omochix_core_gate_on_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( in_array( $taxonomy, array( OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'learn_format', OMOCHIX_CORE_PROVIDER_TAXONOMY ), true ) && 'learn_practice' === get_post_type( (int) $object_id ) ) {
		omochix_core_gate_invalidate();
	}
}
add_action( 'set_object_terms', 'omochix_core_gate_on_object_terms', 20, 4 );

/** Workflow fields or required tools of a practice item changed. */
function omochix_core_gate_on_post_meta( $meta_id, $object_id, $meta_key ) {
	if ( ( 0 === strpos( (string) $meta_key, 'workflow_' ) || 'related_tool_ids' === $meta_key ) && 'learn_practice' === get_post_type( (int) $object_id ) ) {
		omochix_core_gate_invalidate();
	}
}
add_action( 'added_post_meta', 'omochix_core_gate_on_post_meta', 10, 3 );
add_action( 'updated_post_meta', 'omochix_core_gate_on_post_meta', 10, 3 );
add_action( 'deleted_post_meta', 'omochix_core_gate_on_post_meta', 10, 3 );

/* -------------------------------------------------------------------------
 * Leak protection (only while hubs are public)
 * ---------------------------------------------------------------------- */

/** Whether the current user sees everything (editors) — no filtering needed. */
function omochix_core_industry_viewer_is_editor() {
	return current_user_can( 'edit_posts' );
}

/**
 * Remove industry IDs that are not visible from a list (sub-industries follow
 * their parent).
 *
 * @param int[] $ids Term IDs.
 * @return int[]
 */
function omochix_core_filter_visible_industry_ids( $ids ) {
	return array_values( array_filter( array_map( 'intval', (array) $ids ), 'omochix_core_industry_term_is_visible' ) );
}

/** REST list /wp/v2/industries: visitors get visible hubs (and their sub-industries) only. */
function omochix_core_industry_rest_term_query( $args ) {
	if ( ! omochix_core_industry_is_public() || omochix_core_industry_viewer_is_editor() ) {
		return $args;
	}
	$visible = omochix_core_get_visible_industry_ids();
	$allowed = $visible;
	foreach ( $visible as $id ) {
		$allowed = array_merge( $allowed, array_map( 'intval', (array) get_term_children( $id, OMOCHIX_CORE_INDUSTRY_TAXONOMY ) ) );
	}
	$requested       = isset( $args['include'] ) ? array_filter( array_map( 'intval', (array) $args['include'] ) ) : array();
	$allowed         = $requested ? array_values( array_intersect( $requested, $allowed ) ) : $allowed;
	$args['include'] = $allowed ? $allowed : array( 0 ); // [0] matches nothing; [] would mean "all"
	return $args;
}
add_filter( 'rest_' . OMOCHIX_CORE_INDUSTRY_TAXONOMY . '_query', 'omochix_core_industry_rest_term_query' );

/** REST single /wp/v2/industries/<id>: 404 for hidden industries. */
function omochix_core_industry_rest_term_single( $response, $item, $request ) {
	if ( ! omochix_core_industry_is_public() || omochix_core_industry_viewer_is_editor() || ! $item instanceof WP_Term ) {
		return $response;
	}
	if ( ! omochix_core_industry_term_is_visible( $item ) ) {
		return new WP_Error( 'rest_term_invalid', __( 'Term does not exist.', 'omochix-core' ), array( 'status' => 404 ) );
	}
	return $response;
}
add_filter( 'rest_prepare_' . OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'omochix_core_industry_rest_term_single', 10, 3 );

/** Post responses: the `industries` field and term links keep visible industries only. */
function omochix_core_industry_rest_post_fields( $response ) {
	if ( ! omochix_core_industry_is_public() || omochix_core_industry_viewer_is_editor() || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
		return $response;
	}
	$data = $response->get_data();
	if ( is_array( $data ) && isset( $data['industries'] ) && is_array( $data['industries'] ) ) {
		$data['industries'] = omochix_core_filter_visible_industry_ids( $data['industries'] );
		$response->set_data( $data );
	}
	return $response;
}

/** Post collections: ?industries=<hidden id> filters nothing in, i.e. returns nothing. */
function omochix_core_industry_rest_post_query( $args ) {
	if ( ! omochix_core_industry_is_public() || omochix_core_industry_viewer_is_editor() || empty( $args['tax_query'] ) || ! is_array( $args['tax_query'] ) ) {
		return $args;
	}
	$walk = static function ( $clauses ) use ( &$walk ) {
		foreach ( $clauses as $key => $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}
			if ( isset( $clause['taxonomy'] ) ) {
				if ( OMOCHIX_CORE_INDUSTRY_TAXONOMY === $clause['taxonomy'] && 'term_id' === ( $clause['field'] ?? 'term_id' ) ) {
					$visible                  = omochix_core_filter_visible_industry_ids( (array) ( $clause['terms'] ?? array() ) );
					$clauses[ $key ]['terms'] = $visible ? $visible : array( 0 );
				}
			} else {
				$clauses[ $key ] = $walk( $clause );
			}
		}
		return $clauses;
	};
	$args['tax_query'] = $walk( $args['tax_query'] );
	return $args;
}
foreach ( array( 'post', 'page', 'ai_tool', 'prompt', 'learn_practice' ) as $omochix_core_vis_type ) {
	add_filter( 'rest_prepare_' . $omochix_core_vis_type, 'omochix_core_industry_rest_post_fields', 21 );
	add_filter( 'rest_' . $omochix_core_vis_type . '_query', 'omochix_core_industry_rest_post_query', 21 );
}
unset( $omochix_core_vis_type );

/** post_class: never print industry classes (they would name hidden industries). */
function omochix_core_industry_post_class_taxonomies( $taxonomies ) {
	return is_array( $taxonomies ) ? array_values( array_diff( $taxonomies, array( OMOCHIX_CORE_INDUSTRY_TAXONOMY ) ) ) : $taxonomies;
}
add_filter( 'post_class_taxonomies', 'omochix_core_industry_post_class_taxonomies' );

/**
 * Main front-end query: site search (and any other main query) cannot be
 * narrowed by a hidden industry through ?omx_industry=; the condition is
 * dropped for visitors.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function omochix_core_industry_main_query_guard( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! omochix_core_industry_is_public() || omochix_core_industry_viewer_is_editor() ) {
		return;
	}
	if ( ! $query->is_search() ) {
		return;
	}
	$slug = (string) $query->get( OMOCHIX_CORE_INDUSTRY_TAXONOMY );
	if ( '' === $slug ) {
		return;
	}
	$term = get_term_by( 'slug', sanitize_title( $slug ), OMOCHIX_CORE_INDUSTRY_TAXONOMY );
	if ( ! $term instanceof WP_Term || ! omochix_core_industry_term_is_visible( $term ) ) {
		$query->set( OMOCHIX_CORE_INDUSTRY_TAXONOMY, '' );
		$query->tax_query = null;
		$tax              = array_filter( (array) $query->get( 'tax_query' ), static fn( $c ) => ! is_array( $c ) || OMOCHIX_CORE_INDUSTRY_TAXONOMY !== ( $c['taxonomy'] ?? '' ) );
		$query->set( 'tax_query', $tax );
	}
}
add_action( 'pre_get_posts', 'omochix_core_industry_main_query_guard', 1 );

/**
 * Feeds and embeds of industries are not offered (hub pages are the only
 * public surface): /learn/industries/{slug}/feed/, /embed/ and
 * /feed/?omx_industry= answer 404.
 *
 * @return void
 */
function omochix_core_industry_block_feeds() {
	if ( ! omochix_core_industry_is_public() ) {
		return;
	}
	if ( ! ( is_feed() || is_embed() ) ) {
		return;
	}
	if ( ! is_tax( OMOCHIX_CORE_INDUSTRY_TAXONOMY ) && '' === (string) get_query_var( OMOCHIX_CORE_INDUSTRY_TAXONOMY ) ) {
		return;
	}
	global $wp_query;
	if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
		$wp_query->set_404();
	}
	status_header( 404 );
	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, follow' );
	}
}
add_action( 'template_redirect', 'omochix_core_industry_block_feeds', 0 );

/* -------------------------------------------------------------------------
 * Sitemap (only while hubs are public)
 * ---------------------------------------------------------------------- */

/**
 * The industry term sitemap lists indexable top-level hubs only (G1..G7).
 * hide_empty is off because a hub whose posts are tagged with a
 * sub-industry has a parent count of 0.
 *
 * @param array<string, mixed> $args Query args.
 * @return array<string, mixed>
 */
function omochix_core_industry_sitemap_term_args( $args ) {
	if ( ! omochix_core_industry_is_public() || OMOCHIX_CORE_INDUSTRY_TAXONOMY !== ( $args['taxonomy'] ?? '' ) ) {
		return $args;
	}
	$ids = array_map(
		static fn( $row ) => (int) $row['term']->term_id,
		array_filter( omochix_core_get_industries_with_visibility( false ), static fn( $row ) => $row['indexable'] )
	);
	$args['include']    = $ids ? $ids : array( 0 );
	$args['parent']     = 0;
	$args['hide_empty'] = false;
	return $args;
}
add_filter( 'slim_seo_taxonomy_query_args', 'omochix_core_industry_sitemap_term_args' );
