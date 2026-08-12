<?php
/**
 * Registered AI tool meta and normalization helpers.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the MVP meta contract.
 *
 * Formal enum values are normalized for legacy Home reads in compatibility.php.
 *
 * @return array<string, array<string, mixed>>
 */
function omochix_core_get_meta_schema() {
	return array(
		'short_description' => array(
			'type'     => 'string',
			'default'  => '',
			'sanitize' => 'textarea',
		),
		'tool_logo'         => array(
			'type'     => 'integer',
			'default'  => 0,
			'sanitize' => 'image_id',
		),
		'official_url'      => array(
			'type'     => 'string',
			'default'  => '',
			'sanitize' => 'url',
		),
		'company_name'      => array(
			'type'     => 'string',
			'default'  => '',
			'sanitize' => 'text',
		),
		'tool_status'       => array(
			'type'     => 'string',
			'default'  => 'active',
			'sanitize' => 'enum',
			'options'  => array( 'active', 'beta', 'waitlist', 'discontinued' ),
		),
		'pricing_type'      => array(
			'type'     => 'string',
			'default'  => 'contact',
			'sanitize' => 'enum',
			'options'  => array( 'free', 'freemium', 'paid', 'trial', 'contact' ),
		),
		'has_free_plan'     => array(
			'type'     => 'boolean',
			'default'  => false,
			'sanitize' => 'boolean',
		),
		'japanese_support'  => array(
			'type'     => 'string',
			'default'  => 'unknown',
			'sanitize' => 'enum',
			'options'  => array( 'full', 'partial', 'none', 'unknown' ),
		),
		'rating_overall'    => array(
			'type'     => 'number',
			'default'  => 0,
			'sanitize' => 'rating',
		),
		'key_features'      => array(
			'type'     => 'array',
			'default'  => array(),
			'sanitize' => 'string_array',
		),
		'pros'              => array(
			'type'     => 'array',
			'default'  => array(),
			'sanitize' => 'string_array',
		),
		'cons'              => array(
			'type'     => 'array',
			'default'  => array(),
			'sanitize' => 'string_array',
		),
		'recommended_for'   => array(
			'type'     => 'array',
			'default'  => array(),
			'sanitize' => 'string_array',
		),
		'is_featured'       => array(
			'type'     => 'boolean',
			'default'  => false,
			'sanitize' => 'boolean',
		),
		'display_order'     => array(
			'type'     => 'integer',
			'default'  => 0,
			'sanitize' => 'nonnegative_integer',
		),
	);
}

/**
 * Normalize current and legacy Japanese support values.
 *
 * @param mixed $value Stored or submitted value.
 * @return string
 */
function omochix_core_normalize_japanese_support( $value ) {
	if ( in_array( $value, array( 'full', 'partial', 'none', 'unknown' ), true ) ) {
		return $value;
	}
	if ( in_array( $value, array( true, 1, '1', 'true', 'yes', 'on' ), true ) ) {
		return 'full';
	}
	// WordPress commonly persists a boolean false meta value as an empty string.
	if ( in_array( $value, array( false, 0, '', '0', 'false', 'no', 'off' ), true ) ) {
		return 'none';
	}
	return 'unknown';
}

/**
 * Return the translated Japanese support label.
 *
 * @param mixed $value Current or legacy value.
 * @return string
 */
function omochix_core_get_japanese_support_label( $value ) {
	$labels = array(
		'full'    => __( '日本語対応', 'omochix-core' ),
		'partial' => __( '一部日本語対応', 'omochix-core' ),
		'none'    => __( '日本語非対応', 'omochix-core' ),
		'unknown' => __( '未確認', 'omochix-core' ),
	);
	return $labels[ omochix_core_normalize_japanese_support( $value ) ];
}

/**
 * Normalize current and legacy pricing values.
 *
 * @param mixed $value Stored or submitted value.
 * @return string
 */
function omochix_core_normalize_pricing_type( $value ) {
	$legacy_map = array(
		'free'           => 'free',
		'freemium'       => 'freemium',
		'paid'           => 'paid',
		'trial'          => 'trial',
		'contact'        => 'contact',
		'無料'           => 'free',
		'無料・有料'     => 'freemium',
		'無料プランあり' => 'freemium',
		'有料'           => 'paid',
		'無料体験'       => 'trial',
		'無料体験あり'   => 'trial',
		'要問い合わせ'   => 'contact',
		'料金情報なし'   => 'contact',
	);
	$value = is_scalar( $value ) ? (string) $value : '';
	return isset( $legacy_map[ $value ] ) ? $legacy_map[ $value ] : 'contact';
}

/**
 * Return the translated pricing label.
 *
 * @param mixed $value Current or legacy value.
 * @return string
 */
function omochix_core_get_pricing_type_label( $value ) {
	$labels = array(
		'free'     => __( '無料', 'omochix-core' ),
		'freemium' => __( '無料プランあり', 'omochix-core' ),
		'paid'     => __( '有料', 'omochix-core' ),
		'trial'    => __( '無料体験あり', 'omochix-core' ),
		'contact'  => __( '要問い合わせ', 'omochix-core' ),
	);
	return $labels[ omochix_core_normalize_pricing_type( $value ) ];
}

/**
 * Normalize a meta value according to its registered contract.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed
 */
function omochix_core_sanitize_meta_value( $value, $key ) {
	$schema = omochix_core_get_meta_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}

	$field = $schema[ $key ];
	switch ( $field['sanitize'] ) {
		case 'textarea':
			return sanitize_textarea_field( $value );
		case 'url':
			$url = esc_url_raw( $value, array( 'http', 'https' ) );
			return $url ? $url : '';
		case 'enum':
			$value = sanitize_text_field( $value );
			return in_array( $value, $field['options'], true ) ? $value : $field['default'];
		case 'boolean':
			return in_array( $value, array( true, 1, '1', 'true', 'yes', 'on' ), true );
		case 'rating':
			if ( '' === $value || ! is_numeric( $value ) ) {
				return 0;
			}
			return round( min( 5, max( 1, (float) $value ) ), 1 );
		case 'nonnegative_integer':
			return max( 0, absint( $value ) );
		case 'image_id':
			$attachment_id = absint( $value );
			if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
				return 0;
			}
			return $attachment_id;
		case 'string_array':
			if ( is_string( $value ) ) {
				$value = preg_split( '/\r\n|\r|\n/', $value );
			}
			if ( ! is_array( $value ) ) {
				return array();
			}
			$value = array_map( 'sanitize_text_field', $value );
			$value = array_filter( array_map( 'trim', $value ) );
			return array_values( array_unique( $value ) );
		case 'text':
		default:
			return sanitize_text_field( $value );
	}
}

/**
 * REST/meta API sanitize callback.
 *
 * @param mixed  $value    Raw value.
 * @param string $meta_key Meta key.
 * @return mixed
 */
function omochix_core_sanitize_registered_meta( $value, $meta_key ) {
	return omochix_core_sanitize_meta_value( $value, $meta_key );
}

/**
 * Restrict meta writes to users who can edit the current post.
 *
 * @param bool   $allowed Whether access is currently allowed.
 * @param string $meta_key Meta key.
 * @param int    $post_id Post ID.
 * @return bool
 */
function omochix_core_auth_registered_meta( $allowed, $meta_key, $post_id ) {
	unset( $allowed, $meta_key );
	return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
}

/**
 * Register all public, typed AI tool meta.
 *
 * @return void
 */
function omochix_core_register_post_meta() {
	foreach ( omochix_core_get_meta_schema() as $key => $field ) {
		$show_in_rest = true;
		if ( 'array' === $field['type'] ) {
			$show_in_rest = array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			);
		}

		$args = array(
			'type'              => $field['type'],
			'single'            => true,
			'show_in_rest'      => $show_in_rest,
			'sanitize_callback' => 'omochix_core_sanitize_registered_meta',
			'auth_callback'     => 'omochix_core_auth_registered_meta',
		);
		if ( 'rating_overall' !== $key ) {
			$args['default'] = $field['default'];
		}

		register_post_meta( 'ai_tool', $key, $args );
	}
}
