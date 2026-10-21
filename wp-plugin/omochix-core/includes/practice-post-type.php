<?php
/**
 * Learn Practice custom post type (Learn Phase 4).
 *
 * A practice item is a hands-on tutorial / project / demo / reference that
 * carries structured data (prompts, code, steps, verification, sources) on
 * top of the usual title / excerpt / body. Archive: /learn/practice
 * (has_archive). Single: /learn/practice/{slug}. The slug nests under the
 * "learn" page on purpose: it does not overlap the theme's
 * learn/motion/{slug} rewrite, and no "learn/practice" page must ever be
 * created (its page rules would compete with this post type).
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_PRACTICE_POST_TYPE = 'learn_practice';
const OMOCHIX_CORE_PRACTICE_SLUG      = 'learn/practice';

/**
 * Register the Learn Practice post type.
 *
 * @return void
 */
function omochix_core_register_practice_post_type() {
	$labels = array(
		'name'               => __( '実践教材', 'omochix-core' ),
		'singular_name'      => __( '実践教材', 'omochix-core' ),
		'menu_name'          => __( 'Learn 実践教材', 'omochix-core' ),
		'name_admin_bar'     => __( '実践教材', 'omochix-core' ),
		'add_new'            => __( '新規追加', 'omochix-core' ),
		'add_new_item'       => __( '実践教材を追加', 'omochix-core' ),
		'new_item'           => __( '新しい実践教材', 'omochix-core' ),
		'edit_item'          => __( '実践教材を編集', 'omochix-core' ),
		'view_item'          => __( '実践教材を表示', 'omochix-core' ),
		'all_items'          => __( '実践教材一覧', 'omochix-core' ),
		'search_items'       => __( '実践教材を検索', 'omochix-core' ),
		'not_found'          => __( '実践教材が見つかりません。', 'omochix-core' ),
		'not_found_in_trash' => __( 'ゴミ箱に実践教材はありません。', 'omochix-core' ),
		'archives'           => __( '実践教材アーカイブ', 'omochix-core' ),
	);

	register_post_type(
		OMOCHIX_CORE_PRACTICE_POST_TYPE,
		array(
			'labels'              => $labels,
			'description'         => __( '見る → 学ぶ → 試す → 組み合わせる → AIで実装する → 検証する、を 1 ページで完結させる教材。', 'omochix-core' ),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'rest_base'           => 'learn-practice',
			'has_archive'         => OMOCHIX_CORE_PRACTICE_SLUG,
			'rewrite'             => array(
				'slug'       => OMOCHIX_CORE_PRACTICE_SLUG,
				'with_front' => false,
			),
			'query_var'           => true,
			'exclude_from_search' => false,
			'menu_icon'           => 'dashicons-welcome-learn-more',
			'menu_position'       => 8,
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
				array( 'core/paragraph', array( 'placeholder' => __( '補足説明（任意）。手順・プロンプト・コードは下の各パネルに入力します。', 'omochix-core' ) ) ),
			),
		)
	);
}
