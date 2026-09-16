<?php
/**
 * Registered Prompt Library meta and normalization helpers.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the Prompt Library meta contract.
 *
 * @return array<string, array<string, mixed>>
 */
function omochix_core_get_prompt_meta_schema() {
	return array(
		'prompt_body'       => array(
			'type'     => 'string',
			'default'  => '',
			'sanitize' => 'code_text',
		),
		'prompt_usage'      => array(
			'type'     => 'string',
			'default'  => '',
			'sanitize' => 'code_text',
		),
		'prompt_example'    => array(
			'type'     => 'string',
			'default'  => '',
			'sanitize' => 'code_text',
		),
		'prompt_difficulty' => array(
			'type'     => 'string',
			'default'  => 'beginner',
			'sanitize' => 'enum',
			'options'  => array( 'beginner', 'intermediate', 'advanced' ),
		),
	);
}

/**
 * Return the translated difficulty label.
 *
 * @param mixed $value Stored or submitted value.
 * @return string
 */
function omochix_core_get_prompt_difficulty_label( $value ) {
	$labels = array(
		'beginner'     => __( '初級', 'omochix-core' ),
		'intermediate' => __( '中級', 'omochix-core' ),
		'advanced'     => __( '上級', 'omochix-core' ),
	);
	return $labels[ $value ] ?? $labels['beginner'];
}

/**
 * Sanitize a prompt text field (prompt_body / prompt_usage / prompt_example)
 * while preserving HTML, JSX, PHP and other code-fragment content verbatim.
 *
 * Prompt Library entries routinely need to store literal markup — e.g.
 * "<button>送信</button>", "<Component enabled={true} />", or
 * "<?php echo '<div>test</div>'; ?>" — as the actual prompt text, not as
 * something WordPress should interpret as HTML. sanitize_text_field(),
 * sanitize_textarea_field(), wp_strip_all_tags() and strip_tags() all treat
 * "<...>"-shaped substrings as markup to remove, which silently corrupts
 * exactly this kind of content. This function intentionally never calls any
 * of those: it only normalizes line endings, drops invalid UTF-8 byte
 * sequences, and strips null bytes / non-printable control characters
 * (keeping newlines and tabs), leaving every other character — including
 * "<", ">", quotes, braces, and brackets — untouched.
 *
 * This is safe specifically because every render path for these three
 * fields (single-prompt.php) passes the value through esc_html() — or
 * nl2br(esc_html()) — before output, so XSS protection lives entirely at
 * the display boundary, not at save time. Never echo these values without
 * that escaping.
 *
 * @param mixed $value Raw (already wp_unslash()'d) value.
 * @return string
 */
function omochix_core_sanitize_prompt_text( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}

	// Normalize CRLF/CR to LF before anything else; this is a byte-safe
	// operation on ASCII characters and cannot corrupt multi-byte UTF-8.
	$value = str_replace( array( "\r\n", "\r" ), "\n", $value );

	// Drop invalid UTF-8 byte sequences without altering valid content.
	$value = wp_check_invalid_utf8( $value );

	// Remove null bytes and other non-printable ASCII control characters,
	// but keep newlines (\n) and tabs (\t) — no tag/markup stripping here.
	$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );

	return trim( (string) $value );
}

/**
 * Normalize a meta value according to its registered contract.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed
 */
function omochix_core_sanitize_prompt_meta_value( $value, $key ) {
	$schema = omochix_core_get_prompt_meta_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}

	$field = $schema[ $key ];
	switch ( $field['sanitize'] ) {
		case 'enum':
			$value = sanitize_text_field( $value );
			return in_array( $value, $field['options'], true ) ? $value : $field['default'];
		case 'code_text':
		default:
			return omochix_core_sanitize_prompt_text( $value );
	}
}

/**
 * REST/meta API sanitize callback.
 *
 * @param mixed  $value    Raw value.
 * @param string $meta_key Meta key.
 * @return mixed
 */
function omochix_core_sanitize_registered_prompt_meta( $value, $meta_key ) {
	return omochix_core_sanitize_prompt_meta_value( $value, $meta_key );
}

/**
 * Restrict meta writes to users who can edit the current post.
 *
 * @param bool   $allowed  Whether access is currently allowed.
 * @param string $meta_key Meta key.
 * @param int    $post_id  Post ID.
 * @return bool
 */
function omochix_core_auth_registered_prompt_meta( $allowed, $meta_key, $post_id ) {
	unset( $allowed, $meta_key );
	return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
}

/**
 * Register all public, typed Prompt Library meta.
 *
 * @return void
 */
function omochix_core_register_prompt_post_meta() {
	foreach ( omochix_core_get_prompt_meta_schema() as $key => $field ) {
		register_post_meta(
			'prompt',
			$key,
			array(
				'type'              => $field['type'],
				'single'            => true,
				'default'           => $field['default'],
				'show_in_rest'      => true,
				'sanitize_callback' => 'omochix_core_sanitize_registered_prompt_meta',
				'auth_callback'     => 'omochix_core_auth_registered_prompt_meta',
			)
		);
	}
}
