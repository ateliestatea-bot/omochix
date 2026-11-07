<?php
/**
 * Industry hub term meta (Industry Hub Phase 1).
 *
 * The editorial content of a hub (lead, challenges, use cases, curated tools
 * and workflows, Motion samples, sources) lives on the top-level omx_industry
 * term, so no post or page is created or changed for a hub. Row values reuse
 * the practice sanitizers (omochix_core_sanitize_practice_rows()).
 *
 * The content gate (docs/Industry-Hub-Design.md §6.1) decides whether a hub
 * is rich enough to be indexed: written content counts, collected links and
 * cards do not.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Industry term meta contract.
 *
 * @return array<string, array<string, mixed>>
 */
function omochix_core_get_industry_meta_schema() {
	return array(
		'industry_hub_ready'    => array( 'type' => 'boolean', 'default' => false, 'sanitize' => 'bool' ),
		'industry_order'        => array( 'type' => 'integer', 'default' => 0, 'sanitize' => 'nonnegative_integer' ),
		'industry_icon'         => array( 'type' => 'string', 'default' => '', 'sanitize' => 'enum', 'options' => array( '', 'building', 'utensils', 'sparkles', 'cart', 'helmet', 'code' ) ),
		'industry_lead'         => array( 'type' => 'string', 'default' => '', 'sanitize' => 'textarea', 'max' => 600 ),
		'industry_benefits'     => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 6, 'rows' => array( 'title' => 'text', 'body' => 'textarea' ) ),
		'industry_challenges'   => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 10, 'rows' => array( 'title' => 'text', 'body' => 'textarea', 'purpose' => 'key' ) ),
		'industry_usecases'     => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 12, 'rows' => array( 'title' => 'text', 'body' => 'textarea', 'purpose' => 'key' ) ),
		'industry_tool_ids'     => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'post_id_array', 'max' => 12, 'post_types' => array( 'ai_tool' ) ),
		'industry_featured_ids' => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'post_id_array', 'max' => 6, 'post_types' => array( 'learn_practice', 'page' ) ),
		'industry_motion_slugs' => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'slug_array', 'max' => 6 ),
		'industry_verified_at'  => array( 'type' => 'string', 'default' => '', 'sanitize' => 'date' ),
		'industry_sources'      => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 20, 'rows' => array( 'label' => 'text', 'url' => 'url' ) ),
	);
}

/**
 * Normalise a list of post IDs: integers, unique, of the allowed post types,
 * capped. Unknown IDs are dropped (a curated list never points at nothing).
 *
 * @param mixed    $value      Array or comma / space separated text.
 * @param string[] $post_types Allowed post types.
 * @param int      $max        Maximum count.
 * @return int[]
 */
function omochix_core_sanitize_typed_post_ids( $value, $post_types, $max ) {
	if ( is_string( $value ) ) {
		$value = preg_split( '/[\s,]+/', $value );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $id ) {
		if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) ) {
			continue;
		}
		$id = (int) $id;
		if ( $id && ! in_array( $id, $out, true ) && in_array( get_post_type( $id ), $post_types, true ) ) {
			$out[] = $id;
		}
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return $out;
}

/**
 * Normalise a Motion slug list (same rule as practice related_motion_slugs).
 *
 * @param mixed $value Array or comma / space separated text.
 * @param int   $max   Maximum count.
 * @return string[]
 */
function omochix_core_sanitize_motion_slugs( $value, $max ) {
	if ( is_string( $value ) ) {
		$value = preg_split( '/[\s,]+/', $value );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$slugs = array();
	foreach ( $value as $slug ) {
		$slug = is_scalar( $slug ) ? sanitize_title( (string) $slug ) : '';
		if ( '' !== $slug && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) && ! in_array( $slug, $slugs, true ) ) {
			$slugs[] = $slug;
		}
		if ( count( $slugs ) >= $max ) {
			break;
		}
	}
	return $slugs;
}

/**
 * Sanitize one industry meta value.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed Null for an unknown key.
 */
function omochix_core_sanitize_industry_meta_value( $value, $key ) {
	$schema = omochix_core_get_industry_meta_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}
	$field = $schema[ $key ];
	switch ( $field['sanitize'] ) {
		case 'bool':
			return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
		case 'nonnegative_integer':
			return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
		case 'enum':
			$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
			return in_array( $value, $field['options'], true ) ? $value : $field['default'];
		case 'textarea':
			$value = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
			return mb_substr( $value, 0, $field['max'] );
		case 'date':
			return omochix_core_sanitize_practice_row_value( is_scalar( $value ) ? (string) $value : '', 'date' );
		case 'post_id_array':
			return omochix_core_sanitize_typed_post_ids( $value, $field['post_types'], $field['max'] );
		case 'slug_array':
			return omochix_core_sanitize_motion_slugs( $value, $field['max'] );
		case 'json_rows':
			$rows = omochix_core_sanitize_practice_rows( $value, $field['rows'], $field['max'] );
			if ( 'industry_usecases' === $key || 'industry_challenges' === $key ) {
				foreach ( $rows as &$row ) {
					$row['purpose'] = term_exists( $row['purpose'], OMOCHIX_CORE_PURPOSE_TAXONOMY ) ? $row['purpose'] : '';
				}
				unset( $row );
			}
			return $rows;
	}
	return null;
}

/** register_term_meta sanitize callback. */
function omochix_core_sanitize_registered_industry_meta( $value, $meta_key ) {
	return omochix_core_sanitize_industry_meta_value( $value, $meta_key );
}

/**
 * register_term_meta auth callback: only users who can edit the term.
 *
 * @param bool   $allowed  Current decision.
 * @param string $meta_key Meta key.
 * @param int    $term_id  Term ID.
 * @return bool
 */
function omochix_core_auth_registered_industry_meta( $allowed, $meta_key, $term_id ) {
	unset( $allowed, $meta_key );
	return $term_id ? current_user_can( 'edit_term', (int) $term_id ) : current_user_can( 'manage_categories' );
}

/**
 * Register the industry term meta (not exposed in REST in Phase 1).
 *
 * @return void
 */
function omochix_core_register_industry_term_meta() {
	foreach ( omochix_core_get_industry_meta_schema() as $key => $field ) {
		register_term_meta(
			OMOCHIX_CORE_INDUSTRY_TAXONOMY,
			$key,
			array(
				'type'              => $field['type'],
				'single'            => true,
				'default'           => $field['default'],
				'show_in_rest'      => false,
				'sanitize_callback' => 'omochix_core_sanitize_registered_industry_meta',
				'auth_callback'     => 'omochix_core_auth_registered_industry_meta',
			)
		);
	}
}

/**
 * Stored industry meta, sanitized again on read (defence against values
 * written around the API).
 *
 * @param int $term_id Term ID.
 * @return array<string, mixed>
 */
function omochix_core_get_industry_meta( $term_id ) {
	$out    = array();
	$schema = omochix_core_get_industry_meta_schema();
	// Re-sanitizing checks the post type of every curated ID; load them in one
	// query first so a hub costs one query here, not one per ID.
	$ids = array();
	foreach ( $schema as $key => $field ) {
		if ( 'post_id_array' === $field['sanitize'] ) {
			$ids = array_merge( $ids, array_filter( array_map( 'absint', (array) get_term_meta( (int) $term_id, $key, true ) ) ) );
		}
	}
	if ( $ids && function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( array_values( array_unique( $ids ) ), false, false );
	}
	foreach ( $schema as $key => $field ) {
		$raw         = get_term_meta( (int) $term_id, $key, true );
		$out[ $key ] = '' === $raw ? $field['default'] : omochix_core_sanitize_industry_meta_value( $raw, $key );
	}
	return $out;
}

/**
 * Motion samples a hub may show: the stored slugs that exist in the theme's
 * Motion catalog. The catalog belongs to the theme and is never written by
 * the plugin; without the theme's catalog reader nothing can be verified, so
 * nothing is returned (fail closed).
 *
 * @param string[] $slugs Stored slugs.
 * @return string[]
 */
function omochix_core_filter_known_motion_slugs( $slugs ) {
	if ( ! function_exists( 'omochix_learn_gallery_get_item' ) ) {
		return array();
	}
	return array_values(
		array_filter(
			(array) $slugs,
			static function ( $slug ) {
				return is_string( $slug ) && null !== omochix_learn_gallery_get_item( $slug );
			}
		)
	);
}

/**
 * Content-gate thresholds (docs/Industry-Hub-Design.md §6.1, provisional B-8).
 *
 * @return array<string, int>
 */
function omochix_core_get_industry_gate_thresholds() {
	$defaults = array(
		'lead_min'        => 120,
		'challenges_min'  => 3,
		'usecases_min'    => 3,
		'row_body_min'    => 80,
		'purposes_min'    => 2,
		'workflows_min'   => 1,
		'fresh_days'      => 180,
		'sources_min'     => 1,
	);
	$values   = apply_filters( 'omochix_core_industry_gate_thresholds', $defaults );
	return array_map( 'absint', array_merge( $defaults, is_array( $values ) ? array_intersect_key( $values, $defaults ) : array() ) );
}

/**
 * Number of published, OmochiX-official workflows tagged with an industry.
 *
 * @param int $term_id Industry term ID.
 * @param int $limit   Stop counting at this many.
 * @return int
 */
function omochix_core_count_official_workflows( $term_id, $limit = 1 ) {
	if ( ! post_type_exists( 'learn_practice' ) ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'        => 'learn_practice',
			'post_status'      => 'publish',
			'has_password'     => false,
			'fields'           => 'ids',
			'posts_per_page'   => max( 10, 5 * (int) $limit ), // headroom for the provider re-check below
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'tax_query'        => array(
				'relation' => 'AND',
				array( 'taxonomy' => OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'field' => 'term_id', 'terms' => array( (int) $term_id ), 'include_children' => true ),
				array( 'taxonomy' => 'learn_format', 'field' => 'slug', 'terms' => array( 'workflow' ) ),
				array( 'taxonomy' => OMOCHIX_CORE_PROVIDER_TAXONOMY, 'field' => 'slug', 'terms' => array( 'official' ) ),
			),
		)
	);
	$ids = omochix_core_filter_counting_workflows( is_array( $ids ) ? $ids : array() );
	return min( count( $ids ), max( 1, (int) $limit ) );
}

/**
 * Of candidate workflow IDs, those that count as OmochiX-official workflows.
 * The tax_query also matches a post that (against the rules) carries two
 * providers, and live workflows edited into an incomplete state are only
 * warned about by the guard; neither counts. Posts, meta, terms and the
 * referenced tools are loaded in one go first, so the checks below run from
 * the cache instead of querying per post.
 *
 * @param int[] $ids Candidate IDs.
 * @return int[]
 */
function omochix_core_filter_counting_workflows( array $ids ) {
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( ! $ids ) {
		return array();
	}
	if ( function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( $ids, true, true );
		$tools = array();
		foreach ( $ids as $id ) {
			$tools = array_merge( $tools, array_map( 'absint', (array) get_post_meta( $id, 'related_tool_ids', true ) ) );
		}
		$tools = array_values( array_unique( array_filter( $tools ) ) );
		if ( $tools ) {
			_prime_post_caches( $tools, false, false );
		}
	}
	return array_values( array_filter( $ids, static fn( $id ) => 'official' === omochix_core_get_provider_type( $id ) && ! omochix_core_workflow_stored_blockers( $id ) ) );
}

/**
 * Official workflow counts for several industries with one query (the hub
 * index computes the gate of every industry at once).
 *
 * @param int[] $term_ids Top-level industry term IDs.
 * @return array<int, int> term_id => count.
 */
function omochix_core_count_official_workflows_batch( array $term_ids ) {
	$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
	$counts   = array_fill_keys( $term_ids, 0 );
	if ( ! $term_ids || ! post_type_exists( 'learn_practice' ) ) {
		return $counts;
	}
	$ids = get_posts(
		array(
			'post_type'        => 'learn_practice',
			'post_status'      => 'publish',
			'has_password'     => false,
			'fields'           => 'ids',
			'posts_per_page'   => 200,
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'tax_query'        => array(
				'relation' => 'AND',
				array( 'taxonomy' => OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'field' => 'term_id', 'terms' => $term_ids, 'include_children' => true ),
				array( 'taxonomy' => 'learn_format', 'field' => 'slug', 'terms' => array( 'workflow' ) ),
				array( 'taxonomy' => OMOCHIX_CORE_PROVIDER_TAXONOMY, 'field' => 'slug', 'terms' => array( 'official' ) ),
			),
		)
	);
	foreach ( omochix_core_filter_counting_workflows( is_array( $ids ) ? $ids : array() ) as $id ) {
		foreach ( omochix_core_get_post_industries( $id ) as $top ) {
			if ( isset( $counts[ (int) $top->term_id ] ) ) {
				++$counts[ (int) $top->term_id ];
			}
		}
	}
	return $counts;
}

/**
 * Content gate for a hub. Every condition is about content written for the
 * hub; tool cards, prompts and external links are deliberately not counted.
 *
 * @param int $term_id Industry term ID.
 * @param int $now     Unix time (tests).
 * @return array{ready: bool, unmet: string[]}
 */
function omochix_core_industry_hub_gate( $term_id, $now = 0, $workflow_count = null ) {
	$term = get_term( (int) $term_id, OMOCHIX_CORE_INDUSTRY_TAXONOMY );
	if ( ! $term instanceof WP_Term ) {
		return array( 'ready' => false, 'unmet' => array( 'missing_term' ) );
	}
	if ( (int) $term->parent ) {
		return array( 'ready' => false, 'unmet' => array( 'not_top_level' ) );
	}
	$t     = omochix_core_get_industry_gate_thresholds();
	$m     = omochix_core_get_industry_meta( $term->term_id );
	$unmet = array();
	$rich  = static function ( $rows, $min_rows ) use ( $t ) {
		$good = array_filter(
			(array) $rows,
			static function ( $row ) use ( $t ) {
				return '' !== trim( (string) ( $row['title'] ?? '' ) ) && mb_strlen( trim( (string) ( $row['body'] ?? '' ) ) ) >= $t['row_body_min'];
			}
		);
		return count( $good ) >= $min_rows;
	};
	if ( true !== $m['industry_hub_ready'] ) {
		$unmet[] = 'G1_editor_ready';
	}
	if ( mb_strlen( trim( (string) $m['industry_lead'] ) ) < $t['lead_min'] ) {
		$unmet[] = 'G2_lead';
	}
	if ( ! $rich( $m['industry_challenges'], $t['challenges_min'] ) ) {
		$unmet[] = 'G3_challenges';
	}
	$purposes = array_unique( array_filter( wp_list_pluck( (array) $m['industry_usecases'], 'purpose' ) ) );
	if ( ! $rich( $m['industry_usecases'], $t['usecases_min'] ) || count( $purposes ) < $t['purposes_min'] ) {
		$unmet[] = 'G4_usecases';
	}
	$workflow_count = null === $workflow_count ? omochix_core_count_official_workflows( $term->term_id, $t['workflows_min'] ) : (int) $workflow_count;
	if ( $workflow_count < $t['workflows_min'] ) {
		$unmet[] = 'G5_official_workflow';
	}
	$verified = (string) $m['industry_verified_at'];
	$now      = $now ? (int) $now : time();
	$then     = '' !== $verified ? strtotime( $verified . ' 00:00:00 UTC' ) : false;
	if ( ! $then || ( $now - $then ) > $t['fresh_days'] * DAY_IN_SECONDS ) {
		$unmet[] = 'G6_fresh';
	}
	$sources = array_filter(
		(array) $m['industry_sources'],
		static function ( $row ) {
			return '' !== (string) ( $row['url'] ?? '' );
		}
	);
	if ( count( $sources ) < $t['sources_min'] ) {
		$unmet[] = 'G7_sources';
	}
	return array( 'ready' => ! $unmet, 'unmet' => $unmet );
}

/**
 * Editor-facing label of a gate condition.
 *
 * @param string $code Gate code.
 * @return string
 */
function omochix_core_industry_gate_label( $code ) {
	$labels = array(
		'G1_editor_ready'      => __( '編集者による「ハブ公開可」のチェック', 'omochix-core' ),
		'G2_lead'              => __( '導入文（独自の文章）', 'omochix-core' ),
		'G3_challenges'        => __( '業種の課題（本文が一定の長さ以上のもの）', 'omochix-core' ),
		'G4_usecases'          => __( 'AI活用方法（本文が一定の長さ以上、2つ以上の業務目的）', 'omochix-core' ),
		'G5_official_workflow' => __( 'OmochiX公式のワークフロー（公開済み）', 'omochix-core' ),
		'G6_fresh'             => __( '最終確認日（期限内）', 'omochix-core' ),
		'G7_sources'           => __( '出典', 'omochix-core' ),
		'not_top_level'        => __( 'サブ業種はハブになりません（親の業種で管理します）', 'omochix-core' ),
		'missing_term'         => __( '業種が見つかりません', 'omochix-core' ),
	);
	return $labels[ $code ] ?? $code;
}
