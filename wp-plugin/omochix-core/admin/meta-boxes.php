<?php
/**
 * AI tool editor fields.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register ordered editor panels.
 *
 * @return void
 */
function omochix_core_add_meta_boxes() {
	add_meta_box( 'omochix-tool-basic', __( '1. 基本情報', 'omochix-core' ), 'omochix_core_render_basic_meta_box', 'ai_tool', 'normal', 'high' );
	add_meta_box( 'omochix-tool-pricing', __( '2. 料金・利用条件', 'omochix-core' ), 'omochix_core_render_pricing_meta_box', 'ai_tool', 'normal', 'high' );
	add_meta_box( 'omochix-tool-platforms', __( '3. 対応環境', 'omochix-core' ), 'omochix_core_render_platforms_note', 'ai_tool', 'normal', 'default' );
	add_meta_box( 'omochix-tool-rating', __( '4. 編集部評価', 'omochix-core' ), 'omochix_core_render_rating_meta_box', 'ai_tool', 'normal', 'default' );
	add_meta_box( 'omochix-tool-features', __( '5. 特徴・向いている人', 'omochix-core' ), 'omochix_core_render_features_meta_box', 'ai_tool', 'normal', 'default' );
	add_meta_box( 'omochix-tool-relations', __( '6. 関連コンテンツ', 'omochix-core' ), 'omochix_core_render_relations_note', 'ai_tool', 'normal', 'low' );
	add_meta_box( 'omochix-tool-display', __( '7. 表示設定', 'omochix-core' ), 'omochix_core_render_display_meta_box', 'ai_tool', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'omochix_core_add_meta_boxes' );

/**
 * Print the shared nonce once.
 *
 * @return void
 */
function omochix_core_meta_box_nonce() {
	static $printed = false;
	if ( ! $printed ) {
		wp_nonce_field( 'omochix_core_save_ai_tool', 'omochix_core_ai_tool_nonce' );
		$printed = true;
	}
}

/**
 * Get a post meta value for an editor field.
 *
 * @param WP_Post $post Post object.
 * @param string  $key  Meta key.
 * @return mixed
 */
function omochix_core_editor_value( $post, $key ) {
	return get_post_meta( $post->ID, $key, true );
}

/**
 * Render basic fields and the native media picker.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_basic_meta_box( $post ) {
	omochix_core_meta_box_nonce();
	$logo_id  = absint( omochix_core_editor_value( $post, 'tool_logo' ) );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
	?>
	<div class="omochix-core-fields">
		<p><label for="omochix-short-description"><strong><?php esc_html_e( '短い説明', 'omochix-core' ); ?></strong></label></p>
		<textarea class="widefat" id="omochix-short-description" name="omochix_core_meta[short_description]" rows="3" maxlength="180"><?php echo esc_textarea( omochix_core_editor_value( $post, 'short_description' ) ); ?></textarea>
		<p class="description"><?php esc_html_e( '一覧・比較用。60〜90文字を推奨します。', 'omochix-core' ); ?></p>

		<div class="omochix-core-logo-field">
			<p><strong><?php esc_html_e( 'ツールロゴ', 'omochix-core' ); ?></strong></p>
			<input type="hidden" id="omochix-tool-logo" name="omochix_core_meta[tool_logo]" value="<?php echo esc_attr( $logo_id ); ?>">
			<div class="omochix-core-logo-preview" data-logo-preview>
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="" width="96" height="96">
				<?php endif; ?>
			</div>
			<button type="button" class="button" data-select-logo><?php esc_html_e( 'Media Libraryから選択', 'omochix-core' ); ?></button>
			<button type="button" class="button-link-delete" data-remove-logo <?php disabled( ! $logo_id ); ?>><?php esc_html_e( '削除', 'omochix-core' ); ?></button>
		</div>

		<p><label for="omochix-official-url"><strong><?php esc_html_e( '公式URL', 'omochix-core' ); ?></strong></label></p>
		<input class="widefat" id="omochix-official-url" name="omochix_core_meta[official_url]" type="url" value="<?php echo esc_attr( omochix_core_editor_value( $post, 'official_url' ) ); ?>" placeholder="https://">

		<p><label for="omochix-company-name"><strong><?php esc_html_e( '運営会社名', 'omochix-core' ); ?></strong></label></p>
		<input class="widefat" id="omochix-company-name" name="omochix_core_meta[company_name]" type="text" value="<?php echo esc_attr( omochix_core_editor_value( $post, 'company_name' ) ); ?>">

		<p><label for="omochix-tool-status"><strong><?php esc_html_e( '提供状況', 'omochix-core' ); ?></strong></label></p>
		<select id="omochix-tool-status" name="omochix_core_meta[tool_status]">
			<?php
			$status_options = array( 'active' => '提供中', 'beta' => 'ベータ', 'waitlist' => '招待・待機中', 'discontinued' => '提供終了' );
			$current_status = omochix_core_editor_value( $post, 'tool_status' ) ?: 'active';
			foreach ( $status_options as $value => $label ) :
				?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
}

/**
 * Render pricing and availability fields.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_pricing_meta_box( $post ) {
	omochix_core_meta_box_nonce();
	$pricing         = omochix_core_editor_value( $post, 'pricing_type' ) ?: 'contact';
	$pricing_options = array( 'free', 'freemium', 'paid', 'trial', 'contact' );
	$japanese         = omochix_core_editor_value( $post, 'japanese_support' ) ?: 'unknown';
	$japanese_options = array( 'full', 'partial', 'none', 'unknown' );
	?>
	<p><label for="omochix-pricing-type"><strong><?php esc_html_e( '料金タイプ', 'omochix-core' ); ?></strong></label></p>
	<select id="omochix-pricing-type" name="omochix_core_meta[pricing_type]">
		<?php foreach ( $pricing_options as $value ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $pricing, $value ); ?>><?php echo esc_html( omochix_core_get_pricing_type_label( $value ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<p><label><input type="checkbox" name="omochix_core_meta[has_free_plan]" value="1" <?php checked( (bool) omochix_core_editor_value( $post, 'has_free_plan' ) ); ?>> <?php esc_html_e( '無料プランあり', 'omochix-core' ); ?></label></p>
	<p><label for="omochix-japanese-support"><strong><?php esc_html_e( '日本語対応', 'omochix-core' ); ?></strong></label></p>
	<select id="omochix-japanese-support" name="omochix_core_meta[japanese_support]">
		<?php foreach ( $japanese_options as $value ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $japanese, $value ); ?>><?php echo esc_html( omochix_core_get_japanese_support_label( $value ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Explain that platforms use the native taxonomy panel.
 *
 * @return void
 */
function omochix_core_render_platforms_note() {
	omochix_core_meta_box_nonce();
	echo '<p>' . esc_html__( '右側の「対応環境」パネルから複数選択してください。絞り込みに利用されます。', 'omochix-core' ) . '</p>';
}

/**
 * Render editorial rating.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_rating_meta_box( $post ) {
	omochix_core_meta_box_nonce();
	$rating = omochix_core_editor_value( $post, 'rating_overall' );
	?>
	<p><label for="omochix-rating"><strong><?php esc_html_e( '総合評価', 'omochix-core' ); ?></strong></label></p>
	<input id="omochix-rating" name="omochix_core_meta[rating_overall]" type="number" min="1" max="5" step="0.1" value="<?php echo $rating ? esc_attr( $rating ) : ''; ?>">
	<p class="description"><?php esc_html_e( '1.0〜5.0。未評価の場合は空欄にしてください。', 'omochix-core' ); ?></p>
	<?php
}

/**
 * Render newline-delimited structured lists.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_features_meta_box( $post ) {
	omochix_core_meta_box_nonce();
	$fields = array(
		'key_features'    => __( '主な特徴', 'omochix-core' ),
		'pros'            => __( 'メリット', 'omochix-core' ),
		'cons'            => __( 'デメリット', 'omochix-core' ),
		'recommended_for' => __( '向いている人', 'omochix-core' ),
	);
	foreach ( $fields as $key => $label ) {
		$value = omochix_core_editor_value( $post, $key );
		$value = is_array( $value ) ? implode( "\n", $value ) : '';
		?>
		<p><label for="omochix-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label></p>
		<textarea class="widefat" id="omochix-<?php echo esc_attr( $key ); ?>" name="omochix_core_meta[<?php echo esc_attr( $key ); ?>]" rows="4"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( '1行につき1項目を入力してください。', 'omochix-core' ); ?></p>
		<?php
	}
}

/**
 * Explain the automatic relation policy.
 *
 * @return void
 */
function omochix_core_render_relations_note() {
	omochix_core_meta_box_nonce();
	echo '<p>' . esc_html__( 'MVPではカテゴリー・機能・タグをもとに関連コンテンツを自動取得します。手動指定は将来追加します。', 'omochix-core' ) . '</p>';
}

/**
 * Render Home visibility controls.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_display_meta_box( $post ) {
	omochix_core_meta_box_nonce();
	?>
	<p><label><input type="checkbox" name="omochix_core_meta[is_featured]" value="1" <?php checked( (bool) omochix_core_editor_value( $post, 'is_featured' ) ); ?>> <?php esc_html_e( '注目ツールとしてHomeへ表示', 'omochix-core' ); ?></label></p>
	<p><label for="omochix-display-order"><strong><?php esc_html_e( '表示順', 'omochix-core' ); ?></strong></label></p>
	<input class="small-text" id="omochix-display-order" name="omochix_core_meta[display_order]" type="number" min="0" step="1" value="<?php echo esc_attr( absint( omochix_core_editor_value( $post, 'display_order' ) ) ); ?>">
	<p class="description"><?php esc_html_e( '小さい数字を優先します。', 'omochix-core' ); ?></p>
	<?php
}

/**
 * Load the native Media Library only on AI tool edit screens.
 *
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function omochix_core_admin_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'edit.php', 'ai_tool_page_omochix-ai-tool-import' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'ai_tool' !== $screen->post_type ) {
		return;
	}

	if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
	}
	wp_enqueue_style( 'omochix-core-admin', OMOCHIX_CORE_URL . 'admin/assets/admin.css', array(), OMOCHIX_CORE_VERSION );
	wp_enqueue_script( 'omochix-core-admin', OMOCHIX_CORE_URL . 'admin/assets/admin.js', array(), OMOCHIX_CORE_VERSION, true );
	wp_localize_script(
		'omochix-core-admin',
		'omochixCoreAdmin',
		array(
			'mediaTitle'  => __( 'ツールロゴを選択', 'omochix-core' ),
			'mediaButton' => __( 'ロゴとして使用', 'omochix-core' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'omochix_core_admin_assets' );
