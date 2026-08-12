<?php
/**
 * Read-only compatibility with existing theme contracts.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read post meta without re-entering the get_post_metadata filter.
 *
 * Compatibility callbacks run on get_post_metadata itself, so helpers such as
 * metadata_exists() and get_post_meta() would recursively invoke the callback.
 * WordPress' primed meta cache provides the same stored values safely.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return array{exists:bool,values:array<int,mixed>}
 */
function omochix_core_get_cached_raw_post_meta( $post_id, $key ) {
	$cache = wp_cache_get( $post_id, 'post_meta' );
	if ( false === $cache ) {
		$loaded = update_meta_cache( 'post', array( $post_id ) );
		$cache  = isset( $loaded[ $post_id ] ) ? $loaded[ $post_id ] : array();
	}

	if ( ! isset( $cache[ $key ] ) || ! is_array( $cache[ $key ] ) ) {
		return array( 'exists' => false, 'values' => array() );
	}

	return array(
		'exists' => true,
		'values' => array_map( 'maybe_unserialize', $cache[ $key ] ),
	);
}

/**
 * Expose company_name to the current Home template's legacy company_id read.
 *
 * No company_id value is stored. Once the theme consumes company_name directly,
 * this filter can be removed without a data migration.
 *
 * @param mixed  $value     Existing short-circuit value.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Requested key.
 * @param bool   $single    Whether a single value was requested.
 * @return mixed
 */
function omochix_core_legacy_company_meta( $value, $object_id, $meta_key, $single ) {
	if ( null !== $value || 'company_id' !== $meta_key || 'ai_tool' !== get_post_type( $object_id ) ) {
		return $value;
	}
	$company_id = omochix_core_get_cached_raw_post_meta( $object_id, 'company_id' );
	if ( $company_id['exists'] ) {
		return $value;
	}

	$company_meta = omochix_core_get_cached_raw_post_meta( $object_id, 'company_name' );
	$company_name = $company_meta['exists'] ? reset( $company_meta['values'] ) : '';
	if ( ! $company_name ) {
		return $value;
	}

	return $single ? $company_name : array( $company_name );
}
add_filter( 'get_post_metadata', 'omochix_core_legacy_company_meta', 10, 4 );

/**
 * Normalize legacy contract values without mutating stored data.
 *
 * The existing Home receives its historical presentation values. Every other
 * consumer, including REST, receives the formal English enum.
 *
 * @param mixed  $value     Existing short-circuit value.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Requested key.
 * @param bool   $single    Whether a single value was requested.
 * @return mixed
 */
function omochix_core_normalize_legacy_contract_meta( $value, $object_id, $meta_key, $single ) {
	if ( null !== $value || ! in_array( $meta_key, array( 'japanese_support', 'pricing_type' ), true ) ) {
		return $value;
	}
	if ( 'ai_tool' !== get_post_type( $object_id ) ) {
		return $value;
	}

	$meta = omochix_core_get_cached_raw_post_meta( $object_id, $meta_key );
	if ( ! $meta['exists'] ) {
		return $value;
	}

	$raw_value  = reset( $meta['values'] );
	$normalized = 'japanese_support' === $meta_key
		? omochix_core_normalize_japanese_support( $raw_value )
		: omochix_core_normalize_pricing_type( $raw_value );

	$is_rest_request = defined( 'REST_REQUEST' ) && REST_REQUEST;
	$is_legacy_home  = ! is_admin() && ! $is_rest_request && function_exists( 'is_front_page' ) && is_front_page();
	if ( $is_legacy_home ) {
		$normalized = 'japanese_support' === $meta_key
			? in_array( $normalized, array( 'full', 'partial' ), true )
			: omochix_core_get_pricing_type_label( $normalized );
	}

	return $single ? $normalized : array( $normalized );
}
add_filter( 'get_post_metadata', 'omochix_core_normalize_legacy_contract_meta', 9, 4 );
