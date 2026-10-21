<?php
/**
 * Learn Practice taxonomies (Learn Phase 4).
 *
 * Two axes are kept apart on purpose: the field (分野) is the navigation
 * axis, the format (形式) is the content axis. Field and use-case are also
 * attached to pages so the existing Learn pages can join a field hub without
 * moving. Term archives exist (for term links) but the theme marks all of
 * them noindex except use-cases with enough content; navigation goes
 * through the field hub pages.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_PRACTICE_REWRITE_VERSION = '1';

/**
 * Taxonomy configuration keyed by taxonomy name.
 *
 * @return array<string, array<string, mixed>>
 */
function omochix_core_get_practice_taxonomy_config() {
	return array(
		'learn_field'   => array(
			'singular'     => __( '分野', 'omochix-core' ),
			'plural'       => __( '分野', 'omochix-core' ),
			'hierarchical' => true,
			'rest_base'    => 'learn-fields',
			'rewrite'      => 'learn/field',
			'object_types' => array( OMOCHIX_CORE_PRACTICE_POST_TYPE, 'page' ),
		),
		'learn_format'  => array(
			'singular'     => __( '形式', 'omochix-core' ),
			'plural'       => __( '形式', 'omochix-core' ),
			'hierarchical' => false,
			'rest_base'    => 'learn-formats',
			'rewrite'      => 'learn/format',
			'object_types' => array( OMOCHIX_CORE_PRACTICE_POST_TYPE ),
		),
		'learn_usecase' => array(
			'singular'     => __( '用途', 'omochix-core' ),
			'plural'       => __( '用途', 'omochix-core' ),
			'hierarchical' => false,
			'rest_base'    => 'learn-usecases',
			'rewrite'      => 'learn/usecase',
			'object_types' => array( OMOCHIX_CORE_PRACTICE_POST_TYPE, 'page' ),
		),
		'learn_ai'      => array(
			'singular'     => __( '対応AI', 'omochix-core' ),
			'plural'       => __( '対応AI', 'omochix-core' ),
			'hierarchical' => false,
			'rest_base'    => 'learn-ai',
			'rewrite'      => 'learn/ai',
			'object_types' => array( OMOCHIX_CORE_PRACTICE_POST_TYPE ),
		),
		'learn_tech'    => array(
			'singular'     => __( '使用技術', 'omochix-core' ),
			'plural'       => __( '使用技術', 'omochix-core' ),
			'hierarchical' => false,
			'rest_base'    => 'learn-tech',
			'rewrite'      => 'learn/tech',
			'object_types' => array( OMOCHIX_CORE_PRACTICE_POST_TYPE ),
		),
	);
}

/**
 * Register the five Learn Practice taxonomies.
 *
 * @return void
 */
function omochix_core_register_practice_taxonomies() {
	foreach ( omochix_core_get_practice_taxonomy_config() as $taxonomy => $config ) {
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
			$config['object_types'],
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
}

/**
 * Starter terms. Field and format slugs are stable identifiers the theme
 * relies on (field hub pages are matched by slug, the format drives icons),
 * so they are English; the names are what editors and visitors see.
 *
 * @return array<string, array<string, string>>
 */
function omochix_core_get_practice_initial_terms() {
	return array(
		'learn_field'  => array(
			'web'        => 'Webサイト・LP制作',
			'apps'       => 'アプリ・システム開発',
			'motion'     => 'モーション・インタラクション',
			'video'      => '動画・映像制作',
			'automation' => '外部連携・自動化',
			'ai'         => 'AI入門・AI活用',
			'projects'   => '実践プロジェクト',
			'devtools'   => '開発ツール・制作環境',
		),
		'learn_format' => array(
			'project'   => '作る',
			'lesson'    => '学ぶ',
			'demo'      => '試す',
			'reference' => '調べる',
			'prompt'    => '使う',
		),
		'learn_ai'     => array(
			'claude-code' => 'Claude Code',
			'codex'       => 'Codex',
			'cursor'      => 'Cursor',
			'chatgpt'     => 'ChatGPT',
			'claude'      => 'Claude',
			'gemini'      => 'Gemini',
			'sora'        => 'Sora',
			'veo'         => 'Veo',
			'runway'      => 'Runway',
			'kling'       => 'Kling',
		),
		'learn_tech'   => array(
			'html-css'           => 'HTML / CSS',
			'javascript'         => 'JavaScript',
			'typescript'         => 'TypeScript',
			'php'                => 'PHP',
			'wordpress'          => 'WordPress',
			'nextjs'             => 'Next.js',
			'supabase'           => 'Supabase',
			'neon'               => 'Neon',
			'vercel'             => 'Vercel',
			'line-messaging-api' => 'LINE Messaging API',
			'google-apps-script' => 'Google Apps Script',
			'zapier'             => 'Zapier',
			'make'               => 'Make',
			'n8n'                => 'n8n',
		),
	);
}

/**
 * Insert missing starter terms without overwriting editorial changes.
 *
 * @return void
 */
function omochix_core_insert_practice_initial_terms() {
	foreach ( omochix_core_get_practice_initial_terms() as $taxonomy => $terms ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
			}
		}
	}
}

/**
 * The format labels in display order (also the order of the filter UI).
 *
 * @return array<string, string>
 */
function omochix_core_get_practice_format_labels() {
	return omochix_core_get_practice_initial_terms()['learn_format'];
}

/**
 * Plugin updates do not run the activation hook, so new rewrite rules and
 * starter terms would never arrive on an existing site. Flush and seed once
 * per rewrite version, the same way the theme's Learn modules do.
 *
 * @return void
 */
function omochix_core_practice_maybe_flush_rewrite_rules() {
	if ( get_option( 'omochix_core_practice_rewrite_version' ) === OMOCHIX_CORE_PRACTICE_REWRITE_VERSION ) {
		return;
	}
	omochix_core_insert_practice_initial_terms();
	flush_rewrite_rules( false );
	update_option( 'omochix_core_practice_rewrite_version', OMOCHIX_CORE_PRACTICE_REWRITE_VERSION );
}
