<?php
// omochix-glossary-version: 1
/**
 * AI glossary: post meta.
 *
 * Every field is single, typed, sanitized on save and editable only by users
 * who can edit that term. Sanitizing keeps the shape (drops what does not
 * fit); whether a term may be published is decided by the publish guard with
 * the full rules of glossary-validate.php.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta keys and their types.
 *
 * @return array<string, string> key => string|array|sources|ids|int|bool|date|level
 */
function omochix_core_glossary_meta_fields() {
	return array(
		'glossary_reading'       => 'string',
		'glossary_en'            => 'string',
		'glossary_aliases'       => 'array',
		'glossary_summary'       => 'string',
		'glossary_sources'       => 'sources',
		'glossary_verified_on'   => 'date',
		'glossary_review_days'   => 'int',
		'glossary_ai_assisted'   => 'bool',
		'glossary_level'         => 'level',
		'related_glossary_ids'   => 'ids',
		'related_tool_ids'       => 'ids',
		'related_learn_page_ids' => 'ids',
		'related_practice_ids'   => 'ids',
		'related_industry_ids'   => 'ids',
	);
}

/**
 * One line of plain text: no tags, no control characters, trimmed, capped.
 *
 * @param mixed $v   Value.
 * @param int   $max Maximum characters.
 * @return string
 */
function omochix_core_glossary_clean_line( $v, $max ) {
	if ( ! is_scalar( $v ) ) {
		return '';
	}
	$v = trim( (string) preg_replace( '/[\x00-\x1f\x7f]+/', ' ', wp_strip_all_tags( (string) $v ) ) );
	return function_exists( 'mb_substr' ) ? (string) mb_substr( $v, 0, $max ) : $v;
}

/**
 * Sanitize a value of one meta key.
 *
 * @param mixed  $value Value.
 * @param string $key   Meta key.
 * @return mixed
 */
function omochix_core_glossary_sanitize_meta( $value, $key ) {
	$rules  = omochix_core_glossary_rules();
	$fields = omochix_core_glossary_meta_fields();
	switch ( $fields[ $key ] ?? '' ) {
		case 'string':
			$max = array(
				'glossary_reading' => $rules['reading_max'],
				'glossary_en'      => $rules['en_max'],
				'glossary_summary' => $rules['summary_max'],
			);
			return omochix_core_glossary_clean_line( $value, $max[ $key ] ?? 200 );
		case 'array':
			$out = array();
			foreach ( is_array( $value ) ? $value : array() as $v ) {
				$v = omochix_core_glossary_clean_line( $v, $rules['alias_len'] );
				if ( '' !== $v && ! in_array( $v, $out, true ) ) {
					$out[] = $v;
				}
			}
			return array_slice( $out, 0, $rules['alias_max'] );
		case 'sources':
			$out = array();
			foreach ( is_array( $value ) ? $value : array() as $s ) {
				if ( ! is_array( $s ) ) {
					continue;
				}
				$url = is_string( $s['url'] ?? null ) ? trim( $s['url'] ) : '';
				if ( '' !== omochix_core_glossary_url_problem( $url ) ) {
					continue; // Never store a URL the rules refuse.
				}
				$out[] = array(
					'url'        => esc_url_raw( $url, array( 'https' ) ),
					'title'      => omochix_core_glossary_clean_line( $s['title'] ?? '', $rules['source_title_max'] ),
					'publisher'  => omochix_core_glossary_clean_line( $s['publisher'] ?? '', $rules['publisher_max'] ),
					'kind'       => in_array( $s['kind'] ?? '', $rules['kinds'], true ) ? $s['kind'] : '',
					'checked_on' => is_string( $s['checked_on'] ?? null ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $s['checked_on'] ) ? $s['checked_on'] : '',
				);
			}
			return array_slice( $out, 0, $rules['source_max'] );
		case 'date':
			return is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ? $value : '';
		case 'int':
			$n = (int) $value;
			return $n >= $rules['review_days_min'] && $n <= $rules['review_days_max'] ? $n : $rules['review_days'];
		case 'bool':
			return (bool) $value;
		case 'level':
			return in_array( $value, $rules['levels'], true ) ? $value : '';
		case 'ids':
			$max = array(
				'related_glossary_ids'   => $rules['related']['glossary'],
				'related_tool_ids'       => $rules['related']['tools'],
				'related_learn_page_ids' => $rules['related']['learn_pages'],
				'related_practice_ids'   => $rules['related']['practice'],
				'related_industry_ids'   => $rules['related']['industries'],
			);
			$ids = array();
			foreach ( is_array( $value ) ? $value : array() as $v ) {
				// Positive whole numbers only: absint() would turn -1 into ID 1.
				if ( ( is_int( $v ) && $v > 0 ) || ( is_string( $v ) && ctype_digit( $v ) && (int) $v > 0 ) ) {
					$ids[] = (int) $v;
				}
			}
			$ids = array_values( array_unique( $ids ) );
			return array_slice( $ids, 0, $max[ $key ] ?? 0 );
	}
	return null;
}

/**
 * REST schema of one meta key (editors only; visitors are refused).
 *
 * @param string $type Field type.
 * @return array|bool
 */
function omochix_core_glossary_meta_rest( $type ) {
	switch ( $type ) {
		case 'array':
			return array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) );
		case 'ids':
			return array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) );
		case 'sources':
			return array(
				'schema' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'url'        => array( 'type' => 'string' ),
							'title'      => array( 'type' => 'string' ),
							'publisher'  => array( 'type' => 'string' ),
							'kind'       => array( 'type' => 'string' ),
							'checked_on' => array( 'type' => 'string' ),
						),
					),
				),
			);
	}
	return true;
}

/** Register the meta keys. */
function omochix_core_glossary_register_meta() {
	$types = array(
		'string'  => 'string',
		'date'    => 'string',
		'level'   => 'string',
		'array'   => 'array',
		'sources' => 'array',
		'ids'     => 'array',
		'int'     => 'integer',
		'bool'    => 'boolean',
	);
	foreach ( omochix_core_glossary_meta_fields() as $key => $type ) {
		register_post_meta(
			'omx_glossary',
			$key,
			array(
				'type'              => $types[ $type ],
				'single'            => true,
				'default'           => 'int' === $type ? omochix_core_glossary_rules()['review_days'] : ( 'bool' === $type ? false : ( in_array( $type, array( 'array', 'sources', 'ids' ), true ) ? array() : '' ) ),
				'show_in_rest'      => omochix_core_glossary_meta_rest( $type ),
				'revisions_enabled' => true,
				'sanitize_callback' => static function ( $value ) use ( $key ) {
					return omochix_core_glossary_sanitize_meta( $value, $key );
				},
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);
	}
}
// omochix-glossary-version: 1 (end)
