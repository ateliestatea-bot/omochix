<?php
/**
 * Minimal content-role classification for regular posts.
 *
 * v2.1 finding: the AI Tools "related content" pickers for Learn / News /
 * Lab-How-to / Compare need to offer distinct candidate lists, but the site
 * has no existing taxonomy, category, or meta that distinguishes a "Lab /
 * How-to" or "Compare" article from an ordinary News post — only the
 * `ai-news` category exists (see functions.php / inc/seo.php), and Learn
 * content is a separate `page` hierarchy (inc/learn.php). Sorting by title
 * text was explicitly ruled out as unreliable and not requested.
 *
 * Rather than introduce a new taxonomy (bigger surface area: term admin UI,
 * rewrite rules, migration risk) this adds one optional, single-value post
 * meta on the existing `post` post type, used only for the "lab"/"compare"
 * opt-in. "News" is deliberately NOT defined by this meta: the site already
 * has a real, existing source of truth for "this is an AI News article" —
 * the `ai-news` category (same one used by header.php, inc/seo.php and
 * omochix_get_category_url()) — so the News picker queries that category by
 * slug instead. A post that is in neither the ai-news category nor marked
 * lab/compare (e.g. an ordinary, uncategorized post) appears in none of the
 * three pickers, which is the explicit v2.1 requirement: unclassified
 * content must not silently leak into "関連News" candidates. This can be
 * promoted to a real taxonomy later without changing any already-published
 * URL, since it never touches post_type, slug, or the `category`/`post_tag`
 * taxonomies.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the allowed content-role values and their editor labels.
 *
 * @return array<string, string>
 */
function omochix_core_get_content_role_labels() {
	return array(
		'lab'     => __( 'Lab / How-to（実践・検証・使い方）', 'omochix-core' ),
		'compare' => __( 'Compare（比較・選択）', 'omochix-core' ),
	);
}

/**
 * Register the content-role meta on the `post` post type.
 *
 * @return void
 */
function omochix_core_register_content_role_meta() {
	register_post_meta(
		'post',
		'omochix_content_role',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'omochix_core_sanitize_content_role',
			'auth_callback'     => 'omochix_core_auth_registered_meta',
		)
	);
}
add_action( 'init', 'omochix_core_register_content_role_meta' );

/**
 * Sanitize a submitted content-role value.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function omochix_core_sanitize_content_role( $value ) {
	$value = sanitize_key( (string) $value );
	return array_key_exists( $value, omochix_core_get_content_role_labels() ) ? $value : '';
}

/**
 * Render the content-role meta box on the Post editor.
 *
 * @return void
 */
function omochix_core_add_content_role_meta_box() {
	add_meta_box( 'omochix-content-role', __( 'OmochiX コンテンツ種別', 'omochix-core' ), 'omochix_core_render_content_role_meta_box', 'post', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'omochix_core_add_content_role_meta_box' );

/**
 * Render the content-role field.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function omochix_core_render_content_role_meta_box( $post ) {
	wp_nonce_field( 'omochix_core_save_content_role', 'omochix_core_content_role_nonce' );
	$current = get_post_meta( $post->ID, 'omochix_content_role', true );
	?>
	<p>
		<label>
			<input type="radio" name="omochix_content_role" value="" <?php checked( '', $current ); ?>>
			<?php esc_html_e( '指定なし', 'omochix-core' ); ?>
		</label>
	</p>
	<?php foreach ( omochix_core_get_content_role_labels() as $value => $label ) : ?>
		<p>
			<label>
				<input type="radio" name="omochix_content_role" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $current ); ?>>
				<?php echo esc_html( $label ); ?>
			</label>
		</p>
	<?php endforeach; ?>
	<p class="description"><?php esc_html_e( 'AI Toolsの「関連Lab/How-to」「関連Compare」ピッカーに、この記事をどちらの候補として出すかを選びます。「指定なし」はどちらの候補にもなりません。「関連News」候補になるのは、通常投稿カテゴリーで「AIニュース」に属する記事だけです（ここでは設定しません）。', 'omochix-core' ); ?></p>
	<?php
}

/**
 * Save the content-role field.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_content_role( $post_id ) {
	if ( 'post' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['omochix_core_content_role_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST['omochix_core_content_role_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'omochix_core_save_content_role' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['omochix_content_role'] ) ) {
		return;
	}

	$value = omochix_core_sanitize_content_role( wp_unslash( $_POST['omochix_content_role'] ) );
	if ( '' === $value ) {
		delete_post_meta( $post_id, 'omochix_content_role' );
	} else {
		update_post_meta( $post_id, 'omochix_content_role', $value );
	}
}
add_action( 'save_post_post', 'omochix_core_save_content_role' );

/**
 * Return published posts filtered by content role, for the AI Tools
 * relation pickers.
 *
 * 'news' is intentionally NOT sourced from omochix_content_role: it queries
 * the site's existing `ai-news` category by slug (the same source of truth
 * used by header.php / inc/seo.php / omochix_get_category_url()), so no bulk
 * meta backfill is ever needed for existing AI News articles. An ordinary
 * post that is neither in that category nor explicitly marked lab/compare
 * matches none of the three roles, by design.
 *
 * @param string $role  'news', 'lab', or 'compare'.
 * @param int    $limit Maximum posts to return.
 * @return WP_Post[]
 */
function omochix_core_get_posts_by_content_role( $role, $limit = 200 ) {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);

	if ( 'news' === $role ) {
		$ai_news_category = get_category_by_slug( 'ai-news' );
		if ( ! ( $ai_news_category instanceof WP_Term ) ) {
			return array();
		}
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $ai_news_category->term_id,
			),
		);
	} elseif ( in_array( $role, array( 'lab', 'compare' ), true ) ) {
		$args['meta_query'] = array(
			array( 'key' => 'omochix_content_role', 'value' => $role, 'compare' => '=' ),
		);
	} else {
		return array();
	}

	return get_posts( $args );
}
