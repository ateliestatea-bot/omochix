<?php
/**
 * CSV importer for the Prompt Library.
 *
 * Workflow is deliberately two-pass and stateless:
 *
 * 1. Dry-run: the uploaded file is parsed and validated, and every row is
 *    compared with the current database. Nothing is written — no posts, no
 *    meta, no terms, and no transient/option either.
 * 2. Import: the editor re-selects the same file. It is re-validated from
 *    scratch and only imported when its SHA-256 matches the dry-run the
 *    editor confirmed and it has zero errors.
 *
 * Taxonomy terms are never created by the importer; unknown terms are
 * errors, so a typo can never spawn a new public archive URL.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maximum data rows accepted in one prompt import. */
define( 'OMOCHIX_CORE_PROMPT_CSV_MAX_ROWS', 500 );

/**
 * Register the importer below the prompt post type.
 *
 * @return void
 */
function omochix_core_register_prompt_csv_import_page() {
	add_submenu_page(
		'edit.php?post_type=prompt',
		__( 'プロンプト CSVインポート', 'omochix-core' ),
		__( 'CSVインポート', 'omochix-core' ),
		'edit_others_posts',
		'omochix-prompt-import',
		'omochix_core_render_prompt_csv_import_page'
	);
}
add_action( 'admin_menu', 'omochix_core_register_prompt_csv_import_page' );

/**
 * Required CSV columns, in the documented order.
 *
 * @return string[]
 */
function omochix_core_prompt_csv_headers() {
	return array(
		'slug',
		'title',
		'excerpt',
		'description',
		'prompt_body',
		'usage',
		'example',
		'difficulty',
		'categories',
		'models',
	);
}

/**
 * Optional CSV columns. Absent columns leave the related data untouched.
 *
 * @return string[]
 */
function omochix_core_prompt_csv_optional_headers() {
	return array( 'related_tool_slugs' );
}

/**
 * Check importer-level capabilities.
 *
 * @return bool
 */
function omochix_core_can_import_prompts() {
	$post_type = get_post_type_object( 'prompt' );
	if ( ! $post_type || ! current_user_can( $post_type->cap->edit_posts ) || ! current_user_can( $post_type->cap->edit_others_posts ) ) {
		return false;
	}
	foreach ( array( 'prompt_category', 'prompt_model' ) as $taxonomy_name ) {
		$taxonomy = get_taxonomy( $taxonomy_name );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Load existing, non-trashed prompts keyed by slug.
 *
 * Trashed posts are excluded: WordPress renames their slug on trash, so they
 * can never collide with an imported slug.
 *
 * @return array<string, WP_Post>
 */
function omochix_core_prompt_csv_existing_prompts() {
	$posts = get_posts(
		array(
			'post_type'      => 'prompt',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	$by_slug = array();
	foreach ( $posts as $post ) {
		if ( $post->post_name ) {
			$by_slug[ $post->post_name ] = $post;
		}
	}
	return $by_slug;
}

/**
 * Load existing, non-trashed AI tool IDs keyed by slug.
 *
 * @return array<string, int>
 */
function omochix_core_prompt_csv_tool_slugs() {
	$ids = get_posts(
		array(
			'post_type'      => 'ai_tool',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$slugs = array();
	foreach ( $ids as $id ) {
		$slug = get_post_field( 'post_name', $id );
		if ( $slug ) {
			$slugs[ $slug ] = (int) $id;
		}
	}
	return $slugs;
}

/**
 * Build a slug/name → term lookup for one taxonomy.
 *
 * @param string $taxonomy Taxonomy name.
 * @return array{by_slug: array<string, WP_Term>, by_name: array<string, WP_Term>}
 */
function omochix_core_prompt_csv_term_map( $taxonomy ) {
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
	$map   = array( 'by_slug' => array(), 'by_name' => array() );
	if ( ! is_array( $terms ) ) {
		return $map;
	}
	foreach ( $terms as $term ) {
		$map['by_slug'][ $term->slug ] = $term;
		$map['by_name'][ $term->name ] = $term;
	}
	return $map;
}

/**
 * Split a comma-separated cell (half- or full-width comma).
 *
 * @param string $value Cell value.
 * @return string[]
 */
function omochix_core_prompt_csv_list( $value ) {
	$items = preg_split( '/[,、，]/u', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
	$items = array_filter( array_map( 'trim', array_map( 'sanitize_text_field', (array) $items ) ), 'strlen' );
	return array_values( array_unique( $items ) );
}

/**
 * Resolve term slugs/names in a cell against existing terms only.
 *
 * @param string                                                          $value    Cell value.
 * @param array{by_slug: array<string, WP_Term>, by_name: array<string, WP_Term>} $map      Term lookup.
 * @param string                                                          $column   Column name for messages.
 * @param string[]                                                        $errors   Error list (by reference).
 * @return WP_Term[]
 */
function omochix_core_prompt_csv_resolve_terms( $value, $map, $column, &$errors ) {
	$terms = array();
	foreach ( omochix_core_prompt_csv_list( $value ) as $item ) {
		$slug = sanitize_title( $item );
		if ( isset( $map['by_slug'][ $slug ] ) ) {
			$terms[ $map['by_slug'][ $slug ]->term_id ] = $map['by_slug'][ $slug ];
		} elseif ( isset( $map['by_name'][ $item ] ) ) {
			$terms[ $map['by_name'][ $item ]->term_id ] = $map['by_name'][ $item ];
		} else {
			$errors[] = sprintf( __( '%1$sの「%2$s」は存在しないタームです。先に管理画面でタームを作成するか、既存のslugを指定してください。', 'omochix-core' ), $column, $item );
		}
	}
	return array_values( $terms );
}

/**
 * Normalize line endings of a free-text cell.
 *
 * @param string $value Raw value.
 * @return string
 */
function omochix_core_prompt_csv_text( $value ) {
	return trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $value ) );
}

/**
 * Validate and normalize one CSV row.
 *
 * @param array<string, string> $raw     Raw row keyed by header.
 * @param array<string, mixed>  $context Lookups: term maps, tool slugs.
 * @return array{row: array<string, mixed>|null, errors: string[], warnings: string[]}
 */
function omochix_core_prompt_csv_normalize_row( $raw, $context ) {
	$errors   = array();
	$warnings = array();

	$raw_slug = trim( $raw['slug'] );
	if ( '' === $raw_slug ) {
		$errors[] = __( 'slugは必須です。', 'omochix-core' );
	} elseif ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $raw_slug ) || strlen( $raw_slug ) > 190 ) {
		$errors[] = sprintf( __( 'slug「%s」は半角小文字英数字とハイフンのみで指定してください。', 'omochix-core' ), $raw_slug );
	}

	$title = sanitize_text_field( $raw['title'] );
	if ( '' === $title ) {
		$errors[] = __( 'titleは必須です。', 'omochix-core' );
	}

	$prompt_body = omochix_core_sanitize_prompt_text( omochix_core_prompt_csv_text( $raw['prompt_body'] ) );
	if ( '' === $prompt_body ) {
		$errors[] = __( 'prompt_bodyは必須です。', 'omochix-core' );
	}

	$difficulty = sanitize_key( $raw['difficulty'] );
	$schema     = omochix_core_get_prompt_meta_schema();
	if ( ! in_array( $difficulty, $schema['prompt_difficulty']['options'], true ) ) {
		$errors[] = sprintf( __( 'difficultyの値「%s」は許可されていません（beginner / intermediate / advanced）。', 'omochix-core' ), $raw['difficulty'] );
	}

	$categories = omochix_core_prompt_csv_resolve_terms( $raw['categories'], $context['category_map'], 'categories', $errors );
	if ( ! $categories && '' === trim( $raw['categories'] ) ) {
		$errors[] = __( 'categoriesは1件以上必須です。', 'omochix-core' );
	}
	$models = omochix_core_prompt_csv_resolve_terms( $raw['models'], $context['model_map'], 'models', $errors );

	// related_tool_slugs: absent column or empty cell → leave relations untouched.
	$related_tool_ids = null;
	if ( isset( $raw['related_tool_slugs'] ) && '' !== trim( $raw['related_tool_slugs'] ) ) {
		$related_tool_ids = array();
		foreach ( omochix_core_prompt_csv_list( $raw['related_tool_slugs'] ) as $tool_slug ) {
			$tool_slug = sanitize_title( $tool_slug );
			if ( isset( $context['tool_slugs'][ $tool_slug ] ) ) {
				$related_tool_ids[] = $context['tool_slugs'][ $tool_slug ];
			} else {
				$warnings[] = sprintf( __( 'related_tool_slugsの「%s」に一致するAIツールがないため、この関連はスキップします。', 'omochix-core' ), $tool_slug );
			}
		}
		$related_tool_ids = array_values( array_unique( $related_tool_ids ) );
		if ( ! $related_tool_ids ) {
			$related_tool_ids = null;
		}
	}

	if ( $errors ) {
		return array( 'row' => null, 'errors' => $errors, 'warnings' => $warnings );
	}

	return array(
		'row'      => array(
			'slug'             => $raw_slug,
			'title'            => $title,
			'excerpt'          => sanitize_textarea_field( omochix_core_prompt_csv_text( $raw['excerpt'] ) ),
			'description'      => wp_kses_post( omochix_core_prompt_csv_text( $raw['description'] ) ),
			'prompt_body'      => $prompt_body,
			'prompt_usage'     => omochix_core_sanitize_prompt_text( omochix_core_prompt_csv_text( $raw['usage'] ) ),
			'prompt_example'   => omochix_core_sanitize_prompt_text( omochix_core_prompt_csv_text( $raw['example'] ) ),
			'prompt_difficulty' => $difficulty,
			'prompt_category'  => $categories,
			'prompt_model'     => $models,
			'related_tool_ids' => $related_tool_ids,
		),
		'errors'   => array(),
		'warnings' => $warnings,
	);
}

/**
 * Compare a normalized row with an existing prompt and list changed fields.
 *
 * @param array<string, mixed> $row  Normalized row.
 * @param WP_Post              $post Existing prompt.
 * @return string[] Human-readable changed field labels.
 */
function omochix_core_prompt_csv_diff( $row, $post ) {
	$changes = array();
	$scalar  = array(
		'title'       => array( $post->post_title, __( 'タイトル', 'omochix-core' ) ),
		'excerpt'     => array( $post->post_excerpt, __( '抜粋', 'omochix-core' ) ),
		'description' => array( $post->post_content, __( '説明文', 'omochix-core' ) ),
	);
	foreach ( $scalar as $key => $current ) {
		if ( trim( (string) $current[0] ) !== $row[ $key ] ) {
			$changes[] = $current[1];
		}
	}

	$meta_labels = array(
		'prompt_body'       => __( 'プロンプト本文', 'omochix-core' ),
		'prompt_usage'      => __( '使い方', 'omochix-core' ),
		'prompt_example'    => __( '出力例', 'omochix-core' ),
		'prompt_difficulty' => __( '難易度', 'omochix-core' ),
	);
	foreach ( $meta_labels as $key => $label ) {
		$current = (string) get_post_meta( $post->ID, $key, true );
		if ( 'prompt_difficulty' === $key && '' === $current ) {
			$current = 'beginner';
		}
		if ( $current !== $row[ $key ] ) {
			$changes[] = $label;
		}
	}

	$tax_labels = array(
		'prompt_category' => __( 'カテゴリー', 'omochix-core' ),
		'prompt_model'    => __( '対応AI', 'omochix-core' ),
	);
	foreach ( $tax_labels as $taxonomy => $label ) {
		$current = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		$current = is_array( $current ) ? array_map( 'intval', $current ) : array();
		$next    = array_map( 'intval', wp_list_pluck( $row[ $taxonomy ], 'term_id' ) );
		sort( $current );
		sort( $next );
		if ( $current !== $next ) {
			$changes[] = $label;
		}
	}

	if ( null !== $row['related_tool_ids'] ) {
		$current = get_post_meta( $post->ID, 'related_tool_ids', true );
		$current = is_array( $current ) ? array_map( 'absint', $current ) : array();
		if ( $current !== $row['related_tool_ids'] ) {
			$changes[] = __( '関連AIツール', 'omochix-core' );
		}
	}

	return $changes;
}

/**
 * Parse, validate and plan an uploaded CSV without writing anything.
 *
 * @param array<string, mixed> $file Uploaded file array.
 * @return array<string, mixed>|WP_Error Plan with rows, errors, warnings, hash and summary.
 */
function omochix_core_prompt_csv_plan( $file ) {
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'invalid_upload', __( 'アップロードされたファイルを確認できません。', 'omochix-core' ) );
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'upload_error', __( 'CSVのアップロードに失敗しました。', 'omochix-core' ) );
	}
	if ( (int) $file['size'] <= 0 || (int) $file['size'] > OMOCHIX_CORE_CSV_MAX_BYTES ) {
		return new WP_Error( 'file_size', __( 'CSVは5MB以下にしてください。', 'omochix-core' ) );
	}
	if ( 'csv' !== strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) ) ) {
		return new WP_Error( 'file_extension', __( '拡張子.csvのファイルを選択してください。', 'omochix-core' ) );
	}
	$allowed_mimes = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream' );
	$mime          = function_exists( 'finfo_open' ) ? ( new finfo( FILEINFO_MIME_TYPE ) )->file( $file['tmp_name'] ) : '';
	if ( $mime && ! in_array( strtolower( $mime ), $allowed_mimes, true ) ) {
		return new WP_Error( 'file_mime', sprintf( __( 'CSVとして認識できないMIMEタイプです：%s', 'omochix-core' ), $mime ) );
	}

	$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $contents || '' === $contents ) {
		return new WP_Error( 'empty_csv', __( 'CSVが空です。', 'omochix-core' ) );
	}
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $contents, 'UTF-8' ) ) {
		return new WP_Error( 'encoding', __( 'CSVがUTF-8ではありません。UTF-8（BOMあり・なし可）で保存し直してください。', 'omochix-core' ) );
	}
	$hash = hash( 'sha256', $contents );
	unset( $contents );

	$handle = fopen( $file['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( false === $handle ) {
		return new WP_Error( 'file_open', __( 'CSVを読み込めません。', 'omochix-core' ) );
	}
	$headers = fgetcsv( $handle, 0, ',', '"', '' );
	if ( false === $headers || array( null ) === $headers ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'empty_csv', __( 'CSVが空です。', 'omochix-core' ) );
	}
	$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $headers[0] );
	$headers    = array_map( static function ( $value ) { return trim( (string) $value ); }, $headers );
	$missing    = array_diff( omochix_core_prompt_csv_headers(), $headers );
	$unknown    = array_diff( $headers, omochix_core_prompt_csv_headers(), omochix_core_prompt_csv_optional_headers() );
	if ( $missing || $unknown || count( $headers ) !== count( array_unique( $headers ) ) ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$messages = array();
		if ( $missing ) {
			$messages[] = sprintf( __( '必須ヘッダーがありません：%s', 'omochix-core' ), implode( ', ', $missing ) );
		}
		if ( $unknown ) {
			$messages[] = sprintf( __( '未対応のヘッダーがあります：%s', 'omochix-core' ), implode( ', ', $unknown ) );
		}
		if ( count( $headers ) !== count( array_unique( $headers ) ) ) {
			$messages[] = __( '同じヘッダーが重複しています。', 'omochix-core' );
		}
		return new WP_Error( 'headers', implode( ' / ', $messages ) );
	}

	$context = array(
		'category_map' => omochix_core_prompt_csv_term_map( 'prompt_category' ),
		'model_map'    => omochix_core_prompt_csv_term_map( 'prompt_model' ),
		'tool_slugs'   => omochix_core_prompt_csv_tool_slugs(),
	);
	$existing = omochix_core_prompt_csv_existing_prompts();

	$rows      = array();
	$errors    = array();
	$warnings  = array();
	$seen      = array();
	$row_count = 0;
	$next_line = 2;
	while ( false !== ( $values = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
		// Report the physical line a record starts on, even when earlier
		// quoted cells (prompt bodies) span several lines.
		$line      = $next_line;
		$next_line = $line + 1;
		foreach ( $values as $value ) {
			$next_line += substr_count( (string) $value, "\n" );
		}

		if ( array( null ) === $values || ( 1 === count( $values ) && '' === trim( (string) $values[0] ) ) ) {
			continue;
		}
		++$row_count;
		if ( $row_count > OMOCHIX_CORE_PROMPT_CSV_MAX_ROWS ) {
			$errors[] = array( 'line' => $line, 'reason' => sprintf( __( '1回の上限%d件を超えています。CSVを分割してください。', 'omochix-core' ), OMOCHIX_CORE_PROMPT_CSV_MAX_ROWS ) );
			break;
		}
		if ( count( $values ) !== count( $headers ) ) {
			$errors[] = array( 'line' => $line, 'reason' => __( '列数がヘッダーと一致しません。カンマや改行を含む値はダブルクォートで囲んでください。', 'omochix-core' ) );
			continue;
		}

		$raw        = array_combine( $headers, array_map( static function ( $value ) { return (string) $value; }, $values ) );
		$normalized = omochix_core_prompt_csv_normalize_row( $raw, $context );
		foreach ( $normalized['warnings'] as $reason ) {
			$warnings[] = array( 'line' => $line, 'reason' => $reason );
		}

		$slug = trim( $raw['slug'] );
		if ( '' !== $slug && isset( $seen[ $slug ] ) ) {
			$normalized['errors'][] = sprintf( __( 'slug「%1$s」が%2$d行目と重複しています。', 'omochix-core' ), $slug, $seen[ $slug ] );
			$normalized['row']      = null;
		} elseif ( '' !== $slug ) {
			$seen[ $slug ] = $line;
		}

		if ( $normalized['errors'] ) {
			foreach ( $normalized['errors'] as $reason ) {
				$errors[] = array( 'line' => $line, 'reason' => $reason );
			}
			continue;
		}

		$row         = $normalized['row'];
		$row['line'] = $line;
		if ( isset( $existing[ $row['slug'] ] ) ) {
			$post           = $existing[ $row['slug'] ];
			$row['post_id'] = (int) $post->ID;
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				$errors[] = array( 'line' => $line, 'reason' => __( '既存プロンプトを更新する権限がありません。', 'omochix-core' ) );
				continue;
			}
			$row['changes'] = omochix_core_prompt_csv_diff( $row, $post );
			$row['action']  = $row['changes'] ? 'update' : 'unchanged';
			$row['status']  = $post->post_status;
		} else {
			$row['post_id'] = 0;
			$row['changes'] = array();
			$row['action']  = 'create';
			$row['status']  = 'draft';
		}
		$rows[] = $row;
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	$summary = array( 'total' => 0, 'create' => 0, 'update' => 0, 'unchanged' => 0, 'error_rows' => 0 );
	foreach ( $rows as $row ) {
		++$summary[ $row['action'] ];
	}
	$summary['error_rows'] = count( array_unique( wp_list_pluck( $errors, 'line' ) ) );
	$summary['total']      = count( $rows ) + $summary['error_rows'];

	return array(
		'hash'     => $hash,
		'rows'     => $rows,
		'errors'   => $errors,
		'warnings' => $warnings,
		'summary'  => $summary,
	);
}

/**
 * Write the planned rows. Only called after a matching, error-free dry-run.
 *
 * @param array<int, array<string, mixed>> $rows Planned rows.
 * @return array{created:int, updated:int, unchanged:int, errors: array<int, array{line:int,reason:string}>, warnings: array<int, array{line:int,reason:string}>}
 */
function omochix_core_prompt_csv_execute( $rows ) {
	$result = array( 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => array(), 'warnings' => array() );

	foreach ( $rows as $row ) {
		if ( 'unchanged' === $row['action'] ) {
			++$result['unchanged'];
			continue;
		}

		$post_data = array(
			'post_type'    => 'prompt',
			'post_title'   => $row['title'],
			'post_name'    => $row['slug'],
			'post_excerpt' => $row['excerpt'],
			'post_content' => $row['description'],
		);
		if ( $row['post_id'] ) {
			$post_data['ID'] = $row['post_id'];
			$post_id         = wp_update_post( wp_slash( $post_data ), true );
		} else {
			$post_data['post_status'] = 'draft';
			$post_id                  = wp_insert_post( wp_slash( $post_data ), true );
		}
		if ( is_wp_error( $post_id ) ) {
			$result['errors'][] = array( 'line' => $row['line'], 'reason' => $post_id->get_error_message() );
			continue;
		}
		$post_id = (int) $post_id;

		if ( get_post_field( 'post_name', $post_id ) !== $row['slug'] ) {
			$result['warnings'][] = array( 'line' => $row['line'], 'reason' => sprintf( __( 'slug「%1$s」は使用できないため「%2$s」で保存されました。', 'omochix-core' ), $row['slug'], get_post_field( 'post_name', $post_id ) ) );
		}

		foreach ( array( 'prompt_body', 'prompt_usage', 'prompt_example', 'prompt_difficulty' ) as $key ) {
			if ( '' === $row[ $key ] ) {
				delete_post_meta( $post_id, $key );
			} else {
				// update_post_meta() unslashes its value; slash so backslashes
				// inside prompt text survive verbatim.
				update_post_meta( $post_id, $key, wp_slash( $row[ $key ] ) );
			}
		}
		if ( null !== $row['related_tool_ids'] ) {
			update_post_meta( $post_id, 'related_tool_ids', $row['related_tool_ids'] );
		}

		foreach ( array( 'prompt_category', 'prompt_model' ) as $taxonomy ) {
			$term_result = wp_set_object_terms( $post_id, array_map( 'intval', wp_list_pluck( $row[ $taxonomy ], 'term_id' ) ), $taxonomy, false );
			if ( is_wp_error( $term_result ) ) {
				$result['errors'][] = array( 'line' => $row['line'], 'reason' => sprintf( __( '%1$sの設定に失敗しました：%2$s', 'omochix-core' ), $taxonomy, $term_result->get_error_message() ) );
			}
		}

		$row['post_id'] ? ++$result['updated'] : ++$result['created'];
	}

	return $result;
}

/**
 * Render a line-numbered message list.
 *
 * @param array<int, array{line:int,reason:string}> $items Messages.
 * @param string                                    $title Heading.
 * @param string                                    $class Wrapper class.
 * @return void
 */
function omochix_core_render_prompt_csv_messages( $items, $title, $class ) {
	if ( ! $items ) {
		return;
	}
	?>
	<div class="<?php echo esc_attr( $class ); ?>" role="<?php echo 'omochix-import-errors' === $class ? 'alert' : 'status'; ?>"><h2><?php echo esc_html( $title ); ?></h2><ul>
		<?php foreach ( $items as $item ) : ?><li><strong><?php echo esc_html( sprintf( __( '%d行目', 'omochix-core' ), $item['line'] ) ); ?></strong>：<?php echo esc_html( $item['reason'] ); ?></li><?php endforeach; ?>
	</ul></div>
	<?php
}

/**
 * Render summary counters.
 *
 * @param array<string, int> $counts Label => count.
 * @return void
 */
function omochix_core_render_prompt_csv_counts( $counts ) {
	?>
	<dl class="omochix-import-summary">
		<?php foreach ( $counts as $label => $count ) : ?>
			<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( number_format_i18n( $count ) ); ?></dd></div>
		<?php endforeach; ?>
	</dl>
	<?php
}

/**
 * Render the dry-run → import workflow.
 *
 * @return void
 */
function omochix_core_render_prompt_csv_import_page() {
	if ( ! omochix_core_can_import_prompts() ) {
		wp_die( esc_html__( 'プロンプトをインポートする権限がありません。', 'omochix-core' ) );
	}

	$plan   = null;
	$result = null;
	$notice = null;

	if ( isset( $_POST['omochix_prompt_csv_dry_run'] ) ) {
		check_admin_referer( 'omochix_core_prompt_csv_dry_run', 'omochix_core_prompt_csv_nonce' );
		$plan = empty( $_FILES['omochix_prompt_csv_file'] )
			? new WP_Error( 'missing_file', __( 'CSVファイルを選択してください。', 'omochix-core' ) )
			: omochix_core_prompt_csv_plan( $_FILES['omochix_prompt_csv_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated in omochix_core_prompt_csv_plan().
		if ( is_wp_error( $plan ) ) {
			$notice = $plan;
			$plan   = null;
		}
	} elseif ( isset( $_POST['omochix_prompt_csv_execute'] ) ) {
		check_admin_referer( 'omochix_core_prompt_csv_execute', 'omochix_core_prompt_csv_execute_nonce' );
		$confirmed_hash = isset( $_POST['omochix_prompt_csv_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['omochix_prompt_csv_hash'] ) ) : '';
		$execute_plan   = empty( $_FILES['omochix_prompt_csv_file'] )
			? new WP_Error( 'missing_file', __( 'dry-runしたCSVファイルを再度選択してください。', 'omochix-core' ) )
			: omochix_core_prompt_csv_plan( $_FILES['omochix_prompt_csv_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated in omochix_core_prompt_csv_plan().

		if ( is_wp_error( $execute_plan ) ) {
			$notice = $execute_plan;
		} elseif ( empty( $_POST['omochix_prompt_csv_confirm'] ) ) {
			$notice = new WP_Error( 'not_confirmed', __( 'dry-run結果の確認チェックが必要です。', 'omochix-core' ) );
		} elseif ( ! hash_equals( $execute_plan['hash'], $confirmed_hash ) ) {
			$notice = new WP_Error( 'hash_mismatch', __( 'dry-runで確認したファイルと内容が異なります。インポートは実行していません。もう一度dry-runからやり直してください。', 'omochix-core' ) );
		} elseif ( $execute_plan['errors'] ) {
			$notice = new WP_Error( 'has_errors', __( 'エラー行があるためインポートは実行していません。CSVを修正してdry-runからやり直してください。', 'omochix-core' ) );
			$plan   = $execute_plan;
		} else {
			$result             = omochix_core_prompt_csv_execute( $execute_plan['rows'] );
			$result['warnings'] = array_merge( $execute_plan['warnings'], $result['warnings'] );
		}
	}

	$action_labels = array(
		'create'    => __( '新規（下書き）', 'omochix-core' ),
		'update'    => __( '更新', 'omochix-core' ),
		'unchanged' => __( '変更なし', 'omochix-core' ),
	);
	?>
	<div class="wrap omochix-import-page">
		<h1><?php esc_html_e( 'プロンプト CSVインポート', 'omochix-core' ); ?></h1>
		<p class="description"><?php esc_html_e( 'まずdry-runで検証し、変更内容を確認してから同じファイルでインポートを実行します。dry-runはデータベースを一切変更しません。', 'omochix-core' ); ?></p>
		<?php if ( $notice ) : ?><div class="notice notice-error"><p><?php echo esc_html( $notice->get_error_message() ); ?></p></div><?php endif; ?>

		<?php if ( $result ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'インポートが完了しました。新規プロンプトは下書きとして作成されています。', 'omochix-core' ); ?></p></div>
			<?php
			omochix_core_render_prompt_csv_counts(
				array(
					__( '新規作成', 'omochix-core' ) => $result['created'],
					__( '更新', 'omochix-core' )     => $result['updated'],
					__( '変更なし', 'omochix-core' ) => $result['unchanged'],
					__( 'エラー', 'omochix-core' )   => count( $result['errors'] ),
				)
			);
			omochix_core_render_prompt_csv_messages( $result['errors'], __( 'インポート中のエラー', 'omochix-core' ), 'omochix-import-errors' );
			omochix_core_render_prompt_csv_messages( $result['warnings'], __( '警告', 'omochix-core' ), 'notice notice-warning inline' );
			?>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=prompt' ) ); ?>"><?php esc_html_e( 'プロンプト一覧を確認', 'omochix-core' ); ?></a></p>
		<?php elseif ( $plan ) : ?>
			<h2><?php esc_html_e( 'dry-run結果（データベースは変更されていません）', 'omochix-core' ); ?></h2>
			<?php
			omochix_core_render_prompt_csv_counts(
				array(
					__( '対象行', 'omochix-core' )     => $plan['summary']['total'],
					__( '新規（下書き）', 'omochix-core' ) => $plan['summary']['create'],
					__( '更新', 'omochix-core' )       => $plan['summary']['update'],
					__( '変更なし', 'omochix-core' )   => $plan['summary']['unchanged'],
					__( 'エラー行', 'omochix-core' )   => $plan['summary']['error_rows'],
				)
			);
			omochix_core_render_prompt_csv_messages( $plan['errors'], __( '修正が必要な行（インポートできません）', 'omochix-core' ), 'omochix-import-errors' );
			omochix_core_render_prompt_csv_messages( $plan['warnings'], __( '警告（インポートは可能です）', 'omochix-core' ), 'notice notice-warning inline' );
			?>
			<?php if ( $plan['rows'] ) : ?>
				<div class="omochix-import-table-wrap"><table class="widefat striped"><thead><tr>
					<th><?php esc_html_e( '行', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( '処理', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( 'タイトル / slug', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( '難易度', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( 'カテゴリー', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( '対応AI', 'omochix-core' ); ?></th>
					<th><?php esc_html_e( '変更項目', 'omochix-core' ); ?></th>
				</tr></thead><tbody>
				<?php foreach ( $plan['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['line'] ); ?></td>
						<td><?php echo esc_html( $action_labels[ $row['action'] ] ); ?></td>
						<td>
							<strong><?php echo esc_html( $row['title'] ); ?></strong><br><code><?php echo esc_html( $row['slug'] ); ?></code>
							<details><summary><?php esc_html_e( 'プロンプト本文', 'omochix-core' ); ?></summary><pre style="white-space: pre-wrap; max-height: 16em; overflow: auto;"><?php echo esc_html( $row['prompt_body'] ); ?></pre></details>
						</td>
						<td><?php echo esc_html( omochix_core_get_prompt_difficulty_label( $row['prompt_difficulty'] ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', wp_list_pluck( $row['prompt_category'], 'name' ) ) ?: '—' ); ?></td>
						<td><?php echo esc_html( implode( ', ', wp_list_pluck( $row['prompt_model'], 'name' ) ) ?: '—' ); ?></td>
						<td><?php echo esc_html( 'create' === $row['action'] ? __( 'すべて（新規）', 'omochix-core' ) : ( $row['changes'] ? implode( ', ', $row['changes'] ) : '—' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>

			<?php if ( ! $plan['errors'] && ( $plan['summary']['create'] || $plan['summary']['update'] ) ) : ?>
				<div class="omochix-import-panel">
					<h2><?php esc_html_e( 'インポートを実行', 'omochix-core' ); ?></h2>
					<p><?php esc_html_e( '確認のため、dry-runしたものと同じCSVファイルをもう一度選択してください。内容が1文字でも異なる場合は実行されません。', 'omochix-core' ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'omochix_core_prompt_csv_execute', 'omochix_core_prompt_csv_execute_nonce' ); ?>
						<input type="hidden" name="omochix_prompt_csv_hash" value="<?php echo esc_attr( $plan['hash'] ); ?>">
						<label for="omochix-prompt-csv-execute-file"><strong><?php esc_html_e( 'CSVファイル（dry-runと同じもの）', 'omochix-core' ); ?></strong></label>
						<input id="omochix-prompt-csv-execute-file" name="omochix_prompt_csv_file" type="file" accept=".csv,text/csv" required>
						<label><input type="checkbox" name="omochix_prompt_csv_confirm" value="1" required> <?php echo esc_html( sprintf( __( '上記の内容（新規%1$d件・更新%2$d件）を確認しました', 'omochix-core' ), $plan['summary']['create'], $plan['summary']['update'] ) ); ?></label>
						<p><button class="button button-primary" type="submit" name="omochix_prompt_csv_execute" value="1"><?php esc_html_e( 'インポートを実行', 'omochix-core' ); ?></button></p>
					</form>
				</div>
			<?php elseif ( ! $plan['errors'] ) : ?>
				<p><?php esc_html_e( '反映が必要な変更はありません。', 'omochix-core' ); ?></p>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=prompt&page=omochix-prompt-import' ) ); ?>"><?php esc_html_e( '別のCSVでやり直す', 'omochix-core' ); ?></a></p>
		<?php else : ?>
			<div class="omochix-import-panel">
				<h2><?php esc_html_e( 'CSVをdry-run', 'omochix-core' ); ?></h2>
				<p><?php echo esc_html( sprintf( __( '必須列：%1$s　任意列：%2$s', 'omochix-core' ), implode( ', ', omochix_core_prompt_csv_headers() ), implode( ', ', omochix_core_prompt_csv_optional_headers() ) ) ); ?></p>
				<ul class="ul-disc">
					<li><?php esc_html_e( 'UTF-8（BOMあり・なし可）。改行やカンマを含む値はダブルクォートで囲みます。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( 'slugが既存プロンプトと一致する行は更新、一致しない行は下書きとして新規作成します。既存プロンプトの公開状態は変更しません。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( 'difficultyはbeginner / intermediate / advanced。categories・modelsは既存タームのslugか名前をカンマ区切りで指定します（タームは自動作成しません）。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( '更新時、必須列の空欄はその項目を空にします。related_tool_slugsの空欄・列なしは既存の関連を変更しません。', 'omochix-core' ); ?></li>
				</ul>
				<p><a href="<?php echo esc_url( OMOCHIX_CORE_URL . 'sample/sample-prompts.csv' ); ?>" download><?php esc_html_e( 'サンプルCSVをダウンロード', 'omochix-core' ); ?></a></p>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'omochix_core_prompt_csv_dry_run', 'omochix_core_prompt_csv_nonce' ); ?>
					<label for="omochix-prompt-csv-file"><strong><?php esc_html_e( 'CSVファイル', 'omochix-core' ); ?></strong></label>
					<input id="omochix-prompt-csv-file" name="omochix_prompt_csv_file" type="file" accept=".csv,text/csv" required>
					<p><button class="button button-primary" type="submit" name="omochix_prompt_csv_dry_run" value="1"><?php esc_html_e( 'dry-run（検証のみ）', 'omochix-core' ); ?></button></p>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
