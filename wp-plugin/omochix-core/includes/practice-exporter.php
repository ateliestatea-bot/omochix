<?php
/**
 * Learn Practice JSON exporter (Phase 6A-S1).
 *
 * Writes practice posts in the importer's file shape, so a file that was
 * exported can be edited outside WordPress and imported again, and so an
 * import backup (the before-snapshots the importer hands out) can be fed
 * back through the same dry-run → apply path to restore an item.
 *
 * Shape of an exported file:
 *   { "version": 1, "exported_at": "...", "source": "export", "items": [ item, ... ] }
 * An item carries slug / title / status / excerpt / content / menu_order,
 * "meta" (every schema key except the related_*_ids, empty values left out),
 * "terms" (slug arrays per practice taxonomy) and "relations" (prompts /
 * tools / learn / practice / motion as slugs or Learn page paths). Values are
 * run through the schema sanitizers, so a file always validates and a
 * freshly exported item plans as "unchanged".
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_PRACTICE_EXPORT_VERSION = 1;

/** Relation meta key → [post type, relations key], shared with the importer. */
function omochix_core_practice_export_relation_map() {
	return array(
		'related_prompt_ids'   => array( 'prompt', 'prompts' ),
		'related_tool_ids'     => array( 'ai_tool', 'tools' ),
		'related_learn_ids'    => array( 'page', 'learn' ),
		'related_practice_ids' => array( OMOCHIX_CORE_PRACTICE_POST_TYPE, 'practice' ),
	);
}

/**
 * Post IDs → slugs the importer resolves back: a Learn page becomes its path
 * ("learn/tools/vercel"), everything else its post_name. Posts that no longer
 * exist, or are of another type, are dropped.
 *
 * @param int[]  $ids       Post IDs.
 * @param string $post_type Expected post type.
 * @return string[]
 */
function omochix_core_practice_export_relation_slugs( $ids, $post_type ) {
	$slugs = array();
	foreach ( array_map( 'absint', (array) $ids ) as $id ) {
		if ( ! $id || get_post_type( $id ) !== $post_type ) {
			continue;
		}
		$slug = 'page' === $post_type ? (string) get_page_uri( $id ) : (string) get_post_field( 'post_name', $id );
		if ( '' !== $slug ) {
			$slugs[] = $slug;
		}
	}
	return array_values( array_unique( $slugs ) );
}

/**
 * Whether a sanitized meta value counts as empty (the importer's "" / [] would
 * delete it, so an export simply leaves it out).
 *
 * @param mixed  $value Value.
 * @param string $key   Meta key.
 * @return bool
 */
function omochix_core_practice_export_is_empty( $value, $key ) {
	unset( $key ); // 0 is "missing" for every integer key (minutes 0 fails the required check, poster 0 is "no image").
	return null === $value || '' === $value || array() === $value || 0 === $value;
}

/**
 * One practice post as an importer item, from live post data.
 *
 * @param int|WP_Post $post Practice post.
 * @return array<string, mixed>|null Null when the post is not a practice item.
 */
function omochix_core_practice_export_item( $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || OMOCHIX_CORE_PRACTICE_POST_TYPE !== $post->post_type ) {
		return null;
	}
	$meta = array();
	foreach ( array_keys( omochix_core_get_practice_meta_schema() ) as $key ) {
		$meta[ $key ] = get_post_meta( $post->ID, $key, true );
	}
	$terms = array();
	foreach ( array_keys( omochix_core_get_practice_taxonomy_config() ) as $taxonomy ) {
		$current            = get_the_terms( $post->ID, $taxonomy );
		$terms[ $taxonomy ] = is_array( $current ) ? array_values( array_map( static fn( $t ) => $t->slug, $current ) ) : array();
	}
	return omochix_core_practice_export_build_item(
		array(
			'slug'    => $post->post_name,
			'post'    => array(
				'post_title'   => $post->post_title,
				'post_status'  => $post->post_status,
				'post_excerpt' => $post->post_excerpt,
				'post_content' => $post->post_content,
				'menu_order'   => (int) $post->menu_order,
			),
			'meta'    => $meta,
			'terms'   => $terms,
		),
		false
	);
}

/**
 * Build an importer item from snapshot-shaped data (post / meta / terms).
 *
 * @param array<string, mixed> $data     ['slug', 'post' => [...], 'meta' => [...], 'terms' => [...]].
 * @param bool                 $explicit True for a restore: empty meta and taxonomies are written
 *                                       out ("" / []) so the import clears what the snapshot did
 *                                       not have. False for an export: empties are left out.
 * @return array<string, mixed>
 */
function omochix_core_practice_export_build_item( array $data, $explicit ) {
	$post   = isset( $data['post'] ) && is_array( $data['post'] ) ? $data['post'] : array();
	$schema = omochix_core_get_practice_meta_schema();
	$map    = omochix_core_practice_export_relation_map();

	$item = array(
		'slug'   => sanitize_title( (string) ( $data['slug'] ?? '' ) ),
		'title'  => (string) ( $post['post_title'] ?? '' ),
		'status' => (string) ( $post['post_status'] ?? 'draft' ),
	);
	$excerpt = (string) ( $post['post_excerpt'] ?? '' );
	$content = (string) ( $post['post_content'] ?? '' );
	$order   = (int) ( $post['menu_order'] ?? 0 );
	if ( $explicit || '' !== $excerpt ) {
		$item['excerpt'] = $excerpt;
	}
	if ( $explicit || '' !== $content ) {
		$item['content'] = $content;
	}
	if ( $explicit || 0 !== $order ) {
		$item['menu_order'] = $order;
	}

	$meta      = array();
	$relations = array();
	$raw_meta  = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();
	foreach ( $schema as $key => $field ) {
		// A stored empty stays empty: sanitizing it would invent the schema default
		// (e.g. difficulty "beginner"), which the importer would then count as a change.
		$raw   = array_key_exists( $key, $raw_meta ) ? $raw_meta[ $key ] : null;
		$empty = omochix_core_practice_export_is_empty( $raw, $key ) || '0' === $raw;
		$value = $empty ? null : omochix_core_sanitize_practice_meta_value( $raw, $key );
		$empty = $empty || omochix_core_practice_export_is_empty( $value, $key );
		if ( isset( $map[ $key ] ) ) {
			$slugs = $empty ? array() : omochix_core_practice_export_relation_slugs( is_array( $value ) ? $value : array(), $map[ $key ][0] );
			if ( $explicit || $slugs ) {
				$relations[ $map[ $key ][1] ] = $slugs;
			}
			continue;
		}
		if ( 'related_motion_slugs' === $key ) {
			if ( $explicit || ! $empty ) {
				$relations['motion'] = $empty || ! is_array( $value ) ? array() : array_values( $value );
			}
			continue;
		}
		if ( $empty ) {
			if ( $explicit ) {
				$meta[ $key ] = 'array' === $field['type'] ? array() : ''; // the importer clears a field with "" / []
			}
			continue;
		}
		$meta[ $key ] = $value;
	}

	$terms     = array();
	$raw_terms = isset( $data['terms'] ) && is_array( $data['terms'] ) ? $data['terms'] : array();
	foreach ( array_keys( omochix_core_get_practice_taxonomy_config() ) as $taxonomy ) {
		$slugs = isset( $raw_terms[ $taxonomy ] ) && is_array( $raw_terms[ $taxonomy ] ) ? array_values( array_filter( array_map( 'sanitize_title', $raw_terms[ $taxonomy ] ) ) ) : array();
		if ( $explicit || $slugs ) {
			$terms[ $taxonomy ] = $slugs;
		}
	}

	if ( $meta || $explicit ) {
		$item['meta'] = $meta;
	}
	if ( $terms ) {
		$item['terms'] = $terms;
	}
	if ( $relations ) {
		$item['relations'] = $relations;
	}
	return $item;
}

/**
 * The export document for a list of practice posts.
 *
 * @param int[] $post_ids Post IDs (non-practice IDs are skipped).
 * @return array{version: int, exported_at: string, source: string, items: array<int, array<string, mixed>>}
 */
function omochix_core_practice_export( $post_ids ) {
	$items = array();
	foreach ( array_unique( array_map( 'absint', (array) $post_ids ) ) as $id ) {
		$item = $id ? omochix_core_practice_export_item( $id ) : null;
		if ( $item ) {
			$items[] = $item;
		}
	}
	return array(
		'version'     => OMOCHIX_CORE_PRACTICE_EXPORT_VERSION,
		'exported_at' => current_time( 'mysql' ),
		'source'      => 'export',
		'items'       => $items,
	);
}

/**
 * Practice post IDs for an export scope.
 *
 * @param string $scope 'all' | 'publish' | 'draft' | 'pending' | 'private' | 'future'.
 * @return int[] Ordered by menu_order, then title.
 */
function omochix_core_practice_export_ids( $scope ) {
	$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );
	$scope    = sanitize_key( (string) $scope );
	$status   = in_array( $scope, $statuses, true ) ? $scope : $statuses;
	$ids      = get_posts(
		array(
			'post_type'      => OMOCHIX_CORE_PRACTICE_POST_TYPE,
			'post_status'    => $status,
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return array_values( array_map( 'intval', (array) $ids ) );
}

/**
 * Import backup JSON (the before-snapshots from omochix_core_practice_import_snapshot())
 * as importer items. A restore is explicit: every meta key and taxonomy of
 * the snapshot is written, so fields the snapshot did not have are cleared.
 *
 * @param array<int, array<string, mixed>> $backups Snapshot list.
 * @return array<int, array<string, mixed>> Items (snapshots without a slug are skipped).
 */
function omochix_core_practice_export_backup_to_items( array $backups ) {
	$items = array();
	foreach ( $backups as $backup ) {
		if ( ! is_array( $backup ) || empty( $backup['slug'] ) || ! is_string( $backup['slug'] ) ) {
			continue;
		}
		$items[] = omochix_core_practice_export_build_item( $backup, true );
	}
	return $items;
}

/**
 * Whether decoded JSON is an import backup rather than an items file: a list
 * of snapshots (or {"backups": [...]}) whose entries carry slug + meta + terms.
 *
 * @param mixed $decoded Decoded JSON.
 * @return array<int, array<string, mixed>>|null The snapshot list, or null.
 */
function omochix_core_practice_export_detect_backup( $decoded ) {
	if ( ! is_array( $decoded ) ) {
		return null;
	}
	$list = isset( $decoded['backups'] ) && is_array( $decoded['backups'] ) ? $decoded['backups'] : $decoded;
	if ( ! $list || array_keys( $list ) !== range( 0, count( $list ) - 1 ) ) {
		return null;
	}
	foreach ( $list as $entry ) {
		if ( ! is_array( $entry ) || ! array_key_exists( 'slug', $entry ) || ! isset( $entry['meta'] ) || ! is_array( $entry['meta'] ) || ! isset( $entry['terms'] ) || ! is_array( $entry['terms'] ) ) {
			return null;
		}
	}
	return $list;
}
