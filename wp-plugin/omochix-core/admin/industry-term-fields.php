<?php
/**
 * Industry term edit screen fields (Industry Hub Phase 1).
 *
 * Only top-level industries carry hub content. Rows are edited as JSON (the
 * same convention as the practice meta boxes); malformed JSON or a
 * secret-looking string keeps the stored value and is reported.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_INDUSTRY_NONCE_FIELD = 'omochix_core_industry_nonce';
const OMOCHIX_CORE_INDUSTRY_FIELDS      = 'omochix_core_industry_meta';
const OMOCHIX_CORE_INDUSTRY_NOTICE      = 'omochix_core_industry_notice_';

/** Nonce action for one term. */
function omochix_core_industry_nonce_action( $term_id ) {
	return 'omochix_core_save_industry_' . (int) $term_id;
}

/**
 * Human label and help text per field.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function omochix_core_industry_field_labels() {
	return array(
		'industry_hub_ready'    => array( __( 'ハブ公開可', 'omochix-core' ), __( '内容を確認した編集者だけがチェックします。チェックしても、下の条件を満たすまで検索エンジンには登録されません。', 'omochix-core' ) ),
		'industry_order'        => array( __( '表示順', 'omochix-core' ), __( '小さい順に並びます。', 'omochix-core' ) ),
		'industry_icon'         => array( __( 'アイコン', 'omochix-core' ), __( 'テーマ同梱のアイコンから選びます。', 'omochix-core' ) ),
		'industry_lead'         => array( __( '導入文', 'omochix-core' ), __( 'OmochiXが書く独自の文章（600字まで）。', 'omochix-core' ) ),
		'industry_benefits'     => array( __( 'AI活用のメリット（JSON）', 'omochix-core' ), '[{"title":"…","body":"…"}]（最大6）' ),
		'industry_challenges'   => array( __( '業種の課題（JSON）', 'omochix-core' ), '[{"title":"…","body":"…","purpose":"customer-support"}]（purpose は任意。選ぶとその業務目的の事例とワークフローへ絞り込みます）' ),
		'industry_usecases'     => array( __( 'AI活用方法（JSON）', 'omochix-core' ), '[{"title":"…","body":"…","purpose":"marketing"}]' ),
		'industry_tool_ids'     => array( __( '推奨AIツール（ID）', 'omochix-core' ), __( 'AIツールの投稿IDをカンマ区切りで（最大12）。', 'omochix-core' ) ),
		'industry_featured_ids' => array( __( '先頭に出す教材・ワークフロー（ID）', 'omochix-core' ), __( '実践教材または固定ページのIDをカンマ区切りで（最大6）。', 'omochix-core' ) ),
		'industry_motion_slugs' => array( __( '関連Motion（slug）', 'omochix-core' ), __( 'Motion Gallery のslugをカンマ区切りで（最大6）。catalog.json に無いslugは表示されません。', 'omochix-core' ) ),
		'industry_verified_at'  => array( __( '最終確認日', 'omochix-core' ), 'YYYY-MM-DD' ),
		'industry_sources'      => array( __( '出典（JSON）', 'omochix-core' ), '[{"label":"…","url":"https://…"}]' ),
	);
}

/**
 * Fields on the edit-term screen.
 *
 * @param WP_Term $term Term being edited.
 * @return void
 */
function omochix_core_industry_edit_fields( $term ) {
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	if ( (int) $term->parent ) {
		echo '<tr class="form-field"><th scope="row">' . esc_html__( '業種ハブ', 'omochix-core' ) . '</th><td><p class="description">' . esc_html( omochix_core_industry_gate_label( 'not_top_level' ) ) . '</p></td></tr>';
		return;
	}
	$meta   = omochix_core_get_industry_meta( $term->term_id );
	$schema = omochix_core_get_industry_meta_schema();
	$labels = omochix_core_industry_field_labels();
	$json   = static function ( $value ) {
		return $value ? (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) : '';
	};
	wp_nonce_field( omochix_core_industry_nonce_action( $term->term_id ), OMOCHIX_CORE_INDUSTRY_NONCE_FIELD );
	foreach ( $schema as $key => $field ) {
		$id    = 'omochix-industry-' . str_replace( '_', '-', $key );
		$name  = OMOCHIX_CORE_INDUSTRY_FIELDS . '[' . $key . ']';
		$label = $labels[ $key ][0] ?? $key;
		$help  = $labels[ $key ][1] ?? '';
		echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $field['sanitize'] ) {
			case 'bool':
				echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( true, $meta[ $key ], false ) . '>';
				break;
			case 'nonnegative_integer':
				echo '<input type="number" min="0" step="1" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $meta[ $key ] ) . '">';
				break;
			case 'enum':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $field['options'] as $option ) {
					echo '<option value="' . esc_attr( $option ) . '"' . selected( $option, $meta[ $key ], false ) . '>' . esc_html( '' === $option ? __( 'なし', 'omochix-core' ) : $option ) . '</option>';
				}
				echo '</select>';
				break;
			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" maxlength="' . (int) $field['max'] . '">' . esc_textarea( (string) $meta[ $key ] ) . '</textarea>';
				break;
			case 'date':
				echo '<input type="date" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $meta[ $key ] ) . '">';
				break;
			case 'post_id_array':
			case 'slug_array':
				echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ', ', (array) $meta[ $key ] ) ) . '">';
				if ( 'post_id_array' === $field['sanitize'] && $meta[ $key ] ) {
					echo '<ul class="omochix-industry-picked">';
					foreach ( (array) $meta[ $key ] as $post_id ) {
						echo '<li>' . esc_html( '#' . (int) $post_id . ' ' . get_the_title( (int) $post_id ) . '（' . get_post_status( (int) $post_id ) . '）' ) . '</li>';
					}
					echo '</ul>';
				}
				if ( 'industry_motion_slugs' === $key && $meta[ $key ] && function_exists( 'omochix_learn_gallery_get_item' ) ) {
					$unknown = array_diff( (array) $meta[ $key ], omochix_core_filter_known_motion_slugs( $meta[ $key ] ) );
					if ( $unknown ) {
						echo '<p class="description" style="color:#b32d2e">' . esc_html( sprintf( __( 'catalog.json に無いslug：%s', 'omochix-core' ), implode( ', ', $unknown ) ) ) . '</p>';
					}
				}
				break;
			case 'json_rows':
				echo '<textarea class="code" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="6">' . esc_textarea( $json( $meta[ $key ] ) ) . '</textarea>';
				break;
		}
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}
	$gate = omochix_core_industry_hub_gate( $term->term_id );
	echo '<tr class="form-field"><th scope="row">' . esc_html__( '検索エンジン登録の条件', 'omochix-core' ) . '</th><td>';
	if ( $gate['ready'] ) {
		echo '<p>' . esc_html__( 'すべての条件を満たしています。', 'omochix-core' ) . '</p>';
	} else {
		echo '<p>' . esc_html__( '未達の条件があるため、公開しても noindex になります：', 'omochix-core' ) . '</p><ul>';
		foreach ( $gate['unmet'] as $code ) {
			echo '<li>' . esc_html( omochix_core_industry_gate_label( $code ) ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</td></tr>';
}
add_action( OMOCHIX_CORE_INDUSTRY_TAXONOMY . '_edit_form_fields', 'omochix_core_industry_edit_fields' );

/**
 * Save the industry fields (edit-term screen only).
 *
 * @param int $term_id Term ID.
 * @return void
 */
function omochix_core_save_industry_fields( $term_id ) {
	$term_id = (int) $term_id;
	if ( ! isset( $_POST[ OMOCHIX_CORE_INDUSTRY_NONCE_FIELD ] ) ) {
		return; // not our form (quick edit, REST, programmatic updates)
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST[ OMOCHIX_CORE_INDUSTRY_NONCE_FIELD ] ) );
	if ( ! wp_verify_nonce( $nonce, omochix_core_industry_nonce_action( $term_id ) ) || ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}
	$term = get_term( $term_id, OMOCHIX_CORE_INDUSTRY_TAXONOMY );
	if ( ! $term instanceof WP_Term || (int) $term->parent ) {
		return;
	}
	$submitted = isset( $_POST[ OMOCHIX_CORE_INDUSTRY_FIELDS ] ) && is_array( $_POST[ OMOCHIX_CORE_INDUSTRY_FIELDS ] ) ? wp_unslash( $_POST[ OMOCHIX_CORE_INDUSTRY_FIELDS ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.
	$problems  = array();
	foreach ( omochix_core_get_industry_meta_schema() as $key => $field ) {
		if ( 'bool' === $field['sanitize'] ) {
			$submitted[ $key ] = isset( $submitted[ $key ] ) ? $submitted[ $key ] : '0'; // an unchecked box is not posted
		}
		if ( ! array_key_exists( $key, $submitted ) ) {
			continue;
		}
		$raw = $submitted[ $key ];
		if ( 'json_rows' === $field['sanitize'] && ! omochix_core_practice_rows_input_is_valid( $raw ) ) {
			$problems[] = sprintf( __( '%s のJSONが正しくないため、前の値を残しました。', 'omochix-core' ), $key );
			continue;
		}
		$value    = omochix_core_sanitize_industry_meta_value( $raw, $key );
		$findings = array();
		omochix_core_practice_import_lint_secrets( $value, $key, $findings );
		if ( $findings ) {
			$problems[] = sprintf( __( '%s に秘密情報らしき文字列があるため、前の値を残しました。', 'omochix-core' ), $key );
			continue;
		}
		if ( $value === $field['default'] || '' === $value || array() === $value ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $value );
		}
	}
	if ( $problems ) {
		set_transient( OMOCHIX_CORE_INDUSTRY_NOTICE . get_current_user_id(), $problems, 10 * MINUTE_IN_SECONDS );
	}
}
add_action( 'edited_' . OMOCHIX_CORE_INDUSTRY_TAXONOMY, 'omochix_core_save_industry_fields' );

/** Show save problems once on the next admin page. */
function omochix_core_industry_notice() {
	$problems = get_transient( OMOCHIX_CORE_INDUSTRY_NOTICE . get_current_user_id() );
	if ( ! is_array( $problems ) || ! $problems ) {
		return;
	}
	delete_transient( OMOCHIX_CORE_INDUSTRY_NOTICE . get_current_user_id() );
	echo '<div class="notice notice-warning is-dismissible"><ul>';
	foreach ( $problems as $line ) {
		echo '<li>' . esc_html( (string) $line ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'omochix_core_industry_notice' );
