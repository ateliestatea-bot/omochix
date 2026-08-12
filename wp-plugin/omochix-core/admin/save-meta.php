<?php
/**
 * Secure AI tool meta persistence.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save whitelisted AI tool fields.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_ai_tool_meta( $post_id ) {
	if ( 'ai_tool' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
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

	$submitted = array();
	if ( isset( $_POST['omochix_core_meta'] ) && is_array( $_POST['omochix_core_meta'] ) ) {
		$submitted = wp_unslash( $_POST['omochix_core_meta'] );
	}

	foreach ( omochix_core_get_meta_schema() as $key => $field ) {
		if ( 'boolean' === $field['type'] ) {
			$raw_value = isset( $submitted[ $key ] ) ? $submitted[ $key ] : false;
		} elseif ( array_key_exists( $key, $submitted ) ) {
			$raw_value = $submitted[ $key ];
		} else {
			// A field absent from the current editor panel must not be destroyed.
			continue;
		}

		$value = omochix_core_sanitize_meta_value( $raw_value, $key );

		if ( 'rating_overall' === $key && 0 === $value ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		if ( 'array' === $field['type'] ) {
			if ( empty( $value ) ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
			continue;
		}

		if ( 'string' === $field['type'] && '' === $value ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		update_post_meta( $post_id, $key, $value );
	}
}
add_action( 'save_post_ai_tool', 'omochix_core_save_ai_tool_meta' );
