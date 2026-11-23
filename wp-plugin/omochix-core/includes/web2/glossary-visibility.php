<?php
// omochix-glossary-version: 1
/**
 * AI glossary: who can see what (gate G-B per term) and the leak guards.
 *
 * - REST: /wp/v2/glossary* and /wp/v2/glossary-categories* answer only to
 *   editors, also once the glossary is open (Phase 1). The post type and the
 *   taxonomy are left out of /wp/v2/types, /wp/v2/taxonomies and
 *   /wp/v2/search for everyone else.
 * - oEmbed: a term can never be embedded.
 * - Sitemaps (WordPress core and Slim SEO): left out while the glossary is
 *   closed; once open, only indexable terms.
 * Existence of a non-public term is never revealed: these all answer as if
 * it did not exist.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Days after the review date before a term stops being indexable (D-3). */
if ( ! defined( 'OMOCHIX_CORE_GLOSSARY_NOINDEX_GRACE_DAYS' ) ) {
	define( 'OMOCHIX_CORE_GLOSSARY_NOINDEX_GRACE_DAYS', 30 );
}

/**
 * A stored term in the shape of glossary-validate.php (relations are IDs and
 * are checked on display, so they are left out).
 *
 * @param WP_Post|object $post Post.
 * @param array          $over Values that replace the stored ones (a save in progress).
 * @return array<string, mixed>
 */
function omochix_core_glossary_term_data( $post, array $over = array() ) {
	$id   = (int) ( $post->ID ?? 0 );
	$meta = static function ( $key, $default ) use ( $id, $over ) {
		if ( array_key_exists( $key, $over ) ) {
			return $over[ $key ];
		}
		$v = $id ? get_post_meta( $id, $key, true ) : '';
		return '' === $v || null === $v ? $default : $v;
	};
	$cats = array_key_exists( 'category', $over ) ? $over['category'] : ( $id ? wp_get_object_terms( $id, 'omx_glossary_category', array( 'fields' => 'slugs' ) ) : array() );
	$cats = is_array( $cats ) ? array_values( $cats ) : array();
	$data = array(
		'schema'       => omochix_core_glossary_rules()['schema'],
		'slug'         => (string) ( $over['slug'] ?? $post->post_name ?? '' ),
		'title'        => (string) ( $over['title'] ?? $post->post_title ?? '' ),
		'reading'      => (string) $meta( 'glossary_reading', '' ),
		'summary'      => (string) $meta( 'glossary_summary', '' ),
		'category'     => 1 === count( $cats ) ? (string) $cats[0] : ( $cats ? '(2つ以上)' : '' ),
		'content_html' => (string) ( $over['content'] ?? $post->post_content ?? '' ),
		'sources'      => (array) $meta( 'glossary_sources', array() ),
		'verified_on'  => (string) $meta( 'glossary_verified_on', '' ),
		'review_days'  => (int) $meta( 'glossary_review_days', omochix_core_glossary_rules()['review_days'] ),
		'ai_assisted'  => (bool) $meta( 'glossary_ai_assisted', false ),
	);
	$en = (string) $meta( 'glossary_en', '' );
	if ( '' !== $en ) {
		$data['en'] = $en;
	}
	$aliases = (array) $meta( 'glossary_aliases', array() );
	if ( $aliases ) {
		$data['aliases'] = array_values( $aliases );
	}
	$level = (string) $meta( 'glossary_level', '' );
	if ( '' !== $level ) {
		$data['level'] = $level;
	}
	return $data;
}

/**
 * The review date of a term (verified_on + review_days), or '' when unknown.
 *
 * @param array $data Term data.
 * @return string YYYY-MM-DD.
 */
function omochix_core_glossary_review_due( array $data ) {
	$v = (string) ( $data['verified_on'] ?? '' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $v ) ) {
		return '';
	}
	$days = (int) ( $data['review_days'] ?? omochix_core_glossary_rules()['review_days'] );
	return gmdate( 'Y-m-d', (int) strtotime( $v . ' 00:00:00 UTC' ) + $days * 86400 );
}

/**
 * Gate G-B: whether a term is visible (may be shown) and indexable.
 *
 * visible: the glossary is open, the term is published, has no password and
 * passes every rule. indexable: visible and not more than the grace period
 * past its review date. review: the review date has passed.
 *
 * @param WP_Post|object|int $post  Post or ID.
 * @param string|null        $today YYYY-MM-DD (tests).
 * @return array{visible: bool, indexable: bool, review: bool, due: string, errors: string[]}
 */
function omochix_core_glossary_visibility( $post, $today = null ) {
	$post  = is_object( $post ) ? $post : get_post( (int) $post );
	$today = null === $today ? gmdate( 'Y-m-d' ) : $today;
	$none  = array(
		'visible'   => false,
		'indexable' => false,
		'review'    => false,
		'due'       => '',
		'errors'    => array(),
	);
	if ( ! $post || 'omx_glossary' !== ( $post->post_type ?? '' ) ) {
		return $none;
	}
	$data   = omochix_core_glossary_term_data( $post );
	$check  = omochix_core_glossary_validate_term( $data, array( 'today' => $today ) );
	$due    = omochix_core_glossary_review_due( $data );
	$review = '' === $due || strcmp( $today, $due ) > 0;
	$grace  = '' === $due ? '' : gmdate( 'Y-m-d', (int) strtotime( $due . ' 00:00:00 UTC' ) + OMOCHIX_CORE_GLOSSARY_NOINDEX_GRACE_DAYS * 86400 );
	$open   = omochix_core_glossary_public() && 'publish' === ( $post->post_status ?? '' ) && '' === (string) ( $post->post_password ?? '' );
	$ok     = $open && ! $check['errors'];
	return array(
		'visible'   => $ok,
		'indexable' => $ok && '' !== $grace && strcmp( $today, $grace ) <= 0,
		'review'    => $review,
		'due'       => $due,
		'errors'    => $check['errors'],
	);
}

/**
 * REST: glossary routes answer only to editors (the block editor uses them).
 *
 * @param mixed $response Response so far.
 * @param mixed $handler  Handler.
 * @param mixed $request  Request.
 * @return mixed
 */
function omochix_core_glossary_rest_guard( $response, $handler, $request ) {
	if ( is_wp_error( $response ) || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
		return $response;
	}
	// WP_REST_Server matches routes case-insensitively, so this check must too.
	$route = strtolower( rawurldecode( (string) $request->get_route() ) );
	if ( ! preg_match( '#^/+wp/v2/+(?:glossary|glossary-categories|types/+omx_glossary|taxonomies/+omx_glossary_category)(?:/|$)#', $route ) ) {
		return $response;
	}
	if ( omochix_core_glossary_current_user_is_editor() ) {
		return $response;
	}
	return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'omochix-core' ), array( 'status' => function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : 401 ) );
}
add_filter( 'rest_request_before_callbacks', 'omochix_core_glossary_rest_guard', 10, 3 );

/**
 * REST: drop the glossary from the /wp/v2/types and /wp/v2/taxonomies lists
 * for everyone but editors.
 *
 * @param mixed $result  Response.
 * @param mixed $server  Server.
 * @param mixed $request Request.
 * @return mixed
 */
function omochix_core_glossary_rest_lists( $result, $server, $request ) {
	if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! is_object( $result ) || ! method_exists( $result, 'get_data' ) || omochix_core_glossary_current_user_is_editor() ) {
		return $result;
	}
	$route = strtolower( rtrim( (string) $request->get_route(), '/' ) );
	$key   = array(
		'/wp/v2/types'      => 'omx_glossary',
		'/wp/v2/taxonomies' => 'omx_glossary_category',
	);
	if ( isset( $key[ $route ] ) ) {
		$data = $result->get_data();
		if ( is_array( $data ) && isset( $data[ $key[ $route ] ] ) ) {
			unset( $data[ $key[ $route ] ] );
			$result->set_data( $data );
		}
	}
	return $result;
}
add_filter( 'rest_post_dispatch', 'omochix_core_glossary_rest_lists', 10, 3 );

/**
 * REST /wp/v2/search: never search the glossary for anyone but editors.
 *
 * @param array $args Query arguments.
 * @return array
 */
function omochix_core_glossary_rest_search_args( $args ) {
	if ( ! is_array( $args ) || omochix_core_glossary_current_user_is_editor() ) {
		return $args;
	}
	$types = isset( $args['post_type'] ) ? (array) $args['post_type'] : array();
	if ( in_array( 'omx_glossary', $types, true ) ) {
		$types             = array_values( array_diff( $types, array( 'omx_glossary' ) ) );
		$args['post_type'] = $types ? $types : array( '__omx_none__' );
	}
	return $args;
}
add_filter( 'rest_post_search_query', 'omochix_core_glossary_rest_search_args' );

/**
 * oEmbed: a glossary term is never embedded.
 *
 * @param int $post_id Post ID found for the URL.
 * @return int
 */
function omochix_core_glossary_oembed_post_id( $post_id ) {
	return $post_id && 'omx_glossary' === get_post_type( (int) $post_id ) ? 0 : $post_id;
}
add_filter( 'oembed_request_post_id', 'omochix_core_glossary_oembed_post_id' );

/**
 * Sitemaps: no glossary post type while closed (core passes name => object,
 * Slim SEO a list of names; both are handled).
 *
 * @param mixed $post_types Post types.
 * @return mixed
 */
function omochix_core_glossary_sitemap_post_types( $post_types ) {
	if ( ! is_array( $post_types ) || omochix_core_glossary_public() ) {
		return $post_types;
	}
	$list = array_is_list( $post_types );
	unset( $post_types['omx_glossary'] );
	$post_types = array_filter( $post_types, static fn( $v ) => 'omx_glossary' !== $v );
	return $list ? array_values( $post_types ) : $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'omochix_core_glossary_sitemap_post_types' );
add_filter( 'slim_seo_sitemap_post_types', 'omochix_core_glossary_sitemap_post_types' );

/**
 * Sitemaps: no glossary category taxonomy while closed.
 *
 * @param mixed $taxonomies Taxonomies.
 * @return mixed
 */
function omochix_core_glossary_sitemap_taxonomies( $taxonomies ) {
	if ( ! is_array( $taxonomies ) || omochix_core_glossary_public() ) {
		return $taxonomies;
	}
	$list = array_is_list( $taxonomies );
	unset( $taxonomies['omx_glossary_category'] );
	$taxonomies = array_filter( $taxonomies, static fn( $v ) => 'omx_glossary_category' !== $v );
	return $list ? array_values( $taxonomies ) : $taxonomies;
}
add_filter( 'wp_sitemaps_taxonomies', 'omochix_core_glossary_sitemap_taxonomies' );
add_filter( 'slim_seo_sitemap_taxonomies', 'omochix_core_glossary_sitemap_taxonomies' );

/**
 * Slim SEO sitemap: leave out every term that is not indexable.
 *
 * @param bool  $ignore  Ignore so far.
 * @param mixed $post_id Post or ID.
 * @return bool
 */
function omochix_core_glossary_sitemap_post_ignore( $ignore, $post_id = 0 ) {
	$post = $post_id instanceof WP_Post ? $post_id : ( $post_id ? get_post( (int) $post_id ) : null );
	if ( ! $post || 'omx_glossary' !== $post->post_type ) {
		return $ignore;
	}
	return $ignore || ! omochix_core_glossary_visibility( $post )['indexable'];
}
add_filter( 'slim_seo_sitemap_post_ignore', 'omochix_core_glossary_sitemap_post_ignore', 10, 2 );

/**
 * WordPress core sitemap: only indexable terms once open.
 *
 * @param array  $args      Query arguments.
 * @param string $post_type Post type.
 * @return array
 */
function omochix_core_glossary_sitemap_query_args( $args, $post_type = '' ) {
	if ( 'omx_glossary' !== $post_type || ! is_array( $args ) ) {
		return $args;
	}
	$ids = array();
	foreach ( get_posts( array( 'post_type' => 'omx_glossary', 'post_status' => 'publish', 'posts_per_page' => 500, 'has_password' => false, 'fields' => 'all', 'no_found_rows' => true, 'suppress_filters' => true ) ) as $p ) {
		if ( omochix_core_glossary_visibility( $p )['indexable'] ) {
			$ids[] = (int) $p->ID;
		}
	}
	$args['post__in'] = $ids ? $ids : array( 0 );
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'omochix_core_glossary_sitemap_query_args', 10, 2 );
// omochix-glossary-version: 1 (end)
