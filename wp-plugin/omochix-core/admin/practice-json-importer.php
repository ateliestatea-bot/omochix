<?php
/**
 * Learn Practice JSON importer: the admin page (Learn Phase 4C).
 *
 * Same safety flow as the AI Tool Content Updater: upload → dry-run (no
 * writes) → re-upload the identical file (SHA-256 must match) with a
 * confirmation → apply → rollback JSON. Requires manage_options. The logic
 * lives in includes/practice-importer.php.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function omochix_core_register_practice_importer_page() {
	add_submenu_page(
		'edit.php?post_type=' . OMOCHIX_CORE_PRACTICE_POST_TYPE,
		__( '実践教材 JSON インポート', 'omochix-core' ),
		__( 'JSON インポート', 'omochix-core' ),
		'manage_options',
		'omochix-practice-import',
		'omochix_core_render_practice_importer_page'
	);
}
add_action( 'admin_menu', 'omochix_core_register_practice_importer_page' );

/** Read an uploaded file safely, or return WP_Error. */
function omochix_core_practice_import_read_upload( $file ) {
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'invalid_upload', __( 'アップロードされたファイルを確認できません。', 'omochix-core' ) );
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'upload_error', __( 'アップロードに失敗しました。', 'omochix-core' ) );
	}
	if ( (int) $file['size'] <= 0 || (int) $file['size'] > OMOCHIX_CORE_PRACTICE_IMPORT_MAX_BYTES ) {
		return new WP_Error( 'file_size', __( 'JSONは5MB以下にしてください。', 'omochix-core' ) );
	}
	if ( 'json' !== strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) ) ) {
		return new WP_Error( 'file_extension', __( '拡張子.jsonのファイルを選択してください。', 'omochix-core' ) );
	}
	$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	return false === $contents ? new WP_Error( 'read_error', __( 'ファイルを読めませんでした。', 'omochix-core' ) ) : $contents;
}

function omochix_core_practice_import_plan_upload( $file ) {
	$contents = omochix_core_practice_import_read_upload( $file );
	return is_wp_error( $contents ) ? $contents : omochix_core_practice_import_plan( $contents );
}

function omochix_core_practice_admin_importer_assets( $hook_suffix ) {
	if ( OMOCHIX_CORE_PRACTICE_POST_TYPE . '_page_omochix-practice-import' === $hook_suffix ) {
		wp_enqueue_style( 'omochix-core-admin', OMOCHIX_CORE_URL . 'admin/assets/admin.css', array(), OMOCHIX_CORE_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'omochix_core_practice_admin_importer_assets' );

function omochix_core_render_practice_importer_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'この操作を行う権限がありません。', 'omochix-core' ) );
	}
	$plan   = null;
	$result = null;
	$notice = null;

	if ( isset( $_POST['omochix_practice_import_dry_run'] ) ) {
		check_admin_referer( 'omochix_core_practice_import_dry_run', 'omochix_core_practice_import_nonce' );
		$plan = empty( $_FILES['omochix_practice_import_file'] )
			? new WP_Error( 'missing_file', __( 'JSONファイルを選択してください。', 'omochix-core' ) )
			: omochix_core_practice_import_plan_upload( $_FILES['omochix_practice_import_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in omochix_core_practice_import_read_upload().
		if ( is_wp_error( $plan ) ) {
			$notice = $plan;
			$plan   = null;
		}
	} elseif ( isset( $_POST['omochix_practice_import_apply'] ) ) {
		check_admin_referer( 'omochix_core_practice_import_apply', 'omochix_core_practice_import_apply_nonce' );
		$confirmed_hash = isset( $_POST['omochix_practice_import_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['omochix_practice_import_hash'] ) ) : '';
		$apply_plan     = empty( $_FILES['omochix_practice_import_file'] )
			? new WP_Error( 'missing_file', __( 'dry-runしたJSONファイルを再度選択してください。', 'omochix-core' ) )
			: omochix_core_practice_import_plan_upload( $_FILES['omochix_practice_import_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_wp_error( $apply_plan ) ) {
			$notice = $apply_plan;
		} elseif ( empty( $_POST['omochix_practice_import_confirm'] ) ) {
			$notice = new WP_Error( 'not_confirmed', __( 'dry-run結果の確認チェックが必要です。', 'omochix-core' ) );
		} elseif ( ! hash_equals( $apply_plan['hash'], $confirmed_hash ) ) {
			$notice = new WP_Error( 'hash_mismatch', __( 'dry-runで確認したファイルと内容が異なります。反映は実行していません。もう一度dry-runからやり直してください。', 'omochix-core' ) );
		} elseif ( $apply_plan['errors'] ) {
			$notice = new WP_Error( 'has_errors', __( 'エラーがあるため反映は実行していません。JSONを修正してdry-runからやり直してください。', 'omochix-core' ) );
			$plan   = $apply_plan;
		} else {
			$result = omochix_core_practice_import_execute( $apply_plan['rows'] );
			omochix_core_practice_import_log( $apply_plan['hash'], $result, get_current_user_id() );
		}
	}
	$labels = array( 'create' => __( '新規（下書き or 指定の status）', 'omochix-core' ), 'update' => __( '更新', 'omochix-core' ), 'unchanged' => __( '変更なし', 'omochix-core' ) );
	?>
	<div class="wrap omochix-import-page">
		<h1><?php esc_html_e( '実践教材 JSON インポート', 'omochix-core' ); ?></h1>
		<p class="description"><?php esc_html_e( 'JSON ファイルから実践教材を作成・更新します。slug で既存と照合するので、同じファイルを何度実行しても重複は作られません。まず dry-run で内容を確認してから、同じファイルで反映します。dry-run はデータベースを一切変更しません。', 'omochix-core' ); ?></p>
		<?php if ( $notice ) : ?><div class="notice notice-error"><p><?php echo esc_html( $notice->get_error_message() ); ?></p></div><?php endif; ?>

		<?php if ( $result ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( '反映が完了しました。', 'omochix-core' ); ?></p></div>
			<dl class="omochix-import-summary">
				<div><dt><?php esc_html_e( '新規', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $result['created'] ) ); ?></dd></div>
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
					<p><?php esc_html_e( '今回更新した教材の、更新前の全フィールドです。保存しておくと手動での復元に使えます。WordPress のリビジョンからも本文・タイトルは復元できます。', 'omochix-core' ); ?></p>
					<p><a class="button" download="omochix-practice-import-backup.json" href="data:application/json;charset=utf-8,<?php echo rawurlencode( $backup_json ); ?>"><?php esc_html_e( 'バックアップJSONをダウンロード', 'omochix-core' ); ?></a></p>
					<details><summary><?php esc_html_e( '内容を表示', 'omochix-core' ); ?></summary><textarea readonly rows="12" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $backup_json ); ?></textarea></details>
				</div>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OMOCHIX_CORE_PRACTICE_POST_TYPE . '&page=omochix-practice-import' ) ); ?>"><?php esc_html_e( '別のJSONをインポートする', 'omochix-core' ); ?></a></p>

		<?php elseif ( $plan ) : ?>
			<h2><?php esc_html_e( 'dry-run 結果', 'omochix-core' ); ?></h2>
			<?php if ( 'backup' === ( $plan['source'] ?? 'file' ) ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'バックアップ JSON を読み込みました。反映すると、各教材はバックアップ時点の内容に戻ります（バックアップ時に空だった項目は空に戻り、関連先が削除されていればその関連は外れます）。', 'omochix-core' ); ?></p></div>
			<?php endif; ?>
			<dl class="omochix-import-summary">
				<div><dt><?php esc_html_e( '件数', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $plan['summary']['total'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( '新規', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $plan['summary']['create'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( '更新', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $plan['summary']['update'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( '変更なし', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $plan['summary']['unchanged'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'エラー', 'omochix-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( count( $plan['errors'] ) ) ); ?></dd></div>
			</dl>
			<p><code>SHA-256: <?php echo esc_html( $plan['hash'] ); ?></code></p>
			<?php if ( $plan['errors'] ) : ?>
				<div class="omochix-import-errors" role="alert"><h2><?php esc_html_e( 'エラー（反映できません）', 'omochix-core' ); ?></h2><ul><?php foreach ( $plan['errors'] as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul></div>
			<?php endif; ?>
			<?php if ( $plan['warnings'] ) : ?>
				<div class="omochix-import-warnings"><h2><?php esc_html_e( '警告（反映は可能）', 'omochix-core' ); ?></h2><ul><?php foreach ( $plan['warnings'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul></div>
			<?php endif; ?>
			<?php if ( $plan['rows'] ) : ?>
				<div class="omochix-import-table"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'slug', 'omochix-core' ); ?></th><th><?php esc_html_e( '処理', 'omochix-core' ); ?></th><th><?php esc_html_e( 'フィールド', 'omochix-core' ); ?></th><th><?php esc_html_e( '変更前', 'omochix-core' ); ?></th><th><?php esc_html_e( '変更後', 'omochix-core' ); ?></th></tr></thead><tbody>
					<?php foreach ( $plan['rows'] as $row ) : ?>
						<?php if ( ! $row['changes'] ) : ?>
							<tr><td><code><?php echo esc_html( $row['slug'] ); ?></code></td><td><?php echo esc_html( $labels[ $row['action'] ] ); ?></td><td colspan="3">—</td></tr>
						<?php else : ?>
							<?php foreach ( $row['changes'] as $i => $change ) : ?>
								<tr>
									<?php if ( 0 === $i ) : ?><td rowspan="<?php echo esc_attr( count( $row['changes'] ) ); ?>"><code><?php echo esc_html( $row['slug'] ); ?></code></td><td rowspan="<?php echo esc_attr( count( $row['changes'] ) ); ?>"><?php echo esc_html( $labels[ $row['action'] ] ); ?></td><?php endif; ?>
									<td><code><?php echo esc_html( $change['field'] ); ?></code></td><td><?php echo esc_html( $change['before'] ); ?></td><td><?php echo esc_html( $change['after'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
			<?php if ( ! $plan['errors'] && ( $plan['summary']['create'] || $plan['summary']['update'] ) ) : ?>
				<div class="omochix-import-panel">
					<h2><?php esc_html_e( '反映を実行', 'omochix-core' ); ?></h2>
					<p><?php echo esc_html( sprintf( __( '新規 %1$d 件を作成し、既存 %2$d 件を更新します。', 'omochix-core' ), $plan['summary']['create'], $plan['summary']['update'] ) ); ?></p>
					<p><?php esc_html_e( '確認のため、dry-run したものと同じ JSON ファイルをもう一度選択してください。内容が 1 文字でも異なる場合は実行されません。', 'omochix-core' ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'omochix_core_practice_import_apply', 'omochix_core_practice_import_apply_nonce' ); ?>
						<input type="hidden" name="omochix_practice_import_hash" value="<?php echo esc_attr( $plan['hash'] ); ?>">
						<label for="omochix-practice-import-apply-file"><strong><?php esc_html_e( 'JSONファイル（dry-runと同じもの）', 'omochix-core' ); ?></strong></label>
						<input id="omochix-practice-import-apply-file" name="omochix_practice_import_file" type="file" accept=".json,application/json" required>
						<label><input type="checkbox" name="omochix_practice_import_confirm" value="1" required> <?php esc_html_e( 'dry-run の内容を確認しました。', 'omochix-core' ); ?></label>
						<p><button class="button button-primary" type="submit" name="omochix_practice_import_apply" value="1"><?php esc_html_e( '反映を実行', 'omochix-core' ); ?></button></p>
					</form>
				</div>
			<?php elseif ( ! $plan['errors'] ) : ?>
				<p><?php esc_html_e( '反映が必要な変更はありません。', 'omochix-core' ); ?></p>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OMOCHIX_CORE_PRACTICE_POST_TYPE . '&page=omochix-practice-import' ) ); ?>"><?php esc_html_e( '別のJSONでやり直す', 'omochix-core' ); ?></a></p>

		<?php else : ?>
			<div class="omochix-import-panel">
				<h2><?php esc_html_e( 'JSONをdry-run', 'omochix-core' ); ?></h2>
				<p><?php esc_html_e( '形式：{"version": 1, "items": [ {"slug": "…", "title": "…", "status": "draft", "excerpt": "…", "meta": {…}, "terms": {"learn_field": ["web"]}, "relations": {"prompts": ["slug"], "tools": ["slug"], "learn": ["learn/basics"], "practice": ["slug"], "motion": ["fade-up"]}} ]}', 'omochix-core' ); ?></p>
				<ul class="ul-disc">
					<li><?php esc_html_e( 'slug が既存の教材と一致すれば更新、なければ新規作成（status 未指定なら下書き）。同じファイルを再実行しても重複しません。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( 'JSON に無いキーや null のキーは変更されません。"" や [] は空にします。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( 'status を publish にするには必須項目（説明・所要時間・完成イメージ・プロンプト・手順・検証・安全性・検証日・出典）が揃っている必要があります。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( 'terms のタームは先に管理画面で作成してください（自動作成しません）。relations の不明な slug は警告として無視されます。', 'omochix-core' ); ?></li>
					<li><?php esc_html_e( 'API キー・トークン・Webhook URL らしき文字列があるとエラーになります。公開コンテンツには ＜ここに貼る：…＞ の形のプレースホルダーだけを書いてください。', 'omochix-core' ); ?></li>
				</ul>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'omochix_core_practice_import_dry_run', 'omochix_core_practice_import_nonce' ); ?>
					<label for="omochix-practice-import-file"><strong><?php esc_html_e( 'JSONファイル', 'omochix-core' ); ?></strong></label>
					<input id="omochix-practice-import-file" name="omochix_practice_import_file" type="file" accept=".json,application/json" required>
					<p><button class="button button-primary" type="submit" name="omochix_practice_import_dry_run" value="1"><?php esc_html_e( 'dry-run（検証のみ）', 'omochix-core' ); ?></button></p>
				</form>
			</div>
			<?php $log = omochix_core_practice_import_get_log(); ?>
			<?php if ( $log ) : ?>
				<div class="omochix-import-panel">
					<h2><?php esc_html_e( '最近のインポート', 'omochix-core' ); ?></h2>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( '日時', 'omochix-core' ); ?></th><th><?php esc_html_e( '実行者', 'omochix-core' ); ?></th><th><?php esc_html_e( '新規 / 更新 / 変更なし / 失敗', 'omochix-core' ); ?></th><th><?php esc_html_e( 'slug', 'omochix-core' ); ?></th><th>SHA-256</th></tr></thead><tbody>
						<?php foreach ( array_slice( $log, 0, 10 ) as $entry ) : ?>
							<?php $user = get_userdata( (int) $entry['user_id'] ); ?>
							<tr><td><?php echo esc_html( $entry['at'] ); ?></td><td><?php echo esc_html( $user ? $user->display_name : '#' . (int) $entry['user_id'] ); ?></td><td><?php echo esc_html( sprintf( '%d / %d / %d / %d', $entry['created'], $entry['updated'], $entry['unchanged'], $entry['failed'] ) ); ?></td><td><?php echo esc_html( implode( ', ', (array) $entry['slugs'] ) ); ?></td><td><code><?php echo esc_html( substr( (string) $entry['hash'], 0, 12 ) ); ?>…</code></td></tr>
						<?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
