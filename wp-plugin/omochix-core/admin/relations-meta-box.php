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
 * Adds a live "currently selected" chip list above the <select> (v2.1): the
 * search filter hides non-matching <option> elements, which previously made
 * it easy to lose track of a selection that scrolled out of view or got
 * filtered out. The chips always reflect the live DOM selection state and
 * offer a one-click way to deselect a specific item, without changing how
 * the underlying <select multiple> is saved.
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
		<p>
			<label for="omochix-<?php echo esc_attr( $meta_key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
			<span class="omochix-relation-picker__count" data-relation-count><?php echo esc_html( sprintf( __( '%d件選択中', 'omochix-core' ), count( $selected ) ) ); ?></span>
		</p>
		<?php if ( $options ) : ?>
			<div class="omochix-relation-picker__chips" data-relation-chips></div>
			<input type="search" class="widefat omochix-relation-picker__filter" placeholder="<?php esc_attr_e( '絞り込み...', 'omochix-core' ); ?>" data-relation-filter>
			<select id="omochix-<?php echo esc_attr( $meta_key ); ?>" name="omochix_core_meta[<?php echo esc_attr( $meta_key ); ?>][]" multiple size="8" class="widefat omochix-relation-picker__select">
				<?php foreach ( $options as $option ) : ?>
					<option value="<?php echo esc_attr( $option->ID ); ?>" <?php selected( in_array( (int) $option->ID, $selected, true ) ); ?>><?php echo esc_html( get_the_title( $option ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php echo esc_html( $description ); ?> <?php esc_html_e( 'チップの×で選択解除、Ctrl/Cmdキーでリストから複数選択もできます。', 'omochix-core' ); ?></p>
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

	// News / Lab-How-to / Compare are disjoint lists, classified by the post's
	// own omochix_content_role meta (see includes/post-content-role.php) —
	// never by guessing from the title. A post with no role set (every post
	// published before v2.1, and any new post by default) is "News".
	$news_posts    = omochix_core_get_posts_by_content_role( 'news' );
	$lab_posts     = omochix_core_get_posts_by_content_role( 'lab' );
	$compare_posts = omochix_core_get_posts_by_content_role( 'compare' );

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
		__( '通常投稿カテゴリーで「AIニュース」に属する公開記事のみ表示します。公開日の新しい順でフロントに表示されます。', 'omochix-core' ),
		$news_posts,
		$get_selected( 'related_news_ids' )
	);

	omochix_core_render_relation_picker(
		'related_lab_ids',
		__( '関連Lab / How-to（実践・使い方）', 'omochix-core' ),
		__( '投稿の「OmochiX コンテンツ種別」でLab / How-toに分類された記事のみ表示します。', 'omochix-core' ),
		$lab_posts,
		$get_selected( 'related_lab_ids' )
	);

	omochix_core_render_relation_picker(
		'related_compare_ids',
		__( '関連Compare（比較する）', 'omochix-core' ),
		__( '投稿の「OmochiX コンテンツ種別」でCompareに分類された記事のみ表示します。', 'omochix-core' ),
		$compare_posts,
		$get_selected( 'related_compare_ids' )
	);

	if ( ! $lab_posts || ! $compare_posts ) {
		echo '<p class="description">' . esc_html__( 'Lab / How-toやCompareの候補が出ない場合は、対象の投稿を編集し「OmochiX コンテンツ種別」を設定してください。', 'omochix-core' ) . '</p>';
	}
}
