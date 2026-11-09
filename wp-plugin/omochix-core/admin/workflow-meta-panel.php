<?php
// omochix-industry-hub-api: 2
/**
 * Workflow fields panel on the practice edit screen (Industry Hub Phase 1).
 *
 * Shown on every practice item; the fields are required only when the
 * format is 導入する (workflow). Rows are JSON like the practice panels.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_WORKFLOW_NONCE_FIELD  = 'omochix_core_workflow_nonce';
const OMOCHIX_CORE_WORKFLOW_NONCE_ACTION = 'omochix_core_save_workflow';
const OMOCHIX_CORE_WORKFLOW_FIELDS       = 'omochix_core_workflow_meta';
const OMOCHIX_CORE_WORKFLOW_SAVE_NOTICE  = 'omochix_core_workflow_save_';

/** Register the panel. */
function omochix_core_add_workflow_meta_box() {
	add_meta_box( 'omochix-core-workflow', __( 'ワークフロー（形式「導入する」で必須）', 'omochix-core' ), 'omochix_core_render_workflow_meta_box', 'learn_practice', 'normal', 'default' );
}
add_action( 'add_meta_boxes_learn_practice', 'omochix_core_add_workflow_meta_box' );

/** Labels and examples per field. */
function omochix_core_workflow_field_labels() {
	return array(
		'workflow_problem'      => array( __( '解決する業務課題', 'omochix-core' ), __( '600字まで。', 'omochix-core' ) ),
		'workflow_audience'     => array( __( '想定利用者（JSON）', 'omochix-core' ), '[{"role":"店長","note":"…"}]' ),
		'workflow_cost'         => array( __( '費用（JSON）', 'omochix-core' ), '[{"item":"ChatGPT Plus","amount":"3,000円","period":"month","note":"参考","checked_at":"2026-11-01"}] — period: once / month / year / usage' ),
		'workflow_metrics'      => array( __( '効果測定方法（JSON）', 'omochix-core' ), '[{"metric":"返信時間","how":"…","frequency":"週1回"}]' ),
		'workflow_results'      => array( __( '成果（JSON）', 'omochix-core' ), '[{"metric":"…","value":"…","evidence":"expected","source_url":"","source_resource_id":null,"measured_at":"","note":""}] — evidence: measured（OmochiX検証済み）/ reported（公表値）/ expected（想定）' ),
		'workflow_resource_ids' => array( __( '外部コンテンツ（台帳ID）', 'omochix-core' ), __( '外部コンテンツ台帳のIDをカンマ区切りで（最大10）。', 'omochix-core' ) ),
	);
}

/**
 * Render the panel.
 *
 * @param WP_Post $post Practice item.
 * @return void
 */
function omochix_core_render_workflow_meta_box( $post ) {
	$meta   = omochix_core_workflow_effective_meta( 0 === (int) $post->ID ? 0 : (int) $post->ID, array( 'meta' => array() ) );
	$labels = omochix_core_workflow_field_labels();
	wp_nonce_field( OMOCHIX_CORE_WORKFLOW_NONCE_ACTION, OMOCHIX_CORE_WORKFLOW_NONCE_FIELD );
	echo '<p class="description">' . esc_html__( '成果は「検証済み・公表値・想定」を必ず区別します。想定の成果に断定表現は使えません。', 'omochix-core' ) . '</p>';
	foreach ( omochix_core_get_workflow_meta_schema() as $key => $field ) {
		$id   = 'omochix-' . str_replace( '_', '-', $key );
		$name = OMOCHIX_CORE_WORKFLOW_FIELDS . '[' . $key . ']';
		echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $labels[ $key ][0] ) . '</strong></label><br>';
		if ( 'post_id_array' === $field['sanitize'] ) {
			echo '<input type="text" class="large-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ', ', (array) $meta[ $key ] ) ) . '">';
		} elseif ( 'json_rows' === $field['sanitize'] ) {
			$json = $meta[ $key ] ? (string) wp_json_encode( $meta[ $key ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) : '';
			echo '<textarea class="large-text code" rows="5" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $json ) . '</textarea>';
		} else {
			echo '<textarea class="large-text" rows="3" maxlength="' . (int) $field['max'] . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $meta[ $key ] ) . '</textarea>';
		}
		echo '<span class="description">' . esc_html( $labels[ $key ][1] ) . '</span></p>';
	}
}

/**
 * Save the panel. Malformed JSON and secret-looking strings keep the stored
 * value and are reported.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_workflow_meta( $post_id ) {
	$post_id = (int) $post_id;
	if ( 'learn_practice' !== get_post_type( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST[ OMOCHIX_CORE_WORKFLOW_NONCE_FIELD ] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST[ OMOCHIX_CORE_WORKFLOW_NONCE_FIELD ] ) );
	if ( ! wp_verify_nonce( $nonce, OMOCHIX_CORE_WORKFLOW_NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$submitted = isset( $_POST[ OMOCHIX_CORE_WORKFLOW_FIELDS ] ) && is_array( $_POST[ OMOCHIX_CORE_WORKFLOW_FIELDS ] ) ? wp_unslash( $_POST[ OMOCHIX_CORE_WORKFLOW_FIELDS ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.
	$problems  = array();
	foreach ( omochix_core_get_workflow_meta_schema() as $key => $field ) {
		if ( ! array_key_exists( $key, $submitted ) ) {
			continue;
		}
		if ( 'json_rows' === $field['sanitize'] && ! omochix_core_practice_rows_input_is_valid( $submitted[ $key ] ) ) {
			$problems[] = $key;
			continue;
		}
		$value    = omochix_core_sanitize_workflow_meta_value( $submitted[ $key ], $key );
		$findings = array();
		omochix_core_practice_import_lint_secrets( $value, $key, $findings );
		if ( $findings ) {
			$problems[] = $key;
			continue;
		}
		if ( '' === $value || array() === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
	if ( $problems ) {
		set_transient( OMOCHIX_CORE_WORKFLOW_SAVE_NOTICE . $post_id, $problems, 10 * MINUTE_IN_SECONDS );
	}
}
add_action( 'save_post_learn_practice', 'omochix_core_save_workflow_meta', 20 );

/** Report fields that were not saved. */
function omochix_core_workflow_save_notice() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$keys    = $post_id ? get_transient( OMOCHIX_CORE_WORKFLOW_SAVE_NOTICE . $post_id ) : false;
	if ( ! is_array( $keys ) || ! $keys ) {
		return;
	}
	delete_transient( OMOCHIX_CORE_WORKFLOW_SAVE_NOTICE . $post_id );
	echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( sprintf( __( '次の項目はJSONが正しくないか秘密情報らしき文字列を含むため、前の値を残しました：%s', 'omochix-core' ), implode( ', ', array_map( 'sanitize_key', $keys ) ) ) ) . '</p></div>';
}
add_action( 'admin_notices', 'omochix_core_workflow_save_notice' );
// omochix-industry-hub-api: 2 (end)
