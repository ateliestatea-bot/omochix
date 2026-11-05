<?php
/**
 * Provider type (提供元) rules (Industry Hub Phase 1).
 *
 * - One provider per post. More than one is ambiguous, so the post falls back
 *   to "unclassified" (fail closed) and the editor is told why.
 * - No provider term means "unclassified". Nothing is inferred from the
 *   author, the post type or the absence of a term: existing content is never
 *   certified as OmochiX official by default.
 * - "partner" content is advertising (PR) and its links are rel="sponsored".
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_PROVIDER_UNCLASSIFIED = 'unclassified';
const OMOCHIX_CORE_PROVIDER_NOTICE       = 'omochix_core_provider_notice_';

/**
 * Provider type of a post: one of the vocabulary slugs or "unclassified".
 *
 * @param WP_Post|int $post Post or ID.
 * @return string
 */
function omochix_core_get_provider_type( $post ) {
	$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
	if ( ! $post_id || ! taxonomy_exists( OMOCHIX_CORE_PROVIDER_TAXONOMY ) ) {
		return OMOCHIX_CORE_PROVIDER_UNCLASSIFIED;
	}
	$terms = get_the_terms( $post_id, OMOCHIX_CORE_PROVIDER_TAXONOMY );
	return omochix_core_provider_type_from_slugs( is_array( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array() );
}

/**
 * Resolve a list of provider slugs: exactly one known slug, else unclassified.
 *
 * @param string[] $slugs Slugs.
 * @return string
 */
function omochix_core_provider_type_from_slugs( $slugs ) {
	$known = array_values( array_intersect( array_unique( array_map( 'strval', (array) $slugs ) ), array_keys( omochix_core_get_provider_vocabulary() ) ) );
	return 1 === count( $known ) && 1 === count( array_unique( (array) $slugs ) ) ? $known[0] : OMOCHIX_CORE_PROVIDER_UNCLASSIFIED;
}

/**
 * Visible label for a provider type ('' for unclassified: no badge is shown).
 *
 * @param string $type Provider type.
 * @return string
 */
function omochix_core_get_provider_label( $type ) {
	$vocabulary = omochix_core_get_provider_vocabulary();
	return $vocabulary[ $type ] ?? '';
}

/** Whether a provider type must be marked as advertising (PR). */
function omochix_core_provider_is_pr( $type ) {
	return 'partner' === $type;
}

/**
 * rel attribute for an outbound link to content of this provider type.
 *
 * @param string $type Provider type.
 * @return string
 */
function omochix_core_provider_link_rel( $type ) {
	return omochix_core_provider_is_pr( $type ) ? 'sponsored noopener noreferrer' : 'noopener noreferrer';
}

/**
 * Enforce a single provider: when a save leaves more than one provider term
 * on a post, remove them all (unclassified) and leave a notice.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms     Terms passed to wp_set_object_terms.
 * @param array  $tt_ids    Resulting term taxonomy IDs.
 * @param string $taxonomy  Taxonomy.
 * @return void
 */
function omochix_core_provider_enforce_single( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( OMOCHIX_CORE_PROVIDER_TAXONOMY !== $taxonomy || count( (array) $tt_ids ) <= 1 ) {
		return;
	}
	wp_set_object_terms( (int) $object_id, array(), OMOCHIX_CORE_PROVIDER_TAXONOMY, false );
	set_transient( OMOCHIX_CORE_PROVIDER_NOTICE . (int) $object_id, 1, 10 * MINUTE_IN_SECONDS );
}
add_action( 'set_object_terms', 'omochix_core_provider_enforce_single', 10, 4 );

/** Admin notice after a save that had more than one provider. */
function omochix_core_provider_notice() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id || ! get_transient( OMOCHIX_CORE_PROVIDER_NOTICE . $post_id ) ) {
		return;
	}
	delete_transient( OMOCHIX_CORE_PROVIDER_NOTICE . $post_id );
	echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( '提供元は1つだけ選べます。複数選ばれていたため「未分類」に戻しました。', 'omochix-core' ) . '</p></div>';
}
add_action( 'admin_notices', 'omochix_core_provider_notice' );

/**
 * Classic-editor meta box: radio buttons (one provider or 未分類). The field
 * name matches WordPress's own hierarchical tax_input, so core saves it.
 *
 * @param WP_Post $post Post.
 * @return void
 */
function omochix_core_provider_meta_box( $post ) {
	$current = omochix_core_get_provider_type( $post );
	$terms   = get_terms(
		array(
			'taxonomy'   => OMOCHIX_CORE_PROVIDER_TAXONOMY,
			'hide_empty' => false,
		)
	);
	$by_slug = array();
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$by_slug[ $term->slug ] = $term;
	}
	$can_assign = current_user_can( 'assign_terms', OMOCHIX_CORE_PROVIDER_TAXONOMY );
	echo '<fieldset><legend class="screen-reader-text">' . esc_html__( '提供元', 'omochix-core' ) . '</legend>';
	// An empty value keeps tax_input present, so choosing 未分類 clears the term.
	echo '<input type="hidden" name="tax_input[' . esc_attr( OMOCHIX_CORE_PROVIDER_TAXONOMY ) . '][]" value="0">';
	printf(
		'<p><label><input type="radio" name="tax_input[%1$s][]" value="0" %2$s %3$s> %4$s</label></p>',
		esc_attr( OMOCHIX_CORE_PROVIDER_TAXONOMY ),
		checked( OMOCHIX_CORE_PROVIDER_UNCLASSIFIED, $current, false ),
		disabled( ! $can_assign, true, false ),
		esc_html__( '未分類（確認待ち）', 'omochix-core' )
	);
	foreach ( omochix_core_get_provider_vocabulary() as $slug => $label ) {
		if ( ! isset( $by_slug[ $slug ] ) ) {
			continue;
		}
		printf(
			'<p><label><input type="radio" name="tax_input[%1$s][]" value="%2$d" %3$s %4$s> %5$s</label></p>',
			esc_attr( OMOCHIX_CORE_PROVIDER_TAXONOMY ),
			(int) $by_slug[ $slug ]->term_id,
			checked( $slug, $current, false ),
			disabled( ! $can_assign, true, false ),
			esc_html( $label )
		);
	}
	echo '<p class="description">' . esc_html__( '未分類のままでは「公式」として扱われません。パートナー提供は PR と表示されます。', 'omochix-core' ) . '</p></fieldset>';
}
