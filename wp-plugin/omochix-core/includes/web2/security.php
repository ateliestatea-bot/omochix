<?php
/**
 * Web 2.0 security layer, Core side (sec stream, docs/web2/sec/README.md).
 *
 * Narrows what WordPress itself renders; it never opens anything. Not a
 * Web 2.0 module: the Web 2.0 loader does not load it and it carries no
 * module marker. omochix-core.php requires it directly, guarded (hooks
 * stream), so it also runs while every module is off, while
 * OMOCHIX_WEB2_DISABLED is true and while no glossary code is loaded. It
 * depends on no module: no glossary function, switch or allow list.
 *
 * What it does NOT do: read request data, use the database handle, register
 * routes, options or capabilities, or print anything. With the Core plugin
 * deactivated it is not loaded and WordPress renders as it always does.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Navigation block (review N-1): the menu a block points to.
 *
 * core/navigation renders the post its "ref" (or the legacy
 * "navigationMenuId") points to: get_post( ref ) and, if that post is
 * published, its title (aria-label) and blocks. It checks neither the post
 * type nor a password, so any published post (glossary term rows left in
 * the database, a password-protected post) is printed by a page an author
 * publishes, and by the block renderer REST route for a contributor.
 *
 * A reference is accepted only as a positive whole number: an int, a whole
 * float (the block renderer REST route passes numbers as floats), or a
 * string of digits without sign, spaces or leading zero.
 *
 * @param mixed $value One reference value.
 * @return int The post ID, or 0 when the value is not a well-formed reference.
 */
function omochix_core_security_nav_ref_id( $value ) {
	if ( is_int( $value ) ) {
		return $value > 0 ? $value : 0;
	}
	if ( is_float( $value ) ) {
		return ( $value >= 1 && $value < 9007199254740992 && floor( $value ) === $value ) ? (int) $value : 0;
	}
	if ( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]{0,18}\z/', $value ) ) {
		$id = (int) $value;
		return $id > 0 && $id < PHP_INT_MAX ? $id : 0;
	}
	return 0;
}

/**
 * Whether one reference may be rendered: a published navigation menu
 * without a password. Drafts, scheduled, pending, private and trashed menus,
 * any other post type and unknown IDs are refused.
 *
 * @param mixed $value One reference value.
 * @return bool
 */
function omochix_core_security_nav_ref_allowed( $value ) {
	$id = omochix_core_security_nav_ref_id( $value );
	if ( 0 === $id ) {
		return false;
	}
	$post = get_post( $id );
	return $post instanceof WP_Post
		&& (int) $post->ID === $id
		&& 'wp_navigation' === $post->post_type
		&& 'publish' === $post->post_status
		&& '' === (string) $post->post_password;
}

/**
 * Whether a navigation block may be rendered. A block without a reference is
 * left to WordPress (its own inner blocks or its fallback). With a
 * reference, every value given, in the prepared attributes and in the stored
 * ones (WordPress drops a value of the wrong type before rendering, which
 * would show the fallback menu), must be allowed.
 *
 * @param mixed $attributes Prepared attributes.
 * @param mixed $block      WP_Block (parsed_block keeps the stored attributes).
 * @return bool
 */
function omochix_core_security_nav_render_allowed( $attributes, $block = null ) {
	$sets = array( is_array( $attributes ) ? $attributes : array() );
	if ( $block instanceof WP_Block && isset( $block->parsed_block['attrs'] ) && is_array( $block->parsed_block['attrs'] ) ) {
		$sets[] = $block->parsed_block['attrs'];
	}
	foreach ( $sets as $set ) {
		foreach ( array( 'ref', 'navigationMenuId' ) as $key ) {
			if ( array_key_exists( $key, $set ) && ! omochix_core_security_nav_ref_allowed( $set[ $key ] ) ) {
				return false;
			}
		}
	}
	return true;
}

/**
 * Wraps core/navigation's render_callback when the block type is registered:
 * a refused block renders an empty string (no fallback menu, no title).
 *
 * Measured on WordPress 7.1.4 (2026-11-27): every route that prints the
 * block (page, nested blocks, synced pattern, Query Loop, REST posts, block
 * renderer GET and POST, feeds) calls this callback; a direct
 * WP_Block::render() skips pre_render_block, and render_block_data could
 * only drop the reference, which shows the fallback menu.
 *
 * Core blocks are registered on init; this file is loaded with the plugin,
 * before that. The glossary module's own wrapper steps aside while this
 * filter is registered, so the callback is wrapped once.
 *
 * @param mixed $args Block type arguments.
 * @param mixed $name Block type name.
 * @return mixed
 */
function omochix_core_security_nav_block_args( $args, $name = '' ) {
	if ( 'core/navigation' !== $name || ! is_array( $args ) || empty( $args['render_callback'] ) || ! is_callable( $args['render_callback'] ) ) {
		return $args;
	}
	$inner                   = $args['render_callback'];
	$args['render_callback'] = static function ( $attributes, $content = '', $block = null ) use ( $inner ) {
		if ( ! omochix_core_security_nav_render_allowed( $attributes, $block ) ) {
			return '';
		}
		return call_user_func( $inner, $attributes, $content, $block );
	};
	return $args;
}
add_filter( 'register_block_type_args', 'omochix_core_security_nav_block_args', 9999, 2 );

/**
 * Navigation block without a reference: the fallback menu (review N-1, case
 * "fallback").
 *
 * A navigation block without "ref" and without inner blocks shows a
 * fallback: WP_Navigation_Fallback::get_fallback() takes the most recently
 * published wp_navigation post and prints its blocks, without checking a
 * password. So a password-protected menu reached visitors through any page
 * with a bare navigation block (and contributors through the block renderer
 * REST route).
 *
 * The same query WordPress runs is repeated (newest published menu by date);
 * if that menu, or any menu published at the same moment, is not allowed (see
 * omochix_core_security_nav_ref_allowed: password, wrong type ...), the
 * fallback is emptied and the block renders nothing. Otherwise the fallback
 * WordPress built (that menu, or the page list when there is no menu) is
 * left as it is.
 *
 * @param mixed $blocks Fallback blocks.
 * @return mixed
 */
function omochix_core_security_nav_fallback( $blocks ) {
	if ( empty( $blocks ) || ! is_array( $blocks ) || ! class_exists( 'WP_Query' ) ) {
		return $blocks;
	}
	$args  = array(
		'post_type'              => 'wp_navigation',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'order'                  => 'DESC',
		'orderby'                => 'date',
		'post_status'            => 'publish',
		'posts_per_page'         => 1,
	);
	$query = new WP_Query( $args ); // The very query WordPress runs for the fallback.
	$first = is_array( $query->posts ) && isset( $query->posts[0] ) ? $query->posts[0] : null;
	if ( null === $first ) {
		return $blocks; // No menu: WordPress shows the page list.
	}
	if ( ! $first instanceof WP_Post || ! omochix_core_security_nav_ref_allowed( (int) $first->ID ) ) {
		return array();
	}
	// Menus published at the same moment: WordPress could have taken any of them. Up to 20 are
	// checked; more than that at one moment fails closed.
	$args['posts_per_page'] = 21;
	$args['date_query']     = array(
		array(
			'after'     => $first->post_date,
			'before'    => $first->post_date,
			'inclusive' => true,
		),
	);
	$same = new WP_Query( $args );
	$list = is_array( $same->posts ) ? $same->posts : array();
	if ( count( $list ) > 20 ) {
		return array();
	}
	// The newest menu itself must be among them; if the date could not be matched (a local time that
	// does not exist, for example in a daylight-saving gap), the same-moment check failed: fail closed.
	$found = false;
	foreach ( $list as $post ) {
		if ( $post instanceof WP_Post && (int) $post->ID === (int) $first->ID ) {
			$found = true;
		}
	}
	if ( ! $found ) {
		return array();
	}
	foreach ( $list as $post ) {
		if ( ! $post instanceof WP_Post || ! omochix_core_security_nav_ref_allowed( (int) $post->ID ) ) {
			return array();
		}
	}
	return $blocks;
}
add_filter( 'block_core_navigation_render_fallback', 'omochix_core_security_nav_fallback', 9999 );

/**
 * REST: no raw blocks of a password-protected navigation menu (review N-1,
 * audit 6).
 *
 * WordPress adds the "embed" context to content.raw of wp_navigation
 * (WP_Navigation_Fallback::update_wp_navigation_post_schema), and raw
 * content has no password check: /wp/v2/navigation/<id>?context=embed and
 * the list route returned the blocks of a password-protected menu to
 * visitors. For such a menu, unless the user may edit it, content.raw and
 * content.block_version are removed and content.rendered stays empty (as
 * WordPress already makes it); content.protected stays true. The title is
 * public for every password-protected post in WordPress and is left alone.
 *
 * @param mixed $response WP_REST_Response.
 * @param mixed $post     The menu post.
 * @return mixed
 */
function omochix_core_security_nav_rest( $response, $post = null ) {
	if ( ! $post instanceof WP_Post || 'wp_navigation' !== $post->post_type || '' === (string) $post->post_password ) {
		return $response;
	}
	if ( current_user_can( 'edit_post', (int) $post->ID ) || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) ) {
		return $response;
	}
	$data = $response->get_data();
	if ( is_array( $data ) && isset( $data['content'] ) && is_array( $data['content'] ) ) {
		unset( $data['content']['raw'], $data['content']['block_version'] );
		$data['content']['rendered']  = '';
		$data['content']['protected'] = true;
		$response->set_data( $data );
	}
	return $response;
}
add_filter( 'rest_prepare_wp_navigation', 'omochix_core_security_nav_rest', 9999, 2 );

/**
 * REST search: no guessing at a password-protected menu (review N-1,
 * audit 7).
 *
 * /wp/v2/navigation?search=<text> matches the menu's content, so the answer
 * (the menu or nothing) tells a logged-in user whether a password-protected
 * menu contains that text, one guess at a time. For a user who may not edit
 * menus, a search leaves password-protected menus out of the query.
 *
 * @param mixed $args Query arguments of the navigation REST controller.
 * @return mixed
 */
function omochix_core_security_nav_rest_query( $args ) {
	if ( ! is_array( $args ) || ! isset( $args['s'] ) || '' === (string) $args['s'] ) {
		return $args;
	}
	$type = function_exists( 'get_post_type_object' ) ? get_post_type_object( 'wp_navigation' ) : null;
	$cap  = ( $type && isset( $type->cap->edit_posts ) ) ? (string) $type->cap->edit_posts : 'edit_theme_options';
	if ( ! current_user_can( $cap ) ) {
		$args['has_password'] = false;
	}
	return $args;
}
add_filter( 'rest_wp_navigation_query', 'omochix_core_security_nav_rest_query', 9999 );
