<?php
// omochix-industry-hub-version: 1.10.0-dev
/**
 * External resource ledger: edit screen box and save handler (Industry Hub Phase 1).
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_RESOURCE_NONCE_FIELD  = 'omochix_core_resource_nonce';
const OMOCHIX_CORE_RESOURCE_NONCE_ACTION = 'omochix_core_save_resource';
const OMOCHIX_CORE_RESOURCE_FIELDS       = 'omochix_core_resource_meta';

/** Register the box on the ledger edit screen. */
function omochix_core_add_resource_meta_box() {
	add_meta_box( 'omochix-core-resource', __( '出典・ライセンス', 'omochix-core' ), 'omochix_core_render_resource_meta_box', OMOCHIX_CORE_RESOURCE_POST_TYPE, 'normal', 'high' );
}
add_action( 'add_meta_boxes_' . OMOCHIX_CORE_RESOURCE_POST_TYPE, 'omochix_core_add_resource_meta_box' );

/** Labels and help per field. */
function omochix_core_resource_field_labels() {
	return array(
		'resource_url'            => array( __( 'URL', 'omochix-core' ), __( 'https のみ。', 'omochix-core' ) ),
		'resource_kind'           => array( __( '種別', 'omochix-core' ), '' ),
		'resource_provider_name'  => array( __( '提供者名', 'omochix-core' ), '' ),
		'resource_provider_url'   => array( __( '提供者のURL', 'omochix-core' ), __( 'https のみ。', 'omochix-core' ) ),
		'resource_summary'        => array( __( 'OmochiXの紹介文', 'omochix-core' ), __( '自分の言葉で160字まで。原文のコピーは不可。', 'omochix-core' ) ),
		'resource_license_basis'  => array( __( 'ライセンス区分', 'omochix-core' ), '' ),
		'resource_license_detail' => array( __( 'ライセンスの内容', 'omochix-core' ), __( '例：CC BY 4.0、許諾の範囲。リンクのみ以外は必須。', 'omochix-core' ) ),
		'resource_attribution'    => array( __( 'クレジット表記', 'omochix-core' ), '' ),
		'resource_permission_ref' => array( __( '許諾の社内参照番号（非公開）', 'omochix-core' ), __( '番号だけを書きます（例：LIC-2026-014）。メール本文や個人情報は書かないでください。', 'omochix-core' ) ),
		'resource_embed_provider' => array( __( '埋め込み元', 'omochix-core' ), __( '公式の埋め込み機能の場合だけ。', 'omochix-core' ) ),
		'resource_lang'           => array( __( '言語', 'omochix-core' ), '' ),
		'resource_price'          => array( __( '料金', 'omochix-core' ), '' ),
		'resource_verified_at'    => array( __( '最終確認日', 'omochix-core' ), __( '180日を過ぎると「要再確認」になります。', 'omochix-core' ) ),
		'resource_thumbnail'      => array( __( 'サムネイル（メディアID）', 'omochix-core' ), __( '許諾・提携・オープンライセンスの場合だけ。他サイトの画像を保存しないでください。', 'omochix-core' ) ),
	);
}

/**
 * Render the ledger box.
 *
 * @param WP_Post $post Entry.
 * @return void
 */
function omochix_core_render_resource_meta_box( $post ) {
	$internal = current_user_can( 'edit_others_posts' );
	$meta     = omochix_core_get_resource_meta( $post->ID, $internal );
	$labels   = omochix_core_resource_field_labels();
	$licenses = omochix_core_get_resource_license_labels();
	wp_nonce_field( OMOCHIX_CORE_RESOURCE_NONCE_ACTION, OMOCHIX_CORE_RESOURCE_NONCE_FIELD );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( omochix_core_get_resource_meta_schema() as $key => $field ) {
		if ( ! $field['public'] && ! $internal ) {
			continue;
		}
		$id    = 'omochix-' . str_replace( '_', '-', $key );
		$name  = OMOCHIX_CORE_RESOURCE_FIELDS . '[' . $key . ']';
		$value = $meta[ $key ] ?? $field['default'];
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $labels[ $key ][0] ?? $key ) . '</label></th><td>';
		if ( 'enum' === $field['sanitize'] ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $field['options'] as $option ) {
				$text = '' === $option ? __( '選択してください', 'omochix-core' ) : ( 'resource_license_basis' === $key ? ( $licenses[ $option ] ?? $option ) : $option );
				echo '<option value="' . esc_attr( $option ) . '"' . selected( $option, $value, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'date' === $field['sanitize'] ) {
			echo '<input type="date" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		} elseif ( 'image_id' === $field['sanitize'] ) {
			echo '<input type="number" min="0" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) ( $value ? $value : '' ) ) . '">';
		} else {
			$type = 'https_url' === $field['sanitize'] ? 'url' : 'text';
			$max  = isset( $field['max'] ) ? ' maxlength="' . (int) $field['max'] . '"' : '';
			echo '<input type="' . esc_attr( $type ) . '" class="large-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . $max . ( 'https_url' === $field['sanitize'] ? ' pattern="https://.*"' : '' ) . ( 'resource_permission_ref' === $key ? ' autocomplete="off"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $max is built from an int.
		}
		if ( ! empty( $labels[ $key ][1] ) ) {
			echo '<p class="description">' . esc_html( $labels[ $key ][1] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'この台帳はサイトに公開されません。「公開」は、業種ハブやワークフローで紹介カードとして使える状態を表します。', 'omochix-core' ) . '</p>';
}

/**
 * Save the ledger box.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_resource_meta( $post_id ) {
	$post_id = (int) $post_id;
	if ( OMOCHIX_CORE_RESOURCE_POST_TYPE !== get_post_type( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST[ OMOCHIX_CORE_RESOURCE_NONCE_FIELD ] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST[ OMOCHIX_CORE_RESOURCE_NONCE_FIELD ] ) );
	if ( ! wp_verify_nonce( $nonce, OMOCHIX_CORE_RESOURCE_NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$submitted = isset( $_POST[ OMOCHIX_CORE_RESOURCE_FIELDS ] ) && is_array( $_POST[ OMOCHIX_CORE_RESOURCE_FIELDS ] ) ? wp_unslash( $_POST[ OMOCHIX_CORE_RESOURCE_FIELDS ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.
	foreach ( omochix_core_get_resource_meta_schema() as $key => $field ) {
		if ( ! array_key_exists( $key, $submitted ) ) {
			continue;
		}
		if ( ! $field['public'] && ! current_user_can( 'edit_others_posts' ) ) {
			continue; // the internal reference is not even shown to these users
		}
		$value    = omochix_core_sanitize_resource_meta_value( $submitted[ $key ], $key );
		$findings = array();
		omochix_core_practice_import_lint_secrets( $value, $key, $findings );
		if ( $findings ) {
			continue; // never store a secret-looking string; the guard reports it
		}
		if ( '' === $value || 0 === $value || null === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
add_action( 'save_post_' . OMOCHIX_CORE_RESOURCE_POST_TYPE, 'omochix_core_save_resource_meta' );
// omochix-industry-hub-version: 1.10.0-dev (end)
