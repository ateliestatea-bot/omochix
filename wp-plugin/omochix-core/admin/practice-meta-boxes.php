<?php
/**
 * Learn Practice editor panels (Learn Phase 4).
 *
 * Plain fields use inputs and textareas; structured rows (prompts, code,
 * steps, verification, sources, changelog) are edited as JSON arrays in
 * textareas, validated on save (a typo keeps the previous value and shows a
 * notice). The Phase 4C JSON importer uses exactly the same shapes, so an
 * item can be prepared outside WordPress and pasted in here.
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
function omochix_core_add_practice_meta_boxes() {
	$type = OMOCHIX_CORE_PRACTICE_POST_TYPE;
	add_meta_box( 'omochix-practice-overview', __( '1. 概要（説明・難易度・所要時間・検証日）', 'omochix-core' ), 'omochix_core_render_practice_overview_meta_box', $type, 'normal', 'high' );
	add_meta_box( 'omochix-practice-preview', __( '2. 完成イメージとプレビュー', 'omochix-core' ), 'omochix_core_render_practice_preview_meta_box', $type, 'normal', 'default' );
	add_meta_box( 'omochix-practice-prompts', __( '3. コピー可能なプロンプト（JSON）', 'omochix-core' ), 'omochix_core_render_practice_prompts_meta_box', $type, 'normal', 'default' );
	add_meta_box( 'omochix-practice-code', __( '4. コード例（JSON）', 'omochix-core' ), 'omochix_core_render_practice_code_meta_box', $type, 'normal', 'default' );
	add_meta_box( 'omochix-practice-steps', __( '5. 前提条件と実装手順', 'omochix-core' ), 'omochix_core_render_practice_steps_meta_box', $type, 'normal', 'default' );
	add_meta_box( 'omochix-practice-verify', __( '6. 検証・安全性・公開手順', 'omochix-core' ), 'omochix_core_render_practice_verify_meta_box', $type, 'normal', 'default' );
	add_meta_box( 'omochix-practice-related', __( '7. 関連（教材・Motion・プロンプト・AIツール・Learn）', 'omochix-core' ), 'omochix_core_render_practice_related_meta_box', $type, 'normal', 'default' );
	add_meta_box( 'omochix-practice-sources', __( '8. 参照公式仕様・更新履歴（JSON）', 'omochix-core' ), 'omochix_core_render_practice_sources_meta_box', $type, 'normal', 'default' );
}
add_action( 'add_meta_boxes_' . OMOCHIX_CORE_PRACTICE_POST_TYPE, 'omochix_core_add_practice_meta_boxes' );

/** Print the shared nonce once. */
function omochix_core_practice_meta_box_nonce() {
	static $printed = false;
	if ( ! $printed ) {
		wp_nonce_field( 'omochix_core_save_practice', 'omochix_core_practice_nonce' );
		$printed = true;
	}
}

/**
 * Stored value for an editor field.
 *
 * @param WP_Post $post Post.
 * @param string  $key  Meta key.
 * @return mixed
 */
function omochix_core_practice_editor_value( $post, $key ) {
	return get_post_meta( $post->ID, $key, true );
}

/**
 * Pretty JSON for a rows field ('' when empty).
 *
 * @param mixed $rows Stored rows.
 * @return string
 */
function omochix_core_practice_rows_json( $rows ) {
	if ( ! is_array( $rows ) || ! $rows ) {
		return '';
	}
	return (string) wp_json_encode( array_values( $rows ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * A JSON rows textarea with its example shape.
 *
 * @param WP_Post $post    Post.
 * @param string  $key     Meta key.
 * @param string  $label   Field label.
 * @param string  $example Example JSON (shown under the field).
 * @param int     $rows    Textarea rows.
 * @return void
 */
function omochix_core_practice_rows_field( $post, $key, $label, $example, $rows = 10 ) {
	$id = 'omochix-' . str_replace( '_', '-', $key );
	?>
	<p><label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label></p>
	<textarea class="widefat code" id="<?php echo esc_attr( $id ); ?>" name="omochix_core_practice_meta[<?php echo esc_attr( $key ); ?>]" rows="<?php echo (int) $rows; ?>" spellcheck="false"><?php echo esc_textarea( omochix_core_practice_rows_json( omochix_core_practice_editor_value( $post, $key ) ) ); ?></textarea>
	<p class="description"><?php esc_html_e( 'JSON の配列で入力します。例:', 'omochix-core' ); ?> <code><?php echo esc_html( $example ); ?></code></p>
	<?php
}

/**
 * A "one item per line" textarea.
 *
 * @param WP_Post $post  Post.
 * @param string  $key   Meta key.
 * @param string  $label Label.
 * @param string  $hint  Description.
 * @return void
 */
function omochix_core_practice_lines_field( $post, $key, $label, $hint ) {
	$id    = 'omochix-' . str_replace( '_', '-', $key );
	$value = omochix_core_practice_editor_value( $post, $key );
	?>
	<p><label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label></p>
	<textarea class="widefat" id="<?php echo esc_attr( $id ); ?>" name="omochix_core_practice_meta[<?php echo esc_attr( $key ); ?>]" rows="4"><?php echo esc_textarea( is_array( $value ) ? implode( "\n", $value ) : '' ); ?></textarea>
	<p class="description"><?php echo esc_html( $hint ); ?></p>
	<?php
}

/** Panel 1. */
function omochix_core_render_practice_overview_meta_box( $post ) {
	omochix_core_practice_meta_box_nonce();
	$difficulty = omochix_core_practice_editor_value( $post, 'practice_difficulty' ) ?: 'beginner';
	$missing    = $post->ID ? omochix_core_get_practice_missing_meta( $post->ID ) : array();
	if ( $missing && 'auto-draft' !== $post->post_status ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( '未入力の必須項目: %s', 'omochix-core' ), implode( ', ', $missing ) ) ) . '</p></div>';
	}
	?>
	<p><label for="omochix-practice-summary"><strong><?php esc_html_e( '説明（120文字まで。一覧カードと meta description に使います）', 'omochix-core' ); ?></strong></label></p>
	<textarea class="widefat" id="omochix-practice-summary" name="omochix_core_practice_meta[practice_summary]" rows="2" maxlength="<?php echo (int) OMOCHIX_CORE_PRACTICE_SUMMARY_MAX; ?>"><?php echo esc_textarea( omochix_core_practice_editor_value( $post, 'practice_summary' ) ); ?></textarea>

	<p>
		<label for="omochix-practice-difficulty"><strong><?php esc_html_e( '難易度', 'omochix-core' ); ?></strong></label>
		<select id="omochix-practice-difficulty" name="omochix_core_practice_meta[practice_difficulty]">
			<?php foreach ( array( 'beginner', 'intermediate', 'advanced' ) as $value ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $difficulty, $value ); ?>><?php echo esc_html( omochix_core_get_practice_difficulty_label( $value ) ); ?></option>
			<?php endforeach; ?>
		</select>
		&nbsp;
		<label for="omochix-practice-minutes"><strong><?php esc_html_e( '所要時間（分）', 'omochix-core' ); ?></strong></label>
		<input type="number" min="0" step="5" class="small-text" id="omochix-practice-minutes" name="omochix_core_practice_meta[practice_minutes]" value="<?php echo esc_attr( (string) omochix_core_practice_editor_value( $post, 'practice_minutes' ) ); ?>">
		&nbsp;
		<label for="omochix-practice-verified-at"><strong><?php esc_html_e( '最終検証日', 'omochix-core' ); ?></strong></label>
		<input type="date" id="omochix-practice-verified-at" name="omochix_core_practice_meta[practice_verified_at]" value="<?php echo esc_attr( (string) omochix_core_practice_editor_value( $post, 'practice_verified_at' ) ); ?>">
	</p>
	<p class="description"><?php echo esc_html( sprintf( __( '最終検証日から %d 日を過ぎると「要再検証」と表示されます。', 'omochix-core' ), omochix_core_get_practice_stale_days() ) ); ?></p>

	<p><label for="omochix-practice-outcome"><strong><?php esc_html_e( '完成イメージ（何ができあがるか）', 'omochix-core' ); ?></strong></label></p>
	<textarea class="widefat" id="omochix-practice-outcome" name="omochix_core_practice_meta[practice_outcome]" rows="3"><?php echo esc_textarea( omochix_core_practice_editor_value( $post, 'practice_outcome' ) ); ?></textarea>

	<?php omochix_core_practice_lines_field( $post, 'practice_env', __( '動作確認環境（1 行 1 項目）', 'omochix-core' ), __( '例: Node 20 / Next.js 15 / Supabase（2026年10月時点）', 'omochix-core' ) ); ?>
	<?php
}

/** Panel 2. */
function omochix_core_render_practice_preview_meta_box( $post ) {
	omochix_core_practice_meta_box_nonce();
	$type   = omochix_core_practice_editor_value( $post, 'practice_preview_type' ) ?: 'image';
	$poster = (int) omochix_core_practice_editor_value( $post, 'practice_preview_poster' );
	?>
	<p>
		<label for="omochix-practice-preview-type"><strong><?php esc_html_e( 'プレビューの種類', 'omochix-core' ); ?></strong></label>
		<select id="omochix-practice-preview-type" name="omochix_core_practice_meta[practice_preview_type]">
			<option value="image" <?php selected( $type, 'image' ); ?>><?php esc_html_e( '画像（アイキャッチ画像を使う）', 'omochix-core' ); ?></option>
			<option value="srcdoc" <?php selected( $type, 'srcdoc' ); ?>><?php esc_html_e( 'HTML デモ（サンドボックス iframe）', 'omochix-core' ); ?></option>
			<option value="youtube" <?php selected( $type, 'youtube' ); ?>><?php esc_html_e( 'YouTube（動画 ID）', 'omochix-core' ); ?></option>
		</select>
	</p>
	<p><label for="omochix-practice-preview-src"><strong><?php esc_html_e( 'HTML デモの中身、または YouTube の動画 ID', 'omochix-core' ); ?></strong></label></p>
	<textarea class="widefat code" id="omochix-practice-preview-src" name="omochix_core_practice_meta[practice_preview_src]" rows="8" spellcheck="false"><?php echo esc_textarea( omochix_core_practice_editor_value( $post, 'practice_preview_src' ) ); ?></textarea>
	<p class="description"><?php esc_html_e( 'HTML デモは Motion Gallery と同じサンドボックス（allow-scripts のみ）で動きます。外部への通信や親ページへのアクセスはできません。', 'omochix-core' ); ?></p>
	<p>
		<label for="omochix-practice-preview-poster"><strong><?php esc_html_e( 'ポスター画像の添付 ID（任意。未設定ならアイキャッチ）', 'omochix-core' ); ?></strong></label>
		<input type="number" min="0" class="small-text" id="omochix-practice-preview-poster" name="omochix_core_practice_meta[practice_preview_poster]" value="<?php echo esc_attr( $poster ? (string) $poster : '' ); ?>">
	</p>
	<?php
}

/** Panel 3. */
function omochix_core_render_practice_prompts_meta_box( $post ) {
	omochix_core_practice_meta_box_nonce();
	omochix_core_practice_rows_field( $post, 'practice_prompts', __( 'プロンプト（最大 20 件）', 'omochix-core' ), '[{"title":"要件を整理する","for":"claude-code","body":"あなたは…"}]', 12 );
	echo '<p class="description">' . esc_html__( '"for" は対応AIのスラッグ（claude-code / codex / cursor / chatgpt …）。手順の "prompt" 番号（0 始まり）で参照します。', 'omochix-core' ) . '</p>';
}

/** Panel 4. */
function omochix_core_render_practice_code_meta_box( $post ) {
	omochix_core_practice_meta_box_nonce();
	omochix_core_practice_rows_field( $post, 'practice_code', __( 'コード例（最大 20 件）', 'omochix-core' ), '[{"label":"問い合わせ API","lang":"javascript","file":"api/contact.js","body":"export default async function handler(req, res) { … }"}]', 12 );
}

/** Panel 5. */
function omochix_core_render_practice_steps_meta_box( $post ) {
	omochix_core_practice_meta_box_nonce();
	omochix_core_practice_lines_field( $post, 'practice_prereq', __( '前提条件（1 行 1 項目）', 'omochix-core' ), __( '例: GitHub アカウント / Vercel アカウント / Node 20', 'omochix-core' ) );
	omochix_core_practice_rows_field( $post, 'practice_steps', __( '実装手順（最大 40 件）', 'omochix-core' ), '[{"title":"作りたいものを 1 文で決める","body":"<p>…</p>","prompt":0,"code":null,"check":"1 文で言えたら次へ"}]', 14 );
	echo '<p class="description">' . esc_html__( '"body" は HTML 可（p / ul / ol / strong / a / code）。"prompt" と "code" はパネル 3・4 の番号（0 始まり）か null。', 'omochix-core' ) . '</p>';
}

/** Panel 6. */
function omochix_core_render_practice_verify_meta_box( $post ) {
	omochix_core_practice_meta_box_nonce();
	omochix_core_practice_rows_field( $post, 'practice_verify', __( 'テスト・検証（最大 20 件）', 'omochix-core' ), '[{"title":"フォーム送信で通知が届く","how":"テスト送信する","expect":"メールと LINE に 1 通ずつ届く"}]', 8 );
	omochix_core_practice_lines_field( $post, 'practice_safety', __( '安全性・秘密情報（1 行 1 項目）', 'omochix-core' ), __( 'トークンや API キーは必ずプレースホルダー（＜ここに貼る：…＞）で書きます。実際の値は公開コンテンツに入れません。', 'omochix-core' ) );
	omochix_core_practice_lines_field( $post, 'practice_publish', __( '公開手順（1 行 1 項目）', 'omochix-core' ), __( '例: Vercel に環境変数を登録する / 独自ドメインを設定する', 'omochix-core' ) );
	omochix_core_practice_rows_field( $post, 'practice_troubleshooting', __( 'よくあるエラー（最大 20 件）', 'omochix-core' ), '[{"symptom":"送信しても通知が来ない","cause":"環境変数が未設定","fix":"Vercel の環境変数を確認して再デプロイ"}]', 6 );
}

/**
 * Relation picker (same data-relation-* contract as the prompt editor).
 *
 * @param WP_Post $post      Post.
 * @param string  $key       Meta key.
 * @param string  $label     Label.
 * @param string  $post_type Post type to list.
 * @param WP_Post[]|null $posts Explicit list (for Learn pages).
 * @return void
 */
function omochix_core_practice_relation_picker( $post, $key, $label, $post_type, $posts = null ) {
	$selected = omochix_core_practice_editor_value( $post, $key );
	$selected = is_array( $selected ) ? array_map( 'absint', $selected ) : array();
	if ( null === $posts ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'post__not_in'   => array( $post->ID ),
			)
		);
	}
	$id = 'omochix-practice-' . str_replace( '_', '-', $key );
	?>
	<div class="omochix-relation-picker" data-relation-picker>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
			<span class="omochix-relation-picker__count" data-relation-count><?php echo esc_html( sprintf( __( '%d件選択中', 'omochix-core' ), count( $selected ) ) ); ?></span>
		</p>
		<?php if ( $posts ) : ?>
			<div class="omochix-relation-picker__chips" data-relation-chips></div>
			<input type="search" class="widefat omochix-relation-picker__filter" placeholder="<?php esc_attr_e( '絞り込み...', 'omochix-core' ); ?>" data-relation-filter>
			<input type="hidden" name="omochix_core_practice_relation_fields[]" value="<?php echo esc_attr( $key ); ?>">
			<select id="<?php echo esc_attr( $id ); ?>" name="omochix_core_practice_meta[<?php echo esc_attr( $key ); ?>][]" multiple size="6" class="widefat omochix-relation-picker__select">
				<?php foreach ( $posts as $item ) : ?>
					<option value="<?php echo esc_attr( $item->ID ); ?>" <?php selected( in_array( (int) $item->ID, $selected, true ) ); ?>><?php echo esc_html( 'publish' === $item->post_status ? get_the_title( $item ) : sprintf( __( '%s（未公開）', 'omochix-core' ), get_the_title( $item ) ) ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<p class="description"><?php esc_html_e( '選択できる項目がまだありません。', 'omochix-core' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/** Panel 7. */
function omochix_core_render_practice_related_meta_box( $post ) {
	omochix_core_practice_meta_box_nonce();
	$motion = omochix_core_practice_editor_value( $post, 'related_motion_slugs' );
	?>
	<p><label for="omochix-practice-related-motion-slugs"><strong><?php esc_html_e( '関連 Motion 見本（catalog の slug、カンマ区切り、最大 6）', 'omochix-core' ); ?></strong></label></p>
	<input type="text" class="widefat" id="omochix-practice-related-motion-slugs" name="omochix_core_practice_meta[related_motion_slugs]" value="<?php echo esc_attr( is_array( $motion ) ? implode( ', ', $motion ) : '' ); ?>" placeholder="fade-up, sticky-header, toast">
	<p class="description"><?php esc_html_e( '先頭 3 件が「Composer で開く」リンクになります。存在しない slug は表示時に無視されます。', 'omochix-core' ); ?></p>
	<?php
	omochix_core_practice_relation_picker( $post, 'related_practice_ids', __( '関連教材', 'omochix-core' ), OMOCHIX_CORE_PRACTICE_POST_TYPE );
	omochix_core_practice_relation_picker( $post, 'related_prompt_ids', __( '使うプロンプト', 'omochix-core' ), 'prompt' );
	omochix_core_practice_relation_picker( $post, 'related_tool_ids', __( '対応 AI ツール', 'omochix-core' ), 'ai_tool' );
	omochix_core_practice_relation_picker( $post, 'related_learn_ids', __( '関連 Learn ページ', 'omochix-core' ), 'page', omochix_core_get_learn_picker_pages() );
}

/** Panel 8. */
function omochix_core_render_practice_sources_meta_box( $post ) {
	omochix_core_practice_meta_box_nonce();
	omochix_core_practice_rows_field( $post, 'practice_sources', __( '参照した公式仕様（最大 20 件）', 'omochix-core' ), '[{"label":"LINE Messaging API リファレンス","url":"https://developers.line.biz/ja/reference/messaging-api/"}]', 6 );
	omochix_core_practice_rows_field( $post, 'practice_changelog', __( '更新履歴（最大 50 件）', 'omochix-core' ), '[{"date":"2026-10-21","note":"初版"}]', 5 );
}

/**
 * Load the shared admin styles and relation picker script on practice screens.
 *
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function omochix_core_practice_admin_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || OMOCHIX_CORE_PRACTICE_POST_TYPE !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'omochix-core-admin', OMOCHIX_CORE_URL . 'admin/assets/admin.css', array(), OMOCHIX_CORE_VERSION );
	wp_enqueue_script( 'omochix-core-admin-v2', OMOCHIX_CORE_URL . 'admin/assets/admin-v2.js', array(), OMOCHIX_CORE_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'omochix_core_practice_admin_assets' );

/**
 * Show the "JSON could not be parsed" notice stored by the save handler.
 *
 * @return void
 */
function omochix_core_practice_admin_notices() {
	$screen = get_current_screen();
	if ( ! $screen || OMOCHIX_CORE_PRACTICE_POST_TYPE !== $screen->post_type ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id ) {
		return;
	}
	$keys = get_transient( 'omochix_core_practice_json_error_' . $post_id );
	if ( ! $keys ) {
		return;
	}
	delete_transient( 'omochix_core_practice_json_error_' . $post_id );
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sprintf( __( 'JSON として読めなかったため、前回の値を保持しました: %s', 'omochix-core' ), implode( ', ', array_map( 'sanitize_key', (array) $keys ) ) ) ) . '</p></div>';
}
add_action( 'admin_notices', 'omochix_core_practice_admin_notices' );
