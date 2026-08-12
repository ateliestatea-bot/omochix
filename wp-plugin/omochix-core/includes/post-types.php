<?php
/**
 * Custom post types.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register AI tools.
 *
 * @return void
 */
function omochix_core_register_post_types() {
	$labels = array(
		'name'                  => __( 'AIツール', 'omochix-core' ),
		'singular_name'         => __( 'AIツール', 'omochix-core' ),
		'menu_name'             => __( 'AIツール', 'omochix-core' ),
		'name_admin_bar'        => __( 'AIツール', 'omochix-core' ),
		'add_new'               => __( '新規追加', 'omochix-core' ),
		'add_new_item'          => __( 'AIツールを追加', 'omochix-core' ),
		'new_item'              => __( '新しいAIツール', 'omochix-core' ),
		'edit_item'             => __( 'AIツールを編集', 'omochix-core' ),
		'view_item'             => __( 'AIツールを表示', 'omochix-core' ),
		'all_items'             => __( 'AIツール一覧', 'omochix-core' ),
		'search_items'          => __( 'AIツールを検索', 'omochix-core' ),
		'not_found'             => __( 'AIツールが見つかりません。', 'omochix-core' ),
		'not_found_in_trash'    => __( 'ゴミ箱にAIツールはありません。', 'omochix-core' ),
		'featured_image'        => __( 'Hero画像', 'omochix-core' ),
		'set_featured_image'    => __( 'Hero画像を設定', 'omochix-core' ),
		'remove_featured_image' => __( 'Hero画像を削除', 'omochix-core' ),
		'use_featured_image'    => __( 'Hero画像として使用', 'omochix-core' ),
		'archives'              => __( 'AIツールアーカイブ', 'omochix-core' ),
	);

	register_post_type(
		'ai_tool',
		array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'rest_base'           => 'ai-tools',
			'has_archive'         => 'ai-tools',
			'rewrite'             => array(
				'slug'       => 'ai-tools',
				'with_front' => false,
			),
			'query_var'           => true,
			'exclude_from_search' => false,
			'menu_icon'           => 'dashicons-superhero',
			'menu_position'       => 6,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array(
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'revisions',
				'custom-fields',
				'author',
			),
			'template'            => array(
				array( 'core/heading', array( 'level' => 2, 'placeholder' => __( '概要', 'omochix-core' ) ) ),
				array( 'core/paragraph', array( 'placeholder' => __( 'ツールの概要を入力してください。', 'omochix-core' ) ) ),
			),
		),
	);
}
