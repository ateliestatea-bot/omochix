<?php
// omochix-glossary-version: 1
/**
 * AI glossary: post type omx_glossary and taxonomy omx_glossary_category.
 *
 * Gate G-A (module switch): omochix_core_glossary_public() is true only when
 * the omochix_core_glossary_public filter returns exactly true. The theme's
 * glossary module opts in, in a reviewed release; until then both are
 * registered closed: no public URL, archive, query var, search, feed or
 * REST for visitors, and no rewrite rules. The admin screens work for
 * editors either way, so terms can be drafted and checked before opening.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gate G-A: whether the glossary is open to the public (default: no).
 *
 * @return bool
 */
function omochix_core_glossary_public() {
	return true === apply_filters( 'omochix_core_glossary_public', false );
}

/**
 * Arguments for the post type (closed unless the gate is open).
 *
 * @param bool $open Gate G-A.
 * @return array<string, mixed>
 */
function omochix_core_glossary_post_type_args( $open ) {
	$labels = array(
		'name'               => __( 'AI用語集', 'omochix-core' ),
		'singular_name'      => __( '用語', 'omochix-core' ),
		'add_new'            => __( '用語を追加', 'omochix-core' ),
		'add_new_item'       => __( '用語を追加', 'omochix-core' ),
		'edit_item'          => __( '用語を編集', 'omochix-core' ),
		'new_item'           => __( '新しい用語', 'omochix-core' ),
		'view_item'          => __( '用語を表示', 'omochix-core' ),
		'search_items'       => __( '用語を検索', 'omochix-core' ),
		'not_found'          => __( '用語はありません', 'omochix-core' ),
		'not_found_in_trash' => __( 'ゴミ箱に用語はありません', 'omochix-core' ),
		'all_items'          => __( 'すべての用語', 'omochix-core' ),
		'menu_name'          => __( 'AI用語集', 'omochix-core' ),
	);
	return array(
		'labels'              => $labels,
		'description'         => __( 'AI・テクノロジー用語の定義と解説', 'omochix-core' ),
		'public'              => $open,
		'publicly_queryable'  => $open,
		'exclude_from_search' => true, // Site search is enabled later, after the search template supports terms.
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => false,
		'show_in_admin_bar'   => false,
		'menu_position'       => 11,
		'menu_icon'           => 'dashicons-book-alt',
		'capability_type'     => array( 'omx_glossary', 'omx_glossaries' ),
		'map_meta_cap'        => true,
		'hierarchical'        => false,
		'supports'            => array( 'title', 'editor', 'revisions', 'author' ),
		'has_archive'         => $open ? 'glossary' : false,
		'rewrite'             => $open ? array(
			'slug'       => 'glossary',
			'with_front' => false,
			'feeds'      => false,
			'pages'      => false,
		) : false,
		'query_var'           => $open ? 'omx_glossary' : false,
		'can_export'          => true,
		'delete_with_user'    => false,
		'show_in_rest'        => true, // The block editor needs it; visitors are refused (glossary-visibility.php).
		'rest_base'           => 'glossary',
	);
}

/**
 * Arguments for the category taxonomy.
 *
 * @param bool $open Gate G-A.
 * @return array<string, mixed>
 */
function omochix_core_glossary_taxonomy_args( $open ) {
	return array(
		'labels'             => array(
			'name'          => __( '用語カテゴリ', 'omochix-core' ),
			'singular_name' => __( '用語カテゴリ', 'omochix-core' ),
			'menu_name'     => __( 'カテゴリ', 'omochix-core' ),
			'all_items'     => __( 'すべてのカテゴリ', 'omochix-core' ),
			'edit_item'     => __( 'カテゴリを編集', 'omochix-core' ),
			'add_new_item'  => __( 'カテゴリを追加', 'omochix-core' ),
			'not_found'     => __( 'カテゴリはありません', 'omochix-core' ),
		),
		'public'             => $open,
		'publicly_queryable' => $open,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => false,
		'show_tagcloud'      => false,
		'show_admin_column'  => true,
		'hierarchical'       => false,
		'query_var'          => $open ? 'omx_glossary_category' : false,
		'rewrite'            => $open ? array(
			'slug'         => 'glossary/category',
			'with_front'   => false,
			'hierarchical' => false,
		) : false,
		'show_in_rest'       => true,
		'rest_base'          => 'glossary-categories',
		'capabilities'       => array(
			'manage_terms' => 'manage_options',
			'edit_terms'   => 'manage_options',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'edit_omx_glossaries',
		),
	);
}

/** Register the post type. */
function omochix_core_glossary_register_post_type() {
	register_post_type( 'omx_glossary', omochix_core_glossary_post_type_args( omochix_core_glossary_public() ) );
}

/** Register the category taxonomy. */
function omochix_core_glossary_register_taxonomy() {
	$open = omochix_core_glossary_public();
	register_taxonomy( 'omx_glossary_category', array( 'omx_glossary' ), omochix_core_glossary_taxonomy_args( $open ) );
	if ( $open ) {
		// The category list must win over a single term with the slug "category".
		add_rewrite_rule( '^glossary/category/([^/]+)/?$', 'index.php?omx_glossary_category=$matches[1]', 'top' );
	}
}

/**
 * Flush rewrite rules once when the gate changes. Nothing is written while
 * the glossary has never been opened: no option, no flush.
 *
 * @return void
 */
function omochix_core_glossary_maybe_flush_rewrite_rules() {
	$want = OMOCHIX_CORE_GLOSSARY_VERSION . ':' . ( omochix_core_glossary_public() ? 'open' : 'closed' );
	$have = get_option( 'omochix_core_glossary_rewrite_version', '' );
	if ( '' === $have && ! omochix_core_glossary_public() ) {
		return;
	}
	if ( $have !== $want ) {
		update_option( 'omochix_core_glossary_rewrite_version', $want, false );
		flush_rewrite_rules( false );
	}
}
// omochix-glossary-version: 1 (end)
