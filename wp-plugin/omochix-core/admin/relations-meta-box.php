<?php
/**
 * "Related content" picker: Learn / News / Lab / How-to / Compare.
 *
 * Editors choose real, existing WordPress content instead of typing URLs.
 * Saving reuses the generic schema loop in save-meta.php — these four
 * fields (related_learn_ids, related_news_ids, related_lab_ids,
 * related_compare_ids) are registered in meta-schema.php like any other
 * field, so no separate save handler is needed here.
 *
 * Related Tools is intentionally not part of this box: it stays fully
 * automatic (category/feature/tag based), as it already was before v2.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render one multi-select picker of posts already stored in $meta_key.
 *
 * @param string    $meta_key    Meta key holding the array of selected IDs.
 * @param string    $label       Field label.
 * @param string    $description Helper text under the field.
 * @param WP_Post[] $options     Selectable posts.
 * @param int[]     $selected    Currently selected post IDs.
 * @return void
 */
function omochix_core_render_relation_picker( $meta_key, $label, $description, $options, $selected ) {
	?>
	<div class="omochix-relation-picker" data-relation-picker>
		<p><label for="omochix-<?php echo esc_attr( $meta_key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label></p>
		<?php if ( $options ) : ?>
			<input type="search" class="widefat omochix-relation-picker__filter" placeholder="<?php esc_attr_e( '絞り込み...', 'omochix-core' ); ?>" data-relation-filter>
			<select id="omochix-<?php echo esc_attr( $meta_key ); ?>" name="omochix_core_meta[<?php echo esc_attr( $meta_key ); ?>][]" multiple size="8" class="widefat omochix-relation-picker__select">
				<?php foreach ( $options as $option ) : ?>
					<option value="<?php echo esc_attr( $option->ID ); ?>" <?php selected( in_array( (int) $option->ID, $selected, true ) ); ?>><?php echo esc_html( get_the_title( $option ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php echo esc_html( $description ); ?> <?php esc_html_e( 'Ctrl/Cmdキーで複数選択できます。', 'omochix-core' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( '選択できる公開コンテンツがまだありません。', 'omochix-core' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the relations meta box.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_relations_meta_box( $post ) {
	omochix_core_meta_box_nonce();

	$get_selected = static function ( $key ) use ( $post ) {
		$value = omochix_core_editor_value( $post, $key );
		return is_array( $value ) ? array_map( 'absint', $value ) : array();
	};

	$news_posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	echo '<p class="description">' . esc_html__( '関連ツール（Related Tools）はカテゴリー・機能・タグから自動選出されるため、ここでの手動設定はありません。', 'omochix-core' ) . '</p><hr>';

	omochix_core_render_relation_picker(
		'related_learn_ids',
		__( '関連Learn（このツールを理解する）', 'omochix-core' ),
		__( 'Learnの記事・ページから選択します。', 'omochix-core' ),
		omochix_core_get_learn_picker_pages(),
		$get_selected( 'related_learn_ids' )
	);

	omochix_core_render_relation_picker(
		'related_news_ids',
		__( '関連News（最新ニュース）', 'omochix-core' ),
		__( '公開日の新しい順でフロントに表示されます。', 'omochix-core' ),
		$news_posts,
		$get_selected( 'related_news_ids' )
	);

	omochix_core_render_relation_picker(
		'related_lab_ids',
		__( '関連Lab / How-to（実践・使い方）', 'omochix-core' ),
		__( '検証記事・使い方記事から選択します。', 'omochix-core' ),
		$news_posts,
		$get_selected( 'related_lab_ids' )
	);

	omochix_core_render_relation_picker(
		'related_compare_ids',
		__( '関連Compare（比較する）', 'omochix-core' ),
		__( '比較記事から選択します。', 'omochix-core' ),
		$news_posts,
		$get_selected( 'related_compare_ids' )
	);
}
