<?php
// omochix-industry-hub-version: 1.10.0-dev
/**
 * External resource ledger: omx_resource (Industry Hub Phase 1).
 *
 * A private register of outside content (videos, articles, courses, templates)
 * with its license basis and verification date. It has no public URL, no
 * archive, no REST route, no search presence and no body field: OmochiX shows
 * an outside resource only as a card it writes itself (summary, provider,
 * license basis, outbound link) and never copies the content.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_RESOURCE_POST_TYPE = 'omx_resource';

/**
 * Register the ledger post type.
 *
 * @return void
 */
function omochix_core_register_resource_post_type() {
	register_post_type(
		OMOCHIX_CORE_RESOURCE_POST_TYPE,
		array(
			'labels'              => array(
				'name'               => __( '外部コンテンツ台帳', 'omochix-core' ),
				'singular_name'      => __( '外部コンテンツ', 'omochix-core' ),
				'menu_name'          => __( '外部コンテンツ', 'omochix-core' ),
				'add_new'            => __( '新規登録', 'omochix-core' ),
				'add_new_item'       => __( '外部コンテンツを登録', 'omochix-core' ),
				'edit_item'          => __( '外部コンテンツを編集', 'omochix-core' ),
				'all_items'          => __( '外部コンテンツ一覧', 'omochix-core' ),
				'search_items'       => __( '外部コンテンツを検索', 'omochix-core' ),
				'not_found'          => __( '外部コンテンツは登録されていません。', 'omochix-core' ),
				'not_found_in_trash' => __( 'ゴミ箱に外部コンテンツはありません。', 'omochix-core' ),
			),
			'description'         => __( '外部の動画・記事・講座などの出典とライセンスを管理する非公開の台帳。サイト上では OmochiX が書いた紹介カードと外部リンクだけを表示する。', 'omochix-core' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => false,
			'delete_with_user'    => false,
			'menu_icon'           => 'dashicons-admin-links',
			'menu_position'       => 9,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'revisions' ),
		)
	);
}

/**
 * Defence in depth: even if a request reaches a ledger entry (a guessed
 * ?p=ID, a preview link, a future config change), visitors get a 404.
 * Editors who can edit the entry may still preview it.
 *
 * @return void
 */
function omochix_core_resource_block_front_end() {
	if ( is_admin() || ! is_singular( OMOCHIX_CORE_RESOURCE_POST_TYPE ) ) {
		return;
	}
	$post = get_queried_object();
	if ( $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}
	global $wp_query;
	if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
		$wp_query->set_404();
	}
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'omochix_core_resource_block_front_end', 0 );

/**
 * Keep the ledger out of core sitemaps and Slim SEO even if it were made
 * public by mistake.
 *
 * @param mixed $post_types Post types.
 * @return mixed
 */
function omochix_core_resource_sitemap_post_types( $post_types ) {
	if ( ! is_array( $post_types ) ) {
		return $post_types;
	}
	// Core passes name => object, Slim SEO a list of names; handle both.
	$was_list = array_is_list( $post_types );
	unset( $post_types[ OMOCHIX_CORE_RESOURCE_POST_TYPE ] );
	$post_types = array_filter(
		$post_types,
		static function ( $value ) {
			return OMOCHIX_CORE_RESOURCE_POST_TYPE !== $value;
		}
	);
	return $was_list ? array_values( $post_types ) : $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'omochix_core_resource_sitemap_post_types' );
add_filter( 'slim_seo_sitemap_post_types', 'omochix_core_resource_sitemap_post_types' );
// omochix-industry-hub-version: 1.10.0-dev (end)
