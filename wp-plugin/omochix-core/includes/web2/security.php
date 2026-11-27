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
