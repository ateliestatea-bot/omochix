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

	foreach ( omochix_core_get_prompt_meta_schema() as $key => $field ) {
		if ( ! array_key_exists( $key, $submitted ) ) {
			continue;
		}

		$value = omochix_core_sanitize_prompt_meta_value( $submitted[ $key ], $key );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		update_post_meta( $post_id, $key, $value );
	}
}
add_action( 'save_post_prompt', 'omochix_core_save_prompt_meta' );
