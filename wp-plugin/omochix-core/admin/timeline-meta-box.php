<?php
/**
 * Repeater UI for "Latest updates" and "Changelog".
 *
 * A plain vanilla-JS repeater (admin/assets/admin-timeline.js) clones a
 * <template> row and renumbers its field names; PHP only ever sees a
 * fully-formed, explicitly-indexed array on submit (no client-side
 * reindexing is required server-side). Saving is handled by
 * omochix_core_save_product_timeline() in includes/product-timeline.php.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return a bounded list of posts usable as a "related News" pick.
 *
 * @return WP_Post[]
 */
function omochix_core_get_timeline_news_options() {
	return get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
}

/**
 * Render one repeater row's fields for a product-update entry.
 *
 * @param string             $prefix  Field name prefix, e.g. "omochix_core_updates[0]".
 * @param array<string, mixed> $entry Row values.
 * @param WP_Post[]          $news_options Selectable News posts.
 * @return void
 */
function omochix_core_render_update_row_fields( $prefix, $entry, $news_options ) {
	$statuses = omochix_core_get_update_status_labels();
	?>
	<div class="omochix-repeater-row__grid">
		<label><?php esc_html_e( 'アップデート名', 'omochix-core' ); ?>
			<input type="text" name="<?php echo esc_attr( $prefix ); ?>[title]" value="<?php echo esc_attr( $entry['title'] ?? '' ); ?>">
		</label>
		<label><?php esc_html_e( '日付', 'omochix-core' ); ?>
			<input type="date" name="<?php echo esc_attr( $prefix ); ?>[date]" value="<?php echo esc_attr( $entry['date'] ?? '' ); ?>">
		</label>
		<label><?php esc_html_e( 'ステータス', 'omochix-core' ); ?>
			<select name="<?php echo esc_attr( $prefix ); ?>[status]">
				<?php foreach ( $statuses as $value => $status_label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( ( $entry['status'] ?? 'new' ), $value ); ?>><?php echo esc_html( $status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label><?php esc_html_e( '関連News', 'omochix-core' ); ?>
			<select name="<?php echo esc_attr( $prefix ); ?>[news_id]">
				<option value="0"><?php esc_html_e( '（なし）', 'omochix-core' ); ?></option>
				<?php foreach ( $news_options as $news_post ) : ?>
					<option value="<?php echo esc_attr( $news_post->ID ); ?>" <?php selected( (int) ( $entry['news_id'] ?? 0 ), $news_post->ID ); ?>><?php echo esc_html( get_the_title( $news_post ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</div>
	<label class="omochix-repeater-row__full"><?php esc_html_e( '短い説明', 'omochix-core' ); ?>
		<textarea name="<?php echo esc_attr( $prefix ); ?>[description]" rows="2"><?php echo esc_textarea( $entry['description'] ?? '' ); ?></textarea>
	</label>
	<button type="button" class="button-link-delete" data-repeater-remove><?php esc_html_e( 'この行を削除', 'omochix-core' ); ?></button>
	<?php
}

/**
 * Render one repeater row's fields for a changelog entry.
 *
 * @param string             $prefix       Field name prefix.
 * @param array<string, mixed> $entry      Row values.
 * @param WP_Post[]          $news_options Selectable News posts.
 * @return void
 */
function omochix_core_render_changelog_row_fields( $prefix, $entry, $news_options ) {
	?>
	<div class="omochix-repeater-row__grid omochix-repeater-row__grid--compact">
		<label><?php esc_html_e( '日付', 'omochix-core' ); ?>
			<input type="date" name="<?php echo esc_attr( $prefix ); ?>[date]" value="<?php echo esc_attr( $entry['date'] ?? '' ); ?>">
		</label>
		<label><?php esc_html_e( '内容', 'omochix-core' ); ?>
			<input type="text" name="<?php echo esc_attr( $prefix ); ?>[title]" value="<?php echo esc_attr( $entry['title'] ?? '' ); ?>">
		</label>
		<label><?php esc_html_e( '関連News', 'omochix-core' ); ?>
			<select name="<?php echo esc_attr( $prefix ); ?>[news_id]">
				<option value="0"><?php esc_html_e( '（なし）', 'omochix-core' ); ?></option>
				<?php foreach ( $news_options as $news_post ) : ?>
					<option value="<?php echo esc_attr( $news_post->ID ); ?>" <?php selected( (int) ( $entry['news_id'] ?? 0 ), $news_post->ID ); ?>><?php echo esc_html( get_the_title( $news_post ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</div>
	<button type="button" class="button-link-delete" data-repeater-remove><?php esc_html_e( 'この行を削除', 'omochix-core' ); ?></button>
	<?php
}

/**
 * Render the "最新アップデート" meta box.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_updates_meta_box( $post ) {
	omochix_core_meta_box_nonce();
	$entries      = get_post_meta( $post->ID, 'product_updates', true );
	$entries      = is_array( $entries ) ? $entries : array();
	$news_options = omochix_core_get_timeline_news_options();
	?>
	<p class="description"><?php esc_html_e( '大型機能の追加など「今この製品で何ができるようになったか」を、新しい順で表示します。複数件登録できます。', 'omochix-core' ); ?></p>
	<div class="omochix-repeater" data-repeater="updates">
		<div data-repeater-rows>
			<?php foreach ( $entries as $index => $entry ) : ?>
				<div class="omochix-repeater-row" data-repeater-row>
					<?php omochix_core_render_update_row_fields( "omochix_core_updates[{$index}]", $entry, $news_options ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<template data-repeater-template>
			<div class="omochix-repeater-row" data-repeater-row>
				<?php omochix_core_render_update_row_fields( 'omochix_core_updates[__INDEX__]', array(), $news_options ); ?>
			</div>
		</template>
		<button type="button" class="button" data-repeater-add><?php esc_html_e( '+ アップデートを追加', 'omochix-core' ); ?></button>
	</div>
	<?php
}

/**
 * Render the "更新履歴" meta box.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_changelog_meta_box( $post ) {
	omochix_core_meta_box_nonce();
	$entries      = get_post_meta( $post->ID, 'changelog', true );
	$entries      = is_array( $entries ) ? $entries : array();
	$news_options = omochix_core_get_timeline_news_options();
	?>
	<p class="description"><?php esc_html_e( '製品そのものの更新履歴（Changelog）です。投稿の編集履歴ではありません。新しい順で表示されます。', 'omochix-core' ); ?></p>
	<div class="omochix-repeater" data-repeater="changelog">
		<div data-repeater-rows>
			<?php foreach ( $entries as $index => $entry ) : ?>
				<div class="omochix-repeater-row" data-repeater-row>
					<?php omochix_core_render_changelog_row_fields( "omochix_core_changelog[{$index}]", $entry, $news_options ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<template data-repeater-template>
			<div class="omochix-repeater-row" data-repeater-row>
				<?php omochix_core_render_changelog_row_fields( 'omochix_core_changelog[__INDEX__]', array(), $news_options ); ?>
			</div>
		</template>
		<button type="button" class="button" data-repeater-add><?php esc_html_e( '+ 履歴を追加', 'omochix-core' ); ?></button>
	</div>
	<?php
}
