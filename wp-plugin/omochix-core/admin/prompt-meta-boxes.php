<?php
/**
 * Prompt Library editor fields.
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
function omochix_core_add_prompt_meta_boxes() {
	add_meta_box( 'omochix-prompt-body', __( '1. プロンプト本文', 'omochix-core' ), 'omochix_core_render_prompt_body_meta_box', 'prompt', 'normal', 'high' );
	add_meta_box( 'omochix-prompt-usage', __( '2. 使い方・出力例', 'omochix-core' ), 'omochix_core_render_prompt_usage_meta_box', 'prompt', 'normal', 'default' );
	add_meta_box( 'omochix-prompt-difficulty', __( '3. 難易度', 'omochix-core' ), 'omochix_core_render_prompt_difficulty_meta_box', 'prompt', 'side', 'default' );
	add_meta_box( 'omochix-prompt-related-tools', __( '4. 関連AIツール', 'omochix-core' ), 'omochix_core_render_prompt_related_tools_meta_box', 'prompt', 'normal', 'default' );
}
add_action( 'add_meta_boxes_prompt', 'omochix_core_add_prompt_meta_boxes' );

/**
 * Print the shared nonce once.
 *
 * @return void
 */
function omochix_core_prompt_meta_box_nonce() {
	static $printed = false;
	if ( ! $printed ) {
		wp_nonce_field( 'omochix_core_save_prompt', 'omochix_core_prompt_nonce' );
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
function omochix_core_prompt_editor_value( $post, $key ) {
	return get_post_meta( $post->ID, $key, true );
}

/**
 * Render the copyable prompt body field.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_prompt_body_meta_box( $post ) {
	omochix_core_prompt_meta_box_nonce();
	?>
	<p><label for="omochix-prompt-body"><strong><?php esc_html_e( 'プロンプト本文', 'omochix-core' ); ?></strong></label></p>
	<textarea class="widefat" id="omochix-prompt-body" name="omochix_core_prompt_meta[prompt_body]" rows="8"><?php echo esc_textarea( omochix_core_prompt_editor_value( $post, 'prompt_body' ) ); ?></textarea>
	<p class="description"><?php esc_html_e( '利用者が「コピー」ボタンでそのままコピーする、実際のプロンプト文を入力してください。', 'omochix-core' ); ?></p>
	<?php
}

/**
 * Render usage and example fields.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_prompt_usage_meta_box( $post ) {
	omochix_core_prompt_meta_box_nonce();
	?>
	<p><label for="omochix-prompt-usage"><strong><?php esc_html_e( '使い方', 'omochix-core' ); ?></strong></label></p>
	<textarea class="widefat" id="omochix-prompt-usage" name="omochix_core_prompt_meta[prompt_usage]" rows="4"><?php echo esc_textarea( omochix_core_prompt_editor_value( $post, 'prompt_usage' ) ); ?></textarea>
	<p class="description"><?php esc_html_e( 'コピーした後、どこに貼り付けて何を入力すればよいかを説明してください。', 'omochix-core' ); ?></p>

	<p><label for="omochix-prompt-example"><strong><?php esc_html_e( '出力例', 'omochix-core' ); ?></strong></label></p>
	<textarea class="widefat" id="omochix-prompt-example" name="omochix_core_prompt_meta[prompt_example]" rows="4"><?php echo esc_textarea( omochix_core_prompt_editor_value( $post, 'prompt_example' ) ); ?></textarea>
	<p class="description"><?php esc_html_e( 'このプロンプトを使うとどのような出力が得られるかの例を入力してください。', 'omochix-core' ); ?></p>
	<?php
}

/**
 * Render the difficulty field.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_prompt_difficulty_meta_box( $post ) {
	omochix_core_prompt_meta_box_nonce();
	$difficulty = omochix_core_prompt_editor_value( $post, 'prompt_difficulty' ) ?: 'beginner';
	$options    = array( 'beginner', 'intermediate', 'advanced' );
	?>
	<select id="omochix-prompt-difficulty" name="omochix_core_prompt_meta[prompt_difficulty]">
		<?php foreach ( $options as $value ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $difficulty, $value ); ?>><?php echo esc_html( omochix_core_get_prompt_difficulty_label( $value ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<p class="description"><?php esc_html_e( '一覧・詳細ページの難易度表示に使用します。', 'omochix-core' ); ?></p>
	<?php
}

/**
 * Render the related AI tools picker.
 *
 * Uses the same markup contract (data-relation-*) as the ai_tool relation
 * picker so admin-v2.js provides the filter and selection chips, but posts
 * under omochix_core_prompt_meta so save-prompt-meta.php owns the save.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_prompt_related_tools_meta_box( $post ) {
	omochix_core_prompt_meta_box_nonce();

	$selected = omochix_core_prompt_editor_value( $post, 'related_tool_ids' );
	$selected = is_array( $selected ) ? array_map( 'absint', $selected ) : array();
	// Unpublished tools are selectable so relations can be prepared before a
	// tool goes live; the front end only ever renders published tools.
	$tools    = get_posts(
		array(
			'post_type'      => 'ai_tool',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	?>
	<div class="omochix-relation-picker" data-relation-picker>
		<p>
			<label for="omochix-prompt-related-tool-ids"><strong><?php esc_html_e( '関連AIツール', 'omochix-core' ); ?></strong></label>
			<span class="omochix-relation-picker__count" data-relation-count><?php echo esc_html( sprintf( __( '%d件選択中', 'omochix-core' ), count( $selected ) ) ); ?></span>
		</p>
		<?php if ( $tools ) : ?>
			<div class="omochix-relation-picker__chips" data-relation-chips></div>
			<input type="search" class="widefat omochix-relation-picker__filter" placeholder="<?php esc_attr_e( '絞り込み...', 'omochix-core' ); ?>" data-relation-filter>
			<input type="hidden" name="omochix_core_prompt_relation_fields[]" value="related_tool_ids">
			<select id="omochix-prompt-related-tool-ids" name="omochix_core_prompt_meta[related_tool_ids][]" multiple size="8" class="widefat omochix-relation-picker__select">
				<?php foreach ( $tools as $tool ) : ?>
					<option value="<?php echo esc_attr( $tool->ID ); ?>" <?php selected( in_array( (int) $tool->ID, $selected, true ) ); ?>><?php echo esc_html( 'publish' === $tool->post_status ? get_the_title( $tool ) : sprintf( __( '%s（未公開）', 'omochix-core' ), get_the_title( $tool ) ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'このプロンプトを使えるAIツールを選択します。選択したツールの詳細ページにもこのプロンプトが表示されます。未選択の場合は何も表示されません。', 'omochix-core' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( '選択できるAIツールがまだありません。', 'omochix-core' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Load the shared admin styles and relation picker script on prompt screens.
 *
 * Kept separate from omochix_core_admin_assets() (ai_tool only) so the AI
 * tool editor's asset loading is unchanged.
 *
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function omochix_core_prompt_admin_assets( $hook_suffix ) {
	$is_editor   = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true );
	$is_importer = 'prompt_page_omochix-prompt-import' === $hook_suffix;
	if ( ! $is_editor && ! $is_importer ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'prompt' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style( 'omochix-core-admin', OMOCHIX_CORE_URL . 'admin/assets/admin.css', array(), OMOCHIX_CORE_VERSION );
	if ( $is_editor ) {
		wp_enqueue_script( 'omochix-core-admin-v2', OMOCHIX_CORE_URL . 'admin/assets/admin-v2.js', array(), OMOCHIX_CORE_VERSION, true );
	}
}
add_action( 'admin_enqueue_scripts', 'omochix_core_prompt_admin_assets' );
