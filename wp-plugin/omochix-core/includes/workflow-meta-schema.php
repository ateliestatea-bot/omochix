<?php
/**
 * Workflow data layer on learn_practice (Industry Hub Phase 1).
 *
 * A workflow is a practice item whose format (learn_format) is "workflow"
 * (導入する). Practice items of every other format are not affected: the
 * practice schema, required fields, guard, exporter and revisions keep
 * working exactly as before. The workflow fields live in their own schema
 * and their own guard, which only looks at workflow items.
 *
 * Phase 1 does not create the "workflow" format term (it would add an empty
 * /learn/format/workflow/ archive). omochix_core_insert_workflow_format_term()
 * creates it when Workflow Library work starts (Phase 3); until then no
 * item is a workflow and nothing here changes behaviour.
 *
 * Results distinguish measured (OmochiX verified), reported (published by
 * the provider or a case study) and expected (an estimate, never a promise).
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_WORKFLOW_FORMAT          = 'workflow';
const OMOCHIX_CORE_WORKFLOW_GUARD_TRANSIENT = 'omochix_core_workflow_guard_';

/**
 * Workflow meta contract (registered on learn_practice).
 *
 * @return array<string, array<string, mixed>>
 */
function omochix_core_get_workflow_meta_schema() {
	return array(
		'workflow_problem'      => array( 'type' => 'string', 'default' => '', 'sanitize' => 'textarea', 'max' => 600 ),
		'workflow_audience'     => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 6, 'rows' => array( 'role' => 'text', 'note' => 'textarea' ) ),
		'workflow_cost'         => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 10, 'rows' => array( 'item' => 'text', 'amount' => 'text', 'period' => 'key', 'note' => 'textarea', 'checked_at' => 'date' ), 'enums' => array( 'period' => array( 'once', 'month', 'year', 'usage' ) ) ),
		'workflow_metrics'      => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 8, 'rows' => array( 'metric' => 'text', 'how' => 'textarea', 'frequency' => 'text' ) ),
		'workflow_results'      => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'json_rows', 'max' => 8, 'rows' => array( 'metric' => 'text', 'value' => 'text', 'evidence' => 'key', 'source_url' => 'url', 'source_resource_id' => 'int', 'measured_at' => 'date', 'note' => 'textarea' ), 'enums' => array( 'evidence' => array( 'measured', 'reported', 'expected' ) ) ),
		'workflow_resource_ids' => array( 'type' => 'array', 'default' => array(), 'sanitize' => 'post_id_array', 'max' => 10 ),
	);
}

/** Evidence labels as shown to visitors. */
function omochix_core_get_workflow_evidence_labels() {
	return array(
		'measured' => __( 'OmochiX検証済み', 'omochix-core' ),
		'reported' => __( '提供元・事例の公表値', 'omochix-core' ),
		'expected' => __( '想定・目安（成果を保証しません）', 'omochix-core' ),
	);
}

/**
 * Words that turn an estimate into a promise. An "expected" result may not
 * contain them.
 *
 * @return string[]
 */
function omochix_core_get_workflow_assertive_words() {
	return (array) apply_filters( 'omochix_core_workflow_assertive_words', array( '必ず', '確実', '絶対', '保証', '間違いなく', '誰でも', '100%', '100％', '１００%', '１００％' ) );
}

/**
 * Sanitize one workflow meta value.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed Null for an unknown key.
 */
function omochix_core_sanitize_workflow_meta_value( $value, $key ) {
	$schema = omochix_core_get_workflow_meta_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}
	$field = $schema[ $key ];
	switch ( $field['sanitize'] ) {
		case 'textarea':
			return mb_substr( is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '', 0, $field['max'] );
		case 'post_id_array':
			return omochix_core_sanitize_typed_post_ids( $value, array( OMOCHIX_CORE_RESOURCE_POST_TYPE ), $field['max'] );
		case 'json_rows':
			$rows = omochix_core_sanitize_practice_rows( $value, $field['rows'], $field['max'] );
			foreach ( $rows as &$row ) {
				foreach ( $field['enums'] ?? array() as $column => $allowed ) {
					$row[ $column ] = in_array( $row[ $column ], $allowed, true ) ? $row[ $column ] : '';
				}
				if ( array_key_exists( 'source_resource_id', $row ) && null !== $row['source_resource_id'] && OMOCHIX_CORE_RESOURCE_POST_TYPE !== get_post_type( (int) $row['source_resource_id'] ) ) {
					$row['source_resource_id'] = null;
				}
			}
			unset( $row );
			return $rows;
	}
	return null;
}

/** register_post_meta sanitize callback. */
function omochix_core_sanitize_registered_workflow_meta( $value, $meta_key ) {
	return omochix_core_sanitize_workflow_meta_value( $value, $meta_key );
}

/**
 * Register the workflow meta on learn_practice (same auth as practice meta).
 *
 * @return void
 */
function omochix_core_register_workflow_post_meta() {
	foreach ( omochix_core_get_workflow_meta_schema() as $key => $field ) {
		register_post_meta(
			'learn_practice',
			$key,
			array(
				'type'              => $field['type'],
				'single'            => true,
				'default'           => $field['default'],
				// Ledger IDs stay out of the public REST response (the panel is a classic meta box).
				'show_in_rest'      => 'workflow_resource_ids' === $key ? false : ( 'array' === $field['type'] ? array( 'schema' => omochix_core_practice_rest_schema( $field ) ) : true ),
				'sanitize_callback' => 'omochix_core_sanitize_registered_workflow_meta',
				'auth_callback'     => 'omochix_core_auth_registered_practice_meta',
				'revisions_enabled' => true,
			)
		);
	}
}

/**
 * Create the "workflow" format term (導入する). Not called in Phase 1.
 *
 * @return void
 */
function omochix_core_insert_workflow_format_term() {
	if ( taxonomy_exists( 'learn_format' ) && ! term_exists( OMOCHIX_CORE_WORKFLOW_FORMAT, 'learn_format' ) ) {
		wp_insert_term( '導入する', 'learn_format', array( 'slug' => OMOCHIX_CORE_WORKFLOW_FORMAT ) );
	}
}

/**
 * The practice revision filter only compares the practice schema, so an edit
 * that changes only workflow fields would not get a revision. Add the
 * workflow keys to the comparison (it never turns a "changed" into "same").
 *
 * @param bool    $post_has_changed Current verdict.
 * @param WP_Post $latest_revision  Latest revision.
 * @param WP_Post $post             Post.
 * @return bool
 */
function omochix_core_workflow_revision_has_changed( $post_has_changed, $latest_revision, $post ) {
	if ( $post_has_changed || ! $post instanceof WP_Post || 'learn_practice' !== $post->post_type || ! $latest_revision instanceof WP_Post ) {
		return $post_has_changed;
	}
	foreach ( array_keys( omochix_core_get_workflow_meta_schema() ) as $key ) {
		if ( omochix_core_practice_revision_normalize( get_post_meta( $post->ID, $key, true ) ) !== omochix_core_practice_revision_normalize( get_post_meta( $latest_revision->ID, $key, true ) ) ) {
			return true;
		}
	}
	return false;
}
add_filter( 'wp_save_post_revision_post_has_changed', 'omochix_core_workflow_revision_has_changed', 11, 3 );

/* -------------------------------------------------------------------------
 * What a request would leave on the item
 * ---------------------------------------------------------------------- */

/**
 * Term slugs a request submits for a taxonomy, or null when it submits none.
 * Classic: tax_input (IDs for hierarchical, comma-separated names or IDs for
 * flat taxonomies). REST: the taxonomy's rest_base (IDs).
 *
 * Classic requests are read from wp_insert_post()'s $postarr['tax_input'],
 * which core has already resolved for post.php, quick edit and bulk edit
 * (bulk edit merges the stored terms in); raw $_POST is never used.
 *
 * @param string     $taxonomy  Taxonomy.
 * @param mixed|null $request   WP_REST_Request-like array access, or null for a classic request.
 * @param array|null $tax_input Classic: $postarr['tax_input'] (null when the request submits no terms).
 * @return string[]|null
 */
function omochix_core_workflow_submitted_terms( $taxonomy, $request = null, $tax_input = null ) {
	if ( null !== $request ) {
		$object = get_taxonomy( $taxonomy );
		$base   = $object && ! empty( $object->rest_base ) ? $object->rest_base : $taxonomy;
		$raw    = $request[ $base ] ?? null;
	} else {
		$raw = is_array( $tax_input ) && array_key_exists( $taxonomy, $tax_input ) ? wp_unslash( $tax_input[ $taxonomy ] ) : null;
	}
	if ( null === $raw ) {
		return null;
	}
	$items = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
	$slugs = array();
	foreach ( $items as $item ) {
		if ( ! is_scalar( $item ) ) {
			continue; // nested arrays (tax_input[x][0][]=…) are never a term
		}
		$item = trim( (string) $item );
		if ( '' === $item || '0' === $item ) {
			continue;
		}
		$term = ctype_digit( $item ) ? get_term( (int) $item, $taxonomy ) : ( get_term_by( 'slug', sanitize_title( $item ), $taxonomy ) ?: get_term_by( 'name', $item, $taxonomy ) );
		if ( $term instanceof WP_Term ) {
			$slugs[] = $term->slug;
		}
	}
	return array_values( array_unique( $slugs ) );
}

/**
 * Term slugs the item would have after the request.
 *
 * @param int        $post_id   Post ID.
 * @param string     $taxonomy  Taxonomy.
 * @param mixed|null $request   REST request or null.
 * @param array|null $tax_input Classic $postarr['tax_input'].
 * @return string[]
 */
function omochix_core_workflow_effective_terms( $post_id, $taxonomy, $request = null, $tax_input = null ) {
	$submitted = omochix_core_workflow_submitted_terms( $taxonomy, $request, $tax_input );
	if ( null !== $submitted ) {
		return $submitted;
	}
	$terms = $post_id ? get_the_terms( (int) $post_id, $taxonomy ) : false;
	return is_array( $terms ) ? array_values( wp_list_pluck( $terms, 'slug' ) ) : array();
}

/**
 * Whether the item is (or would become) a workflow.
 *
 * @param int        $post_id   Post ID.
 * @param mixed|null $request   REST request or null.
 * @param array|null $tax_input Classic $postarr['tax_input'].
 * @return bool
 */
function omochix_core_is_workflow( $post_id, $request = null, $tax_input = null ) {
	return in_array( OMOCHIX_CORE_WORKFLOW_FORMAT, omochix_core_workflow_effective_terms( $post_id, 'learn_format', $request, $tax_input ), true );
}

/**
 * Workflow values the request would leave (stored overlaid with the
 * classic panel fields or the REST meta), sanitized.
 *
 * @param int        $post_id Post ID.
 * @param mixed|null $request REST request or null.
 * @return array<string, mixed>
 */
function omochix_core_workflow_effective_meta( $post_id, $request = null ) {
	$meta = array();
	foreach ( omochix_core_get_workflow_meta_schema() as $key => $field ) {
		$raw          = $post_id ? get_post_meta( (int) $post_id, $key, true ) : '';
		$meta[ $key ] = '' === $raw ? $field['default'] : omochix_core_sanitize_workflow_meta_value( $raw, $key );
	}
	if ( null !== $request ) {
		$submitted = is_array( $request['meta'] ?? null ) ? $request['meta'] : array();
	} else {
		// Only values the panel's save handler will really store count: same nonce check.
		$nonce     = isset( $_POST['omochix_core_workflow_nonce'] ) && is_string( $_POST['omochix_core_workflow_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['omochix_core_workflow_nonce'] ) ) : '';
		$submitted = '' !== $nonce && wp_verify_nonce( $nonce, 'omochix_core_save_workflow' ) && isset( $_POST['omochix_core_workflow_meta'] ) && is_array( $_POST['omochix_core_workflow_meta'] ) ? wp_unslash( $_POST['omochix_core_workflow_meta'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.
	}
	foreach ( $submitted as $key => $value ) {
		if ( ! isset( $meta[ $key ] ) ) {
			continue;
		}
		if ( 'json_rows' === omochix_core_get_workflow_meta_schema()[ $key ]['sanitize'] && ! omochix_core_practice_rows_input_is_valid( $value ) ) {
			continue;
		}
		$meta[ $key ] = omochix_core_sanitize_workflow_meta_value( $value, $key );
	}
	return $meta;
}

/**
 * related_tool_ids the request would leave (practice meta, saved by the
 * practice relation picker or REST).
 *
 * @param int        $post_id Post ID.
 * @param mixed|null $request REST request or null.
 * @return int[]
 */
function omochix_core_workflow_effective_tool_ids( $post_id, $request = null ) {
	$value = $post_id ? get_post_meta( (int) $post_id, 'related_tool_ids', true ) : array();
	if ( null !== $request ) {
		if ( is_array( $request['meta'] ?? null ) && array_key_exists( 'related_tool_ids', $request['meta'] ) ) {
			$value = $request['meta']['related_tool_ids'];
		}
	} else {
		// The practice save handler stores relation fields only with its nonce; mirror that.
		$nonce = isset( $_POST['omochix_core_practice_nonce'] ) && is_string( $_POST['omochix_core_practice_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['omochix_core_practice_nonce'] ) ) : '';
		$fields = isset( $_POST['omochix_core_practice_relation_fields'] ) ? array_map( 'sanitize_key', array_filter( (array) wp_unslash( $_POST['omochix_core_practice_relation_fields'] ), 'is_scalar' ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $nonce && wp_verify_nonce( $nonce, 'omochix_core_save_practice' ) && in_array( 'related_tool_ids', $fields, true ) ) {
			$value = $_POST['omochix_core_practice_meta']['related_tool_ids'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		}
	}
	return omochix_core_sanitize_typed_post_ids( is_array( $value ) ? wp_unslash( $value ) : $value, array( 'ai_tool' ), 50 );
}

/* -------------------------------------------------------------------------
 * Guard
 * ---------------------------------------------------------------------- */

/**
 * Why a workflow may not be published. Pure: everything comes in as values.
 *
 * @param array<string, mixed> $meta       Workflow meta (sanitized).
 * @param string[]             $industries Industry slugs.
 * @param string               $provider   Provider type.
 * @param int[]                $tool_ids   Required AI tools.
 * @return string[] Messages.
 */
function omochix_core_workflow_publish_blockers( array $meta, array $industries, $provider, array $tool_ids ) {
	$lines   = array();
	$missing = array();
	if ( '' === trim( (string) ( $meta['workflow_problem'] ?? '' ) ) ) {
		$missing[] = __( '解決する業務課題', 'omochix-core' );
	}
	if ( ! $industries ) {
		$missing[] = __( '対象業種', 'omochix-core' );
	}
	if ( ! array_filter( (array) ( $meta['workflow_audience'] ?? array() ), static fn( $r ) => '' !== (string) ( $r['role'] ?? '' ) ) ) {
		$missing[] = __( '想定利用者', 'omochix-core' );
	}
	if ( ! $tool_ids ) {
		$missing[] = __( '必要なAIツール', 'omochix-core' );
	}
	$costs = array_filter( (array) ( $meta['workflow_cost'] ?? array() ), static fn( $r ) => '' !== (string) ( $r['item'] ?? '' ) && '' !== (string) ( $r['amount'] ?? '' ) );
	if ( ! $costs ) {
		$missing[] = __( '費用（無料の場合も0円の行を1つ）', 'omochix-core' );
	}
	if ( ! array_filter( (array) ( $meta['workflow_metrics'] ?? array() ), static fn( $r ) => '' !== (string) ( $r['metric'] ?? '' ) && '' !== (string) ( $r['how'] ?? '' ) ) ) {
		$missing[] = __( '効果測定方法', 'omochix-core' );
	}
	if ( OMOCHIX_CORE_PROVIDER_UNCLASSIFIED === $provider ) {
		$missing[] = __( '提供元', 'omochix-core' );
	}
	if ( $missing ) {
		$lines[] = sprintf( __( 'ワークフローの必須項目が未入力です：%s', 'omochix-core' ), implode( '、', $missing ) );
	}
	foreach ( $costs as $i => $row ) {
		if ( '' === (string) ( $row['checked_at'] ?? '' ) ) {
			$lines[] = sprintf( __( '費用 %d 行目：料金を確認した日付を入れてください。', 'omochix-core' ), $i + 1 );
		}
	}
	$words = omochix_core_get_workflow_assertive_words();
	foreach ( array_values( (array) ( $meta['workflow_results'] ?? array() ) ) as $i => $row ) {
		$n        = $i + 1;
		$evidence = (string) ( $row['evidence'] ?? '' );
		if ( '' === $evidence ) {
			$lines[] = sprintf( __( '成果 %d 行目：根拠の区分（検証済み・公表値・想定）を選んでください。', 'omochix-core' ), $n );
			continue;
		}
		if ( 'measured' === $evidence && ( '' === (string) ( $row['measured_at'] ?? '' ) || '' === trim( (string) ( $row['note'] ?? '' ) ) ) ) {
			$lines[] = sprintf( __( '成果 %d 行目：検証済みの成果には測定日と測定条件（メモ）が必要です。', 'omochix-core' ), $n );
		}
		if ( 'reported' === $evidence && ( ( '' === (string) ( $row['source_url'] ?? '' ) && empty( $row['source_resource_id'] ) ) || '' === (string) ( $row['measured_at'] ?? '' ) ) ) {
			$lines[] = sprintf( __( '成果 %d 行目：公表値には出典と確認日が必要です。', 'omochix-core' ), $n );
		}
		if ( 'expected' === $evidence ) {
			$text = (string) ( $row['metric'] ?? '' ) . ' ' . (string) ( $row['value'] ?? '' ) . ' ' . (string) ( $row['note'] ?? '' );
			foreach ( $words as $word ) {
				if ( '' !== $word && false !== mb_strpos( $text, $word ) ) {
					$lines[] = sprintf( __( '成果 %1$d 行目：想定の成果に断定表現「%2$s」は使えません。', 'omochix-core' ), $n, $word );
					break;
				}
			}
		}
	}
	$secrets = array();
	omochix_core_practice_import_lint_secrets( $meta, '', $secrets );
	foreach ( $secrets as $finding ) {
		$lines[] = sprintf( __( '秘密情報らしき文字列：%1$s（%2$s）', 'omochix-core' ), $finding['path'], $finding['label'] );
	}
	return $lines;
}

/**
 * Blockers for an item as the request would leave it ([] when not a workflow).
 *
 * @param int        $post_id   Post ID.
 * @param mixed|null $request   REST request or null.
 * @param array|null $tax_input Classic $postarr['tax_input'].
 * @return string[]
 */
function omochix_core_workflow_request_blockers( $post_id, $request = null, $tax_input = null ) {
	if ( ! omochix_core_is_workflow( $post_id, $request, $tax_input ) ) {
		return array();
	}
	return omochix_core_workflow_publish_blockers(
		omochix_core_workflow_effective_meta( $post_id, $request ),
		omochix_core_workflow_effective_terms( $post_id, OMOCHIX_CORE_INDUSTRY_TAXONOMY, $request, $tax_input ),
		omochix_core_provider_type_from_slugs( omochix_core_workflow_effective_terms( $post_id, OMOCHIX_CORE_PROVIDER_TAXONOMY, $request, $tax_input ) ),
		omochix_core_workflow_effective_tool_ids( $post_id, $request )
	);
}

/**
 * Classic path: runs after the practice guard (10). An item the practice
 * guard already held back is draft by now and skipped. Live items are never
 * demoted.
 *
 * @param array<string, mixed> $data    Slashed post data.
 * @param array<string, mixed> $postarr Raw post array.
 * @return array<string, mixed>
 */
function omochix_core_workflow_guard_insert_data( $data, $postarr ) {
	if ( 'learn_practice' !== ( $data['post_type'] ?? '' ) || ! omochix_core_practice_is_public_status( $data['post_status'] ?? '' ) ) {
		return $data;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return $data;
	}
	$post_id = (int) ( $postarr['ID'] ?? 0 );
	$lines   = omochix_core_workflow_request_blockers( $post_id, null, isset( $postarr['tax_input'] ) && is_array( $postarr['tax_input'] ) ? $postarr['tax_input'] : null );
	if ( ! $lines ) {
		return $data;
	}
	$stored = $post_id ? (string) get_post_field( 'post_status', $post_id ) : '';
	if ( omochix_core_practice_is_public_status( $stored ) ) {
		set_transient( OMOCHIX_CORE_WORKFLOW_GUARD_TRANSIENT . $post_id, array( 'type' => 'warn', 'lines' => $lines ), 10 * MINUTE_IN_SECONDS );
		return $data;
	}
	$data['post_status'] = 'draft';
	set_transient( OMOCHIX_CORE_WORKFLOW_GUARD_TRANSIENT . $post_id, array( 'type' => 'blocked', 'lines' => $lines ), 10 * MINUTE_IN_SECONDS );
	return $data;
}
add_filter( 'wp_insert_post_data', 'omochix_core_workflow_guard_insert_data', 11, 2 );

/**
 * REST path (block editor): after the practice guard; refuse with a message.
 *
 * @param stdClass|WP_Error $prepared Prepared post or an earlier error.
 * @param mixed             $request  Request.
 * @return stdClass|WP_Error
 */
function omochix_core_workflow_guard_rest( $prepared, $request ) {
	if ( $prepared instanceof WP_Error || ! omochix_core_practice_is_public_status( isset( $prepared->post_status ) ? (string) $prepared->post_status : '' ) ) {
		return $prepared;
	}
	$post_id = isset( $prepared->ID ) ? (int) $prepared->ID : 0;
	if ( $post_id && omochix_core_practice_is_public_status( (string) get_post_field( 'post_status', $post_id ) ) ) {
		return $prepared;
	}
	$lines = omochix_core_workflow_request_blockers( $post_id, $request );
	if ( ! $lines ) {
		return $prepared;
	}
	return new WP_Error( 'omochix_workflow_blocked', __( 'ワークフローとして公開できません。', 'omochix-core' ) . ' ' . implode( ' / ', $lines ) . ' ' . __( '入力した項目は「下書き保存」で保存してから、もう一度「公開」してください。', 'omochix-core' ), array( 'status' => 400 ) );
}
add_filter( 'rest_pre_insert_learn_practice', 'omochix_core_workflow_guard_rest', 11, 2 );

/** Admin notice after a held-back classic save. */
function omochix_core_workflow_guard_notice() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id || 'learn_practice' !== get_post_type( $post_id ) ) {
		return;
	}
	$held = get_transient( OMOCHIX_CORE_WORKFLOW_GUARD_TRANSIENT . $post_id );
	if ( ! is_array( $held ) || empty( $held['lines'] ) ) {
		return;
	}
	delete_transient( OMOCHIX_CORE_WORKFLOW_GUARD_TRANSIENT . $post_id );
	$blocked = 'blocked' === ( $held['type'] ?? '' );
	echo '<div class="notice ' . ( $blocked ? 'notice-error' : 'notice-warning' ) . ' is-dismissible"><p><strong>' . esc_html( $blocked ? __( 'ワークフローの記載が不足しているため下書きのまま保存しました。', 'omochix-core' ) : __( '公開中のワークフローに不足があります（公開状態は変えていません）。', 'omochix-core' ) ) . '</strong></p><ul>';
	foreach ( (array) $held['lines'] as $line ) {
		echo '<li>' . esc_html( (string) $line ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'omochix_core_workflow_guard_notice' );
