<?php
/**
 * "Latest updates" and "Changelog" repeatable groups for AI tools.
 *
 * Both are stored as plain arrays of associative arrays (one post meta row
 * each, not one row per entry), matching the low-overhead pattern already
 * used for key_features/pros/cons. They are intentionally kept out of the
 * generic omochix_core_get_meta_schema() contract in meta-schema.php: that
 * contract assumes one scalar or one flat string list per key, and these are
 * compound rows (title + description + date + status + related news), so
 * they get their own small, explicit sanitizer instead of being forced into
 * the generic shape.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return allowed "status" values for a product update entry.
 *
 * @return array<string, string>
 */
function omochix_core_get_update_status_labels() {
	return array(
		'new'        => __( '新機能', 'omochix-core' ),
		'improved'   => __( '改善', 'omochix-core' ),
		'fixed'      => __( '修正', 'omochix-core' ),
		'beta'       => __( 'ベータ提供', 'omochix-core' ),
		'deprecated' => __( '廃止予定', 'omochix-core' ),
	);
}

/**
 * Validate a Y-m-d date string.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function omochix_core_sanitize_entry_date( $value ) {
	$value = sanitize_text_field( (string) $value );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
		return '';
	}
	$parts = explode( '-', $value );
	return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $value : '';
}

/**
 * Sanitize submitted "product update" rows.
 *
 * A row with no title and no date is dropped as an empty/unused row (the
 * repeater UI always renders one trailing blank row).
 *
 * @param mixed $rows Raw $_POST rows.
 * @return array<int, array<string, mixed>>
 */
function omochix_core_sanitize_product_updates( $rows ) {
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$statuses = array_keys( omochix_core_get_update_status_labels() );
	$clean    = array();

	foreach ( array_slice( $rows, 0, 50 ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$date  = omochix_core_sanitize_entry_date( $row['date'] ?? '' );
		if ( '' === $title && '' === $date ) {
			continue;
		}
		$status = sanitize_key( (string) ( $row['status'] ?? '' ) );
		$clean[] = array(
			'title'       => $title,
			'description' => sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ),
			'date'        => $date,
			'status'      => in_array( $status, $statuses, true ) ? $status : 'new',
			'news_id'     => absint( $row['news_id'] ?? 0 ),
		);
	}

	return $clean;
}

/**
 * Sanitize submitted "changelog" rows.
 *
 * @param mixed $rows Raw $_POST rows.
 * @return array<int, array<string, mixed>>
 */
function omochix_core_sanitize_changelog_entries( $rows ) {
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$clean = array();

	foreach ( array_slice( $rows, 0, 100 ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$date  = omochix_core_sanitize_entry_date( $row['date'] ?? '' );
		if ( '' === $title && '' === $date ) {
			continue;
		}
		$clean[] = array(
			'title'   => $title,
			'date'    => $date,
			'news_id' => absint( $row['news_id'] ?? 0 ),
		);
	}

	return $clean;
}

/**
 * Return an AI tool's product-update entries, newest first, with any
 * related News post resolved and validated as still published.
 *
 * @param int $tool_id AI tool post ID.
 * @param int $limit   Maximum entries to return, 0 for all.
 * @return array<int, array<string, mixed>>
 */
function omochix_core_get_product_updates( $tool_id, $limit = 0 ) {
	$entries = get_post_meta( $tool_id, 'product_updates', true );
	$entries = is_array( $entries ) ? $entries : array();

	usort(
		$entries,
		static function ( $a, $b ) {
			return strcmp( (string) ( $b['date'] ?? '' ), (string) ( $a['date'] ?? '' ) );
		}
	);

	foreach ( $entries as &$entry ) {
		$entry['news_url']   = '';
		$entry['news_title'] = '';
		$news_id              = absint( $entry['news_id'] ?? 0 );
		if ( $news_id && 'publish' === get_post_status( $news_id ) && 'post' === get_post_type( $news_id ) ) {
			$entry['news_url']   = get_permalink( $news_id );
			$entry['news_title'] = get_the_title( $news_id );
		}
	}
	unset( $entry );

	return $limit > 0 ? array_slice( $entries, 0, $limit ) : $entries;
}

/**
 * Return an AI tool's changelog entries, newest first, with any related
 * News post resolved and validated as still published.
 *
 * @param int $tool_id AI tool post ID.
 * @return array<int, array<string, mixed>>
 */
function omochix_core_get_changelog_entries( $tool_id ) {
	$entries = get_post_meta( $tool_id, 'changelog', true );
	$entries = is_array( $entries ) ? $entries : array();

	usort(
		$entries,
		static function ( $a, $b ) {
			return strcmp( (string) ( $b['date'] ?? '' ), (string) ( $a['date'] ?? '' ) );
		}
	);

	foreach ( $entries as &$entry ) {
		$entry['news_url']   = '';
		$entry['news_title'] = '';
		$news_id              = absint( $entry['news_id'] ?? 0 );
		if ( $news_id && 'publish' === get_post_status( $news_id ) && 'post' === get_post_type( $news_id ) ) {
			$entry['news_url']   = get_permalink( $news_id );
			$entry['news_title'] = get_the_title( $news_id );
		}
	}
	unset( $entry );

	return $entries;
}

/**
 * Save product_updates and changelog under the same nonce as the rest of
 * the AI tool editor fields.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_product_timeline( $post_id ) {
	if ( 'ai_tool' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['omochix_core_ai_tool_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST['omochix_core_ai_tool_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'omochix_core_save_ai_tool' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['omochix_core_updates'] ) ) {
		$updates = omochix_core_sanitize_product_updates( wp_unslash( $_POST['omochix_core_updates'] ) );
		if ( $updates ) {
			update_post_meta( $post_id, 'product_updates', $updates );
		} else {
			delete_post_meta( $post_id, 'product_updates' );
		}
	}

	if ( isset( $_POST['omochix_core_changelog'] ) ) {
		$changelog = omochix_core_sanitize_changelog_entries( wp_unslash( $_POST['omochix_core_changelog'] ) );
		if ( $changelog ) {
			update_post_meta( $post_id, 'changelog', $changelog );
		} else {
			delete_post_meta( $post_id, 'changelog' );
		}
	}
}
add_action( 'save_post_ai_tool', 'omochix_core_save_product_timeline' );
