<?php
// omochix-industry-hub-version: 1.10.0-dev
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

const OMOCHIX_CORE_GATE_CACHE_OPT     = 'omochix_core_industry_gate_cache';
const OMOCHIX_CORE_GATE_VERSION_OPT   = 'omochix_core_industry_gate_version';
const OMOCHIX_CORE_GATE_TTL           = DAY_IN_SECONDS;
const OMOCHIX_CORE_GATE_VISIBLE_CODES = array( 'G1_editor_ready', 'G2_lead', 'G3_challenges', 'G4_usecases', 'G5_official_workflow' );
const OMOCHIX_CORE_BLOCKED_CACHE_OPT  = 'omochix_core_blocked_workflow_cache';

/** Current gate cache version. */
function omochix_core_gate_version() {
	return (int) get_option( OMOCHIX_CORE_GATE_VERSION_OPT, 1 );
}

/**
 * Invalidate every cached gate. The first change in a request bumps the
 * version at once; any later change in the same request bumps it again at
 * shutdown, so a recompute between two changes is never kept. At most two
 * option writes per request, however many meta keys a save touches.
 */
function omochix_core_gate_invalidate() {
	if ( empty( $GLOBALS['omochix_core_gate_bumped'] ) ) {
		$GLOBALS['omochix_core_gate_bumped'] = true;
		update_option( OMOCHIX_CORE_GATE_VERSION_OPT, omochix_core_gate_version() + 1, true );
		return;
	}
	if ( empty( $GLOBALS['omochix_core_gate_dirty'] ) ) {
		$GLOBALS['omochix_core_gate_dirty'] = true;
		add_action( 'shutdown', 'omochix_core_gate_invalidate_at_shutdown', 0 );
	}
}

/** Second bump of a request (see omochix_core_gate_invalidate()). */
function omochix_core_gate_invalidate_at_shutdown() {
	if ( ! empty( $GLOBALS['omochix_core_gate_dirty'] ) ) {
		$GLOBALS['omochix_core_gate_dirty'] = false;
		update_option( OMOCHIX_CORE_GATE_VERSION_OPT, omochix_core_gate_version() + 1, true );
	}
}

/** Start a new "request" for the coalescing above (tests, long-running CLI jobs). */
function omochix_core_gate_new_request() {
	omochix_core_gate_invalidate_at_shutdown();
	$GLOBALS['omochix_core_gate_bumped'] = false;
	$GLOBALS['omochix_core_gate_memo']   = null;
	$GLOBALS['omochix_core_blocked_memo'] = null;
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
	// One option (not autoloaded) holds every industry's result for the
	// current version. Writing term meta here would bump the terms
	// last_changed salt and flush every term query cache on a page view.
	$memo = $GLOBALS['omochix_core_gate_memo'] ?? null;
	if ( ! is_array( $memo ) || ( $memo['v'] ?? 0 ) !== $version ) {
		$stored = get_option( OMOCHIX_CORE_GATE_CACHE_OPT, array() );
		$memo   = is_array( $stored ) && ( $stored['v'] ?? 0 ) === $version ? $stored : array( 'v' => $version, 'gates' => array() );
	}
	$out   = array();
	$stale = array();
	foreach ( array_values( array_unique( array_map( 'intval', $term_ids ) ) ) as $id ) {
		$cached = $memo['gates'][ $id ] ?? null;
		if ( is_array( $cached ) && ( $now - (int) ( $cached['t'] ?? 0 ) ) < OMOCHIX_CORE_GATE_TTL && isset( $cached['unmet'] ) && is_array( $cached['unmet'] ) ) {
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
			if ( ! in_array( 'missing_term', $gate['unmet'], true ) ) {
				$memo['gates'][ $id ] = array( 't' => $now, 'unmet' => $gate['unmet'] );
			}
		}
		// Write only if nothing invalidated meanwhile (a stale compute is never stored).
		if ( omochix_core_gate_version() === $version ) {
			update_option( OMOCHIX_CORE_GATE_CACHE_OPT, $memo, false );
		}
	}
	$GLOBALS['omochix_core_gate_memo'] = $memo;
	return $out;
}

/**
 * Published workflows that fail the §7 rules now (for example a required
 * field emptied after publishing). Hub lists leave them out.
 *
 * Every published workflow is checked, 200 at a time, so no count cap lets
 * one through. The result is kept in one option (not autoloaded) for the
 * current gate version: everything a check reads (workflow fields, required
 * tools, industry / format / provider terms, status, password, deletion)
 * already bumps that version. Entries also expire daily (the assertive-word
 * list is a filter). A page view on a fresh cache costs one option read.
 *
 * @param int $now Unix time (tests).
 * @return int[] Post IDs.
 */
function omochix_core_blocked_workflow_ids( $now = 0 ) {
	$now     = $now ? (int) $now : time();
	$version = omochix_core_gate_version();
	$fresh   = static fn( $e ) => is_array( $e ) && ( $e['v'] ?? 0 ) === $version && isset( $e['ids'] ) && is_array( $e['ids'] ) && ( $now - (int) ( $e['t'] ?? 0 ) ) < OMOCHIX_CORE_GATE_TTL;
	$memo    = $GLOBALS['omochix_core_blocked_memo'] ?? null;
	if ( $fresh( $memo ) ) {
		return $memo['ids'];
	}
	$stored = get_option( OMOCHIX_CORE_BLOCKED_CACHE_OPT, array() );
	if ( $fresh( $stored ) ) {
		$GLOBALS['omochix_core_blocked_memo'] = $stored;
		return $stored['ids'];
	}
	$blocked = array();
	if ( post_type_exists( 'learn_practice' ) && function_exists( 'omochix_core_workflow_stored_blockers' ) ) {
		for ( $page = 1; $page <= 500; $page++ ) {
			$ids = get_posts(
				array(
					'post_type'           => 'learn_practice',
					'post_status'         => 'publish',
					'fields'              => 'ids',
					'posts_per_page'      => 200,
					'paged'               => $page,
					'orderby'             => 'ID',
					'order'               => 'ASC',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'tax_query'           => array( array( 'taxonomy' => 'learn_format', 'field' => 'slug', 'terms' => array( OMOCHIX_CORE_WORKFLOW_FORMAT ) ) ),
				)
			);
			$ids = array_map( 'intval', is_array( $ids ) ? $ids : array() );
			if ( $ids ) {
				_prime_post_caches( $ids, true, true );
				foreach ( $ids as $id ) {
					if ( omochix_core_workflow_stored_blockers( $id ) ) {
						$blocked[] = $id;
					}
				}
			}
			if ( count( $ids ) < 200 ) {
				break;
			}
		}
	}
	$entry = array( 'v' => $version, 't' => $now, 'ids' => $blocked );
	// Write only if nothing invalidated meanwhile (a stale compute is never stored).
	if ( omochix_core_gate_version() === $version ) {
		update_option( OMOCHIX_CORE_BLOCKED_CACHE_OPT, $entry, false );
	}
	$GLOBALS['omochix_core_blocked_memo'] = $entry;
	return $blocked;
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
	$key = omochix_core_gate_version() . ':' . (int) $only_visible . ':' . (int) $now;
	if ( isset( $GLOBALS['omochix_core_vis_memo'][ $key ] ) ) {
		return $GLOBALS['omochix_core_vis_memo'][ $key ];
	}
	$GLOBALS['omochix_core_vis_memo'][ $key ] = omochix_core_compute_industries_with_visibility( $only_visible, $now );
	return $GLOBALS['omochix_core_vis_memo'][ $key ];
}

/** Uncached body of omochix_core_get_industries_with_visibility(). */
function omochix_core_compute_industries_with_visibility( $only_visible, $now ) {
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
	if ( 0 !== strpos( (string) $meta_key, 'industry_' ) ) {
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

/** Business purposes feed G4 (use cases must name existing purposes). */
add_action( 'edited_' . OMOCHIX_CORE_PURPOSE_TAXONOMY, 'omochix_core_gate_on_term_change' );
add_action( 'delete_' . OMOCHIX_CORE_PURPOSE_TAXONOMY, 'omochix_core_gate_on_term_change' );

/** A password set or removed on a practice item, or a required AI tool deleted. */
function omochix_core_gate_on_post_updated( $post_id, $after, $before ) {
	if ( $after instanceof WP_Post && 'learn_practice' === $after->post_type && $before instanceof WP_Post && $after->post_password !== $before->post_password ) {
		omochix_core_gate_invalidate();
	}
}
add_action( 'post_updated', 'omochix_core_gate_on_post_updated', 10, 3 );
function omochix_core_gate_on_tool_removed( $post_id ) {
	if ( in_array( get_post_type( (int) $post_id ), array( 'ai_tool', 'learn_practice' ), true ) ) {
		omochix_core_gate_invalidate();
	}
}
add_action( 'before_delete_post', 'omochix_core_gate_on_tool_removed' );
add_action( 'trashed_post', 'omochix_core_gate_on_tool_removed' );
/**
 * A permanent delete bumps the version before the row is gone; a page view
 * in between would store a result that still sees the post under the new
 * version. Bumping again at once when the post is gone (the post type is
 * passed in, the row no longer exists) makes that entry stale before any
 * other request can read it; the usual coalescing would wait for shutdown.
 * One extra option write per permanent delete.
 */
function omochix_core_gate_on_deleted( $post_id, $post = null ) {
	if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'ai_tool', 'learn_practice' ), true ) ) {
		$GLOBALS['omochix_core_gate_bumped'] = true;
		update_option( OMOCHIX_CORE_GATE_VERSION_OPT, omochix_core_gate_version() + 1, true );
	}
}
add_action( 'deleted_post', 'omochix_core_gate_on_deleted', 10, 2 );

/** wp_remove_object_terms() does not fire set_object_terms. */
function omochix_core_gate_on_removed_terms( $object_id, $tt_ids, $taxonomy ) {
	omochix_core_gate_on_object_terms( $object_id, array(), $tt_ids, $taxonomy );
}
add_action( 'deleted_term_relationships', 'omochix_core_gate_on_removed_terms', 10, 3 );

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
 * Every front-end main query that is narrowed by an industry (site search,
 * category or tag archives with ?omx_industry=, ?taxonomy=omx_industry&term=,
 * feeds, hub archives) treats a hidden industry exactly like one that does
 * not exist: the clause matches nothing. Dropping the clause instead would
 * let visitors tell hidden industries from missing ones.
 *
 * Runs on parse_tax_query (also re-fired by WP_Query::get_posts()), after
 * WordPress has turned every query var form into tax_query clauses.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function omochix_core_industry_tax_query_guard( $query ) {
	if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() || ! omochix_core_industry_is_public() || omochix_core_industry_viewer_is_editor() || ! isset( $query->tax_query->queries ) || ! is_array( $query->tax_query->queries ) ) {
		return;
	}
	foreach ( $query->tax_query->queries as $key => $clause ) {
		if ( ! is_array( $clause ) || OMOCHIX_CORE_INDUSTRY_TAXONOMY !== ( $clause['taxonomy'] ?? '' ) ) {
			continue;
		}
		$field = (string) ( $clause['field'] ?? 'term_id' );
		$ids   = array();
		foreach ( (array) ( $clause['terms'] ?? array() ) as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$term = 'term_id' === $field || 'term_taxonomy_id' === $field ? get_term( (int) $value, OMOCHIX_CORE_INDUSTRY_TAXONOMY ) : get_term_by( 'name' === $field ? 'name' : 'slug', (string) $value, OMOCHIX_CORE_INDUSTRY_TAXONOMY );
			if ( $term instanceof WP_Term && omochix_core_industry_term_is_visible( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		$query->tax_query->queries[ $key ]['field'] = 'term_id';
		$query->tax_query->queries[ $key ]['terms'] = $ids ? $ids : array( 0 );
	}
}
add_action( 'parse_tax_query', 'omochix_core_industry_tax_query_guard' );

/** REST /wp/v2/search?type=term: hidden industries (and their sub-industries) are excluded. */
function omochix_core_industry_rest_term_search( $args ) {
	if ( ! omochix_core_industry_is_public() || omochix_core_industry_viewer_is_editor() ) {
		return $args;
	}
	$hidden = array();
	foreach ( omochix_core_get_industries_with_visibility( false ) as $row ) {
		if ( ! $row['visible'] ) {
			$hidden[] = (int) $row['term']->term_id;
			$hidden   = array_merge( $hidden, array_map( 'intval', (array) get_term_children( (int) $row['term']->term_id, OMOCHIX_CORE_INDUSTRY_TAXONOMY ) ) );
		}
	}
	if ( $hidden ) {
		$args['exclude'] = array_values( array_unique( array_merge( array_map( 'intval', (array) ( $args['exclude'] ?? array() ) ), $hidden ) ) );
	}
	return $args;
}
add_filter( 'rest_term_search_query', 'omochix_core_industry_rest_term_search' );

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
	// Term archives never set is_embed(), but /{term}/embed/ still routes here with embed=true.
	$embed = get_query_var( 'embed' );
	if ( ! ( is_feed() || is_embed() || ( is_scalar( $embed ) && '' !== (string) $embed ) ) ) {
		return;
	}
	$var = get_query_var( OMOCHIX_CORE_INDUSTRY_TAXONOMY );
	if ( ! is_tax( OMOCHIX_CORE_INDUSTRY_TAXONOMY ) && ( ! is_scalar( $var ) || '' === (string) $var ) && ! ( is_array( $var ) && $var ) ) {
		return;
	}
	global $wp_query;
	if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
		$wp_query->set_404();
		// set_404() keeps is_feed, and template-loader would still print the
		// feed of the main query's posts: empty both.
		$wp_query->is_feed    = false;
		$wp_query->posts      = array();
		$wp_query->post_count = 0;
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

/** The same rule for core's wp-sitemap (when Slim SEO is not active). */
function omochix_core_industry_core_sitemap_term_args( $args, $taxonomy ) {
	return OMOCHIX_CORE_INDUSTRY_TAXONOMY === $taxonomy ? omochix_core_industry_sitemap_term_args( array_merge( (array) $args, array( 'taxonomy' => $taxonomy ) ) ) : $args;
}
add_filter( 'wp_sitemaps_taxonomies_query_args', 'omochix_core_industry_core_sitemap_term_args', 10, 2 );
// omochix-industry-hub-version: 1.10.0-dev (end)
