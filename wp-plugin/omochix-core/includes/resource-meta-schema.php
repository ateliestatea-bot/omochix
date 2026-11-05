<?php
/**
 * External resource ledger meta and publish guard (Industry Hub Phase 1).
 *
 * "publish" on a ledger entry means "usable in hubs and workflows", never a
 * public page. An entry cannot reach publish / future while its license
 * record is incomplete:
 *
 * - provider type, URL, kind, provider name, OmochiX summary, license basis
 *   and verified date are always required;
 * - every basis except link_only needs license details;
 * - licensed / partner need an internal permission reference;
 * - a thumbnail is allowed only when the basis permits using images;
 * - an embed is allowed only for official_embed with an allow-listed provider.
 *
 * resource_permission_ref is internal: never in REST, exports or the front
 * end, readable and writable only with edit_others_posts.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMOCHIX_CORE_RESOURCE_GUARD_TRANSIENT = 'omochix_core_resource_guard_';
const OMOCHIX_CORE_RESOURCE_STALE_DAYS      = 180;

/**
 * Ledger meta contract.
 *
 * @return array<string, array<string, mixed>>
 */
function omochix_core_get_resource_meta_schema() {
	return array(
		'resource_url'            => array( 'type' => 'string', 'default' => '', 'sanitize' => 'https_url', 'public' => true ),
		'resource_kind'           => array( 'type' => 'string', 'default' => '', 'sanitize' => 'enum', 'options' => array( '', 'video', 'article', 'course', 'template', 'docs', 'tool' ), 'public' => true ),
		'resource_provider_name'  => array( 'type' => 'string', 'default' => '', 'sanitize' => 'text', 'max' => 80, 'public' => true ),
		'resource_provider_url'   => array( 'type' => 'string', 'default' => '', 'sanitize' => 'https_url', 'public' => true ),
		'resource_summary'        => array( 'type' => 'string', 'default' => '', 'sanitize' => 'text', 'max' => 160, 'public' => true ),
		'resource_license_basis'  => array( 'type' => 'string', 'default' => '', 'sanitize' => 'enum', 'options' => array( '', 'link_only', 'quote', 'official_embed', 'licensed', 'partner', 'open_license' ), 'public' => true ),
		'resource_license_detail' => array( 'type' => 'string', 'default' => '', 'sanitize' => 'text', 'max' => 200, 'public' => true ),
		'resource_attribution'    => array( 'type' => 'string', 'default' => '', 'sanitize' => 'text', 'max' => 200, 'public' => true ),
		'resource_permission_ref' => array( 'type' => 'string', 'default' => '', 'sanitize' => 'reference', 'max' => 64, 'public' => false ),
		'resource_embed_provider' => array( 'type' => 'string', 'default' => '', 'sanitize' => 'enum', 'options' => array( '', 'youtube' ), 'public' => true ),
		'resource_lang'           => array( 'type' => 'string', 'default' => '', 'sanitize' => 'enum', 'options' => array( '', 'ja', 'en', 'other' ), 'public' => true ),
		'resource_price'          => array( 'type' => 'string', 'default' => '', 'sanitize' => 'enum', 'options' => array( '', 'free', 'freemium', 'paid' ), 'public' => true ),
		'resource_verified_at'    => array( 'type' => 'string', 'default' => '', 'sanitize' => 'date', 'public' => true ),
		'resource_thumbnail'      => array( 'type' => 'integer', 'default' => 0, 'sanitize' => 'image_id', 'public' => true ),
	);
}

/** Readable labels of the license bases. */
function omochix_core_get_resource_license_labels() {
	return array(
		'link_only'      => __( 'リンクのみ（タイトルと紹介文、外部リンク）', 'omochix-core' ),
		'quote'          => __( '引用（出典を明記した短い引用）', 'omochix-core' ),
		'official_embed' => __( '公式の埋め込み機能', 'omochix-core' ),
		'licensed'       => __( '許諾あり', 'omochix-core' ),
		'partner'        => __( '提携（PR）', 'omochix-core' ),
		'open_license'   => __( 'オープンライセンス（CC等）', 'omochix-core' ),
	);
}

/**
 * An https URL with a host and no credentials, or ''.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function omochix_core_sanitize_https_url( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = trim( $value );
	$parts = wp_parse_url( $value );
	if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || '' === (string) ( $parts['host'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return '';
	}
	$clean = esc_url_raw( $value, array( 'https' ) );
	return is_string( $clean ) && 0 === strpos( $clean, 'https://' ) ? $clean : '';
}

/**
 * Sanitize one ledger meta value.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed Null for an unknown key.
 */
function omochix_core_sanitize_resource_meta_value( $value, $key ) {
	$schema = omochix_core_get_resource_meta_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}
	$field = $schema[ $key ];
	switch ( $field['sanitize'] ) {
		case 'https_url':
			return omochix_core_sanitize_https_url( $value );
		case 'enum':
			$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
			return in_array( $value, $field['options'], true ) ? $value : '';
		case 'text':
			$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			return mb_substr( $value, 0, $field['max'] );
		case 'reference':
			// An internal reference number only (e.g. "LIC-2026-014"): no spaces,
			// no "@", so mail bodies, addresses and names cannot be stored here.
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';
			return preg_match( '/^[A-Za-z0-9][A-Za-z0-9._#\/-]{0,63}$/', $value ) ? $value : '';
		case 'date':
			return omochix_core_sanitize_practice_row_value( is_scalar( $value ) ? (string) $value : '', 'date' );
		case 'image_id':
			$id = is_scalar( $value ) ? absint( $value ) : 0;
			return $id && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) ? $id : 0;
	}
	return null;
}

/** register_post_meta sanitize callback. */
function omochix_core_sanitize_registered_resource_meta( $value, $meta_key ) {
	return omochix_core_sanitize_resource_meta_value( $value, $meta_key );
}

/**
 * register_post_meta auth callback: edit the entry; the permission
 * reference additionally needs edit_others_posts.
 *
 * @param bool   $allowed  Current decision.
 * @param string $meta_key Meta key.
 * @param int    $post_id  Post ID.
 * @return bool
 */
function omochix_core_auth_registered_resource_meta( $allowed, $meta_key, $post_id ) {
	unset( $allowed );
	$can = $post_id ? current_user_can( 'edit_post', (int) $post_id ) : current_user_can( 'edit_posts' );
	if ( 'resource_permission_ref' === $meta_key ) {
		$can = $can && current_user_can( 'edit_others_posts' );
	}
	return $can;
}

/**
 * Register the ledger meta. Nothing is shown in REST (the post type has no
 * REST route either) and the permission reference is protected.
 *
 * @return void
 */
function omochix_core_register_resource_post_meta() {
	foreach ( omochix_core_get_resource_meta_schema() as $key => $field ) {
		register_post_meta(
			OMOCHIX_CORE_RESOURCE_POST_TYPE,
			$key,
			array(
				'type'              => $field['type'],
				'single'            => true,
				'default'           => $field['default'],
				'show_in_rest'      => false,
				'sanitize_callback' => 'omochix_core_sanitize_registered_resource_meta',
				'auth_callback'     => 'omochix_core_auth_registered_resource_meta',
				'revisions_enabled' => $field['public'],
			)
		);
	}
}

/**
 * Hide the internal permission reference from the "Custom Fields" box and
 * from any generic meta listing (WordPress treats underscore keys as
 * protected; this key keeps its name but gets the same protection).
 *
 * @param bool   $protected Whether the key is protected.
 * @param string $meta_key  Meta key.
 * @return bool
 */
function omochix_core_resource_protect_meta( $protected, $meta_key ) {
	return 'resource_permission_ref' === $meta_key ? true : $protected;
}
add_filter( 'is_protected_meta', 'omochix_core_resource_protect_meta', 10, 2 );

/**
 * Stored ledger values, re-sanitized.
 *
 * @param int  $post_id          Post ID.
 * @param bool $include_internal Include resource_permission_ref (editors with edit_others_posts only).
 * @return array<string, mixed>
 */
function omochix_core_get_resource_meta( $post_id, $include_internal = false ) {
	$out = array();
	foreach ( omochix_core_get_resource_meta_schema() as $key => $field ) {
		if ( ! $field['public'] && ( ! $include_internal || ! current_user_can( 'edit_others_posts' ) ) ) {
			continue;
		}
		$raw         = get_post_meta( (int) $post_id, $key, true );
		$out[ $key ] = '' === $raw ? $field['default'] : omochix_core_sanitize_resource_meta_value( $raw, $key );
	}
	return $out;
}

/**
 * Public card data for a published ledger entry, or null. Only the public
 * fields; the permission reference never appears. The link rel follows the
 * provider type (partner = sponsored).
 *
 * @param WP_Post|int $post Entry.
 * @return array<string, mixed>|null
 */
function omochix_core_get_resource_card( $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || OMOCHIX_CORE_RESOURCE_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}
	$meta = omochix_core_get_resource_meta( $post->ID, false );
	if ( '' === $meta['resource_url'] ) {
		return null;
	}
	$provider = omochix_core_get_provider_type( $post );
	// A live entry edited into an incomplete record is only warned about by the
	// guard; the card re-applies the rules, so it is simply not shown.
	$full = $meta;
	$full['resource_permission_ref'] = (string) get_post_meta( $post->ID, 'resource_permission_ref', true );
	if ( omochix_core_resource_publish_blockers( $full, $provider, array( 'title' => (string) $post->post_title ) ) ) {
		return null;
	}
	return array(
		'id'            => (int) $post->ID,
		'title'         => get_the_title( $post ),
		'url'           => $meta['resource_url'],
		'summary'       => $meta['resource_summary'],
		'kind'          => $meta['resource_kind'],
		'provider_name' => $meta['resource_provider_name'],
		'provider_type' => $provider,
		'is_pr'         => omochix_core_provider_is_pr( $provider ) || 'partner' === $meta['resource_license_basis'],
		'rel'           => omochix_core_provider_is_pr( $provider ) || 'partner' === $meta['resource_license_basis'] ? 'sponsored noopener noreferrer' : 'noopener noreferrer',
		'license_basis' => $meta['resource_license_basis'],
		'license_label' => omochix_core_get_resource_license_labels()[ $meta['resource_license_basis'] ] ?? '',
		'attribution'   => $meta['resource_attribution'],
		'verified_at'   => $meta['resource_verified_at'],
		'stale'         => omochix_core_resource_is_stale( $meta['resource_verified_at'] ),
		'thumbnail'     => in_array( $meta['resource_license_basis'], array( 'licensed', 'partner', 'open_license' ), true ) ? $meta['resource_thumbnail'] : 0,
	);
}

/** Whether a verified date is missing or older than 180 days. */
function omochix_core_resource_is_stale( $date, $now = 0 ) {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
		return true;
	}
	$now = $now ? (int) $now : time();
	return ( $now - strtotime( $date . ' 00:00:00 UTC' ) ) > OMOCHIX_CORE_RESOURCE_STALE_DAYS * DAY_IN_SECONDS;
}

/**
 * Why a ledger entry may not be published.
 *
 * @param array<string, mixed> $meta     Sanitized ledger values (all keys).
 * @param string               $provider Provider type.
 * @param array<string, string> $fields  Title (secret lint).
 * @return string[] Messages.
 */
function omochix_core_resource_publish_blockers( array $meta, $provider, array $fields = array() ) {
	$lines    = array();
	$required = array(
		'resource_url'           => __( 'URL（https）', 'omochix-core' ),
		'resource_kind'          => __( '種別', 'omochix-core' ),
		'resource_provider_name' => __( '提供者名', 'omochix-core' ),
		'resource_summary'       => __( 'OmochiXの紹介文', 'omochix-core' ),
		'resource_license_basis' => __( 'ライセンス区分', 'omochix-core' ),
		'resource_verified_at'   => __( '最終確認日', 'omochix-core' ),
	);
	$missing  = array();
	foreach ( $required as $key => $label ) {
		if ( '' === (string) ( $meta[ $key ] ?? '' ) ) {
			$missing[] = $label;
		}
	}
	if ( OMOCHIX_CORE_PROVIDER_UNCLASSIFIED === $provider ) {
		$missing[] = __( '提供元', 'omochix-core' );
	}
	$basis = (string) ( $meta['resource_license_basis'] ?? '' );
	if ( '' !== $basis && 'link_only' !== $basis && '' === (string) ( $meta['resource_license_detail'] ?? '' ) ) {
		$missing[] = __( 'ライセンスの内容', 'omochix-core' );
	}
	if ( in_array( $basis, array( 'licensed', 'partner' ), true ) && '' === (string) ( $meta['resource_permission_ref'] ?? '' ) ) {
		$missing[] = __( '許諾の社内参照番号', 'omochix-core' );
	}
	if ( $missing ) {
		$lines[] = sprintf( __( '必須項目が未入力です：%s', 'omochix-core' ), implode( '、', $missing ) );
	}
	if ( ! empty( $meta['resource_thumbnail'] ) && ! in_array( $basis, array( 'licensed', 'partner', 'open_license' ), true ) ) {
		$lines[] = __( 'サムネイル画像は、許諾・提携・オープンライセンスの場合だけ使えます。', 'omochix-core' );
	}
	if ( '' !== (string) ( $meta['resource_embed_provider'] ?? '' ) && 'official_embed' !== $basis ) {
		$lines[] = __( '埋め込みは「公式の埋め込み機能」の場合だけ使えます。', 'omochix-core' );
	}
	if ( 'official_embed' === $basis && '' === (string) ( $meta['resource_embed_provider'] ?? '' ) ) {
		$lines[] = __( '「公式の埋め込み機能」では埋め込み元を選んでください。', 'omochix-core' );
	}
	if ( 'partner' === $basis && 'partner' !== $provider ) {
		$lines[] = __( '提携（PR）のコンテンツは、提供元を「パートナー（PR）」にしてください。', 'omochix-core' );
	}
	$secrets = array();
	omochix_core_practice_import_lint_secrets( array_merge( $fields, $meta ), '', $secrets );
	foreach ( $secrets as $finding ) {
		$lines[] = sprintf( __( '秘密情報らしき文字列：%1$s（%2$s）', 'omochix-core' ), $finding['path'], $finding['label'] );
	}
	return $lines;
}

/**
 * Provider type a request would leave on the entry: the tax_input core
 * resolved into wp_insert_post()'s $postarr (post.php, quick and bulk edit)
 * when present, else the stored term.
 *
 * @param int        $post_id   Post ID.
 * @param array|null $tax_input $postarr['tax_input'].
 * @return string
 */
function omochix_core_resource_effective_provider( $post_id, $tax_input = null ) {
	if ( is_array( $tax_input ) && array_key_exists( OMOCHIX_CORE_PROVIDER_TAXONOMY, $tax_input ) ) {
		$raw   = $tax_input[ OMOCHIX_CORE_PROVIDER_TAXONOMY ];
		$ids   = array_filter( array_map( 'absint', array_filter( is_array( $raw ) ? $raw : explode( ',', (string) $raw ), 'is_scalar' ) ) );
		$slugs = array();
		foreach ( $ids as $id ) {
			$term = get_term( $id, OMOCHIX_CORE_PROVIDER_TAXONOMY );
			if ( $term instanceof WP_Term ) {
				$slugs[] = $term->slug;
			}
		}
		return omochix_core_provider_type_from_slugs( $slugs );
	}
	return $post_id ? omochix_core_get_provider_type( $post_id ) : OMOCHIX_CORE_PROVIDER_UNCLASSIFIED;
}

/**
 * Ledger values a request would leave: stored values overlaid with this
 * request's meta-box fields (sanitized).
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function omochix_core_resource_effective_meta( $post_id ) {
	$meta = array();
	foreach ( omochix_core_get_resource_meta_schema() as $key => $field ) {
		$raw          = $post_id ? get_post_meta( (int) $post_id, $key, true ) : '';
		$meta[ $key ] = '' === $raw ? $field['default'] : omochix_core_sanitize_resource_meta_value( $raw, $key );
	}
	// Overlay only what the box's save handler will really store: same nonce,
	// same capability rule for the internal reference.
	$nonce = isset( $_POST['omochix_core_resource_nonce'] ) && is_string( $_POST['omochix_core_resource_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['omochix_core_resource_nonce'] ) ) : '';
	if ( '' !== $nonce && wp_verify_nonce( $nonce, 'omochix_core_save_resource' ) && isset( $_POST['omochix_core_resource_meta'] ) && is_array( $_POST['omochix_core_resource_meta'] ) ) {
		$schema = omochix_core_get_resource_meta_schema();
		foreach ( wp_unslash( $_POST['omochix_core_resource_meta'] ) as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
			if ( ! isset( $meta[ $key ] ) || ( ! $schema[ $key ]['public'] && ! current_user_can( 'edit_others_posts' ) ) ) {
				continue;
			}
			$meta[ $key ] = omochix_core_sanitize_resource_meta_value( $value, $key );
		}
	}
	return $meta;
}

/**
 * Hold an entry back from publish / future while its record is incomplete.
 * The ledger has no REST route, so this classic path is the only one.
 * Entries that are already published keep their status and get a warning.
 *
 * @param array<string, mixed> $data    Slashed post data.
 * @param array<string, mixed> $postarr Raw post array.
 * @return array<string, mixed>
 */
function omochix_core_resource_guard_insert_data( $data, $postarr ) {
	if ( OMOCHIX_CORE_RESOURCE_POST_TYPE !== ( $data['post_type'] ?? '' ) || ! in_array( (string) ( $data['post_status'] ?? '' ), array( 'publish', 'future' ), true ) ) {
		return $data;
	}
	$post_id = (int) ( $postarr['ID'] ?? 0 );
	$stored  = $post_id ? (string) get_post_field( 'post_status', $post_id ) : '';
	$lines   = omochix_core_resource_publish_blockers(
		omochix_core_resource_effective_meta( $post_id ),
		omochix_core_resource_effective_provider( $post_id, isset( $postarr['tax_input'] ) && is_array( $postarr['tax_input'] ) ? $postarr['tax_input'] : null ),
		array( 'title' => wp_unslash( (string) ( $data['post_title'] ?? '' ) ) )
	);
	if ( ! $lines ) {
		return $data;
	}
	if ( in_array( $stored, array( 'publish', 'future' ), true ) ) {
		set_transient( OMOCHIX_CORE_RESOURCE_GUARD_TRANSIENT . $post_id, array( 'type' => 'warn', 'lines' => $lines ), 10 * MINUTE_IN_SECONDS );
		return $data;
	}
	$data['post_status'] = 'draft';
	set_transient( OMOCHIX_CORE_RESOURCE_GUARD_TRANSIENT . $post_id, array( 'type' => 'blocked', 'lines' => $lines ), 10 * MINUTE_IN_SECONDS );
	return $data;
}
add_filter( 'wp_insert_post_data', 'omochix_core_resource_guard_insert_data', 10, 2 );

/** Admin notice on the entry's edit screen after a held-back save. */
function omochix_core_resource_guard_notice() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id || OMOCHIX_CORE_RESOURCE_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}
	$held = get_transient( OMOCHIX_CORE_RESOURCE_GUARD_TRANSIENT . $post_id );
	if ( ! is_array( $held ) || empty( $held['lines'] ) ) {
		return;
	}
	delete_transient( OMOCHIX_CORE_RESOURCE_GUARD_TRANSIENT . $post_id );
	$blocked = 'blocked' === ( $held['type'] ?? '' );
	echo '<div class="notice ' . ( $blocked ? 'notice-error' : 'notice-warning' ) . ' is-dismissible"><p><strong>' . esc_html( $blocked ? __( '記録が不足しているため下書きのまま保存しました。', 'omochix-core' ) : __( '公開中の台帳に不足があります（状態は変えていません）。', 'omochix-core' ) ) . '</strong></p><ul>';
	foreach ( (array) $held['lines'] as $line ) {
		echo '<li>' . esc_html( (string) $line ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'omochix_core_resource_guard_notice' );
