<?php
/**
 * Industry Hub taxonomies (Industry Hub Phase 1).
 *
 * Three axes, all added next to the existing data model without changing it:
 *
 * - omx_industry      業種. Hierarchical, two levels (industry → sub-industry).
 *                     Parent terms are the hubs at /learn/industries/{slug}/.
 * - omx_purpose       業務目的. Flat, filter-only (no public URL). Kept apart
 *                     from learn_usecase on purpose: learn_usecase is "what a
 *                     build is for" on practice items and pages and has
 *                     indexable archives; mixing business purposes into it
 *                     would change those pages (docs/Industry-Hub-Design.md §2.2).
 * - omx_provider_type 提供元. Fixed four-term vocabulary, one per post, no
 *                     public URL. No term means "unclassified" — never
 *                     "official" (see includes/provider.php).
 *
 * The existing post types are attached through register_taxonomy()'s object
 * types; their own register_post_type() arguments are untouched.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_INDUSTRY_TAXONOMY        = 'omx_industry';
const OMOCHIX_CORE_PURPOSE_TAXONOMY         = 'omx_purpose';
const OMOCHIX_CORE_PROVIDER_TAXONOMY        = 'omx_provider_type';
const OMOCHIX_CORE_INDUSTRY_SLUG            = 'learn/industries';
const OMOCHIX_CORE_INDUSTRY_REWRITE_VERSION = '1';
const OMOCHIX_CORE_INDUSTRY_MAX_DEPTH       = 2;

/**
 * Whether industry hubs have public URLs. Phase 1 ships the data model only,
 * so the default is false: no /learn/industries/{slug}/ URL exists until the
 * theme that renders the hubs (Phase 2) opts in with
 * add_filter( 'omochix_core_industry_public', '__return_true' ).
 *
 * @return bool
 */
function omochix_core_industry_is_public() {
	return (bool) apply_filters( 'omochix_core_industry_public', false );
}

/**
 * Rewrite version including the public switch, so turning hubs on or off
 * flushes the rules once.
 *
 * @return string
 */
function omochix_core_industry_rewrite_version() {
	return OMOCHIX_CORE_INDUSTRY_REWRITE_VERSION . ( omochix_core_industry_is_public() ? '-public' : '-private' );
}

/**
 * Post types that can carry an industry / purpose.
 *
 * @return string[]
 */
function omochix_core_get_industry_object_types() {
	return array( 'ai_tool', 'prompt', 'learn_practice', 'page', 'post', 'omx_resource' );
}

/**
 * Post types that can carry a provider type. AI tools are the tools
 * themselves, not content someone provides, so they are left out.
 *
 * @return string[]
 */
function omochix_core_get_provider_object_types() {
	return array( 'learn_practice', 'prompt', 'post', 'page', 'omx_resource' );
}

/**
 * Taxonomy configuration keyed by taxonomy name.
 *
 * @return array<string, array<string, mixed>>
 */
function omochix_core_get_industry_taxonomy_config() {
	$public = omochix_core_industry_is_public();
	return array(
		OMOCHIX_CORE_INDUSTRY_TAXONOMY => array(
			'singular'     => __( '業種', 'omochix-core' ),
			'plural'       => __( '業種', 'omochix-core' ),
			'hierarchical' => true,
			'public'       => $public,
			'rest_base'    => 'industries',
			'rewrite'      => $public ? array(
				'slug'         => OMOCHIX_CORE_INDUSTRY_SLUG,
				'with_front'   => false,
				'hierarchical' => false,
			) : false,
			'object_types' => omochix_core_get_industry_object_types(),
			'capabilities' => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_posts',
			),
		),
		OMOCHIX_CORE_PURPOSE_TAXONOMY  => array(
			'singular'     => __( '業務目的', 'omochix-core' ),
			'plural'       => __( '業務目的', 'omochix-core' ),
			'hierarchical' => false,
			'public'       => false,
			'rest_base'    => 'business-purposes',
			'rewrite'      => false,
			'object_types' => omochix_core_get_industry_object_types(),
			'capabilities' => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_posts',
			),
		),
		// Hierarchical only so editors get a checkbox list instead of a free-text
		// tag box; the vocabulary is fixed and nobody can add, rename or delete terms.
		OMOCHIX_CORE_PROVIDER_TAXONOMY => array(
			'singular'     => __( '提供元', 'omochix-core' ),
			'plural'       => __( '提供元', 'omochix-core' ),
			'hierarchical' => true,
			'public'       => false,
			'rest_base'    => 'provider-types',
			'rewrite'      => false,
			'object_types' => omochix_core_get_provider_object_types(),
			'capabilities' => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'do_not_allow',
				'delete_terms' => 'do_not_allow',
				'assign_terms' => 'edit_posts',
			),
		),
	);
}

/**
 * Register the three Industry Hub taxonomies.
 *
 * @return void
 */
function omochix_core_register_industry_taxonomies() {
	foreach ( omochix_core_get_industry_taxonomy_config() as $taxonomy => $config ) {
		$labels = array(
			'name'          => $config['plural'],
			'singular_name' => $config['singular'],
			'search_items'  => sprintf( __( '%sを検索', 'omochix-core' ), $config['plural'] ),
			'all_items'     => sprintf( __( '%s一覧', 'omochix-core' ), $config['plural'] ),
			'edit_item'     => sprintf( __( '%sを編集', 'omochix-core' ), $config['singular'] ),
			'update_item'   => sprintf( __( '%sを更新', 'omochix-core' ), $config['singular'] ),
			'add_new_item'  => sprintf( __( '%sを追加', 'omochix-core' ), $config['singular'] ),
			'new_item_name' => sprintf( __( '新しい%s', 'omochix-core' ), $config['singular'] ),
			'menu_name'     => $config['plural'],
		);
		$args   = array(
			'labels'             => $labels,
			'hierarchical'       => $config['hierarchical'],
			'public'             => $config['public'],
			'publicly_queryable' => $config['public'],
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => $config['public'],
			'show_tagcloud'      => false,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'rest_base'          => $config['rest_base'],
			'query_var'          => $config['public'],
			'rewrite'            => $config['rewrite'],
			'capabilities'       => $config['capabilities'],
		);
		if ( OMOCHIX_CORE_PROVIDER_TAXONOMY === $taxonomy && function_exists( 'omochix_core_provider_meta_box' ) ) {
			$args['meta_box_cb'] = 'omochix_core_provider_meta_box';
			// The radio box posts term IDs like the category box; without this
			// WordPress would read them as term names (taxonomy_meta_box_sanitize_cb_input).
			$args['meta_box_sanitize_cb'] = 'taxonomy_meta_box_sanitize_cb_checkboxes';
		}
		register_taxonomy( $taxonomy, $config['object_types'], $args );
	}
}

/**
 * Starter terms. Slugs are stable identifiers (English); names are what
 * editors and visitors see and stay editable (except the provider vocabulary).
 *
 * @return array<string, array<string, string>>
 */
function omochix_core_get_industry_initial_terms() {
	return array(
		OMOCHIX_CORE_INDUSTRY_TAXONOMY => array(
			'real-estate'    => '不動産',
			'restaurant'     => '飲食',
			'beauty'         => '美容',
			'ecommerce'      => 'EC',
			'construction'   => '建設',
			'web-production' => 'Web制作',
		),
		OMOCHIX_CORE_PURPOSE_TAXONOMY  => array(
			'marketing'        => '集客・販促',
			'customer-support' => '接客・問い合わせ対応',
			'document'         => '書類作成・事務',
			'creative'         => '画像・動画・デザイン制作',
			'analysis'         => '分析・レポート',
			'knowledge'        => '社内ナレッジ・教育',
			'sales'            => '見積・提案・営業',
		),
		OMOCHIX_CORE_PROVIDER_TAXONOMY => omochix_core_get_provider_vocabulary(),
	);
}

/**
 * The fixed provider vocabulary (slug => label).
 *
 * @return array<string, string>
 */
function omochix_core_get_provider_vocabulary() {
	return array(
		'official'         => 'OmochiX公式',
		'external-service' => '外部サービス',
		'creator'          => 'クリエイター',
		'partner'          => 'パートナー（PR）',
	);
}

/**
 * Slugs an industry term may not take: they are WordPress endpoints under
 * the taxonomy base (/learn/industries/page/2/, …/feed/), so a term with that
 * slug could never be reached.
 *
 * @return string[]
 */
function omochix_core_get_industry_reserved_slugs() {
	return array( 'page', 'feed', 'rss', 'rss2', 'rdf', 'atom', 'embed', 'trackback', 'comments', 'amp' );
}

/**
 * Insert missing starter terms without overwriting editorial changes.
 *
 * @return void
 */
function omochix_core_insert_industry_initial_terms() {
	foreach ( omochix_core_get_industry_initial_terms() as $taxonomy => $terms ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
			}
		}
	}
}

/**
 * Flush and seed once per rewrite version (plugin updates do not run the
 * activation hook). Same pattern as the practice module.
 *
 * @return void
 */
function omochix_core_industry_maybe_flush_rewrite_rules() {
	if ( get_option( 'omochix_core_industry_rewrite_version' ) === omochix_core_industry_rewrite_version() ) {
		return;
	}
	omochix_core_insert_industry_initial_terms();
	flush_rewrite_rules( false );
	update_option( 'omochix_core_industry_rewrite_version', omochix_core_industry_rewrite_version() );
}

/**
 * Depth of a term in omx_industry (1 = top level), following parents.
 *
 * @param int $term_id Term ID (0 = none).
 * @return int 0 when the term does not exist.
 */
function omochix_core_industry_term_depth( $term_id ) {
	$depth = 0;
	$seen  = array();
	$id    = (int) $term_id;
	while ( $id > 0 && ! isset( $seen[ $id ] ) ) {
		$seen[ $id ] = true;
		$term        = get_term( $id, OMOCHIX_CORE_INDUSTRY_TAXONOMY );
		if ( ! $term instanceof WP_Term ) {
			return $depth;
		}
		++$depth;
		$id = (int) $term->parent;
	}
	return $depth;
}

/**
 * Slug a new term would get from its name / args.
 *
 * @param string               $name Term name.
 * @param array<string, mixed> $args Insert args.
 * @return string
 */
function omochix_core_industry_candidate_slug( $name, $args ) {
	$slug = isset( $args['slug'] ) && '' !== (string) $args['slug'] ? (string) $args['slug'] : (string) $name;
	return sanitize_title( $slug );
}

/**
 * Reject new terms that break the Industry Hub rules: a third industry
 * level, a reserved industry slug, or anything outside the provider
 * vocabulary.
 *
 * @param string|WP_Error      $term     Term name.
 * @param string               $taxonomy Taxonomy.
 * @param array<string, mixed> $args     Insert args (WP 6.1+).
 * @return string|WP_Error
 */
function omochix_core_industry_pre_insert_term( $term, $taxonomy, $args = array() ) {
	if ( is_wp_error( $term ) ) {
		return $term;
	}
	$args = is_array( $args ) ? $args : array();
	if ( OMOCHIX_CORE_INDUSTRY_TAXONOMY === $taxonomy ) {
		$slug = omochix_core_industry_candidate_slug( $term, $args );
		if ( in_array( $slug, omochix_core_get_industry_reserved_slugs(), true ) ) {
			return new WP_Error( 'omochix_industry_reserved_slug', sprintf( __( '業種のスラッグ "%s" は予約語のため使えません。', 'omochix-core' ), $slug ) );
		}
		$parent = isset( $args['parent'] ) ? (int) $args['parent'] : 0;
		if ( $parent && omochix_core_industry_term_depth( $parent ) >= OMOCHIX_CORE_INDUSTRY_MAX_DEPTH ) {
			return new WP_Error( 'omochix_industry_depth', __( '業種は「業種 → サブ業種」の2階層までです。', 'omochix-core' ) );
		}
	}
	// Core lets anyone who may assign terms create new ones in a flat
	// taxonomy (tag box, REST). Business purposes are a curated vocabulary:
	// creating one needs manage_categories (seeding on init is exempt).
	if ( OMOCHIX_CORE_PURPOSE_TAXONOMY === $taxonomy && ! doing_action( 'init' ) && is_user_logged_in() && ! current_user_can( 'manage_categories' ) ) {
		return new WP_Error( 'omochix_purpose_curated', __( '業務目的の追加は編集者（カテゴリー管理の権限）だけができます。', 'omochix-core' ) );
	}
	// The workflow format switches on the workflow rules (Phase 3). learn_format
	// is a flat taxonomy, so any author could otherwise create it from the tag
	// box or REST while saving — and an item whose term did not exist yet would
	// skip the workflow guard. Only editors may create it.
	if ( 'learn_format' === $taxonomy && ! doing_action( 'init' ) && ! current_user_can( 'manage_categories' ) ) {
		$slug = omochix_core_industry_candidate_slug( $term, $args );
		if ( 'workflow' === $slug || '導入する' === trim( (string) $term ) ) {
			return new WP_Error( 'omochix_workflow_format_curated', __( '形式「導入する（workflow）」の作成は編集者だけができます。', 'omochix-core' ) );
		}
	}
	if ( OMOCHIX_CORE_PROVIDER_TAXONOMY === $taxonomy ) {
		$vocabulary = omochix_core_get_provider_vocabulary();
		$slug       = omochix_core_industry_candidate_slug( $term, $args );
		if ( ! isset( $vocabulary[ $slug ] ) || ! empty( $args['parent'] ) ) {
			return new WP_Error( 'omochix_provider_fixed', __( '提供元の区分は固定です（OmochiX公式・外部サービス・クリエイター・パートナー）。', 'omochix-core' ) );
		}
	}
	return $term;
}
add_filter( 'pre_insert_term', 'omochix_core_industry_pre_insert_term', 10, 3 );

/**
 * Keep the stored parent when an update would create a third level (moving a
 * term under a sub-industry, or moving an industry that has sub-industries
 * under another term). wp_update_term casts this filter's value to int, so it
 * cannot return an error; the change is simply not applied.
 *
 * @param int                  $parent  Requested parent.
 * @param int                  $term_id Term being updated.
 * @param string               $taxonomy Taxonomy.
 * @return int
 */
function omochix_core_industry_update_term_parent( $parent, $term_id, $taxonomy ) {
	if ( OMOCHIX_CORE_INDUSTRY_TAXONOMY !== $taxonomy ) {
		return $parent;
	}
	$parent  = (int) $parent;
	$current = get_term( (int) $term_id, OMOCHIX_CORE_INDUSTRY_TAXONOMY );
	$stored  = $current instanceof WP_Term ? (int) $current->parent : 0;
	if ( ! $parent ) {
		return $parent;
	}
	$has_children = (bool) get_terms(
		array(
			'taxonomy'   => OMOCHIX_CORE_INDUSTRY_TAXONOMY,
			'parent'     => (int) $term_id,
			'hide_empty' => false,
			'fields'     => 'ids',
			'number'     => 1,
		)
	);
	if ( $parent === (int) $term_id || $has_children || omochix_core_industry_term_depth( $parent ) >= OMOCHIX_CORE_INDUSTRY_MAX_DEPTH ) {
		return $stored;
	}
	return $parent;
}
add_filter( 'wp_update_term_parent', 'omochix_core_industry_update_term_parent', 20, 3 );

/**
 * Keep the stored slug when an update would give an industry a reserved slug.
 *
 * @param array<string, mixed> $data     Term data to write.
 * @param int                  $term_id  Term ID.
 * @param string               $taxonomy Taxonomy.
 * @return array<string, mixed>
 */
function omochix_core_industry_update_term_data( $data, $term_id, $taxonomy ) {
	if ( OMOCHIX_CORE_INDUSTRY_TAXONOMY !== $taxonomy || ! isset( $data['slug'] ) ) {
		return $data;
	}
	if ( in_array( (string) $data['slug'], omochix_core_get_industry_reserved_slugs(), true ) ) {
		$current = get_term( (int) $term_id, OMOCHIX_CORE_INDUSTRY_TAXONOMY );
		if ( $current instanceof WP_Term ) {
			$data['slug'] = $current->slug;
		}
	}
	return $data;
}
add_filter( 'wp_update_term_data', 'omochix_core_industry_update_term_data', 10, 3 );

/**
 * Keep the non-public taxonomies out of Slim SEO's sitemap explicitly (they
 * are not public, this is belt and braces for a future config change).
 *
 * @param mixed $taxonomies Sitemap taxonomies.
 * @return mixed
 */
function omochix_core_industry_sitemap_taxonomies( $taxonomies ) {
	return is_array( $taxonomies ) ? array_values( array_diff( $taxonomies, array( OMOCHIX_CORE_PURPOSE_TAXONOMY, OMOCHIX_CORE_PROVIDER_TAXONOMY ) ) ) : $taxonomies;
}
add_filter( 'slim_seo_sitemap_taxonomies', 'omochix_core_industry_sitemap_taxonomies' );

/**
 * Safety net for when hubs are switched public: an industry archive stays
 * noindex unless the theme decides otherwise through
 * omochix_core_industry_archive_noindex (Phase 2 wires the content-quality
 * gate, docs/Industry-Hub-Design.md §6.1, into that filter).
 *
 * @return bool
 */
function omochix_core_industry_archive_is_noindex() {
	if ( ! function_exists( 'is_tax' ) || ! is_tax( OMOCHIX_CORE_INDUSTRY_TAXONOMY ) ) {
		return false;
	}
	return (bool) apply_filters( 'omochix_core_industry_archive_noindex', true );
}

add_filter(
	'wp_robots',
	static function ( $robots ) {
		if ( omochix_core_industry_archive_is_noindex() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);
add_filter(
	'slim_seo_robots_index',
	static function ( $indexed ) {
		return omochix_core_industry_archive_is_noindex() ? false : $indexed;
	}
);
add_filter(
	'slim_seo_sitemap_taxonomies',
	static function ( $taxonomies ) {
		if ( ! is_array( $taxonomies ) || ! apply_filters( 'omochix_core_industry_archive_noindex', true ) ) {
			return $taxonomies;
		}
		return array_values( array_diff( $taxonomies, array( OMOCHIX_CORE_INDUSTRY_TAXONOMY ) ) );
	},
	11
);

/**
 * The three taxonomies are in REST only for the editor panels. Visitors may
 * not list them (term names, descriptions and counts would be a new public
 * surface): their REST routes answer 401 to anyone who cannot edit posts.
 * Industries open up only when hubs are public (Phase 2).
 *
 * @param mixed           $response Response so far (WP_Error short-circuits).
 * @param array           $handler  Route handler.
 * @param WP_REST_Request $request  Request.
 * @return mixed
 */
function omochix_core_industry_rest_read_guard( $response, $handler, $request ) {
	if ( is_wp_error( $response ) || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
		return $response;
	}
	// WP_REST_Server matches routes case-insensitively, so this check must too.
	$route = strtolower( (string) $request->get_route() );
	if ( ! preg_match( '#^/+wp/v2/+(industries|business-purposes|provider-types)(?:/|$)#', $route, $m ) ) {
		return $response;
	}
	if ( 'industries' === $m[1] && omochix_core_industry_is_public() ) {
		return $response;
	}
	if ( current_user_can( 'edit_posts' ) ) {
		return $response;
	}
	return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'omochix-core' ), array( 'status' => function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : 401 ) );
}
add_filter( 'rest_request_before_callbacks', 'omochix_core_industry_rest_read_guard', 10, 3 );

/**
 * Post responses would otherwise carry the term IDs of the three taxonomies
 * (industries / business-purposes / provider-types fields) and accept them as
 * filters (?provider-types=ID), letting visitors tell which public posts are
 * partner (PR) or official. In Phase 1 these stay editor-only; industries
 * open up with the hubs.
 *
 * @return string[] REST field / parameter names hidden from visitors.
 */
function omochix_core_industry_private_rest_fields() {
	$fields = array( 'business-purposes', 'provider-types' );
	if ( ! omochix_core_industry_is_public() ) {
		$fields[] = 'industries';
	}
	return $fields;
}

/**
 * Remove the fields from post responses for users who cannot edit posts.
 *
 * @param WP_REST_Response $response Response.
 * @return WP_REST_Response
 */
function omochix_core_industry_rest_hide_fields( $response ) {
	if ( current_user_can( 'edit_posts' ) || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
		return $response;
	}
	$data = $response->get_data();
	if ( is_array( $data ) ) {
		foreach ( omochix_core_industry_private_rest_fields() as $field ) {
			unset( $data[ $field ] );
		}
		$response->set_data( $data );
	}
	if ( method_exists( $response, 'get_links' ) && method_exists( $response, 'remove_link' ) ) {
		foreach ( (array) ( $response->get_links()['https://api.w.org/term'] ?? array() ) as $link ) {
			$taxonomy = $link['attributes']['taxonomy'] ?? '';
			if ( in_array( $taxonomy, array( OMOCHIX_CORE_PURPOSE_TAXONOMY, OMOCHIX_CORE_PROVIDER_TAXONOMY ), true ) || ( OMOCHIX_CORE_INDUSTRY_TAXONOMY === $taxonomy && ! omochix_core_industry_is_public() ) ) {
				$response->remove_link( 'https://api.w.org/term', $link['href'] );
			}
		}
	}
	return $response;
}

/**
 * Ignore the taxonomies as collection filters for users who cannot edit
 * posts (the parameters are dropped before the query is built).
 *
 * @param array<string, mixed> $args    WP_Query args.
 * @return array<string, mixed>
 */
function omochix_core_industry_rest_strip_query( $args ) {
	if ( current_user_can( 'edit_posts' ) || empty( $args['tax_query'] ) || ! is_array( $args['tax_query'] ) ) {
		return $args;
	}
	$private = array( OMOCHIX_CORE_PURPOSE_TAXONOMY, OMOCHIX_CORE_PROVIDER_TAXONOMY );
	if ( ! omochix_core_industry_is_public() ) {
		$private[] = OMOCHIX_CORE_INDUSTRY_TAXONOMY;
	}
	$strip = static function ( $clauses ) use ( &$strip, $private ) {
		$out = array();
		foreach ( $clauses as $key => $clause ) {
			if ( 'relation' === $key ) {
				$out[ $key ] = $clause;
			} elseif ( is_array( $clause ) && isset( $clause['taxonomy'] ) ) {
				if ( ! in_array( $clause['taxonomy'], $private, true ) ) {
					$out[ $key ] = $clause;
				}
			} elseif ( is_array( $clause ) ) {
				$out[ $key ] = $strip( $clause );
			}
		}
		return $out;
	};
	$args['tax_query'] = $strip( $args['tax_query'] );
	return $args;
}

foreach ( array( 'post', 'page', 'ai_tool', 'prompt', 'learn_practice' ) as $omochix_core_rest_type ) {
	add_filter( 'rest_prepare_' . $omochix_core_rest_type, 'omochix_core_industry_rest_hide_fields', 20 );
	add_filter( 'rest_' . $omochix_core_rest_type . '_query', 'omochix_core_industry_rest_strip_query', 20 );
}
unset( $omochix_core_rest_type );
