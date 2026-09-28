<?php
/**
 * AI Tool Content Updater.
 *
 * Updates existing `ai_tool` posts from a JSON file: full editorial content
 * (post_content, structured detail fields, SEO title/description, image
 * attachment references) that the CSV importer's 9-field whitelist was never
 * designed to carry. This tool never creates a post -- a slug that does not
 * already resolve to a published/draft/etc. `ai_tool` post is a per-row
 * error, not a new tool.
 *
 * Deliberately separate from admin/csv-importer.php (new-tool bulk import)
 * and admin/prompt-csv-importer.php (Prompt Library import): neither of
 * those files is modified or called by this one.
 *
 * Workflow mirrors the Prompt Library importer's stateless two-pass design:
 *
 * 1. Dry-run: the uploaded JSON is parsed and validated against
 *    omochix_core_get_meta_schema() (the single source of truth also used by
 *    REST and the classic editor save path), and every field present in the
 *    file is diffed against the current database. Nothing is written -- no
 *    posts, no meta, no transient, no option.
 * 2. Apply: the editor re-selects the same file. It is re-validated from
 *    scratch and only applied when its SHA-256 matches the dry-run the
 *    editor confirmed and it still has zero errors.
 *
 * PATCH semantics: a field key absent from a tool's JSON object leaves that
 * field untouched. A field present with a JSON `null` value is treated
 * exactly like an absent key (untouched) -- this is the safest reading of
 * "null", since it can never be confused with an editor's deliberate
 * instruction to clear a field. To explicitly clear a string field, submit
 * `""`; to explicitly clear an array field, submit `[]`. Image attachment ID
 * fields are the one exception: `0` is rejected as a validation error in
 * this version rather than being read as "remove the image", so a mistaken
 * `0` can never silently detach an image; omit the key entirely to leave an
 * image untouched.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maximum upload size: 5 MiB. */
define( 'OMOCHIX_CORE_TOOL_UPDATE_MAX_BYTES', 5 * MB_IN_BYTES );

/** Maximum tools accepted in one file. */
define( 'OMOCHIX_CORE_TOOL_UPDATE_MAX_ROWS', 200 );

/**
 * Register the updater below the AI tool post type, next to (but separate
 * from) the CSV importer.
 *
 * @return void
 */
function omochix_core_register_tool_content_updater_page() {
	add_submenu_page(
		'edit.php?post_type=ai_tool',
		__( 'AIツール コンテンツ更新', 'omochix-core' ),
		__( 'コンテンツ更新', 'omochix-core' ),
		'manage_options',
		'omochix-ai-tool-content-updater',
		'omochix_core_render_tool_content_updater_page'
	);
}
add_action( 'admin_menu', 'omochix_core_register_tool_content_updater_page' );

/**
 * Capability gate. Deliberately higher than the CSV importer's
 * (`manage_categories`): this tool can rewrite the full editorial body of
 * any existing AI Tool in one operation, so it requires `manage_options`.
 *
 * @return bool
 */
function omochix_core_can_update_tool_content() {
	return current_user_can( 'manage_options' );
}

/**
 * String-type detail fields the updater accepts beyond the array fields in
 * omochix_core_get_meta_schema() -- these ARE in that schema (textarea
 * sanitize), listed here only for readable grouping in validation messages.
 *
 * @return string[]
 */
function omochix_core_tool_update_text_fields() {
	return array( 'short_description', 'pricing_details', 'api_sdk_info', 'security_info', 'notes', 'omochix_view' );
}

/**
 * Array-type detail fields, all sanitized as string_array by the schema.
 *
 * @return string[]
 */
function omochix_core_tool_update_array_fields() {
	return array(
		'key_features',
		'pros',
		'cons',
		'strengths',
		'weaknesses',
		'recommended_for',
		'recommended_use_cases',
		'not_recommended_for',
		'supported_devices',
		'supported_models',
		'integrations',
	);
}

/**
 * Enum-type fields and their allowed values, read directly from the schema
 * so this list can never drift from omochix_core_get_meta_schema().
 *
 * @return array<string, string[]>
 */
function omochix_core_tool_update_enum_fields() {
	$schema = omochix_core_get_meta_schema();
	$enums  = array();
	foreach ( array( 'japanese_support', 'pricing_type', 'tool_status' ) as $key ) {
		$enums[ $key ] = $schema[ $key ]['options'];
	}
	foreach ( array( 'api_available', 'commercial_use' ) as $key ) {
		// support_level fields declare their allowed values in the shared
		// normalizer, not in the schema array itself.
		$enums[ $key ] = array( 'yes', 'partial', 'no', 'unknown' );
	}
	return $enums;
}

/**
 * Fields this updater will write directly through
 * omochix_core_get_meta_schema() + omochix_core_sanitize_meta_value() --
 * i.e. every schema field except the identity/admin-only ones that make no
 * sense in a content update (title/slug live on the post itself and are
 * explicitly read-only here; is_featured and display_order are editorial
 * placement decisions, not content, and are intentionally left to wp-admin).
 *
 * @return string[]
 */
function omochix_core_tool_update_schema_fields() {
	return array_merge(
		array( 'short_description' ),
		omochix_core_tool_update_array_fields(),
		array( 'pricing_details', 'api_sdk_info', 'security_info', 'notes', 'has_free_plan', 'api_available', 'commercial_use', 'japanese_support', 'info_checked_date', 'omochix_view' )
	);
}

/**
 * Look up existing ai_tool posts by slug, across every status, exactly like
 * the CSV importer does -- this updater must find a draft or a published
 * tool equally well, and must never mistake "exists but draft" for
 * "does not exist".
 *
 * @return array<string, int> slug => post ID
 */
function omochix_core_tool_update_existing_slugs() {
	if ( function_exists( 'omochix_core_existing_tool_slugs' ) ) {
		return omochix_core_existing_tool_slugs();
	}
	return array();
}

/**
 * Validate one tool's JSON object and compute its diff against the current
 * database. Writes nothing.
 *
 * @param array<string, mixed> $tool           Decoded JSON object for one tool.
 * @param int                  $line           1-based index in the "tools" array, for error messages.
 * @param array<string, int>   $existing_slugs slug => post ID map.
 * @return array{errors: string[], warnings: string[], row: array<string, mixed>|null}
 */
function omochix_core_tool_update_validate_row( $tool, $line, $existing_slugs ) {
	$errors   = array();
	$warnings = array();

	if ( ! is_array( $tool ) ) {
		return array( 'errors' => array( sprintf( __( '%d件目：オブジェクトではありません。', 'omochix-core' ), $line ) ), 'warnings' => array(), 'row' => null );
	}

	$slug = isset( $tool['slug'] ) && is_scalar( $tool['slug'] ) ? sanitize_title( (string) $tool['slug'] ) : '';
	if ( '' === $slug ) {
		return array( 'errors' => array( sprintf( __( '%d件目：slugが必須です。', 'omochix-core' ), $line ) ), 'warnings' => array(), 'row' => null );
	}
	if ( ! isset( $existing_slugs[ $slug ] ) ) {
		return array(
			'errors'   => array( sprintf( __( '%1$d件目（slug: %2$s）：一致する既存のAIツールが見つかりません。このUpdaterは新規作成を行いません。', 'omochix-core' ), $line, $slug ) ),
			'warnings' => array(),
			'row'      => null,
		);
	}
	$post_id = $existing_slugs[ $slug ];
	$post    = get_post( $post_id );
	if ( ! $post || 'ai_tool' !== $post->post_type ) {
		return array( 'errors' => array( sprintf( __( '%1$d件目（slug: %2$s）：投稿タイプがai_toolではありません。', 'omochix-core' ), $line, $slug ) ), 'warnings' => array(), 'row' => null );
	}

	if ( array_key_exists( 'title', $tool ) || array_key_exists( 'new_slug', $tool ) ) {
		$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：titleまたはslugの変更はこのUpdaterでは行えません（識別専用）。', 'omochix-core' ), $line, $slug );
	}

	$changes = array();
	$clean   = array( 'post_id' => $post_id, 'slug' => $slug, 'title' => get_the_title( $post_id ) );

	// post_content: WP core field, not part of the meta schema. wp_kses_post
	// matches what a contributor without unfiltered_html already gets.
	if ( array_key_exists( 'post_content', $tool ) && null !== $tool['post_content'] ) {
		if ( ! is_string( $tool['post_content'] ) ) {
			$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：post_contentは文字列である必要があります。', 'omochix-core' ), $line, $slug );
		} else {
			$sanitized = wp_kses_post( $tool['post_content'] );
			$current   = (string) $post->post_content;
			if ( trim( $current ) !== trim( $sanitized ) ) {
				$changes[] = array(
					'field'  => 'post_content',
					'before' => '' === trim( $current ) ? __( '（空）', 'omochix-core' ) : sprintf( __( '%d文字', 'omochix-core' ), mb_strlen( $current ) ),
					'after'  => '' === trim( $sanitized ) ? __( '（空）', 'omochix-core' ) : sprintf( __( '%d文字', 'omochix-core' ), mb_strlen( $sanitized ) ),
				);
			}
			$clean['post_content'] = $sanitized;
		}
	}

	// Schema-backed fields: validated and diffed uniformly via the same
	// sanitizer the classic editor and REST already use, so a value that
	// would be rejected there is rejected here identically.
	$schema = omochix_core_get_meta_schema();
	$enums  = omochix_core_tool_update_enum_fields();
	foreach ( omochix_core_tool_update_schema_fields() as $key ) {
		if ( ! array_key_exists( $key, $tool ) || null === $tool[ $key ] ) {
			continue; // Absent or null: leave untouched (see file-level PATCH semantics doc).
		}
		$raw = $tool[ $key ];

		if ( 'has_free_plan' === $key && ! is_bool( $raw ) ) {
			$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：has_free_planはtrue/booleanである必要があります。', 'omochix-core' ), $line, $slug );
			continue;
		}
		if ( isset( $enums[ $key ] ) && ( ! is_string( $raw ) || ! in_array( $raw, $enums[ $key ], true ) ) ) {
			$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：%3$sの値「%4$s」は許可されていません（許可値：%5$s）。', 'omochix-core' ), $line, $slug, $key, is_scalar( $raw ) ? (string) $raw : wp_json_encode( $raw ), implode( '/', $enums[ $key ] ) );
			continue;
		}
		if ( in_array( $key, omochix_core_tool_update_array_fields(), true ) && ! is_array( $raw ) ) {
			$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：%3$sは配列である必要があります。', 'omochix-core' ), $line, $slug, $key );
			continue;
		}
		if ( in_array( $key, omochix_core_tool_update_array_fields(), true ) ) {
			foreach ( $raw as $item ) {
				if ( ! is_scalar( $item ) ) {
					$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：%3$sの各要素は文字列である必要があります。', 'omochix-core' ), $line, $slug, $key );
					continue 2;
				}
			}
		}
		if ( 'info_checked_date' === $key ) {
			if ( ! is_string( $raw ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：info_checked_dateはYYYY-MM-DD形式である必要があります。', 'omochix-core' ), $line, $slug );
				continue;
			}
			$parts = explode( '-', $raw );
			if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
				$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：info_checked_date「%3$s」は実在する日付ではありません。', 'omochix-core' ), $line, $slug, $raw );
				continue;
			}
		}
		if ( ( 'short_description' === $key || in_array( $key, array( 'pricing_details', 'api_sdk_info', 'security_info', 'notes', 'omochix_view' ), true ) ) && ! is_string( $raw ) ) {
			$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：%3$sは文字列である必要があります。', 'omochix-core' ), $line, $slug, $key );
			continue;
		}

		$sanitized = omochix_core_sanitize_meta_value( $raw, $key );
		$current   = get_post_meta( $post_id, $key, true );
		if ( 'boolean' === $schema[ $key ]['type'] ) {
			$current = (bool) $current;
		} elseif ( 'array' === $schema[ $key ]['type'] ) {
			$current = is_array( $current ) ? array_values( $current ) : array();
			$sanitized_compare = array_values( (array) $sanitized );
		}

		$changed = ( 'array' === $schema[ $key ]['type'] ) ? ( $current !== $sanitized_compare ) : ( $current !== $sanitized );
		if ( $changed ) {
			$changes[] = array(
				'field'  => $key,
				'before' => omochix_core_tool_update_format_value( $current ),
				'after'  => omochix_core_tool_update_format_value( $sanitized ),
			);
		}
		$clean[ $key ] = $sanitized;
	}

	// SEO: merge into the existing slim_seo array so unrelated keys (noindex,
	// canonical, OG images, ...) already set by an editor are never touched.
	foreach ( array( 'seo_title' => 'title', 'meta_description' => 'description' ) as $input_key => $slim_key ) {
		if ( ! array_key_exists( $input_key, $tool ) || null === $tool[ $input_key ] ) {
			continue;
		}
		if ( ! is_string( $tool[ $input_key ] ) ) {
			$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：%3$sは文字列である必要があります。', 'omochix-core' ), $line, $slug, $input_key );
			continue;
		}
		$sanitized_seo = sanitize_text_field( $tool[ $input_key ] );
		$slim_seo_data = get_post_meta( $post_id, 'slim_seo', true );
		$slim_seo_data = is_array( $slim_seo_data ) ? $slim_seo_data : array();
		$current_seo   = isset( $slim_seo_data[ $slim_key ] ) ? $slim_seo_data[ $slim_key ] : '';
		if ( $current_seo !== $sanitized_seo ) {
			$changes[] = array(
				'field'  => 'slim_seo.' . $slim_key,
				'before' => $current_seo ?: __( '（未設定）', 'omochix-core' ),
				'after'  => $sanitized_seo,
			);
		}
		$clean[ $input_key ] = $sanitized_seo;
	}

	// Image attachment IDs: integer > 0, a real attachment, an image mime.
	// 0 is a validation error in this version (never an implicit "remove").
	foreach ( array( 'tool_logo_attachment_id' => 'tool_logo', 'featured_image_attachment_id' => 'featured_media' ) as $input_key => $target ) {
		if ( ! array_key_exists( $input_key, $tool ) || null === $tool[ $input_key ] ) {
			continue;
		}
		$raw_id = $tool[ $input_key ];
		if ( ! is_int( $raw_id ) || $raw_id <= 0 ) {
			$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：%3$sは1以上の整数である必要があります（0は現在のバージョンでは許可されません。画像を変更しない場合はこの項目自体を省略してください）。', 'omochix-core' ), $line, $slug, $input_key );
			continue;
		}
		if ( 'attachment' !== get_post_type( $raw_id ) || ! wp_attachment_is_image( $raw_id ) ) {
			$errors[] = sprintf( __( '%1$d件目（slug: %2$s）：%3$s（ID %4$d）は画像の添付ファイルとして存在しません。先にWordPressメディアライブラリへアップロードしてください。', 'omochix-core' ), $line, $slug, $input_key, $raw_id );
			continue;
		}
		$current_id = 'tool_logo' === $target ? absint( get_post_meta( $post_id, 'tool_logo', true ) ) : (int) get_post_thumbnail_id( $post_id );
		if ( $current_id !== $raw_id ) {
			$changes[] = array(
				'field'  => $input_key,
				'before' => $current_id ?: __( '（未設定）', 'omochix-core' ),
				'after'  => $raw_id,
			);
		}
		$clean[ $input_key ] = $raw_id;
	}

	if ( $errors ) {
		return array( 'errors' => $errors, 'warnings' => $warnings, 'row' => null );
	}

	$clean['changes'] = $changes;
	$clean['action']  = $changes ? 'update' : 'unchanged';

	return array( 'errors' => array(), 'warnings' => $warnings, 'row' => $clean );
}

/**
 * Render a before/after value for the dry-run table.
 *
 * @param mixed $value Raw meta value.
 * @return string
 */
function omochix_core_tool_update_format_value( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'true' : 'false';
	}
	if ( is_array( $value ) ) {
		return $value ? implode( ' / ', array_map( 'strval', $value ) ) : __( '（空）', 'omochix-core' );
	}
	if ( '' === $value || null === $value ) {
		return __( '（空）', 'omochix-core' );
	}
	$value = (string) $value;
	return mb_strlen( $value ) > 60 ? mb_substr( $value, 0, 60 ) . '…' : $value;
}

/**
 * Parse and validate an uploaded JSON file into a full plan, without writing
 * anything. Stateless: the plan is always recomputed from whatever file is
 * actually present in the current request, exactly like the Prompt Library
 * importer -- there is no server-side transient to go stale or leak.
 *
 * @param array<string, mixed> $file Uploaded file array ($_FILES entry).
 * @return array<string, mixed>|WP_Error
 */
function omochix_core_tool_update_plan( $file ) {
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'invalid_upload', __( 'アップロードされたファイルを確認できません。', 'omochix-core' ) );
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'upload_error', __( 'アップロードに失敗しました。', 'omochix-core' ) );
	}
	if ( (int) $file['size'] <= 0 || (int) $file['size'] > OMOCHIX_CORE_TOOL_UPDATE_MAX_BYTES ) {
		return new WP_Error( 'file_size', __( 'JSONは5MB以下にしてください。', 'omochix-core' ) );
	}
	if ( 'json' !== strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) ) ) {
		return new WP_Error( 'file_extension', __( '拡張子.jsonのファイルを選択してください。', 'omochix-core' ) );
	}

	$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $contents || '' === trim( (string) $contents ) ) {
		return new WP_Error( 'empty_file', __( 'JSONが空です。', 'omochix-core' ) );
	}
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $contents, 'UTF-8' ) ) {
		return new WP_Error( 'encoding', __( 'JSONがUTF-8ではありません。', 'omochix-core' ) );
	}
	$hash = hash( 'sha256', $contents );

	$decoded = json_decode( $contents, true );
	unset( $contents );
	if ( null === $decoded || JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'invalid_json', sprintf( __( 'JSONの構文が正しくありません：%s', 'omochix-core' ), json_last_error_msg() ) );
	}
	if ( ! is_array( $decoded ) || ! isset( $decoded['tools'] ) || ! is_array( $decoded['tools'] ) ) {
		return new WP_Error( 'invalid_shape', __( 'JSONのトップレベルに "tools" 配列が必要です（例：{"tools": [ {"slug": "heygen", ...} ]}）。', 'omochix-core' ) );
	}
	if ( count( $decoded['tools'] ) > OMOCHIX_CORE_TOOL_UPDATE_MAX_ROWS ) {
		return new WP_Error( 'too_many_rows', sprintf( __( '1回の上限%d件を超えています。ファイルを分割してください。', 'omochix-core' ), OMOCHIX_CORE_TOOL_UPDATE_MAX_ROWS ) );
	}
	if ( ! $decoded['tools'] ) {
		return new WP_Error( 'no_rows', __( '"tools" 配列が空です。', 'omochix-core' ) );
	}

	$existing_slugs = omochix_core_tool_update_existing_slugs();
	$rows           = array();
	$errors         = array();
	$warnings       = array();
	$seen_slugs     = array();
	$line           = 0;

	foreach ( $decoded['tools'] as $tool ) {
		++$line;
		$slug_preview = is_array( $tool ) && isset( $tool['slug'] ) && is_scalar( $tool['slug'] ) ? sanitize_title( (string) $tool['slug'] ) : '';
		if ( '' !== $slug_preview && isset( $seen_slugs[ $slug_preview ] ) ) {
			$errors[] = sprintf( __( '%1$d件目：slug「%2$s」が%3$d件目と重複しています。', 'omochix-core' ), $line, $slug_preview, $seen_slugs[ $slug_preview ] );
			continue;
		}
		if ( '' !== $slug_preview ) {
			$seen_slugs[ $slug_preview ] = $line;
		}

		$validated = omochix_core_tool_update_validate_row( $tool, $line, $existing_slugs );
		$errors    = array_merge( $errors, $validated['errors'] );
		$warnings  = array_merge( $warnings, $validated['warnings'] );
		if ( $validated['row'] ) {
			$rows[] = $validated['row'];
		}
	}

	$summary = array(
		'total'     => count( $decoded['tools'] ),
		'update'    => count( array_filter( $rows, static function ( $r ) { return 'update' === $r['action']; } ) ),
		'unchanged' => count( array_filter( $rows, static function ( $r ) { return 'unchanged' === $r['action']; } ) ),
		'error_rows' => count( array_unique( array_map( static function ( $e ) {
			preg_match( '/^(\d+)/', $e, $m );
			return $m[1] ?? $e;
		}, $errors ) ) ),
	);

	return array(
		'rows'     => $rows,
		'errors'   => $errors,
		'warnings' => $warnings,
		'hash'     => $hash,
		'summary'  => $summary,
	);
}

/**
 * Take a full before-snapshot of one AI Tool, for the downloadable rollback
 * backup. Captures everything this updater can touch, plus enough identity
 * fields to restore unambiguously.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function omochix_core_tool_update_snapshot( $post_id ) {
	$post   = get_post( $post_id );
	$schema = omochix_core_get_meta_schema();
	$meta   = array();
	foreach ( array_keys( $schema ) as $key ) {
		$meta[ $key ] = get_post_meta( $post_id, $key, true );
	}

	return array(
		'post_id'       => $post_id,
		'slug'          => $post->post_name,
		'title'         => $post->post_title,
		'post_content'  => $post->post_content,
		'meta'          => $meta,
		'slim_seo'      => get_post_meta( $post_id, 'slim_seo', true ) ?: array(),
		'tool_logo'     => absint( get_post_meta( $post_id, 'tool_logo', true ) ),
		'featured_media' => (int) get_post_thumbnail_id( $post_id ),
		'snapshot_at'   => current_time( 'mysql' ),
	);
}

/**
 * Apply a validated plan's "update" rows. Assumes the caller has already
 * confirmed the SHA-256 lock and re-run validation on the exact file being
 * applied.
 *
 * @param array<int, array<string, mixed>> $rows Validated rows from the plan.
 * @return array{updated: int, unchanged: int, failed: array<int, array{slug:string, reason:string}>, backups: array<int, array<string, mixed>>}
 */
function omochix_core_tool_update_execute( $rows ) {
	$updated  = 0;
	$unchanged = 0;
	$failed   = array();
	$backups  = array();

	foreach ( $rows as $row ) {
		if ( 'unchanged' === $row['action'] ) {
			++$unchanged;
			continue;
		}

		$post_id = $row['post_id'];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$failed[] = array( 'slug' => $row['slug'], 'reason' => __( 'この投稿を編集する権限がありません。', 'omochix-core' ) );
			continue;
		}

		$backups[] = omochix_core_tool_update_snapshot( $post_id );

		$post_error = null;
		if ( array_key_exists( 'post_content', $row ) ) {
			$result = wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $row['post_content'] ) ), true );
			if ( is_wp_error( $result ) ) {
				$post_error = $result->get_error_message();
			}
		}
		if ( $post_error ) {
			$failed[] = array( 'slug' => $row['slug'], 'reason' => $post_error );
			continue;
		}

		$schema = omochix_core_get_meta_schema();
		foreach ( omochix_core_tool_update_schema_fields() as $key ) {
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$value = $row[ $key ];
			if ( 'array' === $schema[ $key ]['type'] ) {
				if ( empty( $value ) ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $value );
				}
			} elseif ( 'string' === $schema[ $key ]['type'] && '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		if ( array_key_exists( 'seo_title', $row ) || array_key_exists( 'meta_description', $row ) ) {
			$slim_seo_data = get_post_meta( $post_id, 'slim_seo', true );
			$slim_seo_data = is_array( $slim_seo_data ) ? $slim_seo_data : array();
			if ( array_key_exists( 'seo_title', $row ) ) {
				$slim_seo_data['title'] = sanitize_text_field( $row['seo_title'] );
			}
			if ( array_key_exists( 'meta_description', $row ) ) {
				$slim_seo_data['description'] = sanitize_text_field( $row['meta_description'] );
			}
			// Match Slim SEO's own Base::sanitize(): drop empty/falsy keys
			// rather than storing them, so this stays byte-for-byte
			// compatible with what Slim SEO itself would have saved.
			$slim_seo_data = array_filter( $slim_seo_data );
			if ( empty( $slim_seo_data ) ) {
				delete_post_meta( $post_id, 'slim_seo' );
			} else {
				update_post_meta( $post_id, 'slim_seo', $slim_seo_data );
			}
		}

		if ( array_key_exists( 'tool_logo_attachment_id', $row ) ) {
			update_post_meta( $post_id, 'tool_logo', absint( $row['tool_logo_attachment_id'] ) );
		}
		if ( array_key_exists( 'featured_image_attachment_id', $row ) ) {
			set_post_thumbnail( $post_id, absint( $row['featured_image_attachment_id'] ) );
		}

		++$updated;
	}

	return array( 'updated' => $updated, 'unchanged' => $unchanged, 'failed' => $failed, 'backups' => $backups );
}

/**
 * Render the dry-run -> apply workflow.
 *
 * @return void
 */
function omochix_core_render_tool_content_updater_page() {
	if ( ! omochix_core_can_update_tool_content() ) {
		wp_die( esc_html__( 'この操作を行う権限がありません。', 'omochix-core' ) );
	}

	$plan   = null;
	$result = null;
	$notice = null;

	if ( isset( $_POST['omochix_tool_update_dry_run'] ) ) {
		check_admin_referer( 'omochix_core_tool_update_dry_run', 'omochix_core_tool_update_nonce' );
		$plan = empty( $_FILES['omochix_tool_update_file'] )
			? new WP_Error( 'missing_file', __( 'JSONファイルを選択してください。', 'omochix-core' ) )
			: omochix_core_tool_update_plan( $_FILES['omochix_tool_update_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated in omochix_core_tool_update_plan().
		if ( is_wp_error( $plan ) ) {
			$notice = $plan;
			$plan   = null;
		}
	} elseif ( isset( $_POST['omochix_tool_update_apply'] ) ) {
		check_admin_referer( 'omochix_core_tool_update_apply', 'omochix_core_tool_update_apply_nonce' );
		$confirmed_hash = isset( $_POST['omochix_tool_update_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['omochix_tool_update_hash'] ) ) : '';
		$apply_plan     = empty( $_FILES['omochix_tool_update_file'] )
			? new WP_Error( 'missing_file', __( 'dry-runしたJSONファイルを再度選択してください。', 'omochix-core' ) )
			: omochix_core_tool_update_plan( $_FILES['omochix_tool_update_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated in omochix_core_tool_update_plan().

		if ( is_wp_error( $apply_plan ) ) {
			$notice = $apply_plan;
		} elseif ( empty( $_POST['omochix_tool_update_confirm'] ) ) {
			$notice = new WP_Error( 'not_confirmed', __( 'dry-run結果の確認チェックが必要です。', 'omochix-core' ) );
		} elseif ( ! hash_equals( $apply_plan['hash'], $confirmed_hash ) ) {
			$notice = new WP_Error( 'hash_mismatch', __( 'dry-runで確認したファイルと内容が異なります。反映は実行していません。もう一度dry-runからやり直してください。', 'omochix-core' ) );
		} elseif ( $apply_plan['errors'] ) {
			$notice = new WP_Error( 'has_errors', __( 'エラーがあるため反映は実行していません。JSONを修正してdry-runからやり直してください。', 'omochix-core' ) );
			$plan   = $apply_plan;
		} elseif ( ! omochix_core_can_update_tool_content() ) {
			$notice = new WP_Error( 'forbidden', __( 'この操作を行う権限がありません。', 'omochix-core' ) );
		} else {
			$result = omochix_core_tool_update_execute( $apply_plan['rows'] );
		}
	}

	$action_labels = array(
		'update'    => __( '更新', 'omochix-core' ),
		'unchanged' => __( '変更なし', 'omochix-core' ),
	);
	?>
	<div class="wrap omochix-import-page">
		<h1><?php esc_html_e( 'AIツール コンテンツ更新', 'omochix-core' ); ?></h1>
		<p class="description"><?php esc_html_e( '既存のAIツール投稿を、JSONファイルからslugで特定して更新します。新規投稿は作成しません。まずdry-runで変更内容を確認してから、同じファイルで反映します。dry-runはデータベースを一切変更しません。', 'omochix-core' ); ?></p>
		<?php if ( $notice ) : ?><div class="notice notice-error"><p><?php echo esc_html( $notice->get_error_message() ); ?></p></div><?php endif; ?>

		<?php if ( $result ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( '反映が完了しました。', 'omochix-core' ); ?></p></div>
			<dl class="omochix-import-summary">
				<div><dt><?php esc_html_e( '更新', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $result['updated'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( '変更なし', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $result['unchanged'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( '失敗', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( count( $result['failed'] ) ) ); ?></dd></div>
			</dl>
			<?php if ( $result['failed'] ) : ?>
				<div class="omochix-import-errors" role="alert"><h2><?php esc_html_e( '失敗した項目', 'omochix-core' ); ?></h2><ul>
					<?php foreach ( $result['failed'] as $f ) : ?><li><strong><?php echo esc_html( $f['slug'] ); ?></strong>：<?php echo esc_html( $f['reason'] ); ?></li><?php endforeach; ?>
				</ul></div>
			<?php endif; ?>
			<?php if ( $result['backups'] ) : ?>
				<?php $backup_json = wp_json_encode( $result['backups'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); ?>
				<div class="omochix-import-panel">
					<h2><?php esc_html_e( 'ロールバック用バックアップ（更新前の状態）', 'omochix-core' ); ?></h2>
					<p><?php esc_html_e( '今回更新した投稿の、更新前の全フィールドです。このJSONを保存しておくと、手動での復元に使えます。あわせてWordPressの「リビジョン」からも復元できます。', 'omochix-core' ); ?></p>
					<p><a class="button" download="omochix-ai-tool-update-backup.json" href="data:application/json;charset=utf-8,<?php echo rawurlencode( $backup_json ); ?>"><?php esc_html_e( 'バックアップJSONをダウンロード', 'omochix-core' ); ?></a></p>
					<details><summary><?php esc_html_e( '内容を表示', 'omochix-core' ); ?></summary><textarea readonly rows="12" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $backup_json ); ?></textarea></details>
				</div>
			<?php endif; ?>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=ai_tool' ) ); ?>"><?php esc_html_e( 'AIツール一覧を確認', 'omochix-core' ); ?></a></p>
		<?php elseif ( $plan ) : ?>
			<h2><?php esc_html_e( 'dry-run結果（データベースは変更されていません）', 'omochix-core' ); ?></h2>
			<dl class="omochix-import-summary">
				<div><dt><?php esc_html_e( '対象件数', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $plan['summary']['total'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( '更新予定', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $plan['summary']['update'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( '変更なし', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $plan['summary']['unchanged'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'エラー', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $plan['summary']['error_rows'] ) ); ?></dd></div>
			</dl>
			<?php if ( $plan['errors'] ) : ?>
				<div class="omochix-import-errors" role="alert"><h2><?php esc_html_e( '修正が必要な項目（反映できません）', 'omochix-core' ); ?></h2><ul>
					<?php foreach ( $plan['errors'] as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?>
				</ul></div>
			<?php endif; ?>
			<?php if ( $plan['warnings'] ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( implode( ' ', $plan['warnings'] ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( $plan['rows'] ) : ?>
				<div class="omochix-import-table-wrap"><table class="widefat striped"><thead><tr>
					<th><?php esc_html_e( 'ツール', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( '処理', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( 'フィールド', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( 'Before', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( 'After', 'omochix-core' ); ?></th>
				</tr></thead><tbody>
					<?php foreach ( $plan['rows'] as $row ) : ?>
						<?php if ( ! $row['changes'] ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['title'] ); ?></strong><br><code><?php echo esc_html( $row['slug'] ); ?></code> (ID <?php echo esc_html( $row['post_id'] ); ?>)</td>
								<td><?php echo esc_html( $action_labels[ $row['action'] ] ); ?></td>
								<td colspan="3">—</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $row['changes'] as $i => $change ) : ?>
								<tr>
									<?php if ( 0 === $i ) : ?>
										<td rowspan="<?php echo esc_attr( count( $row['changes'] ) ); ?>"><strong><?php echo esc_html( $row['title'] ); ?></strong><br><code><?php echo esc_html( $row['slug'] ); ?></code> (ID <?php echo esc_html( $row['post_id'] ); ?>)</td>
										<td rowspan="<?php echo esc_attr( count( $row['changes'] ) ); ?>"><?php echo esc_html( $action_labels[ $row['action'] ] ); ?></td>
									<?php endif; ?>
									<td><code><?php echo esc_html( $change['field'] ); ?></code></td>
									<td><?php echo esc_html( $change['before'] ); ?></td>
									<td><?php echo esc_html( $change['after'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>

			<?php if ( ! $plan['errors'] && $plan['summary']['update'] ) : ?>
				<div class="omochix-import-panel">
					<h2><?php esc_html_e( '反映を実行', 'omochix-core' ); ?></h2>
					<p><?php echo esc_html( sprintf( __( '既存AIツール%d件を更新します。新規投稿は作成されません。', 'omochix-core' ), $plan['summary']['update'] ) ); ?></p>
					<p><?php esc_html_e( '確認のため、dry-runしたものと同じJSONファイルをもう一度選択してください。内容が1文字でも異なる場合は実行されません。', 'omochix-core' ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'omochix_core_tool_update_apply', 'omochix_core_tool_update_apply_nonce' ); ?>
						<input type="hidden" name="omochix_tool_update_hash" value="<?php echo esc_attr( $plan['hash'] ); ?>">
						<label for="omochix-tool-update-apply-file"><strong><?php esc_html_e( 'JSONファイル（dry-runと同じもの）', 'omochix-core' ); ?></strong></label>
						<input id="omochix-tool-update-apply-file" name="omochix_tool_update_file" type="file" accept=".json,application/json" required>
						<label><input type="checkbox" name="omochix_tool_update_confirm" value="1" required> <?php echo esc_html( sprintf( __( '既存AIツール%d件を更新します（新規作成なし）。内容を確認しました。', 'omochix-core' ), $plan['summary']['update'] ) ); ?></label>
						<p><button class="button button-primary" type="submit" name="omochix_tool_update_apply" value="1"><?php esc_html_e( '反映を実行', 'omochix-core' ); ?></button></p>
					</form>
				</div>
			<?php elseif ( ! $plan['errors'] ) : ?>
				<p><?php esc_html_e( '反映が必要な変更はありません。', 'omochix-core' ); ?></p>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ai_tool&page=omochix-ai-tool-content-updater' ) ); ?>"><?php esc_html_e( '別のJSONでやり直す', 'omochix-core' ); ?></a></p>
		<?php else : ?>
			<div class="omochix-import-panel">
				<h2><?php esc_html_e( 'JSONをdry-run', 'omochix-core' ); ?></h2>
				<p><?php esc_html_e( '形式：{"tools": [ {"slug": "既存のAIツールslug", ...更新したいフィールドのみ... } ]}', 'omochix-core' ); ?></p>
				<ul class="ul-disc">
					<li><?php esc_html_e( 'slugは既存のAIツール（下書き・公開いずれも可）と一致する必要があります。一致しない場合はエラーになり、新規作成は行われません。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( 'titleとslug自体はこのUpdaterでは変更できません（識別専用）。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( 'JSONに存在しないフィールドは変更されません。値をnullにした場合も同様に変更されません。文字列を明示的に空にしたい場合は ""、配列を空にしたい場合は [] を指定してください。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( '画像（tool_logo_attachment_id / featured_image_attachment_id）は既存メディアライブラリの添付ファイルIDのみ指定できます。0は現在のバージョンではエラーになります。', 'omochix-core' ); ?></li>
				</ul>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'omochix_core_tool_update_dry_run', 'omochix_core_tool_update_nonce' ); ?>
					<label for="omochix-tool-update-file"><strong><?php esc_html_e( 'JSONファイル', 'omochix-core' ); ?></strong></label>
					<input id="omochix-tool-update-file" name="omochix_tool_update_file" type="file" accept=".json,application/json" required>
					<p><button class="button button-primary" type="submit" name="omochix_tool_update_dry_run" value="1"><?php esc_html_e( 'dry-run（検証のみ）', 'omochix-core' ); ?></button></p>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
