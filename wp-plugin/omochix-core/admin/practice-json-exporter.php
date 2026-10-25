<?php
/**
 * Admin: Learn Practice → JSON エクスポート (Phase 6A-S1).
 *
 * Downloads practice items in the importer's file shape: everything, one
 * status, or the posts ticked in the list (bulk action "JSON エクスポート").
 * Requires manage_options, like the importer. Nothing is written.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_PRACTICE_EXPORT_PAGE = 'omochix-practice-export';

function omochix_core_register_practice_exporter_page() {
	add_submenu_page(
		'edit.php?post_type=' . OMOCHIX_CORE_PRACTICE_POST_TYPE,
		__( '実践教材 JSON エクスポート', 'omochix-core' ),
		__( 'JSON エクスポート', 'omochix-core' ),
		'manage_options',
		OMOCHIX_CORE_PRACTICE_EXPORT_PAGE,
		'omochix_core_render_practice_exporter_page'
	);
}
add_action( 'admin_menu', 'omochix_core_register_practice_exporter_page' );

/** The export page URL, optionally preselecting post IDs. */
function omochix_core_practice_export_page_url( $ids = array() ) {
	$url = add_query_arg( array( 'post_type' => OMOCHIX_CORE_PRACTICE_POST_TYPE, 'page' => OMOCHIX_CORE_PRACTICE_EXPORT_PAGE ), admin_url( 'edit.php' ) );
	$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	return $ids ? add_query_arg( 'ids', implode( ',', $ids ), $url ) : $url;
}

/** Sanitized ID list from a comma-separated request value. */
function omochix_core_practice_export_parse_ids( $value ) {
	return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ) ) );
}

/**
 * Bulk action on the practice list: "JSON エクスポート" sends the ticked
 * posts to the export page (the download itself is a confirmed POST there).
 */
function omochix_core_practice_export_bulk_action( $actions ) {
	if ( current_user_can( 'manage_options' ) ) {
		$actions['omochix_practice_export_json'] = __( 'JSON エクスポート', 'omochix-core' );
	}
	return $actions;
}
add_filter( 'bulk_actions-edit-' . OMOCHIX_CORE_PRACTICE_POST_TYPE, 'omochix_core_practice_export_bulk_action' );

function omochix_core_practice_export_handle_bulk( $redirect, $action, $post_ids ) {
	if ( 'omochix_practice_export_json' !== $action || ! current_user_can( 'manage_options' ) ) {
		return $redirect;
	}
	return omochix_core_practice_export_page_url( $post_ids );
}
add_filter( 'handle_bulk_actions-edit-' . OMOCHIX_CORE_PRACTICE_POST_TYPE, 'omochix_core_practice_export_handle_bulk', 10, 3 );

/**
 * The download: a POST from the export page, verified, streamed as a file.
 * Runs on admin_init so no HTML has been sent yet.
 */
function omochix_core_practice_export_download() {
	if ( ! isset( $_POST['omochix_practice_export'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'この操作を行う権限がありません。', 'omochix-core' ) );
	}
	check_admin_referer( 'omochix_core_practice_export', 'omochix_core_practice_export_nonce' );

	$scope = isset( $_POST['omochix_practice_export_scope'] ) ? sanitize_key( wp_unslash( $_POST['omochix_practice_export_scope'] ) ) : 'all';
	if ( 'selected' === $scope ) {
		$ids = omochix_core_practice_export_parse_ids( isset( $_POST['omochix_practice_export_ids'] ) ? wp_unslash( $_POST['omochix_practice_export_ids'] ) : '' );
	} else {
		$ids = omochix_core_practice_export_ids( $scope );
	}
	$document = omochix_core_practice_export( $ids );
	if ( ! $document['items'] ) {
		wp_safe_redirect( add_query_arg( 'omochix_export_empty', '1', omochix_core_practice_export_page_url() ) );
		exit;
	}
	$json = wp_json_encode( $document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	$name = sprintf( 'omochix-practice-%s-%s.json', 'selected' === $scope ? 'selected' : $scope, gmdate( 'Ymd-His' ) );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . strlen( $json ) );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file body.
	exit;
}
add_action( 'admin_init', 'omochix_core_practice_export_download' );

function omochix_core_practice_admin_exporter_assets( $hook_suffix ) {
	if ( OMOCHIX_CORE_PRACTICE_POST_TYPE . '_page_' . OMOCHIX_CORE_PRACTICE_EXPORT_PAGE === $hook_suffix ) {
		wp_enqueue_style( 'omochix-core-admin', OMOCHIX_CORE_URL . 'admin/assets/admin.css', array(), OMOCHIX_CORE_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'omochix_core_practice_admin_exporter_assets' );

function omochix_core_render_practice_exporter_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'この操作を行う権限がありません。', 'omochix-core' ) );
	}
	$selected = omochix_core_practice_export_parse_ids( isset( $_GET['ids'] ) ? wp_unslash( $_GET['ids'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preselection from the bulk action.
	$selected = array_values( array_filter( $selected, static fn( $id ) => OMOCHIX_CORE_PRACTICE_POST_TYPE === get_post_type( $id ) ) );
	$counts   = wp_count_posts( OMOCHIX_CORE_PRACTICE_POST_TYPE );
	$scopes   = array(
		'all'     => sprintf( __( 'すべて（%d 件）', 'omochix-core' ), (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 ) + (int) ( $counts->pending ?? 0 ) + (int) ( $counts->future ?? 0 ) + (int) ( $counts->private ?? 0 ) ),
		'publish' => sprintf( __( '公開中（%d 件）', 'omochix-core' ), (int) ( $counts->publish ?? 0 ) ),
		'draft'   => sprintf( __( '下書き（%d 件）', 'omochix-core' ), (int) ( $counts->draft ?? 0 ) ),
	);
	?>
	<div class="wrap omochix-import-page">
		<h1><?php esc_html_e( '実践教材 JSON エクスポート', 'omochix-core' ); ?></h1>
		<p class="description"><?php esc_html_e( '実践教材を JSON インポートと同じ形式で書き出します。編集してから「JSON インポート」で dry-run → 反映すると、変更した項目だけが更新されます（書き出した直後のファイルは「変更なし」になります）。関連は slug で書き出されるので、環境をまたいでも使えます。データベースは変更しません。', 'omochix-core' ); ?></p>
		<?php if ( isset( $_GET['omochix_export_empty'] ) ) : ?><div class="notice notice-warning"><p><?php esc_html_e( '書き出す教材がありませんでした。', 'omochix-core' ); ?></p></div><?php endif; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>

		<form method="post" action="<?php echo esc_url( omochix_core_practice_export_page_url() ); ?>">
			<?php wp_nonce_field( 'omochix_core_practice_export', 'omochix_core_practice_export_nonce' ); ?>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( '書き出す範囲', 'omochix-core' ); ?></legend>
				<?php if ( $selected ) : ?>
					<p><label><input type="radio" name="omochix_practice_export_scope" value="selected" checked> <?php echo esc_html( sprintf( __( '一覧で選択した %d 件', 'omochix-core' ), count( $selected ) ) ); ?></label></p>
					<input type="hidden" name="omochix_practice_export_ids" value="<?php echo esc_attr( implode( ',', $selected ) ); ?>">
					<ul class="omochix-export-selected">
						<?php foreach ( $selected as $id ) : ?><li><code><?php echo esc_html( get_post_field( 'post_name', $id ) ); ?></code> <?php echo esc_html( get_the_title( $id ) ); ?>（<?php echo esc_html( get_post_status( $id ) ); ?>）</li><?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php foreach ( $scopes as $value => $label ) : ?>
					<p><label><input type="radio" name="omochix_practice_export_scope" value="<?php echo esc_attr( $value ); ?>" <?php checked( ! $selected && 'all' === $value ); ?>> <?php echo esc_html( $label ); ?></label></p>
				<?php endforeach; ?>
			</fieldset>
			<p><button type="submit" name="omochix_practice_export" value="1" class="button button-primary"><?php esc_html_e( 'JSON をダウンロード', 'omochix-core' ); ?></button></p>
		</form>

		<h2><?php esc_html_e( '書き出される内容', 'omochix-core' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'slug・タイトル・公開状態・抜粋・本文・表示順（空のものは省略）', 'omochix-core' ); ?></li>
			<li><?php esc_html_e( 'メタ：概要・難易度・所要時間・完成イメージ・プレビュー・プロンプト・コード・前提・手順・検証・安全性・よくあるエラー・公開手順・環境・検証日・参照仕様・更新履歴（空のものは省略）', 'omochix-core' ); ?></li>
			<li><?php esc_html_e( 'terms：分野・形式・用途・対応AI・技術の slug', 'omochix-core' ); ?></li>
			<li><?php esc_html_e( 'relations：関連教材・プロンプト・AIツール・Learn ページ・Motion の slug（削除済みの関連先は省略）', 'omochix-core' ); ?></li>
		</ul>
		<p class="description"><?php esc_html_e( '公開状態もそのまま書き出されます。書き出したファイルを編集して取り込むときは、dry-run の「公開されます」「公開が取り下げられます」の注意を確認してください。', 'omochix-core' ); ?></p>
	</div>
	<?php
}
