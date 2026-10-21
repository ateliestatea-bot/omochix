<?php
/**
 * Learn Practice JSON importer: the logic (Learn Phase 4C).
 *
 * Reads {"version": 1, "items": [ {...} ]}, validates every item against the
 * practice meta contract, lints every string for secrets, resolves terms
 * and relations, and computes a plan (create / update / unchanged with
 * field-level changes) without touching the database. Applying the plan
 * writes only the fields the file carries: a key that is absent or null is
 * left as it is, "" / [] clears. Items are matched by slug, so re-running
 * the same file is a no-op and never creates a duplicate.
 *
 * The admin page (admin/practice-json-importer.php) wraps this with the
 * upload, the dry-run → re-upload SHA-256 lock, the confirmation and the
 * log. Everything here is also callable from tests and CLI scripts.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_PRACTICE_IMPORT_MAX_BYTES = 5242880;
const OMOCHIX_CORE_PRACTICE_IMPORT_MAX_ITEMS = 200;
const OMOCHIX_CORE_PRACTICE_IMPORT_LOG_OPTION = 'omochix_core_practice_import_log';
const OMOCHIX_CORE_PRACTICE_IMPORT_LOG_MAX    = 20;

/**
 * Secret-looking patterns. A match anywhere in the file is an error: the
 * public content must only ever carry placeholders such as ＜ここに貼る：…＞.
 *
 * @return array<string, string> label => regex
 */
function omochix_core_practice_import_secret_patterns() {
	return array(
		'OpenAI / generic sk- key'      => '/\bsk-[A-Za-z0-9_-]{20,}\b/',
		'Google API key'                => '/\bAIza[0-9A-Za-z_-]{30,}\b/',
		'GitHub token'                  => '/\b(ghp|gho|ghu|ghs|ghr|github_pat)_[A-Za-z0-9_]{20,}\b/',
		'AWS access key'                => '/\bAKIA[0-9A-Z]{16}\b/',
		'Slack token'                   => '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
		'Slack / Discord webhook URL'   => '#https://(hooks\.slack\.com/services/|discord(app)?\.com/api/webhooks/)[^\s"\']+#',
		'Private key block'             => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
		'JWT'                           => '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/',
		'LINE channel access token'     => '/\b[A-Za-z0-9+\/]{120,}={0,2}\b/',
		'Assigned secret value'         => '/\b(api[_-]?key|secret|token|password|passwd)\b\s*[:=]\s*["\']?[A-Za-z0-9_\-\/+]{24,}["\']?/i',
	);
}

/**
 * Walk a decoded JSON value and collect secret-looking strings.
 *
 * @param mixed  $value    Any decoded value.
 * @param string $path     JSON path for the report.
 * @param array  $findings Collected ['path' => …, 'label' => …] rows (by reference).
 * @return void
 */
function omochix_core_practice_import_lint_secrets( $value, $path, &$findings ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $child ) {
			omochix_core_practice_import_lint_secrets( $child, $path . '.' . $key, $findings );
		}
		return;
	}
	if ( ! is_string( $value ) || '' === $value ) {
		return;
	}
	foreach ( omochix_core_practice_import_secret_patterns() as $label => $pattern ) {
		if ( preg_match( $pattern, $value, $m ) ) {
			$findings[] = array( 'path' => ltrim( $path, '.' ), 'label' => $label, 'sample' => mb_substr( $m[0], 0, 12 ) . '…' );
		}
	}
}

/**
 * Decode the file contents into the item list.
 *
 * @param string $contents Raw JSON.
 * @return array<string, mixed>|WP_Error {version, items}
 */
function omochix_core_practice_import_decode( $contents ) {
	if ( ! is_string( $contents ) || '' === trim( $contents ) ) {
		return new WP_Error( 'empty_file', __( 'JSONが空です。', 'omochix-core' ) );
	}
	if ( strlen( $contents ) > OMOCHIX_CORE_PRACTICE_IMPORT_MAX_BYTES ) {
		return new WP_Error( 'file_size', __( 'JSONは5MB以下にしてください。', 'omochix-core' ) );
	}
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $contents, 'UTF-8' ) ) {
		return new WP_Error( 'encoding', __( 'JSONがUTF-8ではありません。', 'omochix-core' ) );
	}
	$decoded = json_decode( $contents, true );
	if ( null === $decoded || JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'invalid_json', sprintf( __( 'JSONの構文が正しくありません：%s', 'omochix-core' ), json_last_error_msg() ) );
	}
	if ( ! is_array( $decoded ) || ! isset( $decoded['items'] ) || ! is_array( $decoded['items'] ) ) {
		return new WP_Error( 'invalid_shape', __( 'JSONのトップレベルに "items" 配列が必要です（例：{"version": 1, "items": [ {"slug": "…", "title": "…"} ]}）。', 'omochix-core' ) );
	}
	if ( isset( $decoded['version'] ) && 1 !== (int) $decoded['version'] ) {
		return new WP_Error( 'version', __( '"version" は 1 だけに対応しています。', 'omochix-core' ) );
	}
	if ( ! $decoded['items'] ) {
		return new WP_Error( 'no_rows', __( '"items" 配列が空です。', 'omochix-core' ) );
	}
	if ( count( $decoded['items'] ) > OMOCHIX_CORE_PRACTICE_IMPORT_MAX_ITEMS ) {
		return new WP_Error( 'too_many_rows', sprintf( __( '1回の上限%d件を超えています。ファイルを分割してください。', 'omochix-core' ), OMOCHIX_CORE_PRACTICE_IMPORT_MAX_ITEMS ) );
	}
	return $decoded;
}

/** Existing practice posts by slug (any status except trash): slug => post ID. */
function omochix_core_practice_import_existing() {
	$posts = get_posts(
		array(
			'post_type'      => OMOCHIX_CORE_PRACTICE_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$map = array();
	foreach ( $posts as $post_id ) {
		$map[ get_post_field( 'post_name', $post_id ) ] = (int) $post_id;
	}
	return $map;
}

/**
 * Resolve slugs / paths to post IDs for a relation. Unknown ones are reported.
 *
 * @param string[] $slugs     Slugs or Learn page paths.
 * @param string   $post_type prompt | ai_tool | page | learn_practice.
 * @return array{ids: int[], unknown: string[]}
 */
function omochix_core_practice_import_resolve( $slugs, $post_type ) {
	$ids = array();
	$unknown = array();
	foreach ( (array) $slugs as $slug ) {
		$slug = is_string( $slug ) ? trim( $slug ) : '';
		if ( '' === $slug ) {
			continue;
		}
		if ( 'page' === $post_type ) {
			$post = get_page_by_path( ltrim( $slug, '/' ), OBJECT, 'page' );
			$id   = $post instanceof WP_Post ? (int) $post->ID : 0;
		} else {
			$found = post_type_exists( $post_type ) ? get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'name' => sanitize_title( $slug ), 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) ) : array();
			$id    = $found ? (int) $found[0] : 0;
		}
		if ( $id ) {
			$ids[] = $id;
		} else {
			$unknown[] = $slug;
		}
	}
	return array( 'ids' => array_values( array_unique( $ids ) ), 'unknown' => $unknown );
}

/**
 * Validate and normalize one item. Returns the normalized item (or null)
 * with its errors and warnings. Nothing is written.
 *
 * @param mixed $raw  Decoded item.
 * @param int   $line 1-based position in the file.
 * @return array{item: array<string, mixed>|null, errors: string[], warnings: string[]}
 */
function omochix_core_practice_import_validate_item( $raw, $line, $file_slugs = array() ) {
	$errors   = array();
	$warnings = array();
	$prefix   = sprintf( __( '%d件目', 'omochix-core' ), $line );
	if ( ! is_array( $raw ) ) {
		return array( 'item' => null, 'errors' => array( $prefix . '：' . __( 'オブジェクトではありません。', 'omochix-core' ) ), 'warnings' => array() );
	}
	$allowed_top = array( 'slug', 'title', 'status', 'excerpt', 'content', 'menu_order', 'meta', 'terms', 'relations' );
	foreach ( array_diff( array_keys( $raw ), $allowed_top ) as $key ) {
		$errors[] = $prefix . '：' . sprintf( __( '不明なキー "%s" があります。', 'omochix-core' ), $key );
	}
	$slug = isset( $raw['slug'] ) && is_scalar( $raw['slug'] ) ? sanitize_title( (string) $raw['slug'] ) : '';
	if ( '' === $slug || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) || strlen( $slug ) > 120 ) {
		$errors[] = $prefix . '：' . __( 'slug は英小文字・数字・ハイフンで必須です。', 'omochix-core' );
	}
	$item = array( 'slug' => $slug, 'line' => $line, 'post' => array(), 'meta' => array(), 'terms' => array(), 'relations' => array(), 'relation_meta' => array(), 'pending_practice' => array() );
	if ( array_key_exists( 'title', $raw ) && null !== $raw['title'] ) {
		$title = is_string( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '';
		if ( '' === $title ) {
			$errors[] = $prefix . '：' . __( 'title が空です。', 'omochix-core' );
		}
		$item['post']['post_title'] = $title;
	}
	if ( array_key_exists( 'status', $raw ) && null !== $raw['status'] ) {
		$status = is_string( $raw['status'] ) ? sanitize_key( $raw['status'] ) : '';
		if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private' ), true ) ) {
			$errors[] = $prefix . '：' . __( 'status は draft / pending / publish / private のいずれかです。', 'omochix-core' );
		}
		$item['post']['post_status'] = $status;
	}
	if ( array_key_exists( 'excerpt', $raw ) && null !== $raw['excerpt'] ) {
		$item['post']['post_excerpt'] = is_string( $raw['excerpt'] ) ? sanitize_textarea_field( $raw['excerpt'] ) : '';
	}
	if ( array_key_exists( 'content', $raw ) && null !== $raw['content'] ) {
		$item['post']['post_content'] = is_string( $raw['content'] ) ? wp_kses_post( $raw['content'] ) : '';
	}
	if ( array_key_exists( 'menu_order', $raw ) && null !== $raw['menu_order'] ) {
		$item['post']['menu_order'] = (int) $raw['menu_order'];
	}

	// Meta: only known keys; rows must be arrays (not JSON strings) in a file.
	$schema = omochix_core_get_practice_meta_schema();
	$meta   = isset( $raw['meta'] ) && is_array( $raw['meta'] ) ? $raw['meta'] : array();
	if ( isset( $raw['meta'] ) && ! is_array( $raw['meta'] ) ) {
		$errors[] = $prefix . '：' . __( '"meta" はオブジェクトです。', 'omochix-core' );
	}
	foreach ( $meta as $key => $value ) {
		if ( ! isset( $schema[ $key ] ) ) {
			$errors[] = $prefix . '：' . sprintf( __( 'meta に不明なキー "%s" があります。', 'omochix-core' ), $key );
			continue;
		}
		if ( 'post_id_array' === $schema[ $key ]['sanitize'] ) {
			$errors[] = $prefix . '：' . sprintf( __( '"%s" は meta ではなく relations（slug 指定）で書いてください。', 'omochix-core' ), $key );
			continue;
		}
		if ( null === $value ) {
			continue; // null: leave as it is
		}
		if ( in_array( $schema[ $key ]['sanitize'], array( 'json_rows', 'string_array', 'slug_array' ), true ) && ! is_array( $value ) ) {
			$errors[] = $prefix . '：' . sprintf( __( '"%s" は配列です。', 'omochix-core' ), $key );
			continue;
		}
		if ( 'json_rows' === $schema[ $key ]['sanitize'] && count( $value ) > $schema[ $key ]['max'] ) {
			$errors[] = $prefix . '：' . sprintf( __( '"%1$s" は最大 %2$d 件です。', 'omochix-core' ), $key, $schema[ $key ]['max'] );
			continue;
		}
		$clean = omochix_core_sanitize_practice_meta_value( $value, $key );
		if ( 'practice_verified_at' === $key && is_string( $value ) && '' !== $value && '' === $clean ) {
			$errors[] = $prefix . '：' . __( 'practice_verified_at は YYYY-MM-DD の実在する日付です。', 'omochix-core' );
			continue;
		}
		if ( 'practice_preview_poster' === $key && (int) $value > 0 && 0 === $clean ) {
			$errors[] = $prefix . '：' . __( 'practice_preview_poster はメディアライブラリの画像 ID です。', 'omochix-core' );
			continue;
		}
		if ( 'json_rows' === $schema[ $key ]['sanitize'] && $value && ! $clean ) {
			$warnings[] = $prefix . '：' . sprintf( __( '"%s" の行はすべて空として扱われました。', 'omochix-core' ), $key );
		}
		$item['meta'][ $key ] = $clean;
	}

	// Terms: every slug must exist (never auto-created, like the prompt importer).
	$terms = isset( $raw['terms'] ) && is_array( $raw['terms'] ) ? $raw['terms'] : array();
	if ( isset( $raw['terms'] ) && ! is_array( $raw['terms'] ) ) {
		$errors[] = $prefix . '：' . __( '"terms" はオブジェクトです。', 'omochix-core' );
	}
	$practice_taxonomies = array_keys( omochix_core_get_practice_taxonomy_config() );
	foreach ( $terms as $taxonomy => $slugs ) {
		if ( ! in_array( $taxonomy, $practice_taxonomies, true ) ) {
			$errors[] = $prefix . '：' . sprintf( __( 'terms に不明なタクソノミー "%s" があります。', 'omochix-core' ), $taxonomy );
			continue;
		}
		if ( null === $slugs ) {
			continue;
		}
		if ( ! is_array( $slugs ) ) {
			$errors[] = $prefix . '：' . sprintf( __( 'terms.%s は配列です。', 'omochix-core' ), $taxonomy );
			continue;
		}
		$clean = array();
		foreach ( $slugs as $term_slug ) {
			$term_slug = is_string( $term_slug ) ? sanitize_title( $term_slug ) : '';
			if ( '' === $term_slug ) {
				continue;
			}
			if ( ! term_exists( $term_slug, $taxonomy ) ) {
				$errors[] = $prefix . '：' . sprintf( __( 'terms.%1$s の "%2$s" は存在しません（管理画面で先に作成してください）。', 'omochix-core' ), $taxonomy, $term_slug );
				continue;
			}
			$clean[] = $term_slug;
		}
		$item['terms'][ $taxonomy ] = array_values( array_unique( $clean ) );
	}

	// Relations: slugs → IDs; unknown ones are warnings (dropped), as in the prompt importer.
	$relations = isset( $raw['relations'] ) && is_array( $raw['relations'] ) ? $raw['relations'] : array();
	if ( isset( $raw['relations'] ) && ! is_array( $raw['relations'] ) ) {
		$errors[] = $prefix . '：' . __( '"relations" はオブジェクトです。', 'omochix-core' );
	}
	$relation_map = array( 'prompts' => array( 'prompt', 'related_prompt_ids' ), 'tools' => array( 'ai_tool', 'related_tool_ids' ), 'learn' => array( 'page', 'related_learn_ids' ), 'practice' => array( OMOCHIX_CORE_PRACTICE_POST_TYPE, 'related_practice_ids' ) );
	foreach ( $relations as $kind => $slugs ) {
		if ( 'motion' === $kind ) {
			if ( null === $slugs ) {
				continue;
			}
			if ( ! is_array( $slugs ) ) {
				$errors[] = $prefix . '：' . __( 'relations.motion は配列です。', 'omochix-core' );
				continue;
			}
			$motion = omochix_core_sanitize_practice_meta_value( $slugs, 'related_motion_slugs' );
			if ( function_exists( 'omochix_learn_gallery_get_item' ) ) {
				// With the gallery loaded, unknown catalog slugs are dropped (warned); without it they are kept as given.
				$known = array();
				foreach ( $motion as $motion_slug ) {
					if ( omochix_learn_gallery_get_item( $motion_slug ) ) {
						$known[] = $motion_slug;
					} else {
						$warnings[] = $prefix . '：' . sprintf( __( 'relations.motion の "%s" はカタログにありません（無視されます）。', 'omochix-core' ), $motion_slug );
					}
				}
				$motion = $known;
			}
			$item['meta']['related_motion_slugs'] = $motion;
			continue;
		}
		if ( ! isset( $relation_map[ $kind ] ) ) {
			$errors[] = $prefix . '：' . sprintf( __( 'relations に不明な種類 "%s" があります（prompts / tools / learn / practice / motion）。', 'omochix-core' ), $kind );
			continue;
		}
		if ( null === $slugs ) {
			continue;
		}
		if ( ! is_array( $slugs ) ) {
			$errors[] = $prefix . '：' . sprintf( __( 'relations.%s は配列です。', 'omochix-core' ), $kind );
			continue;
		}
		$resolved = omochix_core_practice_import_resolve( $slugs, $relation_map[ $kind ][0] );
		foreach ( $resolved['unknown'] as $unknown ) {
			if ( 'practice' === $kind && in_array( sanitize_title( $unknown ), $file_slugs, true ) ) {
				$item['pending_practice'][] = sanitize_title( $unknown ); // created by this same file: linked after the posts exist
				continue;
			}
			$warnings[] = $prefix . '：' . sprintf( __( 'relations.%1$s の "%2$s" が見つかりません（無視されます）。', 'omochix-core' ), $kind, $unknown );
		}
		$item['relation_meta'][ $relation_map[ $kind ][1] ] = $resolved['ids'];
	}

	return array( 'item' => $errors ? null : $item, 'errors' => $errors, 'warnings' => $warnings );
}

/**
 * Whether a stored meta value equals a planned one. Scalars come back from
 * the database as strings (240 vs "240"), arrays keep their types.
 *
 * @param mixed $before Stored value.
 * @param mixed $after  Planned value.
 * @return bool
 */
function omochix_core_practice_import_same( $before, $after ) {
	if ( is_array( $before ) || is_array( $after ) ) {
		return is_array( $before ) && is_array( $after ) && wp_json_encode( $before ) === wp_json_encode( $after );
	}
	return (string) $before === (string) $after;
}

/** Short display form of a value for the diff table. */
function omochix_core_practice_import_format_value( $value ) {
	if ( is_array( $value ) ) {
		$json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return mb_strlen( (string) $json ) > 80 ? mb_substr( (string) $json, 0, 80 ) . '…' : (string) $json;
	}
	$text = (string) $value;
	return mb_strlen( $text ) > 80 ? mb_substr( $text, 0, 80 ) . '…' : $text;
}

/**
 * Build the plan for a file: validated rows with their action and changes.
 * Nothing is written.
 *
 * @param string $contents Raw JSON.
 * @return array<string, mixed>|WP_Error {rows, errors, warnings, lint, hash, summary}
 */
function omochix_core_practice_import_plan( $contents ) {
	$decoded = omochix_core_practice_import_decode( $contents );
	if ( is_wp_error( $decoded ) ) {
		return $decoded;
	}
	$hash     = hash( 'sha256', $contents );
	$lint     = array();
	omochix_core_practice_import_lint_secrets( $decoded['items'], 'items', $lint );
	$existing = omochix_core_practice_import_existing();
	$rows     = array();
	$errors   = array();
	$warnings = array();
	$seen     = array();
	$line     = 0;
	$file_slugs = array();
	foreach ( $decoded['items'] as $raw ) {
		if ( is_array( $raw ) && isset( $raw['slug'] ) && is_scalar( $raw['slug'] ) ) {
			$file_slugs[] = sanitize_title( (string) $raw['slug'] );
		}
	}
	foreach ( $decoded['items'] as $raw ) {
		++$line;
		$validated = omochix_core_practice_import_validate_item( $raw, $line, $file_slugs );
		$errors    = array_merge( $errors, $validated['errors'] );
		$warnings  = array_merge( $warnings, $validated['warnings'] );
		$item      = $validated['item'];
		if ( ! $item ) {
			continue;
		}
		if ( isset( $seen[ $item['slug'] ] ) ) {
			$errors[] = sprintf( __( '%1$d件目：slug「%2$s」が%3$d件目と重複しています。', 'omochix-core' ), $line, $item['slug'], $seen[ $item['slug'] ] );
			continue;
		}
		$seen[ $item['slug'] ] = $line;
		$post_id = $existing[ $item['slug'] ] ?? 0;
		$changes = array();
		if ( ! $post_id ) {
			if ( empty( $item['post']['post_title'] ) ) {
				$errors[] = sprintf( __( '%d件目：新規作成には title が必要です。', 'omochix-core' ), $line );
				continue;
			}
			$item['post']['post_status'] = $item['post']['post_status'] ?? 'draft';
		}
		// Publishing requires every required meta (after this import) to be present.
		$wants_publish = isset( $item['post']['post_status'] ) && 'publish' === $item['post']['post_status'];
		if ( $wants_publish ) {
			$missing = array();
			foreach ( omochix_core_get_practice_required_meta() as $key ) {
				$value = array_key_exists( $key, $item['meta'] ) ? $item['meta'][ $key ] : ( $post_id ? get_post_meta( $post_id, $key, true ) : '' );
				if ( '' === $value || null === $value || array() === $value || 0 === $value || '0' === $value ) {
					$missing[] = $key;
				}
			}
			if ( $missing ) {
				$errors[] = sprintf( __( '%1$d件目：publish には必須項目が足りません：%2$s', 'omochix-core' ), $line, implode( ', ', $missing ) );
				continue;
			}
		}
		foreach ( $item['post'] as $field => $value ) {
			$before = $post_id ? (string) get_post_field( $field, $post_id ) : '';
			if ( ! $post_id || (string) $value !== $before ) {
				if ( 'post_status' === $field ) {
					// Visibility changes are called out explicitly so a file never publishes or unpublishes a post unnoticed.
					if ( 'publish' === $value && 'publish' !== $before ) {
						$warnings[] = sprintf( __( '%1$d件目：この反映で「%2$s」が公開されます（status: publish）。', 'omochix-core' ), $line, $item['slug'] );
					} elseif ( 'publish' === $before && 'publish' !== $value ) {
						$warnings[] = sprintf( __( '%1$d件目：公開中の「%2$s」が「%3$s」になり、公開が取り下げられます。', 'omochix-core' ), $line, $item['slug'], $value );
					}
				}
				$changes[] = array( 'field' => $field, 'before' => omochix_core_practice_import_format_value( $before ), 'after' => omochix_core_practice_import_format_value( $value ) );
			}
		}
		foreach ( array_merge( $item['meta'], $item['relation_meta'] ) as $key => $value ) {
			$before = $post_id ? get_post_meta( $post_id, $key, true ) : '';
			$before = ( '' === $before && is_array( $value ) ) ? array() : $before;
			if ( ! $post_id || ! omochix_core_practice_import_same( $before, $value ) ) {
				$changes[] = array( 'field' => $key, 'before' => omochix_core_practice_import_format_value( $before ), 'after' => omochix_core_practice_import_format_value( $value ) );
			}
		}
		foreach ( $item['terms'] as $taxonomy => $slugs ) {
			$before = array();
			if ( $post_id ) {
				$current = get_the_terms( $post_id, $taxonomy );
				$before  = is_array( $current ) ? array_values( array_map( static fn( $t ) => $t->slug, $current ) ) : array();
				sort( $before );
			}
			$after = $slugs;
			sort( $after );
			if ( ! $post_id || $before !== $after ) {
				$changes[] = array( 'field' => 'terms.' . $taxonomy, 'before' => omochix_core_practice_import_format_value( $before ), 'after' => omochix_core_practice_import_format_value( $after ) );
			}
		}
		$rows[] = array(
			'slug'    => $item['slug'],
			'line'    => $line,
			'post_id' => $post_id,
			'action'  => ! $post_id ? 'create' : ( $changes ? 'update' : 'unchanged' ),
			'changes' => $changes,
			'item'    => $item,
		);
	}
	foreach ( $lint as $finding ) {
		$errors[] = sprintf( __( '秘密情報の疑い：%1$s（%2$s、"%3$s"）。公開コンテンツにはプレースホルダー（＜ここに貼る：…＞）だけを書いてください。', 'omochix-core' ), $finding['path'], $finding['label'], $finding['sample'] );
	}
	$count = static function ( $action ) use ( $rows ) {
		return count( array_filter( $rows, static fn( $r ) => $action === $r['action'] ) );
	};
	return array(
		'rows'     => $rows,
		'errors'   => $errors,
		'warnings' => $warnings,
		'lint'     => $lint,
		'hash'     => $hash,
		'summary'  => array( 'total' => count( $decoded['items'] ), 'create' => $count( 'create' ), 'update' => $count( 'update' ), 'unchanged' => $count( 'unchanged' ), 'error_rows' => max( 0, count( $decoded['items'] ) - count( $rows ) ) ),
	);
}

/** Full before-snapshot of a practice post for the downloadable rollback JSON. */
function omochix_core_practice_import_snapshot( $post_id ) {
	$post = get_post( $post_id );
	$meta = array();
	foreach ( array_keys( omochix_core_get_practice_meta_schema() ) as $key ) {
		$meta[ $key ] = get_post_meta( $post_id, $key, true );
	}
	$terms = array();
	foreach ( array_keys( omochix_core_get_practice_taxonomy_config() ) as $taxonomy ) {
		$current           = get_the_terms( $post_id, $taxonomy );
		$terms[ $taxonomy ] = is_array( $current ) ? array_values( array_map( static fn( $t ) => $t->slug, $current ) ) : array();
	}
	return array(
		'post_id'     => (int) $post_id,
		'slug'        => $post ? $post->post_name : '',
		'post'        => $post ? array( 'post_title' => $post->post_title, 'post_status' => $post->post_status, 'post_excerpt' => $post->post_excerpt, 'post_content' => $post->post_content, 'menu_order' => (int) $post->menu_order ) : array(),
		'meta'        => $meta,
		'terms'       => $terms,
		'snapshot_at' => current_time( 'mysql' ),
	);
}

/**
 * Apply a plan. Only the rows' carried fields are written. Returns counts,
 * failures and the before-snapshots of updated posts.
 *
 * @param array<int, array<string, mixed>> $rows Plan rows.
 * @return array{created: int, updated: int, unchanged: int, failed: array, backups: array, slugs: array}
 */
function omochix_core_practice_import_execute( $rows ) {
	$result  = array( 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => array(), 'backups' => array(), 'slugs' => array() );
	$written = array();
	foreach ( $rows as $row ) {
		$item = $row['item'];
		if ( 'unchanged' === $row['action'] ) {
			++$result['unchanged'];
			continue;
		}
		$post_id = (int) $row['post_id'];
		if ( $post_id ) {
			$result['backups'][] = omochix_core_practice_import_snapshot( $post_id );
			if ( $item['post'] ) {
				$updated = wp_update_post( array_merge( array( 'ID' => $post_id ), wp_slash( $item['post'] ) ), true );
				if ( is_wp_error( $updated ) ) {
					$result['failed'][] = array( 'slug' => $row['slug'], 'reason' => $updated->get_error_message() );
					continue;
				}
			}
		} else {
			$insert  = array_merge( array( 'post_type' => OMOCHIX_CORE_PRACTICE_POST_TYPE, 'post_name' => $row['slug'], 'post_status' => 'draft' ), wp_slash( $item['post'] ) );
			$post_id = wp_insert_post( $insert, true );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$result['failed'][] = array( 'slug' => $row['slug'], 'reason' => is_wp_error( $post_id ) ? $post_id->get_error_message() : __( '作成できませんでした。', 'omochix-core' ) );
				continue;
			}
		}
		foreach ( array_merge( $item['meta'], $item['relation_meta'] ) as $key => $value ) {
			if ( '' === $value || array() === $value || null === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			}
		}
		foreach ( $item['terms'] as $taxonomy => $slugs ) {
			wp_set_object_terms( $post_id, $slugs, $taxonomy, false );
		}
		$result['slugs'][] = $row['slug'];
		$written[ $row['slug'] ] = $post_id;
		if ( $row['post_id'] ) {
			++$result['updated'];
		} else {
			++$result['created'];
		}
	}
	// Relations to items created by this same run are linked now that they exist.
	foreach ( $rows as $row ) {
		if ( empty( $row['item']['pending_practice'] ) || empty( $written[ $row['slug'] ] ) ) {
			continue;
		}
		$ids = $row['item']['relation_meta']['related_practice_ids'] ?? array();
		foreach ( $row['item']['pending_practice'] as $pending_slug ) {
			$target = $written[ $pending_slug ] ?? 0;
			if ( ! $target ) {
				$found  = get_posts( array( 'post_type' => OMOCHIX_CORE_PRACTICE_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'name' => $pending_slug, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) );
				$target = $found ? (int) $found[0] : 0;
			}
			if ( $target ) {
				$ids[] = $target;
			}
		}
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		if ( $ids ) {
			update_post_meta( $written[ $row['slug'] ], 'related_practice_ids', $ids );
		}
	}
	return $result;
}

/**
 * Append a run to the import log (last 20 runs, newest first).
 *
 * @param string               $hash   File SHA-256.
 * @param array<string, mixed> $result From omochix_core_practice_import_execute().
 * @param int                  $user_id Acting user.
 * @return void
 */
function omochix_core_practice_import_log( $hash, $result, $user_id ) {
	$log = get_option( OMOCHIX_CORE_PRACTICE_IMPORT_LOG_OPTION, array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift(
		$log,
		array(
			'at'        => current_time( 'mysql' ),
			'user_id'   => (int) $user_id,
			'hash'      => (string) $hash,
			'created'   => (int) $result['created'],
			'updated'   => (int) $result['updated'],
			'unchanged' => (int) $result['unchanged'],
			'failed'    => count( $result['failed'] ),
			'slugs'     => array_slice( array_map( 'sanitize_title', (array) $result['slugs'] ), 0, 50 ),
		)
	);
	update_option( OMOCHIX_CORE_PRACTICE_IMPORT_LOG_OPTION, array_slice( $log, 0, OMOCHIX_CORE_PRACTICE_IMPORT_LOG_MAX ), false );
}

/** The import log, newest first. */
function omochix_core_practice_import_get_log() {
	$log = get_option( OMOCHIX_CORE_PRACTICE_IMPORT_LOG_OPTION, array() );
	return is_array( $log ) ? $log : array();
}
