<?php
/**
 * Secure CSV importer for AI tools.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maximum upload size: 5 MiB. */
define( 'OMOCHIX_CORE_CSV_MAX_BYTES', 5 * MB_IN_BYTES );

/** Maximum data rows accepted in one operation. */
define( 'OMOCHIX_CORE_CSV_MAX_ROWS', 1000 );

/**
 * Register the importer below the AI tool post type.
 *
 * @return void
 */
function omochix_core_register_csv_import_page() {
	add_submenu_page(
		'edit.php?post_type=ai_tool',
		__( 'AIツール CSVインポート', 'omochix-core' ),
		__( 'CSVインポート', 'omochix-core' ),
		'manage_categories',
		'omochix-ai-tool-import',
		'omochix_core_render_csv_import_page'
	);
}
add_action( 'admin_menu', 'omochix_core_register_csv_import_page' );

/**
 * Return the formal ordered CSV contract.
 *
 * @return string[]
 */
function omochix_core_csv_headers() {
	return array(
		'title',
		'slug',
		'company_name',
		'official_url',
		'short_description',
		'pricing_type',
		'japanese_support',
		'tool_status',
		'rating_overall',
		'is_featured',
		'display_order',
		'category',
		'feature',
		'tag',
		'platform',
	);
}

/**
 * Check importer-level capabilities including taxonomy creation.
 *
 * @return bool
 */
function omochix_core_can_import_tools() {
	$post_type = get_post_type_object( 'ai_tool' );
	if ( ! $post_type || ! current_user_can( $post_type->cap->edit_posts ) ) {
		return false;
	}
	foreach ( array( 'ai_tool_category', 'ai_tool_feature', 'ai_tool_tag', 'ai_tool_platform' ) as $taxonomy_name ) {
		$taxonomy = get_taxonomy( $taxonomy_name );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->manage_terms ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Return a user-scoped transient key.
 *
 * @param string $token Random preview token.
 * @return string
 */
function omochix_core_csv_transient_key( $token ) {
	return 'omx_csv_' . get_current_user_id() . '_' . sanitize_key( $token );
}

/**
 * Normalize the requested status for newly created tools.
 *
 * Publishing additionally requires the post type's publish capability. Existing
 * posts never use this value, so their current publication state is preserved.
 *
 * @param mixed $status Submitted status.
 * @return string draft|publish
 */
function omochix_core_csv_new_post_status( $status ) {
	$status    = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';
	$post_type = get_post_type_object( 'ai_tool' );

	if ( 'publish' === $status && $post_type && current_user_can( $post_type->cap->publish_posts ) ) {
		return 'publish';
	}

	return 'draft';
}

/**
 * Return the administration label for a normalized creation status.
 *
 * @param mixed $status Creation status.
 * @return string
 */
function omochix_core_csv_new_post_status_label( $status ) {
	return 'publish' === omochix_core_csv_new_post_status( $status )
		? __( '公開として作成', 'omochix-core' )
		: __( '下書きとして作成', 'omochix-core' );
}

/**
 * Batch-load existing AI tool slugs.
 *
 * @return array<string, int>
 */
function omochix_core_existing_tool_slugs() {
	$query = new WP_Query(
		array(
			'post_type'      => 'ai_tool',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	$slugs = array();
	foreach ( $query->posts as $post_id ) {
		$slug = get_post_field( 'post_name', $post_id );
		if ( $slug ) {
			$slugs[ $slug ] = (int) $post_id;
		}
	}
	wp_reset_postdata();
	return $slugs;
}

/**
 * Parse a comma-separated taxonomy cell.
 *
 * @param string $value Cell value.
 * @return string[]
 */
function omochix_core_csv_term_names( $value ) {
	if ( '' === trim( $value ) ) {
		return array();
	}
	$terms = preg_split( '/[,、]/u', $value, -1, PREG_SPLIT_NO_EMPTY );
	$terms = array_map( 'sanitize_text_field', (array) $terms );
	$terms = array_filter( array_map( 'trim', $terms ) );
	return array_values( array_unique( $terms ) );
}

/**
 * Validate and normalize one CSV row.
 *
 * @param array<string, string> $row Associative raw row.
 * @param int                   $line CSV line number.
 * @return array{row: array<string, mixed>|null, errors: string[]}
 */
function omochix_core_normalize_csv_row( $row, $line ) {
	$errors = array();
	$title  = sanitize_text_field( $row['title'] );
	$slug   = sanitize_title( $row['slug'] );

	if ( '' === $title ) {
		$errors[] = __( 'titleは必須です。', 'omochix-core' );
	}
	if ( '' === $slug ) {
		$errors[] = __( 'slugは必須です。半角英数字を推奨します。', 'omochix-core' );
	}

	$official_url = '' === trim( $row['official_url'] ) ? '' : esc_url_raw( $row['official_url'], array( 'http', 'https' ) );
	if ( '' !== trim( $row['official_url'] ) && '' === $official_url ) {
		$errors[] = __( 'official_urlが有効なHTTP(S) URLではありません。', 'omochix-core' );
	}

	$enum_fields = array(
		'pricing_type'     => array( 'free', 'freemium', 'paid', 'trial', 'contact' ),
		'japanese_support' => array( 'full', 'partial', 'none', 'unknown' ),
		'tool_status'      => array( 'active', 'beta', 'waitlist', 'discontinued' ),
	);
	$enum_defaults = array( 'pricing_type' => 'contact', 'japanese_support' => 'unknown', 'tool_status' => 'active' );
	$enums         = array();
	foreach ( $enum_fields as $key => $allowed ) {
		$value = sanitize_key( $row[ $key ] );
		$value = '' === $value ? $enum_defaults[ $key ] : $value;
		if ( ! in_array( $value, $allowed, true ) ) {
			$errors[] = sprintf( __( '%1$sの値「%2$s」は許可されていません。', 'omochix-core' ), $key, $row[ $key ] );
		}
		$enums[ $key ] = $value;
	}

	$rating = trim( $row['rating_overall'] );
	if ( '' !== $rating && ( ! is_numeric( $rating ) || (float) $rating < 1 || (float) $rating > 5 ) ) {
		$errors[] = __( 'rating_overallは1.0〜5.0で入力してください。', 'omochix-core' );
	}
	$rating = '' === $rating ? 0 : round( (float) $rating, 1 );

	$featured_raw = strtolower( trim( $row['is_featured'] ) );
	$true_values  = array( '1', 'true', 'yes', 'on' );
	$false_values = array( '0', 'false', 'no', 'off', '' );
	if ( ! in_array( $featured_raw, array_merge( $true_values, $false_values ), true ) ) {
		$errors[] = __( 'is_featuredは1/0またはtrue/falseで入力してください。', 'omochix-core' );
	}
	$is_featured = in_array( $featured_raw, $true_values, true );

	$order_raw = trim( $row['display_order'] );
	if ( '' !== $order_raw && ! ctype_digit( $order_raw ) ) {
		$errors[] = __( 'display_orderは0以上の整数で入力してください。', 'omochix-core' );
	}

	if ( $errors ) {
		return array( 'row' => null, 'errors' => $errors );
	}

	return array(
		'row' => array(
			'line'               => $line,
			'title'              => $title,
			'slug'               => $slug,
			'company_name'       => sanitize_text_field( $row['company_name'] ),
			'official_url'       => $official_url,
			'short_description'  => sanitize_textarea_field( $row['short_description'] ),
			'pricing_type'       => $enums['pricing_type'],
			'japanese_support'   => $enums['japanese_support'],
			'tool_status'        => $enums['tool_status'],
			'rating_overall'     => $rating,
			'is_featured'        => $is_featured,
			'display_order'      => '' === $order_raw ? 0 : absint( $order_raw ),
			'ai_tool_category'   => omochix_core_csv_term_names( $row['category'] ),
			'ai_tool_feature'    => omochix_core_csv_term_names( $row['feature'] ),
			'ai_tool_tag'        => omochix_core_csv_term_names( $row['tag'] ),
			'ai_tool_platform'   => omochix_core_csv_term_names( $row['platform'] ),
		),
		'errors' => array(),
	);
}

/**
 * Parse and validate an uploaded CSV file.
 *
 * @param array<string, mixed> $file Uploaded file array.
 * @return array{rows: array<int, array<string, mixed>>, errors: array<int, array{line:int,reason:string}>}|WP_Error
 */
function omochix_core_parse_csv_upload( $file ) {
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

	$handle = fopen( $file['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( false === $handle ) {
		return new WP_Error( 'file_open', __( 'CSVを読み込めません。', 'omochix-core' ) );
	}
	$headers = fgetcsv( $handle, 0, ',', '"', '' );
	if ( false === $headers ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'empty_csv', __( 'CSVが空です。', 'omochix-core' ) );
	}
	$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $headers[0] );
	$headers    = array_map( static function ( $value ) { return trim( (string) $value ); }, $headers );
	$missing    = array_diff( omochix_core_csv_headers(), $headers );
	if ( $missing ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'missing_headers', sprintf( __( '必須ヘッダーがありません：%s', 'omochix-core' ), implode( ', ', $missing ) ) );
	}

	$rows      = array();
	$errors    = array();
	$seen      = array();
	$line      = 1;
	$row_count = 0;
	while ( false !== ( $values = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
		++$line;
		if ( 1 === count( $values ) && '' === trim( (string) $values[0] ) ) {
			continue;
		}
		++$row_count;
		if ( $row_count > OMOCHIX_CORE_CSV_MAX_ROWS ) {
			$errors[] = array( 'line' => $line, 'reason' => __( '1回の上限1000件を超えています。', 'omochix-core' ) );
			break;
		}
		if ( count( $values ) !== count( $headers ) ) {
			$errors[] = array( 'line' => $line, 'reason' => __( '列数がヘッダーと一致しません。カンマを含む値はダブルクォートで囲んでください。', 'omochix-core' ) );
			continue;
		}
		$combined = array_combine( $headers, array_map( static function ( $value ) { return (string) $value; }, $values ) );
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( implode( '', $combined ), 'UTF-8' ) ) {
			$errors[] = array( 'line' => $line, 'reason' => __( 'UTF-8ではない文字が含まれています。', 'omochix-core' ) );
			continue;
		}
		$normalized = omochix_core_normalize_csv_row( $combined, $line );
		if ( $normalized['errors'] ) {
			foreach ( $normalized['errors'] as $reason ) {
				$errors[] = array( 'line' => $line, 'reason' => $reason );
			}
			continue;
		}
		$slug = $normalized['row']['slug'];
		if ( isset( $seen[ $slug ] ) ) {
			$errors[] = array( 'line' => $line, 'reason' => sprintf( __( 'slug「%1$s」が%2$d行目と重複しています。', 'omochix-core' ), $slug, $seen[ $slug ] ) );
			continue;
		}
		$seen[ $slug ] = $line;
		$rows[]        = $normalized['row'];
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return array( 'rows' => $rows, 'errors' => $errors );
}

/**
 * Create missing terms and replace a tool's terms for one taxonomy.
 *
 * @param int      $post_id  Post ID.
 * @param string   $taxonomy Taxonomy name.
 * @param string[] $names    Term names.
 * @return true|WP_Error
 */
function omochix_core_import_tool_terms( $post_id, $taxonomy, $names ) {
	$term_ids = array();
	foreach ( $names as $name ) {
		$existing = term_exists( $name, $taxonomy );
		if ( ! $existing ) {
			$existing = wp_insert_term( $name, $taxonomy, array( 'slug' => sanitize_title( $name ) ) );
		}
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		$term_ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
	}
	$result = wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
	return is_wp_error( $result ) ? $result : true;
}

/**
 * Import validated rows and return a summary.
 *
 * @param array<int, array<string, mixed>> $rows            Valid rows.
 * @param string                           $new_post_status Status for new posts only.
 * @return array{total:int,created:int,updated:int,errors:array<int,array{line:int,reason:string}>}
 */
function omochix_core_execute_csv_import( $rows, $new_post_status = 'draft' ) {
	$existing        = omochix_core_existing_tool_slugs();
	$summary         = array( 'total' => count( $rows ), 'created' => 0, 'updated' => 0, 'errors' => array() );
	$meta_keys       = array( 'company_name', 'official_url', 'short_description', 'pricing_type', 'japanese_support', 'tool_status', 'rating_overall', 'is_featured', 'display_order' );
	$new_post_status = omochix_core_csv_new_post_status( $new_post_status );

	foreach ( $rows as $row_index => $row ) {
		// Each normalized row is independent; release the local copy as it is consumed.
		unset( $rows[ $row_index ] );
		$post_id = isset( $existing[ $row['slug'] ] ) ? $existing[ $row['slug'] ] : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			$summary['errors'][] = array( 'line' => $row['line'], 'reason' => __( '既存ツールを更新する権限がありません。', 'omochix-core' ) );
			continue;
		}
		$post_data = array(
			'ID'         => $post_id,
			'post_type'  => 'ai_tool',
			'post_title' => $row['title'],
			'post_name'  => $row['slug'],
		);
		if ( ! $post_id ) {
			$post_data['post_status'] = $new_post_status;
		}
		$result = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $result ) ) {
			$summary['errors'][] = array( 'line' => $row['line'], 'reason' => $result->get_error_message() );
			continue;
		}
		$post_id = (int) $result;
		foreach ( $meta_keys as $key ) {
			$value = omochix_core_sanitize_meta_value( $row[ $key ], $key );
			if ( ( in_array( $key, array( 'company_name', 'official_url', 'short_description' ), true ) && '' === $value ) || ( 'rating_overall' === $key && 0 === $value ) ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		$term_error = false;
		foreach ( array( 'ai_tool_category', 'ai_tool_feature', 'ai_tool_tag', 'ai_tool_platform' ) as $taxonomy ) {
			$term_result = omochix_core_import_tool_terms( $post_id, $taxonomy, $row[ $taxonomy ] );
			if ( is_wp_error( $term_result ) ) {
				$summary['errors'][] = array( 'line' => $row['line'], 'reason' => sprintf( __( '%1$sの登録に失敗しました：%2$s', 'omochix-core' ), $taxonomy, $term_result->get_error_message() ) );
				$term_error = true;
				break;
			}
		}
		if ( $term_error ) {
			continue;
		}
		if ( isset( $existing[ $row['slug'] ] ) ) {
			++$summary['updated'];
		} else {
			++$summary['created'];
			$existing[ $row['slug'] ] = $post_id;
		}
	}
	return $summary;
}

/**
 * Render summary counters.
 *
 * @param array<string, mixed> $summary Summary data.
 * @return void
 */
function omochix_core_render_csv_summary( $summary ) {
	?>
	<dl class="omochix-import-summary">
		<div><dt><?php esc_html_e( '対象件数', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $summary['total'] ) ); ?></dd></div>
		<div><dt><?php esc_html_e( '新規件数', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $summary['created'] ) ); ?></dd></div>
		<div><dt><?php esc_html_e( '更新件数', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $summary['updated'] ) ); ?></dd></div>
		<div><dt><?php esc_html_e( 'エラー件数', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( omochix_core_csv_error_row_count( $summary['errors'] ) ) ); ?></dd></div>
	</dl>
	<?php
}

/**
 * Count affected CSV rows rather than multiple reasons on the same row.
 *
 * @param array<int, array{line:int,reason:string}> $errors Errors.
 * @return int
 */
function omochix_core_csv_error_row_count( $errors ) {
	return count( array_unique( array_map( 'absint', wp_list_pluck( $errors, 'line' ) ) ) );
}

/**
 * Render line-level errors.
 *
 * @param array<int, array{line:int,reason:string}> $errors Errors.
 * @return void
 */
function omochix_core_render_csv_errors( $errors ) {
	if ( ! $errors ) {
		return;
	}
	?>
	<div class="omochix-import-errors" role="alert"><h2><?php esc_html_e( '確認が必要な行', 'omochix-core' ); ?></h2><ul>
		<?php foreach ( $errors as $error ) : ?><li><strong><?php echo esc_html( sprintf( __( '%d行目', 'omochix-core' ), $error['line'] ) ); ?></strong>：<?php echo esc_html( $error['reason'] ); ?></li><?php endforeach; ?>
	</ul></div>
	<?php
}

/**
 * Render the upload, preview and execution workflow.
 *
 * @return void
 */
function omochix_core_render_csv_import_page() {
	if ( ! omochix_core_can_import_tools() ) {
		wp_die( esc_html__( 'AIツールをインポートする権限がありません。', 'omochix-core' ) );
	}

	$preview = null;
	$result  = null;
	$notice  = null;
	if ( isset( $_POST['omochix_csv_preview'] ) ) {
		check_admin_referer( 'omochix_core_csv_preview', 'omochix_core_csv_nonce' );
		$new_post_status = isset( $_POST['omochix_csv_new_post_status'] )
			? omochix_core_csv_new_post_status( wp_unslash( $_POST['omochix_csv_new_post_status'] ) )
			: 'draft';
		if ( empty( $_FILES['omochix_csv_file'] ) ) {
			$notice = new WP_Error( 'missing_file', __( 'CSVファイルを選択してください。', 'omochix-core' ) );
		} else {
			$preview = omochix_core_parse_csv_upload( $_FILES['omochix_csv_file'] );
			if ( is_wp_error( $preview ) ) {
				$notice  = $preview;
				$preview = null;
			} else {
				$existing = omochix_core_existing_tool_slugs();
				$created  = 0;
				$updated  = 0;
				foreach ( $preview['rows'] as $row ) {
					isset( $existing[ $row['slug'] ] ) ? ++$updated : ++$created;
				}
				$preview['summary'] = array(
					'total'   => count( $preview['rows'] ) + omochix_core_csv_error_row_count( $preview['errors'] ),
					'created' => $created,
					'updated' => $updated,
					'errors'  => $preview['errors'],
				);
				$preview['new_post_status'] = $new_post_status;
				$token = wp_generate_password( 20, false, false );
				set_transient( omochix_core_csv_transient_key( $token ), $preview, 30 * MINUTE_IN_SECONDS );
				$preview['token'] = $token;
			}
		}
	} elseif ( isset( $_POST['omochix_csv_execute'], $_POST['omochix_csv_token'] ) ) {
		check_admin_referer( 'omochix_core_csv_execute', 'omochix_core_csv_execute_nonce' );
		$token   = sanitize_key( wp_unslash( $_POST['omochix_csv_token'] ) );
		$preview = get_transient( omochix_core_csv_transient_key( $token ) );
		if ( ! is_array( $preview ) || empty( $preview['rows'] ) ) {
			$notice  = new WP_Error( 'expired_preview', __( 'プレビューの有効期限が切れました。CSVを再アップロードしてください。', 'omochix-core' ) );
			$preview = null;
		} else {
			$new_post_status = isset( $preview['new_post_status'] )
				? omochix_core_csv_new_post_status( $preview['new_post_status'] )
				: 'draft';
			$result = omochix_core_execute_csv_import( $preview['rows'], $new_post_status );
			$result['total'] += omochix_core_csv_error_row_count( $preview['errors'] );
			$result['errors'] = array_merge( $preview['errors'], $result['errors'] );
			delete_transient( omochix_core_csv_transient_key( $token ) );
			$preview = null;
		}
	}
	?>
	<div class="wrap omochix-import-page">
		<h1><?php esc_html_e( 'AIツール CSVインポート', 'omochix-core' ); ?></h1>
		<p class="description"><?php esc_html_e( 'UTF-8のCSVをアップロードし、内容と新規ツールの作成状態を確認してから反映します。', 'omochix-core' ); ?></p>
		<?php if ( $notice ) : ?><div class="notice notice-error"><p><?php echo esc_html( $notice->get_error_message() ); ?></p></div><?php endif; ?>

		<?php if ( $result ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'CSVインポートが完了しました。', 'omochix-core' ); ?></p></div>
			<?php omochix_core_render_csv_summary( $result ); ?>
			<?php omochix_core_render_csv_errors( $result['errors'] ); ?>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=ai_tool' ) ); ?>"><?php esc_html_e( 'AIツール一覧を確認', 'omochix-core' ); ?></a></p>
		<?php elseif ( $preview ) : ?>
			<h2><?php esc_html_e( 'インポート内容のプレビュー', 'omochix-core' ); ?></h2>
			<?php omochix_core_render_csv_summary( $preview['summary'] ); ?>
			<p><strong><?php esc_html_e( '新規投稿の作成状態：', 'omochix-core' ); ?></strong><?php echo esc_html( omochix_core_csv_new_post_status_label( isset( $preview['new_post_status'] ) ? $preview['new_post_status'] : 'draft' ) ); ?></p>
			<?php omochix_core_render_csv_errors( $preview['errors'] ); ?>
			<?php if ( $preview['rows'] ) : ?>
				<div class="omochix-import-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( '行', 'omochix-core' ); ?></th><th><?php esc_html_e( '処理', 'omochix-core' ); ?></th><th><?php esc_html_e( 'タイトル', 'omochix-core' ); ?></th><th><?php esc_html_e( 'slug', 'omochix-core' ); ?></th><th><?php esc_html_e( '会社', 'omochix-core' ); ?></th><th><?php esc_html_e( '料金', 'omochix-core' ); ?></th><th><?php esc_html_e( '日本語', 'omochix-core' ); ?></th></tr></thead><tbody>
				<?php $existing = omochix_core_existing_tool_slugs(); foreach ( array_slice( $preview['rows'], 0, 100 ) as $row ) : ?><tr><td><?php echo esc_html( $row['line'] ); ?></td><td><?php echo esc_html( isset( $existing[ $row['slug'] ] ) ? __( '更新', 'omochix-core' ) : __( '新規', 'omochix-core' ) ); ?></td><td><?php echo esc_html( $row['title'] ); ?></td><td><code><?php echo esc_html( $row['slug'] ); ?></code></td><td><?php echo esc_html( $row['company_name'] ?: '—' ); ?></td><td><?php echo esc_html( omochix_core_get_pricing_type_label( $row['pricing_type'] ) ); ?></td><td><?php echo esc_html( omochix_core_get_japanese_support_label( $row['japanese_support'] ) ); ?></td></tr><?php endforeach; ?>
				</tbody></table></div>
				<?php if ( count( $preview['rows'] ) > 100 ) : ?><p class="description"><?php esc_html_e( 'プレビュー表は先頭100件を表示しています。全件がインポート対象です。', 'omochix-core' ); ?></p><?php endif; ?>
				<form method="post">
					<?php wp_nonce_field( 'omochix_core_csv_execute', 'omochix_core_csv_execute_nonce' ); ?>
					<input type="hidden" name="omochix_csv_token" value="<?php echo esc_attr( $preview['token'] ); ?>">
					<p><button class="button button-primary button-hero" type="submit" name="omochix_csv_execute" value="1"><?php esc_html_e( 'インポートを実行', 'omochix-core' ); ?></button></p>
				</form>
			<?php endif; ?>
		<?php else : ?>
			<div class="omochix-import-panel">
				<h2><?php esc_html_e( 'CSVアップロード', 'omochix-core' ); ?></h2>
				<p><?php esc_html_e( '全15列のヘッダーを含むUTF-8 CSVを選択してください。BOMあり・なしの両方に対応します。', 'omochix-core' ); ?></p>
				<p><a href="<?php echo esc_url( OMOCHIX_CORE_URL . 'sample/sample-ai-tools.csv' ); ?>" download><?php esc_html_e( 'サンプルCSVをダウンロード', 'omochix-core' ); ?></a></p>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'omochix_core_csv_preview', 'omochix_core_csv_nonce' ); ?>
					<label for="omochix-csv-file"><strong><?php esc_html_e( 'CSVファイル', 'omochix-core' ); ?></strong></label>
					<input id="omochix-csv-file" name="omochix_csv_file" type="file" accept=".csv,text/csv" required>
					<fieldset>
						<legend><strong><?php esc_html_e( '新規投稿の作成状態', 'omochix-core' ); ?></strong></legend>
						<label><input type="radio" name="omochix_csv_new_post_status" value="draft" checked> <?php esc_html_e( '下書きとして作成（デフォルト）', 'omochix-core' ); ?></label><br>
						<label><input type="radio" name="omochix_csv_new_post_status" value="publish" <?php disabled( ! current_user_can( get_post_type_object( 'ai_tool' )->cap->publish_posts ) ); ?>> <?php esc_html_e( '公開として作成', 'omochix-core' ); ?></label>
					</fieldset>
					<p><button class="button button-primary" type="submit" name="omochix_csv_preview" value="1"><?php esc_html_e( 'アップロードしてプレビュー', 'omochix-core' ); ?></button></p>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
