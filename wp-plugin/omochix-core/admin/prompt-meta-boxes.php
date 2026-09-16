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
