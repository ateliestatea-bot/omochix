<?php
// omochix-industry-hub-api: 2
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
 * Provider types only editors may assign: "official" certifies content as
 * OmochiX's own (the hub gate trusts it) and "partner" marks advertising.
 *
 * @return string[]
 */
function omochix_core_get_privileged_provider_types() {
	return array( 'official', 'partner' );
}

/**
 * The provider slugs a save would really leave: a privileged type the post
 * does not have yet is dropped for users without edit_others_posts, exactly
 * as omochix_core_provider_enforce_single() will do after the guards ran.
 * Guards decide on this, so they never pass on a value that is stripped.
 *
 * @param int      $post_id Post ID (0 for new).
 * @param string[] $slugs   Requested slugs.
 * @return string[]
 */
function omochix_core_provider_surviving_slugs( $post_id, $slugs ) {
	if ( current_user_can( 'edit_others_posts' ) ) {
		return array_values( (array) $slugs );
	}
	$stored = array();
	$terms  = $post_id ? get_the_terms( (int) $post_id, OMOCHIX_CORE_PROVIDER_TAXONOMY ) : false;
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$stored[] = $term->slug;
	}
	return array_values(
		array_filter(
			(array) $slugs,
			static function ( $slug ) use ( $stored ) {
				return ! in_array( $slug, omochix_core_get_privileged_provider_types(), true ) || in_array( $slug, $stored, true );
			}
		)
	);
}

/**
 * After any change to a post's provider terms (wp_set_object_terms, append
 * or not, from post.php, quick / bulk edit, REST, WP-CLI or code):
 *
 * 1. a privileged type (official / partner) newly added by a user without
 *    edit_others_posts is removed again;
 * 2. if the post still has more than one provider, all are removed
 *    (unclassified) — counted from the stored terms, so appending cannot
 *    slip a second provider in.
 *
 * Code running with no user (cron, seeding) is held to the same rule.
 *
 * @param int    $object_id  Object ID.
 * @param array  $terms      Terms passed in.
 * @param array  $tt_ids     Term taxonomy IDs passed / created.
 * @param string $taxonomy   Taxonomy.
 * @param bool   $append     Append mode.
 * @param array  $old_tt_ids Term taxonomy IDs before the change.
 * @return void
 */
function omochix_core_provider_enforce_single( $object_id, $terms, $tt_ids, $taxonomy, $append = false, $old_tt_ids = array() ) {
	static $running = false;
	if ( OMOCHIX_CORE_PROVIDER_TAXONOMY !== $taxonomy || $running ) {
		return;
	}
	$current = wp_get_object_terms( (int) $object_id, OMOCHIX_CORE_PROVIDER_TAXONOMY );
	if ( ! is_array( $current ) ) {
		return;
	}
	$old_tt_ids = array_map( 'intval', (array) $old_tt_ids );
	$keep       = array();
	$changed    = false;
	foreach ( $current as $term ) {
		$is_new = ! in_array( (int) $term->term_taxonomy_id, $old_tt_ids, true );
		if ( $is_new && in_array( $term->slug, omochix_core_get_privileged_provider_types(), true ) && ! current_user_can( 'edit_others_posts' ) ) {
			$changed = true;
			set_transient( OMOCHIX_CORE_PROVIDER_NOTICE . (int) $object_id, 'privileged', 10 * MINUTE_IN_SECONDS );
			continue;
		}
		$keep[] = (int) $term->term_id;
	}
	if ( count( $keep ) > 1 ) {
		$keep    = array();
		$changed = true;
		set_transient( OMOCHIX_CORE_PROVIDER_NOTICE . (int) $object_id, 'multiple', 10 * MINUTE_IN_SECONDS );
	}
	if ( $changed ) {
		$running = true;
		wp_set_object_terms( (int) $object_id, $keep, OMOCHIX_CORE_PROVIDER_TAXONOMY, false );
		$running = false;
	}
}
add_action( 'set_object_terms', 'omochix_core_provider_enforce_single', 10, 6 );

/** Admin notice after a save that had more than one provider. */
function omochix_core_provider_notice() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id || ! get_transient( OMOCHIX_CORE_PROVIDER_NOTICE . $post_id ) ) {
		return;
	}
	$reason = get_transient( OMOCHIX_CORE_PROVIDER_NOTICE . $post_id );
	delete_transient( OMOCHIX_CORE_PROVIDER_NOTICE . $post_id );
	$text = 'privileged' === $reason ? __( '「OmochiX公式」と「パートナー（PR）」は編集者だけが設定できます。設定は反映していません。', 'omochix-core' ) : __( '提供元は1つだけ選べます。複数選ばれていたため「未分類」に戻しました。', 'omochix-core' );
	echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
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
	$editor = current_user_can( 'edit_others_posts' );
	foreach ( omochix_core_get_provider_vocabulary() as $slug => $label ) {
		if ( ! isset( $by_slug[ $slug ] ) ) {
			continue;
		}
		// Official / partner can be chosen only by editors (an author keeps a value an editor set).
		$locked = ! $can_assign || ( ! $editor && $slug !== $current && in_array( $slug, omochix_core_get_privileged_provider_types(), true ) );
		printf(
			'<p><label><input type="radio" name="tax_input[%1$s][]" value="%2$d" %3$s %4$s> %5$s</label></p>',
			esc_attr( OMOCHIX_CORE_PROVIDER_TAXONOMY ),
			(int) $by_slug[ $slug ]->term_id,
			checked( $slug, $current, false ),
			disabled( $locked, true, false ),
			esc_html( $label )
		);
	}
	echo '<p class="description">' . esc_html__( '未分類のままでは「公式」として扱われません。パートナー提供は PR と表示されます。', 'omochix-core' ) . '</p></fieldset>';
}
// omochix-industry-hub-api: 2 (end)
