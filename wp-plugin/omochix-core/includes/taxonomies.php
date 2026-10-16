<?php
/**
 * AI tool taxonomies and starter terms.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register AI tool taxonomies.
 *
 * @return void
 */
function omochix_core_register_taxonomies() {
	$taxonomies = array(
		'ai_tool_category' => array(
			'singular'     => __( 'AIツールカテゴリー', 'omochix-core' ),
			'plural'       => __( 'AIツールカテゴリー', 'omochix-core' ),
			'hierarchical' => true,
			'rest_base'    => 'ai-tool-categories',
			'rewrite'      => 'ai-tools/category',
			'public'       => true,
		),
		'ai_tool_feature'  => array(
			'singular'     => __( '機能', 'omochix-core' ),
			'plural'       => __( '機能', 'omochix-core' ),
			'hierarchical' => false,
			'rest_base'    => 'ai-tool-features',
			'rewrite'      => 'ai-tools/features',
			'public'       => true,
		),
		'ai_tool_tag'      => array(
			'singular'     => __( '特徴タグ', 'omochix-core' ),
			'plural'       => __( '特徴タグ', 'omochix-core' ),
			'hierarchical' => false,
			'rest_base'    => 'ai-tool-tags',
			'rewrite'      => 'ai-tools/tag',
			'public'       => true,
		),
		'ai_tool_platform' => array(
			'singular'     => __( '対応環境', 'omochix-core' ),
			'plural'       => __( '対応環境', 'omochix-core' ),
			'hierarchical' => false,
			'rest_base'    => 'ai-tool-platforms',
			'rewrite'      => false,
			'public'       => false,
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
			array( 'ai_tool' ),
			array(
				'labels'            => $labels,
				'hierarchical'      => $config['hierarchical'],
				'public'            => $config['public'],
				'publicly_queryable' => $config['public'],
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => $config['rest_base'],
				'query_var'         => true,
				'rewrite'           => $config['rewrite'] ? array(
					'slug'       => $config['rewrite'],
					'with_front' => false,
				) : false,
			)
		);
	}

	// Keep taxonomy routes ahead of ai_tool attachment rules that share /ai-tools/.
	foreach ( $taxonomies as $taxonomy => $config ) {
		if ( ! $config['rewrite'] ) {
			continue;
		}

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

/**
 * Return initial taxonomy terms.
 *
 * @return array<string, array<string, string>>
 */
function omochix_core_get_initial_terms() {
	return array(
		'ai_tool_category' => array(
			'ai-chat'           => 'AIチャット',
			'writing'           => '文章生成',
			'image-generation'  => '画像生成',
			'video-generation'  => '動画生成',
			'audio-generation'  => '音声生成',
			'search'            => '検索',
			'programming'       => 'プログラミング',
			'productivity'      => '仕事効率化',
			'marketing'         => 'マーケティング',
			'sales'             => '営業',
			'design'            => 'デザイン',
			'education'         => '教育',
			'ai-agent'          => 'AIエージェント',
		),
		'ai_tool_feature'  => array(
			'writing'            => '文章作成',
			'summarization'      => '要約',
			'translation'        => '翻訳',
			'web-search'         => 'Web検索',
			'image-generation'   => '画像生成',
			'video-generation'   => '動画生成',
			'speech-recognition' => '音声認識',
			'text-to-speech'     => '音声合成',
			'code-generation'    => 'コード生成',
			'data-analysis'      => 'データ分析',
			'automation'         => '自動化',
			'api'                => 'API',
			'team-use'           => 'チーム利用',
		),
		'ai_tool_tag'      => array(
			'beginner-friendly' => '初心者向け',
			'japanese'          => '日本語対応',
			'free-plan'         => '無料プランあり',
			'commercial-use'    => '商用利用可能',
			'mobile'            => 'スマホ対応',
			'chrome-extension'  => 'Chrome拡張',
			'high-accuracy'     => '高精度',
			'fast'              => '高速',
		),
		'ai_tool_platform' => array(
			'web'              => 'Web',
			'windows'          => 'Windows',
			'macos'            => 'macOS',
			'linux'            => 'Linux',
			'ios'              => 'iOS',
			'android'          => 'Android',
			'api'              => 'API',
			'chrome-extension' => 'Chrome拡張',
			'slack'            => 'Slack',
			'discord'          => 'Discord',
		),
		'prompt_category'  => array(
			'sales'          => '営業',
			'marketing'      => 'マーケティング',
			'development'    => '開発',
			'image'          => '画像',
			'video'          => '動画',
			'writing'        => '文章',
			'documents'      => '資料',
			'productivity'   => '業務効率化',
		),
		'prompt_model'     => array(
			'chatgpt' => 'ChatGPT',
			'claude'  => 'Claude',
			'gemini'  => 'Gemini',
			'image'   => 'Image',
			'video'   => 'Video',
		),
	);
}

/**
 * Insert missing starter terms without overwriting editorial changes.
 *
 * @return void
 */
function omochix_core_insert_initial_terms() {
	foreach ( omochix_core_get_initial_terms() as $taxonomy => $terms ) {
		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
			}
		}
	}
}

/**
 * Return the ai_tool_platform term slugs that describe where a tool runs.
 *
 * Other platform terms (API, browser extensions, chat apps) are kept for
 * backward compatibility but are not shown as environments, used as filter
 * options, or emitted as an operating system.
 *
 * @return string[]
 */
function omochix_core_get_environment_platform_slugs() {
	return array( 'web', 'ios', 'android', 'macos', 'windows', 'linux' );
}

/**
 * Return the platform term slugs that are operating systems.
 *
 * "Web" is an environment but not an operating system, so it is excluded.
 *
 * @return string[]
 */
function omochix_core_get_operating_system_platform_slugs() {
	return array( 'ios', 'android', 'macos', 'windows', 'linux' );
}

/**
 * Keep only platform terms whose slug is in the given allowlist.
 *
 * Terms are matched by their stable slug, never by display name, and the
 * input order is preserved.
 *
 * @param WP_Term[]|mixed $terms         Terms from get_the_terms() or get_terms().
 * @param string[]|null   $allowed_slugs Allowed slugs. Defaults to environment slugs.
 * @return WP_Term[]
 */
function omochix_core_filter_platform_terms( $terms, $allowed_slugs = null ) {
	if ( ! is_array( $terms ) ) {
		return array();
	}

	$allowed_slugs = null === $allowed_slugs ? omochix_core_get_environment_platform_slugs() : $allowed_slugs;

	return array_values(
		array_filter(
			$terms,
			static function ( $term ) use ( $allowed_slugs ) {
				return $term instanceof WP_Term && in_array( $term->slug, $allowed_slugs, true );
			}
		)
	);
}
