<?php
/**
 * Learn Practice meta contract, sanitizers and registration (Learn Phase 4).
 *
 * Structured parts of a practice item (prompts, code, steps, verification,
 * sources, changelog) are stored as "rows": arrays of associative arrays
 * with a fixed key allow-list and a per-key sanitizer. Editors enter them
 * as JSON in the editor panels (the Phase 4C importer feeds the same shape)
 * and the theme renders them. Code and prompt bodies keep "<", ">" and
 * braces verbatim (see omochix_core_sanitize_prompt_text()); every render
 * path escapes at output.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_PRACTICE_SUMMARY_MAX = 120;

/**
 * Return the Learn Practice meta contract.
 *
 * Row fields carry 'rows' => [key => type] and 'max' (row count). Row value
 * types: text, key, code_text, html, url, int, date, textarea.
 *
 * @return array<string, array<string, mixed>>
 */
function omochix_core_get_practice_meta_schema() {
	return array(
		'practice_summary'        => array( 'type' => 'string', 'default' => '', 'sanitize' => 'summary' ),
		'practice_difficulty'     => array( 'type' => 'string', 'default' => 'beginner', 'sanitize' => 'enum', 'options' => array( 'beginner', 'intermediate', 'advanced' ) ),
		'practice_minutes'        => array( 'type' => 'integer', 'default' => 0, 'sanitize' => 'nonnegative_integer' ),
		'practice_outcome'        => array( 'type' => 'string', 'default' => '', 'sanitize' => 'textarea' ),
		'practice_preview_type'   => array( 'type' => 'string', 'default' => 'image', 'sanitize' => 'enum', 'options' => array( 'image', 'srcdoc', 'youtube' ) ),
		'practice_preview_src'    => array( 'type' => 'string', 'default' => '', 'sanitize' => 'preview_src' ),
		'practice_preview_poster' => array( 'type' => 'integer', 'default' => 0, 'sanitize' => 'image_id' ),
		'practice_prompts'        => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 20, 'rows' => array( 'title' => 'text', 'for' => 'key', 'body' => 'code_text' ) ),
		'practice_code'           => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 20, 'rows' => array( 'label' => 'text', 'lang' => 'key', 'file' => 'text', 'body' => 'code_text' ) ),
		'practice_prereq'         => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'string_array' ),
		'practice_steps'          => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 40, 'rows' => array( 'title' => 'text', 'body' => 'html', 'prompt' => 'int', 'code' => 'int', 'check' => 'text' ) ),
		'practice_verify'         => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 20, 'rows' => array( 'title' => 'text', 'how' => 'textarea', 'expect' => 'textarea' ) ),
		'practice_safety'         => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'string_array' ),
		'practice_troubleshooting' => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 20, 'rows' => array( 'symptom' => 'text', 'cause' => 'textarea', 'fix' => 'textarea' ) ),
		'practice_publish'        => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'string_array' ),
		'practice_env'            => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'string_array' ),
		'related_practice_ids'    => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'post_id_array' ),
		'related_prompt_ids'      => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'post_id_array' ),
		'related_tool_ids'        => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'post_id_array' ),
		'related_learn_ids'       => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'post_id_array' ),
		'related_motion_slugs'    => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'slug_array', 'max' => 6 ),
		'practice_verified_at'    => array( 'type' => 'string', 'default' => '', 'sanitize' => 'date' ),
		'practice_sources'        => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 20, 'rows' => array( 'label' => 'text', 'url' => 'url' ) ),
		'practice_changelog'      => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 50, 'rows' => array( 'date' => 'date', 'note' => 'text' ) ),
	);
}

/**
 * Meta keys that must be filled before a practice item is considered
 * complete (the admin shows a notice, the importer refuses to publish).
 *
 * @return string[]
 */
function omochix_core_get_practice_required_meta() {
	return array( 'practice_summary', 'practice_minutes', 'practice_outcome', 'practice_prompts', 'practice_steps', 'practice_verify', 'practice_safety', 'practice_verified_at', 'practice_sources' );
}

/**
 * Translated difficulty label (same vocabulary as prompts and Learn pages).
 *
 * @param mixed $value Stored value.
 * @return string
 */
function omochix_core_get_practice_difficulty_label( $value ) {
	return omochix_core_get_prompt_difficulty_label( $value );
}

/**
 * Days after which a verified date is considered stale.
 *
 * @return int
 */
function omochix_core_get_practice_stale_days() {
	return (int) apply_filters( 'omochix_core_practice_stale_days', 90 );
}

/**
 * Whether a verified date is older than the stale threshold (or missing).
 *
 * @param string $date Y-m-d.
 * @param int    $now  Unix time (for tests).
 * @return bool
 */
function omochix_core_practice_is_stale( $date, $now = 0 ) {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
		return true;
	}
	$now  = $now ? (int) $now : time();
	$then = strtotime( $date . ' 00:00:00 UTC' );
	return ! $then || ( $now - $then ) > omochix_core_get_practice_stale_days() * DAY_IN_SECONDS;
}

/**
 * Sanitize one row value by type.
 *
 * @param mixed  $value Raw value.
 * @param string $type  Row value type.
 * @return mixed
 */
function omochix_core_sanitize_practice_row_value( $value, $type ) {
	switch ( $type ) {
		case 'code_text':
			return omochix_core_sanitize_prompt_text( $value );
		case 'html':
			return is_string( $value ) ? trim( wp_kses_post( str_replace( array( "\r\n", "\r" ), "\n", $value ) ) ) : '';
		case 'textarea':
			return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
		case 'url':
			$url = is_string( $value ) ? esc_url_raw( $value, array( 'http', 'https' ) ) : '';
			return $url ? $url : '';
		case 'int':
			return ( is_int( $value ) || ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) ) ? (int) $value : null;
		case 'date':
			$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				return '';
			}
			$p = explode( '-', $value );
			return checkdate( (int) $p[1], (int) $p[2], (int) $p[0] ) ? $value : '';
		case 'key':
			return is_string( $value ) ? sanitize_key( $value ) : '';
		case 'text':
		default:
			return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}
}

/**
 * Sanitize a rows field: accepts an array of rows or a JSON string. Unknown
 * keys are dropped, missing keys become '' (or null for ints), empty rows
 * are removed and the row count is capped.
 *
 * @param mixed                $value Raw rows (array or JSON text).
 * @param array<string,string> $rows  Key => type allow-list.
 * @param int                  $max   Maximum rows.
 * @return array<int, array<string, mixed>>
 */
function omochix_core_sanitize_practice_rows( $value, $rows, $max ) {
	if ( is_string( $value ) ) {
		$value = '' === trim( $value ) ? array() : json_decode( $value, true );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$clean = array();
		$empty = true;
		foreach ( $rows as $key => $type ) {
			$clean[ $key ] = omochix_core_sanitize_practice_row_value( $row[ $key ] ?? ( 'int' === $type ? null : '' ), $type );
			if ( '' !== $clean[ $key ] && null !== $clean[ $key ] ) {
				$empty = false;
			}
		}
		if ( ! $empty ) {
			$out[] = $clean;
		}
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return $out;
}

/**
 * Normalize a meta value according to its registered contract.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed Null for an unknown key.
 */
function omochix_core_sanitize_practice_meta_value( $value, $key ) {
	$schema = omochix_core_get_practice_meta_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}
	$field = $schema[ $key ];
	switch ( $field['sanitize'] ) {
		case 'summary':
			$value = sanitize_textarea_field( (string) $value );
			$value = preg_replace( '/\s+/u', ' ', $value );
			return mb_substr( trim( (string) $value ), 0, OMOCHIX_CORE_PRACTICE_SUMMARY_MAX );
		case 'textarea':
			return sanitize_textarea_field( (string) $value );
		case 'enum':
			$value = sanitize_text_field( (string) $value );
			return in_array( $value, $field['options'], true ) ? $value : $field['default'];
		case 'nonnegative_integer':
			return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
		case 'date':
			return omochix_core_sanitize_practice_row_value( $value, 'date' );
		case 'image_id':
			$id = absint( $value );
			if ( ! $id || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
				return 0;
			}
			return $id;
		case 'preview_src':
			// srcdoc HTML is inlined into a sandboxed iframe by the theme; a
			// closing </script> or </style> would break out of its element.
			$value = omochix_core_sanitize_prompt_text( $value );
			return preg_replace( '#</\s*(script|style)#i', '&lt;/$1', $value );
		case 'string_array':
			if ( is_string( $value ) ) {
				$value = preg_split( '/\r\n|\r|\n/', $value );
			}
			if ( ! is_array( $value ) ) {
				return array();
			}
			$value = array_filter( array_map( 'trim', array_map( 'sanitize_text_field', array_map( 'strval', $value ) ) ) );
			return array_values( array_unique( $value ) );
		case 'post_id_array':
			if ( ! is_array( $value ) ) {
				return array();
			}
			return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
		case 'slug_array':
			if ( is_string( $value ) ) {
				$value = preg_split( '/[\s,]+/', $value );
			}
			if ( ! is_array( $value ) ) {
				return array();
			}
			$slugs = array();
			foreach ( $value as $slug ) {
				$slug = sanitize_title( (string) $slug );
				if ( '' !== $slug && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) && ! in_array( $slug, $slugs, true ) ) {
					$slugs[] = $slug;
				}
				if ( count( $slugs ) >= $field['max'] ) {
					break;
				}
			}
			return $slugs;
		case 'json_rows':
			return omochix_core_sanitize_practice_rows( $value, $field['rows'], $field['max'] );
		default:
			return sanitize_text_field( (string) $value );
	}
}

/**
 * Whether a raw rows value is well-formed JSON (used by the admin save to
 * keep the previous value instead of wiping it on a typo).
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function omochix_core_practice_rows_input_is_valid( $value ) {
	if ( is_array( $value ) ) {
		return true;
	}
	if ( ! is_string( $value ) ) {
		return false;
	}
	if ( '' === trim( $value ) ) {
		return true;
	}
	$decoded = json_decode( $value, true );
	return is_array( $decoded ) && JSON_ERROR_NONE === json_last_error();
}

/**
 * Return the meta keys whose value is missing on a post.
 *
 * @param int $post_id Post ID.
 * @return string[]
 */
function omochix_core_get_practice_missing_meta( $post_id ) {
	$missing = array();
	foreach ( omochix_core_get_practice_required_meta() as $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( '' === $value || null === $value || array() === $value || 0 === $value || '0' === $value ) {
			$missing[] = $key;
		}
	}
	return $missing;
}

/**
 * REST/meta API sanitize callback.
 *
 * @param mixed  $value    Raw value.
 * @param string $meta_key Meta key.
 * @return mixed
 */
function omochix_core_sanitize_registered_practice_meta( $value, $meta_key ) {
	return omochix_core_sanitize_practice_meta_value( $value, $meta_key );
}

/**
 * Restrict meta writes to users who can edit the current post.
 *
 * @param bool   $allowed  Whether access is currently allowed.
 * @param string $meta_key Meta key.
 * @param int    $post_id  Post ID.
 * @return bool
 */
function omochix_core_auth_registered_practice_meta( $allowed, $meta_key, $post_id ) {
	unset( $allowed, $meta_key );
	return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
}

/**
 * REST schema for an array field (rows become objects, the rest strings or ints).
 *
 * @param array<string, mixed> $field Schema entry.
 * @return array<string, mixed>
 */
function omochix_core_practice_rest_schema( $field ) {
	if ( 'json_rows' === $field['sanitize'] ) {
		$props = array();
		foreach ( $field['rows'] as $key => $type ) {
			$props[ $key ] = array( 'type' => 'int' === $type ? array( 'integer', 'null' ) : 'string' );
		}
		return array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => $props, 'additionalProperties' => false ) );
	}
	return array( 'type' => 'array', 'items' => array( 'type' => 'post_id_array' === $field['sanitize'] ? 'integer' : 'string' ) );
}

/**
 * Normalised form of a practice meta value for change detection: missing,
 * "", [], 0 and "0" are all "empty"; arrays compare by their JSON.
 *
 * @param mixed $value Stored value.
 * @return string
 */
function omochix_core_practice_revision_normalize( $value ) {
	if ( null === $value || '' === $value || array() === $value || 0 === $value || '0' === $value || false === $value ) {
		return '';
	}
	return is_array( $value ) ? (string) wp_json_encode( $value ) : (string) $value;
}

/**
 * Decide whether a practice post differs from its latest revision.
 *
 * Core compares revisioned meta with get_post_meta( $id, $key ) on both sides;
 * the post answers with the registered default ([""] / [0] / [[]]) for keys it
 * does not have while the revision answers [], so every save looked changed and
 * each editor save produced two revisions. Compare single values, normalised.
 *
 * @param bool    $post_has_changed Core's verdict.
 * @param WP_Post $latest_revision  Latest revision.
 * @param WP_Post $post             The post.
 * @return bool
 */
function omochix_core_practice_revision_has_changed( $post_has_changed, $latest_revision, $post ) {
	if ( ! $post instanceof WP_Post || OMOCHIX_CORE_PRACTICE_POST_TYPE !== $post->post_type || ! $latest_revision instanceof WP_Post ) {
		return $post_has_changed;
	}
	$fields = function_exists( '_wp_post_revision_fields' ) ? array_keys( _wp_post_revision_fields( $post ) ) : array( 'post_title', 'post_content', 'post_excerpt' );
	foreach ( $fields as $field ) {
		$a = (string) ( $post->$field ?? '' );
		$b = (string) ( $latest_revision->$field ?? '' );
		if ( function_exists( 'normalize_whitespace' ) ) {
			$a = normalize_whitespace( $a );
			$b = normalize_whitespace( $b );
		}
		if ( $a !== $b ) {
			return true;
		}
	}
	foreach ( array_keys( omochix_core_get_practice_meta_schema() ) as $key ) {
		if ( omochix_core_practice_revision_normalize( get_post_meta( $post->ID, $key, true ) ) !== omochix_core_practice_revision_normalize( get_post_meta( $latest_revision->ID, $key, true ) ) ) {
			return true;
		}
	}
	return false;
}
add_filter( 'wp_save_post_revision_post_has_changed', 'omochix_core_practice_revision_has_changed', 10, 3 );

/**
 * Register all public, typed Learn Practice meta.
 *
 * @return void
 */
function omochix_core_register_practice_post_meta() {
	foreach ( omochix_core_get_practice_meta_schema() as $key => $field ) {
		register_post_meta(
			OMOCHIX_CORE_PRACTICE_POST_TYPE,
			$key,
			array(
				'type'              => $field['type'],
				'single'            => true,
				'default'           => $field['default'],
				'show_in_rest'      => 'array' === $field['type'] ? array( 'schema' => omochix_core_practice_rest_schema( $field ) ) : true,
				'sanitize_callback' => 'omochix_core_sanitize_registered_practice_meta',
				'auth_callback'     => 'omochix_core_auth_registered_practice_meta',
				// Phase 6A: every save (editor, quick edit, importer) keeps the meta in the
				// post revision, and "この版に戻す" restores it with the post fields (WP 6.4+).
				'revisions_enabled' => true,
			)
		);
	}
}
