<?php
/**
 * Secure Learn Practice meta persistence.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save whitelisted Learn Practice fields.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_practice_meta( $post_id ) {
	if ( OMOCHIX_CORE_PRACTICE_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['omochix_core_practice_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST['omochix_core_practice_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'omochix_core_save_practice' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$submitted = array();
	if ( isset( $_POST['omochix_core_practice_meta'] ) && is_array( $_POST['omochix_core_practice_meta'] ) ) {
		$submitted = wp_unslash( $_POST['omochix_core_practice_meta'] );
	}
	$relation_fields = array();
	if ( isset( $_POST['omochix_core_practice_relation_fields'] ) && is_array( $_POST['omochix_core_practice_relation_fields'] ) ) {
		$relation_fields = array_map( 'sanitize_key', wp_unslash( $_POST['omochix_core_practice_relation_fields'] ) );
	}

	$json_errors   = array();
	$secret_errors = array();
	foreach ( omochix_core_get_practice_meta_schema() as $key => $field ) {
		if ( 'post_id_array' === $field['sanitize'] ) {
			if ( ! in_array( $key, $relation_fields, true ) ) {
				continue;
			}
			$value = omochix_core_sanitize_practice_meta_value( $submitted[ $key ] ?? array(), $key );
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
		if ( 'json_rows' === $field['sanitize'] && ! omochix_core_practice_rows_input_is_valid( $submitted[ $key ] ) ) {
			$json_errors[] = $key;
			continue; // keep the previous value
		}

		$value = omochix_core_sanitize_practice_meta_value( $submitted[ $key ], $key );
		// Phase 6A: a secret-looking string (API key, token, webhook URL, private key, JWT…)
		// never reaches the database from the editor; the stored value is kept and the key reported.
		$findings = array();
		omochix_core_practice_import_lint_secrets( $value, $key, $findings );
		if ( $findings ) {
			$secret_errors[] = $key;
			continue;
		}
		if ( '' === $value || array() === $value || null === $value || ( 0 === $value && 'practice_minutes' !== $key ) ) {
			delete_post_meta( $post_id, $key );
			continue;
		}
		// update_post_meta() unslashes; re-slash so backslashes in prompts and
		// code survive (same as the prompt save handler).
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}

	if ( $json_errors ) {
		set_transient( 'omochix_core_practice_json_error_' . $post_id, $json_errors, MINUTE_IN_SECONDS * 5 );
	}
	if ( $secret_errors ) {
		set_transient( 'omochix_core_practice_secret_error_' . $post_id, array_values( array_unique( $secret_errors ) ), MINUTE_IN_SECONDS * 5 );
	}
}
add_action( 'save_post_' . OMOCHIX_CORE_PRACTICE_POST_TYPE, 'omochix_core_save_practice_meta' );
