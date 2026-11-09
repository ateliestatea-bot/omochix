<?php
/** Regression checks for AI Tool display facts (pricing class, ratings, article date). Run: php tests/ai-tools-display-facts.php */

define( 'ABSPATH', __DIR__ );
$GLOBALS['meta'] = array();          // post_id => key => value
$GLOBALS['revisions'] = array();
$GLOBALS['filters'] = array();       // hook => forced value
$GLOBALS['post'] = (object) array( 'ID' => 1, 'post_title' => 'T', 'post_content' => 'C', 'post_excerpt' => '', 'post_date_gmt' => '2026-09-01 09:00:00', 'post_modified_gmt' => '2026-11-04 09:45:41' );

function __( $text, $domain = null ) { return $text; }
function metadata_exists( $type, $id, $key ) { return array_key_exists( $key, $GLOBALS['meta'][ $id ] ?? array() ); }
function get_post_meta( $id, $key, $single = false ) {
	$value = $GLOBALS['meta'][ $id ][ $key ] ?? null;
	if ( $single ) {
		return $value ?? '';
	}
	return null === $value ? array() : array( $value );
}
function apply_filters( $hook, $value, ...$args ) { return array_key_exists( $hook, $GLOBALS['filters'] ) ? $GLOBALS['filters'][ $hook ] : $value; }
function get_post( $id ) { return $GLOBALS['post']; }
function get_posts( $args ) { return array_keys( $GLOBALS['meta'] ); }
function wp_get_post_revisions( $id, $args = array() ) { return $GLOBALS['revisions']; }
function wp_is_post_autosave( $revision ) { return false !== strpos( $revision->post_name, 'autosave' ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_date( $format, $timestamp ) { return gmdate( 'Y-m-d\TH:i:s', $timestamp + 9 * 3600 ) . '+09:00'; }
function omochix_core_normalize_pricing_type( $value ) {
	return in_array( $value, array( 'free', 'freemium', 'paid', 'trial', 'contact' ), true ) ? $value : 'contact';
}

require __DIR__ . '/../wp-plugin/omochix-core/includes/tool-display-facts.php';

function check( $message, $condition ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

// ---------------------------------------------------------------- pricing class
// has_free_plan: true / false ('' as the editor saves it) / never saved (null).
$cases = array(
	array( 'free', true, 'free', '無料', 'あり' ),
	array( 'free', '', 'unconfirmed', '要確認', '要確認' ),
	array( 'freemium', '1', 'free_plan', '無料プランあり', 'あり' ),
	array( 'freemium', '', 'unconfirmed', '要確認', '要確認' ),
	array( 'freemium', null, 'unconfirmed', '要確認', '要確認' ),
	array( 'trial', '', 'trial', '無料体験あり', 'なし' ),
	array( 'trial', true, 'unconfirmed', '要確認', '要確認' ),
	array( 'paid', '', 'paid', '有料のみ', 'なし' ),
	array( 'paid', '1', 'unconfirmed', '要確認', '要確認' ),
	array( 'contact', '1', 'free_plan', '無料プランあり', 'あり' ),
	array( 'contact', '', 'contact', '要問い合わせ', 'なし' ),
);
$GLOBALS['meta'] = array();
foreach ( $cases as $i => list( $pricing, $has_free, $class, $label, $free_label ) ) {
	$id = $i + 10;
	$GLOBALS['meta'][ $id ] = array( 'pricing_type' => $pricing );
	if ( null !== $has_free ) {
		$GLOBALS['meta'][ $id ]['has_free_plan'] = $has_free;
	}
	$name = "{$pricing} + has_free_plan " . var_export( $has_free, true );
	check( "{$name}: class {$class}", omochix_core_get_tool_pricing_class( $id ) === $class );
	check( "{$name}: pricing label {$label}", omochix_core_get_tool_pricing_display_label( $id ) === $label );
	check( "{$name}: free-plan label {$free_label}", omochix_core_get_tool_free_plan_label( $id ) === $free_label );
}
check( 'never 無料プランあり next to 無料プラン なし', ! array_filter( array_keys( $GLOBALS['meta'] ), static function ( $id ) {
	return '無料プランあり' === omochix_core_get_tool_pricing_display_label( $id ) && 'なし' === omochix_core_get_tool_free_plan_label( $id );
} ) );
// The archive filter lists exactly the posts whose card shows the label.
foreach ( array_keys( omochix_core_get_tool_pricing_class_labels() ) as $class ) {
	$want = array_values( array_filter( array_keys( $GLOBALS['meta'] ), static function ( $id ) use ( $class ) {
		return omochix_core_get_tool_pricing_class( $id ) === $class;
	} ) );
	check( "filter {$class} = posts labelled " . omochix_core_get_tool_pricing_class_labels()[ $class ], omochix_core_get_tool_ids_by_pricing_class( $class ) === $want );
}
check( 'filter free_plan excludes freemium with has_free_plan false', ! in_array( 13, omochix_core_get_tool_ids_by_pricing_class( 'free_plan' ), true ) );

// ---------------------------------------------------------------- ratings
$full = array( 'rating_overall' => 4.6, 'omochix_view' => '編集部の見解', 'pros' => array( 'a' ), 'cons' => array( 'b' ), 'info_checked_date' => '2026-10-05' );
$GLOBALS['meta'] = array( 1 => $full );
check( 'ratings are withheld by default', ! omochix_core_tool_ratings_published() && 0.0 === omochix_core_get_tool_display_rating( 1 ) );
check( 'rating_overall itself is kept', 4.6 === $GLOBALS['meta'][1]['rating_overall'] );
$GLOBALS['filters']['omochix_core_tool_ratings_published'] = true;
check( 'published + full review: rating shown', 4.6 === omochix_core_get_tool_display_rating( 1 ) );
foreach ( array( 'omochix_view' => '  ', 'pros' => array(), 'cons' => array( '' ), 'info_checked_date' => '2026/10/05' ) as $key => $empty ) {
	$GLOBALS['meta'][1] = array_merge( $full, array( $key => $empty ) );
	check( "published but no {$key}: no rating", 0.0 === omochix_core_get_tool_display_rating( 1 ) );
}
foreach ( array( 0, '', 'abc' ) as $rating ) {
	$GLOBALS['meta'][1] = array_merge( $full, array( 'rating_overall' => $rating ) );
	check( 'no rating for rating_overall ' . var_export( $rating, true ), 0.0 === omochix_core_get_tool_display_rating( 1 ) );
}
$GLOBALS['filters'] = array();

// ---------------------------------------------------------------- article date
$GLOBALS['meta'] = array();
$rev = static function ( $id, $name, $gmt, $content ) { return (object) array( 'ID' => $id, 'post_name' => $name, 'post_date_gmt' => $gmt, 'post_title' => 'T', 'post_content' => $content, 'post_excerpt' => '' ); };
$GLOBALS['revisions'] = array();
check( 'no revision: publish date', '2026-09-01 09:00:00' === omochix_core_get_tool_content_modified_gmt( 1 ) );
$GLOBALS['revisions'] = array( $rev( 2, '1-revision-v1', '2026-09-27 03:56:30', 'B' ), $rev( 3, '1-revision-v1', '2026-09-30 15:48:07', 'C' ), $rev( 4, '1-autosave-v1', '2026-09-30 15:50:25', 'D' ) );
check( 'newest revision that is not an autosave', '2026-09-30 15:48:07' === omochix_core_get_tool_content_modified_gmt( 1 ) );
$GLOBALS['revisions'][] = $rev( 5, '1-revision-v1', '2026-11-04 09:45:41', 'C' );
check( 'a revision repeating the previous one (no article change) is skipped', '2026-09-30 15:48:07' === omochix_core_get_tool_content_modified_gmt( 1 ) );
$GLOBALS['meta'][1] = array( 'footnotes' => '' );
check( 'an empty footnotes row on the post is no article change', '2026-09-30 15:48:07' === omochix_core_get_tool_content_modified_gmt( 1 ) );
$GLOBALS['post']->post_content = 'E';
check( 'post edited without a revision: post_modified', '2026-11-04 09:45:41' === omochix_core_get_tool_content_modified_gmt( 1 ) );
$GLOBALS['post']->post_content = 'C';
check( 'ISO date in the site timezone', '2026-10-01T00:48:07+09:00' === omochix_core_get_tool_content_modified_iso( 1 ) );
