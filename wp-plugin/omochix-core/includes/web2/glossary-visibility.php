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
 *   closed; once open, only what is indexed: indexable terms, the list with
 *   10 of them, a category with 3 (gate G-C). A noindex or 404 URL is never
 *   listed.
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
 * Gate G-C (Phase 0 D-4): the list is indexed with 10 indexable terms, a
 * category with 3. One place for both numbers: the theme's robots and
 * canonical and the sitemaps here must give the same answer.
 */
if ( ! defined( 'OMOCHIX_CORE_GLOSSARY_INDEX_MIN' ) ) {
	define( 'OMOCHIX_CORE_GLOSSARY_INDEX_MIN', 10 );
}
if ( ! defined( 'OMOCHIX_CORE_GLOSSARY_CATEGORY_INDEX_MIN' ) ) {
	define( 'OMOCHIX_CORE_GLOSSARY_CATEGORY_INDEX_MIN', 3 );
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
	static $memo = array();
	$post  = is_object( $post ) ? $post : get_post( (int) $post );
	$today = null === $today ? omochix_core_glossary_today() : $today;
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
	// One page asks about the same term several times (the list, the counts, each link, the sitemap).
	// The answer depends on nothing but what is hashed here, so it is kept for the rest of the request;
	// anything that changes (a meta value, the status, the gate, the date) is a different key.
	$key = md5( serialize( array( $data, (string) ( $post->post_status ?? '' ), (string) ( $post->post_password ?? '' ), $today, omochix_core_glossary_public() ) ) );
	if ( isset( $memo[ $key ] ) ) {
		return $memo[ $key ];
	}
	if ( count( $memo ) >= 2000 ) {
		$memo = array();
	}
	$check  = omochix_core_glossary_validate_term( $data, array( 'today' => $today ) );
	$due    = omochix_core_glossary_review_due( $data );
	$review = '' === $due || strcmp( $today, $due ) > 0;
	$grace  = '' === $due ? '' : gmdate( 'Y-m-d', (int) strtotime( $due . ' 00:00:00 UTC' ) + OMOCHIX_CORE_GLOSSARY_NOINDEX_GRACE_DAYS * 86400 );
	$open   = omochix_core_glossary_public() && 'publish' === ( $post->post_status ?? '' ) && '' === (string) ( $post->post_password ?? '' );
	$ok     = $open && ! $check['errors'];
	return $memo[ $key ] = array(
		'visible'   => $ok,
		'indexable' => $ok && '' !== $grace && strcmp( $today, $grace ) <= 0,
		'review'    => $review,
		'due'       => $due,
		'errors'    => $check['errors'],
	);
}

/**
 * A term a visitor may not see has no address for that visitor.
 *
 * WordPress hands out a post's address in places no theme hook can reach:
 * wp-trackback.php/<ID> redirects to it, a 404 for a revision's ID redirects
 * to the parent's. For a hidden term that tells a visitor that the ID is a
 * glossary term, and for a published hidden one its slug. With no address
 * WordPress answers as it does for an ID that does not exist. Only glossary
 * posts are touched, trackbacks and redirects of everything else work as
 * before; editors (who may edit the term) keep every address.
 *
 * @param mixed $link Address so far.
 * @param mixed $post Post.
 * @return mixed
 */
function omochix_core_glossary_post_link( $link, $post = null ) {
	if ( ! is_object( $post ) || '' === (string) $link ) {
		return $link;
	}
	return omochix_core_glossary_is_hidden_from_user( $post ) ? '' : $link;
}
add_filter( 'post_type_link', 'omochix_core_glossary_post_link', 9999, 2 );
// Revisions are a built-in type: WordPress builds their address (/?p=<ID>) through post_link.
add_filter( 'post_link', 'omochix_core_glossary_post_link', 9999, 2 );

/**
 * Whether a post is a glossary term the current user may neither see nor edit.
 *
 * @param mixed $post Post or ID.
 * @return bool
 */
function omochix_core_glossary_is_hidden_from_user( $post ) {
	$post = is_object( $post ) ? $post : ( $post ? get_post( (int) $post ) : null );
	// A revision or an autosave stands for its term: WordPress answers for it with an address of its own.
	if ( is_object( $post ) && 'revision' === ( $post->post_type ?? '' ) && (int) ( $post->post_parent ?? 0 ) > 0 ) {
		$post = get_post( (int) $post->post_parent );
	}
	if ( ! is_object( $post ) || 'omx_glossary' !== ( $post->post_type ?? '' ) ) {
		return false;
	}
	return ! omochix_core_glossary_visibility( $post )['visible'] && ! current_user_can( 'edit_post', (int) ( $post->ID ?? 0 ) );
}

/**
 * A term takes no trackbacks, pingbacks or comments, whatever is stored for
 * it. (A hidden term that accepted a trackback would answer differently
 * from an ID that does not exist.) Only glossary posts are touched.
 *
 * @param mixed $open    Open so far.
 * @param mixed $post_id Post ID.
 * @return mixed
 */
function omochix_core_glossary_no_pings( $open, $post_id = 0 ) {
	return $post_id && 'omx_glossary' === get_post_type( is_object( $post_id ) ? $post_id : (int) $post_id ) ? false : $open;
}
add_filter( 'pings_open', 'omochix_core_glossary_no_pings', 99, 2 );
add_filter( 'comments_open', 'omochix_core_glossary_no_pings', 99, 2 );

/**
 * XML-RPC: pingback.extensions.getPingbacks answers "no such address" for
 * the address of a hidden term, exactly as it does for an address nothing
 * lives at. (Core looks the address up with its own query, which no theme
 * guard sees, and answers an empty list for any post that exists.) The
 * method keeps working for everything else.
 *
 * @param mixed $methods XML-RPC methods.
 * @return mixed
 */
function omochix_core_glossary_xmlrpc_methods( $methods ) {
	if ( is_array( $methods ) && isset( $methods['pingback.extensions.getPingbacks'] ) ) {
		$methods['pingback.extensions.getPingbacks'] = 'omochix_core_glossary_xmlrpc_get_pingbacks';
	}
	return $methods;
}
add_filter( 'xmlrpc_methods', 'omochix_core_glossary_xmlrpc_methods', 99 );

/**
 * The method itself: core's, unless the address is a hidden term's.
 *
 * @param mixed $url Address asked about.
 * @return mixed
 */
function omochix_core_glossary_xmlrpc_get_pingbacks( $url ) {
	global $wp_xmlrpc_server;
	$post_id = is_string( $url ) ? (int) url_to_postid( $url ) : 0;
	if ( is_object( $wp_xmlrpc_server ) && ! ( $post_id && omochix_core_glossary_is_hidden_from_user( $post_id ) ) ) {
		return $wp_xmlrpc_server->pingback_extensions_getPingbacks( $url );
	}
	// What core answers for an address nothing lives at, through the same filter (by default WordPress
	// blanks pingback errors there), so the two answers are the same to the byte.
	return apply_filters( 'xmlrpc_pingback_error', new IXR_Error( 32, __( 'The specified target URL does not exist.' ) ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own text and hook.
}

/**
 * REST /wp/v2/search?type=term: no glossary category for anyone but editors
 * (the category routes themselves are closed to them).
 *
 * @param mixed $args Term query arguments.
 * @return mixed
 */
function omochix_core_glossary_rest_term_search_args( $args ) {
	if ( ! is_array( $args ) || omochix_core_glossary_current_user_is_editor() ) {
		return $args;
	}
	$taxonomies = isset( $args['taxonomy'] ) ? (array) $args['taxonomy'] : array();
	if ( in_array( 'omx_glossary_category', $taxonomies, true ) ) {
		$left = array_values( array_diff( $taxonomies, array( 'omx_glossary_category' ) ) );
		if ( $left ) {
			$args['taxonomy'] = $left;
		} else {
			$args['include'] = array( 0 ); // Nothing else was asked for: find nothing.
		}
	}
	return $args;
}
add_filter( 'rest_term_search_query', 'omochix_core_glossary_rest_term_search_args' );

/**
 * REST /wp/v2/users: a glossary term does not make its author a public
 * author. (Core lists everyone with a published post of a REST post type; a
 * term can be published and hidden.)
 *
 * @param mixed $args User query arguments.
 * @return mixed
 */
function omochix_core_glossary_rest_user_args( $args ) {
	if ( is_array( $args ) && isset( $args['has_published_posts'] ) && is_array( $args['has_published_posts'] ) && ! omochix_core_glossary_current_user_is_editor() ) {
		$left = array_values( array_diff( $args['has_published_posts'], array( 'omx_glossary' ) ) );
		if ( $left ) {
			$args['has_published_posts'] = $left; // Never empty: an empty list would mean "everyone".
		} else {
			$args['include'] = array( 0 ); // Only terms were asked about: nobody is listed (?has_published_posts=omx_glossary).
		}
	}
	return $args;
}
add_filter( 'rest_user_query', 'omochix_core_glossary_rest_user_args' );

/**
 * The same for a single user (core counts the posts of every REST post type
 * to decide whether /wp/v2/users/<id> may be shown).
 *
 * @param mixed $count       Count so far.
 * @param mixed $user_id     User ID.
 * @param mixed $post_type   Post type or types counted.
 * @param mixed $public_only Whether only public posts were counted.
 * @return mixed
 */
function omochix_core_glossary_user_post_count( $count, $user_id = 0, $post_type = 'post', $public_only = false ) {
	if ( omochix_core_glossary_current_user_is_editor() ) {
		return $count;
	}
	if ( 'omx_glossary' === $post_type ) {
		return 0;
	}
	if ( is_array( $post_type ) && in_array( 'omx_glossary', $post_type, true ) ) {
		$left = array_values( array_diff( $post_type, array( 'omx_glossary' ) ) );
		return $left ? count_user_posts( (int) $user_id, $left, (bool) $public_only ) : 0;
	}
	return $count;
}
add_filter( 'get_usernumposts', 'omochix_core_glossary_user_post_count', 99, 4 );

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
 * What is indexable right now (gate G-B per term, gate G-C for the lists).
 *
 * @param bool $reset Forget the cached answer (tests).
 * @return array{ids: int[], categories: array<int, array{slug: string, count: int}>, list: bool, category_ids: int[]}
 */
function omochix_core_glossary_index_state( $reset = false ) {
	static $state = null;
	if ( $reset ) {
		$state = null;
	}
	if ( null !== $state ) {
		return $state;
	}
	$out = array(
		'ids'          => array(),
		'categories'   => array(),
		'list'         => false,
		'category_ids' => array(),
	);
	if ( ! omochix_core_glossary_public() ) {
		return $out; // Not cached: the gate is decided late in the request.
	}
	foreach ( get_posts( array( 'post_type' => 'omx_glossary', 'post_status' => 'publish', 'posts_per_page' => 500, 'has_password' => false, 'fields' => 'all', 'no_found_rows' => true, 'suppress_filters' => true ) ) as $p ) {
		if ( ! omochix_core_glossary_visibility( $p )['indexable'] ) {
			continue;
		}
		$out['ids'][] = (int) $p->ID;
		$terms        = get_the_terms( (int) $p->ID, 'omx_glossary_category' ); // From the cache get_posts() filled.
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$tid = (int) ( $t->term_id ?? 0 );
			if ( $tid ) {
				$out['categories'][ $tid ] = array(
					'slug'  => (string) $t->slug,
					'count' => 1 + ( $out['categories'][ $tid ]['count'] ?? 0 ),
				);
			}
		}
	}
	$out['list'] = count( $out['ids'] ) >= OMOCHIX_CORE_GLOSSARY_INDEX_MIN;
	foreach ( $out['categories'] as $tid => $c ) {
		if ( $c['count'] >= OMOCHIX_CORE_GLOSSARY_CATEGORY_INDEX_MIN ) {
			$out['category_ids'][] = (int) $tid;
		}
	}
	$state = $out;
	return $state;
}

/**
 * Gate G-C: whether the list of all terms (/glossary/) is indexed.
 *
 * @return bool
 */
function omochix_core_glossary_list_indexable() {
	return omochix_core_glossary_index_state()['list'];
}

/**
 * Gate G-C: whether a category page is indexed.
 *
 * @param WP_Term|object|int $term Category term or its ID.
 * @return bool
 */
function omochix_core_glossary_category_indexable( $term ) {
	$id = is_object( $term ) ? (int) ( $term->term_id ?? 0 ) : (int) $term;
	return $id > 0 && in_array( $id, omochix_core_glossary_index_state()['category_ids'], true );
}

/**
 * Sitemaps: no glossary post type while closed (core passes name => object,
 * Slim SEO a list of names; both are handled). Once open the type stays
 * listed and the query filters below decide what is in it: Slim SEO asks
 * for this list on every request, too early and too often to count terms.
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
 * Sitemaps: no glossary category taxonomy while closed. Once open, the term
 * query filter below keeps only the indexed categories.
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
	$ids              = omochix_core_glossary_index_state()['ids'];
	$args['post__in'] = $ids ? $ids : array( 0 );
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'omochix_core_glossary_sitemap_query_args', 10, 2 );

/**
 * Slim SEO sitemap (the index and each page): only indexable terms, so a
 * glossary with none has no sitemap file at all.
 *
 * @param mixed $args  Query arguments.
 * @param mixed $given Arguments the sitemap asked with (post_type).
 * @return mixed
 */
function omochix_core_glossary_slim_sitemap_post_args( $args, $given = array() ) {
	$type = is_array( $args ) ? ( $args['post_type'] ?? ( is_array( $given ) ? ( $given['post_type'] ?? '' ) : '' ) ) : '';
	return 'omx_glossary' === $type ? omochix_core_glossary_sitemap_query_args( $args, 'omx_glossary' ) : $args;
}
add_filter( 'slim_seo_sitemap_post_type_query_args', 'omochix_core_glossary_slim_sitemap_post_args', 10, 2 );

/**
 * Sitemaps: only category pages that are indexed (3 indexable terms).
 *
 * @param mixed $args     Term query arguments.
 * @param mixed $taxonomy Taxonomy name (WordPress core) or the arguments the sitemap asked with (Slim SEO).
 * @return mixed
 */
function omochix_core_glossary_sitemap_term_args( $args, $taxonomy = '' ) {
	$name = is_string( $taxonomy ) ? $taxonomy : ( is_array( $taxonomy ) ? ( $taxonomy['taxonomy'] ?? '' ) : '' );
	if ( is_array( $args ) && '' === $name ) {
		$name = $args['taxonomy'] ?? '';
	}
	if ( ! is_array( $args ) || 'omx_glossary_category' !== $name ) {
		return $args;
	}
	$ids             = omochix_core_glossary_index_state()['category_ids'];
	$args['include'] = $ids ? $ids : array( 0 );
	return $args;
}
add_filter( 'wp_sitemaps_taxonomies_query_args', 'omochix_core_glossary_sitemap_term_args', 10, 2 );
add_filter( 'slim_seo_taxonomy_query_args', 'omochix_core_glossary_sitemap_term_args', 10, 2 );

/**
 * Slim SEO lists a post type's archive URL (/glossary/) on the first page of
 * its sitemap without asking. While the list is noindex (fewer than 10
 * indexable terms) the archive has no link for the length of the sitemap
 * request, which ends when the sitemap is printed.
 *
 * @return void
 */
function omochix_core_glossary_slim_sitemap_before_output() {
	if ( ! omochix_core_glossary_list_indexable() ) {
		add_filter( 'post_type_archive_link', 'omochix_core_glossary_sitemap_no_archive_link', 99, 2 );
	}
}
add_action( 'slim_seo_sitemap_before_output', 'omochix_core_glossary_slim_sitemap_before_output' );

/**
 * No archive link for the glossary (only added while a sitemap is printed).
 *
 * @param mixed $link      Link.
 * @param mixed $post_type Post type.
 * @return mixed
 */
function omochix_core_glossary_sitemap_no_archive_link( $link, $post_type = '' ) {
	return 'omx_glossary' === $post_type ? false : $link;
}
// omochix-glossary-version: 1 (end)
