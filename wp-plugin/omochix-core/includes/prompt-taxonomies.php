<?php
/**
 * Prompt Library taxonomies.
 *
 * Mirrors the ai_tool taxonomy registration pattern in taxonomies.php.
 * Starter terms are inserted via the shared omochix_core_get_initial_terms()
 * list in taxonomies.php, which loops over any taxonomy generically.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Prompt Library taxonomies.
 *
 * @return void
 */
function omochix_core_register_prompt_taxonomies() {
	$taxonomies = array(
		'prompt_category' => array(
			'singular'     => __( 'プロンプトカテゴリー', 'omochix-core' ),
			'plural'       => __( 'プロンプトカテゴリー', 'omochix-core' ),
			'hierarchical' => true,
			'rest_base'    => 'prompt-categories',
			'rewrite'      => 'prompts/category',
		),
		'prompt_model'     => array(
			'singular'     => __( '対応AI', 'omochix-core' ),
			'plural'       => __( '対応AI', 'omochix-core' ),
			'hierarchical' => false,
			'rest_base'    => 'prompt-models',
			'rewrite'      => 'prompts/model',
		),
	);

	foreach ( $taxonomies as $taxonomy => $config ) {
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

		register_taxonomy(
			$taxonomy,
			array( 'prompt' ),
			array(
				'labels'             => $labels,
				'hierarchical'       => $config['hierarchical'],
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rest_base'          => $config['rest_base'],
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => $config['rewrite'],
					'with_front' => false,
				),
			)
		);
	}

	// Keep taxonomy routes ahead of the /prompts/{slug} single rewrite rule,
	// matching the ai_tool taxonomy pattern in taxonomies.php.
	foreach ( $taxonomies as $taxonomy => $config ) {
		$slug = preg_quote( $config['rewrite'], '#' );
		add_rewrite_rule(
			'^' . $slug . '/([^/]+)/page/?([0-9]{1,})/?$',
			'index.php?' . $taxonomy . '=$matches[1]&paged=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^' . $slug . '/([^/]+)/?$',
			'index.php?' . $taxonomy . '=$matches[1]',
			'top'
		);
	}
}
