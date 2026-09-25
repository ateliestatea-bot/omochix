<?php
/**
 * Prompt Library custom post type.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Prompt Library post type.
 *
 * Archive: /prompts (has_archive). Single: /prompts/{slug} (rewrite slug).
 *
 * @return void
 */
function omochix_core_register_prompt_post_type() {
	$labels = array(
		'name'                  => __( 'プロンプト', 'omochix-core' ),
		'singular_name'         => __( 'プロンプト', 'omochix-core' ),
		'menu_name'             => __( 'プロンプトライブラリ', 'omochix-core' ),
		'name_admin_bar'        => __( 'プロンプト', 'omochix-core' ),
		'add_new'               => __( '新規追加', 'omochix-core' ),
		'add_new_item'          => __( 'プロンプトを追加', 'omochix-core' ),
		'new_item'              => __( '新しいプロンプト', 'omochix-core' ),
		'edit_item'             => __( 'プロンプトを編集', 'omochix-core' ),
		'view_item'             => __( 'プロンプトを表示', 'omochix-core' ),
		'all_items'             => __( 'プロンプト一覧', 'omochix-core' ),
		'search_items'          => __( 'プロンプトを検索', 'omochix-core' ),
		'not_found'             => __( 'プロンプトが見つかりません。', 'omochix-core' ),
		'not_found_in_trash'    => __( 'ゴミ箱にプロンプトはありません。', 'omochix-core' ),
		'archives'              => __( 'プロンプトアーカイブ', 'omochix-core' ),
	);

	register_post_type(
		'prompt',
		array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'rest_base'           => 'prompts',
			'has_archive'         => 'prompts',
			'rewrite'             => array(
				'slug'       => 'prompts',
				'with_front' => false,
			),
			'query_var'           => true,
			'exclude_from_search' => false,
			'menu_icon'           => 'dashicons-format-chat',
			'menu_position'       => 7,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array(
				'title',
				'editor',
				'excerpt',
				// Featured image is only used by Slim SEO as the og:image; the
				// prompt templates intentionally do not render it.
				'thumbnail',
				'revisions',
				'custom-fields',
				'author',
			),
			'template'            => array(
				array( 'core/paragraph', array( 'placeholder' => __( 'このプロンプトの説明・使いどころを入力してください。', 'omochix-core' ) ) ),
			),
		),
	);
}
