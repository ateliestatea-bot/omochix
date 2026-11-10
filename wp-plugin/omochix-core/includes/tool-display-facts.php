<?php
/**
 * Display facts for AI tools that the theme, the archive filter and the
 * structured data share: the pricing class, whether editorial ratings may be
 * shown, and the date the article itself last changed.
 *
 * Each helper only reads stored data. None of them guesses a value: when the
 * stored fields disagree or the evidence is missing, the helper says so and
 * the caller shows "要確認" instead of picking a side.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pricing class from pricing_type and has_free_plan. The one rule used by the
 * detail page, the cards, search results and the archive's pricing filter.
 *
 * pricing_type is the pricing model: free (完全無料), freemium (無料プラン＋有料
 * プラン), paid, trial (有料。期間限定の無料体験は無料プランではない), contact.
 * has_free_plan is a separate checkbox the classic editor saves as false
 * whenever it is left unchecked, so false may mean "never set" as well as
 * "researched: none". A class is only given when the two agree:
 *
 * - free + has_free_plan true       -> free        (無料)
 * - freemium + true, contact + true -> free_plan   (無料プランあり)
 * - trial + false                   -> trial       (無料体験あり)
 * - paid + false                    -> paid        (有料のみ)
 * - contact + false                 -> contact     (要問い合わせ)
 * - anything else (free / freemium + false, paid / trial + true,
 *   has_free_plan never saved)      -> unconfirmed (要確認)
 *
 * @param int $post_id AI tool post ID.
 * @return string free|free_plan|trial|paid|contact|unconfirmed
 */
function omochix_core_get_tool_pricing_class( $post_id ) {
	if ( ! metadata_exists( 'post', $post_id, 'has_free_plan' ) ) {
		return 'unconfirmed';
	}
	$has_free = in_array( get_post_meta( $post_id, 'has_free_plan', true ), array( true, 1, '1', 'true', 'yes', 'on' ), true );
	$pricing  = omochix_core_normalize_pricing_type( get_post_meta( $post_id, 'pricing_type', true ) );
	$classes  = array(
		'free'     => array( 'free', 'unconfirmed' ),
		'freemium' => array( 'free_plan', 'unconfirmed' ),
		'trial'    => array( 'unconfirmed', 'trial' ),
		'paid'     => array( 'unconfirmed', 'paid' ),
		'contact'  => array( 'free_plan', 'contact' ),
	);
	return $classes[ $pricing ][ $has_free ? 0 : 1 ];
}

/**
 * Reader-facing labels of the pricing classes (also the archive filter options).
 *
 * @return array<string, string>
 */
function omochix_core_get_tool_pricing_class_labels() {
	return array(
		'free'        => __( '無料', 'omochix-core' ),
		'free_plan'   => __( '無料プランあり', 'omochix-core' ),
		'trial'       => __( '無料体験あり', 'omochix-core' ),
		'paid'        => __( '有料のみ', 'omochix-core' ),
		'contact'     => __( '要問い合わせ', 'omochix-core' ),
		'unconfirmed' => __( '要確認', 'omochix-core' ),
	);
}

/**
 * Pricing label for display.
 *
 * @param int $post_id AI tool post ID.
 * @return string
 */
function omochix_core_get_tool_pricing_display_label( $post_id ) {
	$labels = omochix_core_get_tool_pricing_class_labels();
	return $labels[ omochix_core_get_tool_pricing_class( $post_id ) ];
}

/**
 * Free-plan label for display: あり only for the free and free_plan classes.
 *
 * @param int $post_id AI tool post ID.
 * @return string
 */
function omochix_core_get_tool_free_plan_label( $post_id ) {
	$class = omochix_core_get_tool_pricing_class( $post_id );
	if ( 'unconfirmed' === $class ) {
		return __( '要確認', 'omochix-core' );
	}
	return in_array( $class, array( 'free', 'free_plan' ), true ) ? __( 'あり', 'omochix-core' ) : __( 'なし', 'omochix-core' );
}

/**
 * Published AI tool IDs in one pricing class, for the archive filter. The
 * class is computed per post with omochix_core_get_tool_pricing_class() so the
 * filter can never disagree with the label on the card.
 *
 * @param string $class A key of omochix_core_get_tool_pricing_class_labels().
 * @return int[]
 */
function omochix_core_get_tool_ids_by_pricing_class( $class ) {
	$ids = get_posts(
		array(
			'post_type'      => 'ai_tool',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return array_values(
		array_filter(
			array_map( 'intval', $ids ),
			static function ( $id ) use ( $class ) {
				return omochix_core_get_tool_pricing_class( $id ) === $class;
			}
		)
	);
}

/**
 * Whether editorial ratings are published at all.
 *
 * Off until the rating method, its evidence and the review flow are approved
 * (2026-11-05). rating_overall stays stored; nothing is deleted.
 *
 * @return bool
 */
function omochix_core_tool_ratings_published() {
	/**
	 * Filters whether editorial ratings (scores, stars, Review markup) are shown.
	 *
	 * @param bool $published Default false.
	 */
	return (bool) apply_filters( 'omochix_core_tool_ratings_published', false );
}

/**
 * Whether the editorial rating of one tool may be shown: ratings are
 * published, and the post carries the review the score summarizes (the
 * OmochiX view, pros and cons, and the date the facts were checked).
 *
 * @param int $post_id AI tool post ID.
 * @return bool
 */
function omochix_core_tool_rating_has_basis( $post_id ) {
	if ( ! omochix_core_tool_ratings_published() ) {
		return false;
	}
	$rating = get_post_meta( $post_id, 'rating_overall', true );
	if ( '' === $rating || ! is_numeric( $rating ) || (float) $rating <= 0 ) {
		return false;
	}
	$view    = get_post_meta( $post_id, 'omochix_view', true );
	$pros    = array_filter( (array) get_post_meta( $post_id, 'pros', true ) );
	$cons    = array_filter( (array) get_post_meta( $post_id, 'cons', true ) );
	$checked = (string) get_post_meta( $post_id, 'info_checked_date', true );
	$basis   = is_string( $view ) && '' !== trim( $view ) && $pros && $cons && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checked );

	/**
	 * Filters whether an AI tool's editorial rating may be shown.
	 *
	 * @param bool $basis   Default decision.
	 * @param int  $post_id AI tool post ID.
	 */
	return (bool) apply_filters( 'omochix_core_tool_rating_has_basis', $basis, $post_id );
}

/**
 * The editorial rating to display, or 0 when it must not be shown.
 *
 * @param int $post_id AI tool post ID.
 * @return float
 */
function omochix_core_get_tool_display_rating( $post_id ) {
	return omochix_core_tool_rating_has_basis( $post_id ) ? (float) get_post_meta( $post_id, 'rating_overall', true ) : 0.0;
}

/**
 * When the article itself last changed, as a GMT MySQL datetime.
 *
 * post_modified also moves when only terms or meta change (for example the
 * platform migration), so it is not the article's update date. WordPress saves
 * a revision when the title, content, excerpt or footnotes change. A revision
 * that repeats the previous one (no article change) is skipped; autosaves are
 * ignored. If the post no longer matches its newest revision (edited without a
 * revision being saved), post_modified is the only record of that edit and is
 * used. Without any revision, the publish date.
 *
 * @param int $post_id AI tool post ID.
 * @return string GMT datetime (Y-m-d H:i:s), or '' for an unknown post.
 */
function omochix_core_get_tool_content_modified_gmt( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$fingerprint = static function ( $item ) {
		// An empty footnotes row and no row are the same article.
		$footnotes = array_values( array_filter( (array) get_post_meta( $item->ID, 'footnotes', false ), 'strlen' ) );
		return md5( $item->post_title . "\x1f" . $item->post_content . "\x1f" . $item->post_excerpt . "\x1f" . wp_json_encode( $footnotes ) );
	};
	$revisions = array_filter(
		wp_get_post_revisions( $post_id, array( 'posts_per_page' => -1 ) ),
		static function ( $revision ) {
			return ! wp_is_post_autosave( $revision );
		}
	);
	usort(
		$revisions,
		static function ( $a, $b ) {
			return array( $a->post_date_gmt, $a->ID ) <=> array( $b->post_date_gmt, $b->ID );
		}
	);
	$latest      = '';
	$latest_hash = null;
	foreach ( $revisions as $revision ) {
		$hash = $fingerprint( $revision );
		if ( $hash !== $latest_hash ) {
			$latest      = $revision->post_date_gmt;
			$latest_hash = $hash;
		}
	}
	if ( '' === $latest ) {
		return $post->post_date_gmt;
	}
	return $fingerprint( $post ) === $latest_hash ? $latest : $post->post_modified_gmt;
}

/**
 * The article date in the site's timezone, ISO 8601 (the format Slim SEO uses).
 *
 * @param int $post_id AI tool post ID.
 * @return string
 */
function omochix_core_get_tool_content_modified_iso( $post_id ) {
	$gmt = omochix_core_get_tool_content_modified_gmt( $post_id );
	return '' === $gmt ? '' : wp_date( 'c', strtotime( $gmt . ' UTC' ) );
}
