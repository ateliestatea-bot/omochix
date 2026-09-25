<?php
/**
 * Secure Prompt Library meta persistence.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save whitelisted Prompt Library fields.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_prompt_meta( $post_id ) {
	if ( 'prompt' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['omochix_core_prompt_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST['omochix_core_prompt_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'omochix_core_save_prompt' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$submitted = array();
	if ( isset( $_POST['omochix_core_prompt_meta'] ) && is_array( $_POST['omochix_core_prompt_meta'] ) ) {
		$submitted = wp_unslash( $_POST['omochix_core_prompt_meta'] );
	}

	// Relation pickers announce themselves only when their <select> was
	// rendered, so a screen without the picker (e.g. no published tools yet)
	// never wipes relations set via CSV import or REST.
	$relation_fields = array();
	if ( isset( $_POST['omochix_core_prompt_relation_fields'] ) && is_array( $_POST['omochix_core_prompt_relation_fields'] ) ) {
		$relation_fields = array_map( 'sanitize_key', wp_unslash( $_POST['omochix_core_prompt_relation_fields'] ) );
	}

	foreach ( omochix_core_get_prompt_meta_schema() as $key => $field ) {
		if ( 'post_id_array' === $field['sanitize'] ) {
			if ( ! in_array( $key, $relation_fields, true ) ) {
				continue;
			}
			// A <select multiple> submits nothing when every selection is
			// cleared; treat that as "no relations" (same as save-meta.php).
			$value = omochix_core_sanitize_prompt_meta_value( $submitted[ $key ] ?? array(), $key );
			if ( $value ) {
				update_post_meta( $post_id, $key, $value );
			} else {
				delete_post_meta( $post_id, $key );
			}
			continue;
		}

		if ( ! array_key_exists( $key, $submitted ) ) {
			continue;
		}

		$value = omochix_core_sanitize_prompt_meta_value( $submitted[ $key ], $key );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		// update_post_meta() unslashes its value; re-slash so backslashes in
		// prompt text (regex, Windows paths, LaTeX) are stored verbatim.
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}
}
add_action( 'save_post_prompt', 'omochix_core_save_prompt_meta' );
