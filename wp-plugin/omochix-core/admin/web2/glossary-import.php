<?php
// omochix-glossary-version: 1
/**
 * AI glossary: JSON importer (KH-B). Drafts only.
 *
 * File: {"schema": "omochix-glossary-import/1", "terms": [ <omochix-glossary/1 term>, ... ]}
 * (each term exactly as docs/web2/kh/glossary-schema.json; 1 to 100 terms, 3 MB at most).
 *
 * Flow: upload -> dry-run (nothing written) -> the same file uploaded again -> SHA-256 and the
 * plan recomputed and compared with the approval kept on the server for this user and session
 * -> apply. Restoring a backup follows the same flow.
 *
 * What the importer never does: publish, schedule or make private; change the status of an
 * existing term; touch a term that is not a draft (published, scheduled, private, pending,
 * trashed: refused as a conflict); delete anything; create categories or related content; touch
 * any other post type; fetch a URL; run cron or SQL. New terms are drafts; an existing draft
 * keeps its status. Rules of a term come from glossary-validate.php; values are stored through
 * the meta of glossary-meta.php (whose sanitize callbacks still run). A term may be imported
 * while it does not meet the publish rules yet (the 50 terms are written step by step); the
 * plan says so and never calls such a term publishable.
 *
 * Request data ($_POST, $_FILES) is read only in the omochix_core_glossary_import_handle_*
 * functions, after the nonce and the capability are checked. The one option written is the
 * import lock (OMOCHIX_CORE_GLOSSARY_IMPORT_LOCK).
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_BYTES' ) ) {
	define( 'OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_BYTES', 3 * 1024 * 1024 ); // 100 terms of about 30 KB.
}
if ( ! defined( 'OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_TERMS' ) ) {
	define( 'OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_TERMS', 100 );
}
define( 'OMOCHIX_CORE_GLOSSARY_IMPORT_SCHEMA', 'omochix-glossary-import/1' );
define( 'OMOCHIX_CORE_GLOSSARY_IMPORT_BACKUP_SCHEMA', 'omochix-glossary-import-backup/1' );
define( 'OMOCHIX_CORE_GLOSSARY_IMPORT_LOCK', 'omochix_core_glossary_import_lock' );
define( 'OMOCHIX_CORE_GLOSSARY_IMPORT_APPROVAL_TTL', 15 * MINUTE_IN_SECONDS );

/**
 * Whether the current user may use the importer: manage_options and the glossary's editing
 * capability (editors and administrators get it from edit_others_posts at run time).
 *
 * @return bool
 */
function omochix_core_glossary_import_can() {
	return current_user_can( 'manage_options' ) && current_user_can( 'edit_others_omx_glossaries' );
}

/**
 * Decode an import file. Nothing is looked up or written.
 *
 * @param string $bytes Raw file.
 * @return array{terms: array, errors: string[]}
 */
function omochix_core_glossary_import_decode( $bytes ) {
	$fail = static function ( $m ) {
		return array( 'terms' => array(), 'errors' => array( $m ) );
	};
	if ( ! is_string( $bytes ) || '' === $bytes ) {
		return $fail( 'ファイルが空です' );
	}
	if ( strlen( $bytes ) > OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_BYTES ) {
		return $fail( sprintf( 'ファイルが大きすぎます（上限 %d バイト）', OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_BYTES ) );
	}
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $bytes, 'UTF-8' ) ) {
		return $fail( 'UTF-8 の JSON にしてください' );
	}
	$data = json_decode( $bytes, true, 32 );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) || array_values( $data ) === $data ) {
		return $fail( 'JSON として読めません（オブジェクトのファイルにしてください）' );
	}
	if ( array_diff( array_keys( $data ), array( 'schema', 'terms' ) ) ) {
		return $fail( 'ファイルに使えるのは schema と terms だけです' );
	}
	if ( ( $data['schema'] ?? null ) !== OMOCHIX_CORE_GLOSSARY_IMPORT_SCHEMA ) {
		return $fail( 'schema は ' . OMOCHIX_CORE_GLOSSARY_IMPORT_SCHEMA . ' にしてください' );
	}
	$terms = $data['terms'] ?? null;
	if ( ! is_array( $terms ) || array_values( $terms ) !== $terms || ! $terms ) {
		return $fail( 'terms は1件以上の配列にしてください' );
	}
	if ( count( $terms ) > OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_TERMS ) {
		return $fail( sprintf( '用語は %d 件までです（%d 件あります）', OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_TERMS, count( $terms ) ) );
	}
	return array( 'terms' => $terms, 'errors' => array() );
}

/**
 * Existing glossary rows with this slug (every status but auto-draft; trash included).
 *
 * @param string $slug Slug.
 * @return WP_Post[]
 */
function omochix_core_glossary_import_existing( $slug ) {
	$out = array();
	$got = get_posts(
		array(
			'post_type'        => 'omx_glossary',
			'name'             => $slug,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
			'posts_per_page'   => -1,
			'suppress_filters' => true,
		)
	);
	foreach ( is_array( $got ) ? $got : array() as $p ) {
		if ( $p instanceof WP_Post && 'omx_glossary' === $p->post_type && $slug === $p->post_name ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Glossary categories that exist now: slug => term ID.
 *
 * @return array<string, int>
 */
function omochix_core_glossary_import_categories() {
	$out   = array();
	$terms = get_terms( array( 'taxonomy' => 'omx_glossary_category', 'hide_empty' => false ) );
	foreach ( is_array( $terms ) ? $terms : array() as $t ) {
		if ( $t instanceof WP_Term ) {
			$out[ (string) $t->slug ] = (int) $t->term_id;
		}
	}
	ksort( $out );
	return $out;
}

/**
 * One published post of a type by slug (or page by path), without a password.
 *
 * @param string $type Post type.
 * @param string $slug Slug or, for pages, the path.
 * @return WP_Post|null
 */
function omochix_core_glossary_import_target( $type, $slug ) {
	if ( 'page' === $type ) {
		$p = function_exists( 'get_page_by_path' ) ? get_page_by_path( $slug, OBJECT, 'page' ) : null;
	} else {
		$p   = null;
		$got = get_posts( array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'publish', 'posts_per_page' => -1, 'suppress_filters' => true ) );
		foreach ( is_array( $got ) ? $got : array() as $x ) {
			if ( $x instanceof WP_Post && $type === $x->post_type && $slug === $x->post_name ) {
				$p = $x;
				break;
			}
		}
	}
	return $p instanceof WP_Post && $type === $p->post_type && 'publish' === $p->post_status && '' === (string) $p->post_password ? $p : null;
}

/**
 * Resolve the related slugs of one term to IDs. Glossary slugs of this file stay as "@slug"
 * until the apply has created them. What cannot be resolved is dropped with a warning; nothing
 * is created.
 *
 * @param array    $related    related object of the term.
 * @param string[] $file_slugs Slugs of this file.
 * @param string   $self       Slug of the term itself (never linked to itself).
 * @return array{ids: array<string, array>, warnings: string[], state: array}
 */
function omochix_core_glossary_import_related( $related, array $file_slugs, $self = '' ) {
	$map   = array(
		'glossary'    => array( 'related_glossary_ids', 'omx_glossary' ),
		'tools'       => array( 'related_tool_ids', 'ai_tool' ),
		'learn_pages' => array( 'related_learn_page_ids', 'page' ),
		'practice'    => array( 'related_practice_ids', 'learn_practice' ),
		'industries'  => array( 'related_industry_ids', 'omx_industry' ),
	);
	$ids   = array_fill_keys( array_column( $map, 0 ), array() );
	$warn  = array();
	$state = array();
	foreach ( $map as $key => $m ) {
		foreach ( (array) ( is_array( $related ) ? ( $related[ $key ] ?? array() ) : array() ) as $slug ) {
			if ( ! is_string( $slug ) ) {
				continue;
			}
			$id = 0;
			if ( 'glossary' === $key && $slug === $self ) {
				$warn[] = 'related.glossary：自分自身は結びません';
				continue;
			}
			if ( 'glossary' === $key ) {
				$rows = array_filter( omochix_core_glossary_import_existing( $slug ), static function ( $p ) {
					return 'trash' !== $p->post_status;
				} );
				$id   = 1 === count( $rows ) ? (int) reset( $rows )->ID : 0;
				if ( 0 === $id && in_array( $slug, $file_slugs, true ) ) {
					$ids[ $m[0] ][] = '@' . $slug; // Created by this import: resolved after the first pass.
					$state[]        = $key . ':' . $slug . '=@';
					continue;
				}
			} elseif ( 'industries' === $key ) {
				$t  = taxonomy_exists( 'omx_industry' ) ? get_term_by( 'slug', $slug, 'omx_industry' ) : null;
				$id = $t instanceof WP_Term ? (int) $t->term_id : 0;
			} else {
				$p  = omochix_core_glossary_import_target( $m[1], $slug );
				$id = $p && ( 'learn_pages' !== $key || 0 === strpos( (string) get_page_uri( $p ), 'learn/' ) ) ? (int) $p->ID : 0;
			}
			$state[] = $key . ':' . $slug . '=' . $id;
			if ( $id > 0 ) {
				$ids[ $m[0] ][] = $id;
			} else {
				$warn[] = sprintf( 'related.%s：「%s」が見つかりません（公開されたものだけを結びます。作成はしません）', $key, $slug );
			}
		}
	}
	return array( 'ids' => $ids, 'warnings' => $warn, 'state' => $state );
}

/**
 * The stored meta values a term will have (related IDs left to the caller).
 *
 * @param array $t Term.
 * @return array<string, mixed>
 */
function omochix_core_glossary_import_meta_values( array $t ) {
	$rules = omochix_core_glossary_rules();
	return array(
		'glossary_reading'     => $t['reading'] ?? '',
		'glossary_en'          => $t['en'] ?? '',
		'glossary_aliases'     => $t['aliases'] ?? array(),
		'glossary_summary'     => $t['summary'] ?? '',
		'glossary_sources'     => $t['sources'] ?? array(),
		'glossary_verified_on' => $t['verified_on'] ?? '',
		'glossary_review_days' => $t['review_days'] ?? $rules['review_days'],
		'glossary_ai_assisted' => $t['ai_assisted'] ?? false,
		'glossary_level'       => $t['level'] ?? '',
	);
}

/**
 * Why a term cannot be imported at all (unsafe or malformed data). Rules of glossary-validate.php
 * are reused; anything the meta sanitize would change is refused (it would not be stored as written).
 *
 * @param mixed $t    Term.
 * @param array $cats Existing categories (slug => ID).
 * @return string[]
 */
function omochix_core_glossary_import_blocking( $t, array $cats ) {
	$rules = omochix_core_glossary_rules();
	if ( ! is_array( $t ) || array_values( $t ) === $t ) {
		return array( '用語は JSON のオブジェクトにしてください' );
	}
	$err = array();
	foreach ( array_diff( array_keys( $t ), $rules['term_keys'] ) as $k ) {
		$err[] = $k . '：未知の項目です';
	}
	if ( ( $t['schema'] ?? null ) !== $rules['schema'] ) {
		$err[] = 'schema：' . $rules['schema'] . ' にしてください';
	}
	$why = omochix_core_glossary_slug_problem( $t['slug'] ?? null );
	if ( '' !== $why ) {
		$err[] = 'slug：' . $why;
	}
	foreach ( array( 'title', 'content_html' ) as $k ) {
		if ( ! is_string( $t[ $k ] ?? null ) || '' === trim( (string) $t[ $k ] ) ) {
			$err[] = $k . '：空でない文字列にしてください';
		}
	}
	if ( is_string( $t['title'] ?? null ) && ( omochix_core_glossary_len( $t['title'] ) > $rules['title_max'] || preg_match( '/[<>\x00-\x1f\x7f]/', $t['title'] ) ) ) {
		$err[] = sprintf( 'title：%d文字まで、山かっこと制御文字なしにしてください', $rules['title_max'] );
	}
	foreach ( array( 'reading', 'en', 'summary', 'verified_on', 'level' ) as $k ) {
		if ( array_key_exists( $k, $t ) && ! is_string( $t[ $k ] ) ) {
			$err[] = $k . '：文字列にしてください';
		}
	}
	if ( array_key_exists( 'review_days', $t ) && ! is_int( $t['review_days'] ) ) {
		$err[] = 'review_days：整数にしてください';
	}
	if ( array_key_exists( 'ai_assisted', $t ) && ! is_bool( $t['ai_assisted'] ) ) {
		$err[] = 'ai_assisted：true か false にしてください';
	}
	if ( is_string( $t['content_html'] ?? null ) ) {
		if ( strlen( $t['content_html'] ) > $rules['content_bytes_max'] ) {
			$err[] = sprintf( 'content_html：%d バイトを超えています', $rules['content_bytes_max'] );
		} else {
			foreach ( omochix_core_glossary_html_problems( $t['content_html'] ) as $p ) {
				$err[] = 'content_html：' . $p;
			}
		}
	}
	foreach ( array_values( is_array( $t['sources'] ?? null ) ? $t['sources'] : array() ) as $i => $s ) {
		$u = is_array( $s ) ? ( $s['url'] ?? null ) : null;
		if ( ! is_string( $u ) || '' !== omochix_core_glossary_url_problem( $u ) ) {
			$err[] = sprintf( 'sources[%d].url：保存できない URL です（%s）', $i, is_string( $u ) ? omochix_core_glossary_url_problem( $u ) : 'URL がありません' );
		}
	}
	$strings = array();
	array_walk_recursive(
		$t,
		static function ( $v ) use ( &$strings ) {
			if ( is_string( $v ) ) {
				$strings[] = $v;
			}
		}
	);
	$found = omochix_core_glossary_secret_findings( implode( "\n", $strings ) );
	if ( $found ) {
		$err[] = '秘密情報らしい文字列があります（' . implode( '・', $found ) . '）';
	}
	if ( ! $err ) {
		foreach ( omochix_core_glossary_import_meta_values( $t ) as $key => $v ) {
			if ( ! omochix_core_glossary_meta_shape_ok( $key, $v ) || omochix_core_glossary_sanitize_meta( $v, $key ) !== $v ) {
				$err[] = $key . '：保存すると値が変わります（長さ・形・使えない文字を確かめてください）';
			}
		}
		$rel = $t['related'] ?? array();
		if ( ! is_array( $rel ) || ( $rel && array_values( $rel ) === $rel ) ) {
			$err[] = 'related：オブジェクトにしてください';
		} else {
			foreach ( $rel as $k => $list ) {
				$max = $rules['related'][ $k ] ?? null;
				if ( null === $max ) {
					$err[] = 'related.' . $k . '：未知の項目です';
				} elseif ( ! is_array( $list ) || array_values( $list ) !== $list || count( $list ) > $max || array_filter( $list, 'is_string' ) !== $list ) {
					$err[] = sprintf( 'related.%s：%d件までの文字列の配列にしてください', $k, $max );
				}
			}
		}
	}
	$cat = $t['category'] ?? null;
	if ( ! is_string( $cat ) || ! isset( $cats[ $cat ] ) ) {
		$err[] = 'category：「' . ( is_string( $cat ) ? $cat : '' ) . '」というカテゴリはありません（自動では作りません）';
	}
	return array_values( array_unique( array_map( 'omochix_core_glossary_redact', $err ) ) );
}

/**
 * Fingerprint of a stored term (what a later step compares to see whether it changed).
 *
 * @param int $id Post ID.
 * @return string
 */
function omochix_core_glossary_import_post_state( $id ) {
	$p = get_post( (int) $id );
	if ( ! $p instanceof WP_Post ) {
		return 'gone';
	}
	$meta = array();
	foreach ( array_keys( omochix_core_glossary_meta_fields() ) as $k ) {
		// Compared in the stored type (WordPress returns '180' or 180, '' or false, depending on the row).
		$meta[ $k ] = omochix_core_glossary_sanitize_meta( get_post_meta( $p->ID, $k, true ), $k );
	}
	$cats = wp_get_object_terms( $p->ID, 'omx_glossary_category', array( 'fields' => 'ids' ) );
	return hash( 'sha256', wp_json_encode( array( $p->post_type, $p->post_status, $p->post_name, $p->post_title, $p->post_content, (string) $p->post_password, $meta, is_array( $cats ) ? array_map( 'intval', $cats ) : array() ) ) );
}

/**
 * The dry-run: what an import would do. Reads only.
 *
 * @param string $bytes Raw file.
 * @return array{ok: bool, sha: string, errors: string[], rows: array, counts: array, fingerprint: string}
 */
function omochix_core_glossary_import_plan( $bytes ) {
	$sha  = hash( 'sha256', (string) $bytes );
	$dec  = omochix_core_glossary_import_decode( $bytes );
	$out  = array(
		'ok'          => false,
		'sha'         => $sha,
		'errors'      => $dec['errors'],
		'rows'        => array(),
		'counts'      => array( 'create' => 0, 'update' => 0, 'unchanged' => 0, 'conflict' => 0, 'error' => 0, 'warnings' => 0 ),
		'fingerprint' => '',
	);
	if ( $dec['errors'] ) {
		return $out;
	}
	$cats  = omochix_core_glossary_import_categories();
	$today = omochix_core_glossary_today();
	$slugs = array();
	foreach ( $dec['terms'] as $t ) {
		if ( is_array( $t ) && is_string( $t['slug'] ?? null ) ) {
			$slugs[] = $t['slug'];
		}
	}
	$seen  = array_count_values( $slugs );
	$state = array( 'cats' => $cats );
	foreach ( $dec['terms'] as $i => $t ) {
		$slug = is_array( $t ) && is_string( $t['slug'] ?? null ) ? $t['slug'] : '#' . ( $i + 1 );
		$row  = array(
			'slug'      => $slug,
			'action'    => 'error',
			'errors'    => omochix_core_glossary_import_blocking( $t, $cats ),
			'warnings'  => array(),
			'publish'   => array(),
			'changes'   => array(),
			'id'        => 0,
			'status'    => '',
			'values'    => array(),
		);
		if ( isset( $seen[ $slug ] ) && $seen[ $slug ] > 1 ) {
			$row['errors'][] = 'slug：このファイルの中で重複しています';
		}
		if ( ! $row['errors'] ) {
			$check          = omochix_core_glossary_validate_term( $t, array( 'today' => $today, 'categories' => array_keys( $cats ) ) );
			$row['publish'] = $check['errors'];
			$row['warnings'] = $check['warnings'];
			$rel            = omochix_core_glossary_import_related( $t['related'] ?? array(), array_keys( $seen ), $slug );
			$row['warnings'] = array_merge( $row['warnings'], $rel['warnings'] );
			$state['related'][ $slug ] = $rel['state'];
			$meta           = omochix_core_glossary_import_meta_values( $t ) + $rel['ids'];
			$row['values']  = array(
				'post_title'   => $t['title'],
				'post_content' => $t['content_html'],
				'category'     => (int) $cats[ $t['category'] ],
				'meta'         => $meta,
			);
			foreach ( get_posts( array( 'post_type' => 'any', 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => -1, 'suppress_filters' => true ) ) as $other ) {
				if ( $other instanceof WP_Post && $slug === $other->post_name && 'omx_glossary' !== $other->post_type ) {
					$row['warnings'][] = sprintf( 'slug：種類「%s」に同じ slug の投稿があります（用語の URL は /glossary/ の下なので衝突しません）', $other->post_type );
				}
			}
			$existing = omochix_core_glossary_import_existing( $slug );
			$state['existing'][ $slug ] = array_map( static function ( $p ) {
				return array( (int) $p->ID, omochix_core_glossary_import_post_state( $p->ID ) );
			}, $existing );
			if ( count( $existing ) > 1 ) {
				$row['action']   = 'conflict';
				$row['errors'][] = 'slug：同じ slug の用語が複数あります。手で整理してください';
			} elseif ( 1 === count( $existing ) ) {
				$p             = reset( $existing );
				$row['id']     = (int) $p->ID;
				$row['status'] = (string) $p->post_status;
				if ( 'draft' !== $p->post_status ) {
					$row['action']   = 'conflict';
					$row['errors'][] = sprintf( 'slug：状態が「%s」の用語があります。取り込みで更新できるのは下書きだけです', $p->post_status );
				} elseif ( ! current_user_can( 'edit_post', (int) $p->ID ) ) {
					$row['action']   = 'conflict';
					$row['errors'][] = 'slug：この下書きを編集する権限がありません';
				} else {
					$row['changes'] = omochix_core_glossary_import_changes( $p, $row['values'] );
					$row['action']  = $row['changes'] ? 'update' : 'unchanged';
				}
			} else {
				$row['action'] = 'create';
			}
		}
		if ( $row['errors'] && 'conflict' !== $row['action'] ) {
			$row['action'] = 'error';
		}
		++$out['counts'][ $row['action'] ];
		$out['counts']['warnings'] += count( $row['warnings'] );
		$out['rows'][]              = $row;
	}
	$out['ok']          = 0 === $out['counts']['error'] && 0 === $out['counts']['conflict'];
	$out['fingerprint'] = hash( 'sha256', wp_json_encode( array( $sha, $state, array_map( static function ( $r ) {
		return array( $r['slug'], $r['action'], $r['id'], $r['status'], $r['changes'], $r['values'] );
	}, $out['rows'] ) ) ) );
	return $out;
}

/**
 * Fields of an existing draft that the import would change.
 *
 * @param WP_Post $p      Draft.
 * @param array   $values Planned values.
 * @return string[]
 */
function omochix_core_glossary_import_changes( WP_Post $p, array $values ) {
	$out = array();
	if ( (string) $p->post_title !== $values['post_title'] ) {
		$out[] = 'title';
	}
	if ( (string) $p->post_content !== $values['post_content'] ) {
		$out[] = 'content_html';
	}
	$cats = wp_get_object_terms( $p->ID, 'omx_glossary_category', array( 'fields' => 'ids' ) );
	if ( array( (int) $values['category'] ) !== ( is_array( $cats ) ? array_map( 'intval', $cats ) : array() ) ) {
		$out[] = 'category';
	}
	foreach ( $values['meta'] as $k => $v ) {
		$have = omochix_core_glossary_sanitize_meta( get_post_meta( $p->ID, $k, true ), $k );
		$want = is_array( $v ) && $v && is_string( reset( $v ) ) && 0 === strpos( (string) reset( $v ), '@' ) ? null : $v;
		if ( null === $want || $have !== $want ) {
			$out[] = $k;
		}
	}
	return $out;
}

/**
 * Approval kept on the server after a dry-run: for this user and this login session, for one
 * file (SHA-256) and one plan (fingerprint), for 15 minutes, used once.
 *
 * @param string $kind        import or restore.
 * @param string $sha         SHA-256 of the file.
 * @param string $fingerprint Plan fingerprint.
 * @param array  $counts      Counts (kept as the server-side record of the dry-run).
 * @return string Token for the form (not a proof by itself).
 */
function omochix_core_glossary_import_approve( $kind, $sha, $fingerprint, array $counts ) {
	$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 32, false, false ) : bin2hex( random_bytes( 16 ) );
	set_transient(
		'omochix_core_glossary_import_ap_' . hash( 'sha256', $token ),
		array(
			'kind'    => (string) $kind,
			'user'    => (int) get_current_user_id(),
			'session' => hash( 'sha256', (string) wp_get_session_token() ),
			'sha'     => (string) $sha,
			'fp'      => (string) $fingerprint,
			'counts'  => $counts,
			'expires' => omochix_core_glossary_clock() + OMOCHIX_CORE_GLOSSARY_IMPORT_APPROVAL_TTL,
		),
		OMOCHIX_CORE_GLOSSARY_IMPORT_APPROVAL_TTL
	);
	return $token;
}

/**
 * Take an approval: it must exist, belong to this user and session, be of this kind, not be
 * expired, and is removed so that it can never be used again (a second, concurrent request finds
 * nothing to remove and is refused).
 *
 * @param string $kind  import or restore.
 * @param mixed  $token Token from the form.
 * @return array|string The approval, or the reason it was refused.
 */
function omochix_core_glossary_import_claim( $kind, $token ) {
	if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9]{32}$/D', $token ) ) {
		return 'dry-run の記録がありません。dry-run からやり直してください';
	}
	$key = 'omochix_core_glossary_import_ap_' . hash( 'sha256', $token );
	$ap  = get_transient( $key );
	if ( ! is_array( $ap ) ) {
		return 'dry-run の記録が見つからないか、期限切れか、使用済みです。dry-run からやり直してください';
	}
	if ( true !== delete_transient( $key ) ) {
		return 'この承認は別の要求で使われました（二重送信）';
	}
	if ( (string) $kind !== $ap['kind'] || (int) get_current_user_id() !== (int) $ap['user'] || ! hash_equals( (string) $ap['session'], hash( 'sha256', (string) wp_get_session_token() ) ) ) {
		return 'この dry-run は、別の利用者かログインのものです';
	}
	if ( omochix_core_glossary_clock() > (int) $ap['expires'] ) {
		return 'dry-run の期限（15分）が切れました。dry-run からやり直してください';
	}
	return $ap;
}

/**
 * One import or restore at a time (add_option inserts only when the row does not exist).
 *
 * @param bool $take True to take, false to release.
 * @return bool
 */
function omochix_core_glossary_import_lock( $take ) {
	if ( ! $take ) {
		delete_option( OMOCHIX_CORE_GLOSSARY_IMPORT_LOCK );
		return true;
	}
	if ( add_option( OMOCHIX_CORE_GLOSSARY_IMPORT_LOCK, (string) omochix_core_glossary_clock(), '', 'no' ) ) {
		return true;
	}
	$since = (int) get_option( OMOCHIX_CORE_GLOSSARY_IMPORT_LOCK, 0 );
	if ( $since > 0 && omochix_core_glossary_clock() - $since > 10 * MINUTE_IN_SECONDS ) {
		delete_option( OMOCHIX_CORE_GLOSSARY_IMPORT_LOCK ); // Left by a request that died.
		return (bool) add_option( OMOCHIX_CORE_GLOSSARY_IMPORT_LOCK, (string) omochix_core_glossary_clock(), '', 'no' );
	}
	return false;
}

/**
 * Write the post, meta and category of one term and read them back. Drafts only.
 *
 * @param int    $id     Existing draft, or 0 to create.
 * @param string $slug   Slug.
 * @param array  $values Planned values (related glossary placeholders left out).
 * @return array{id: int, error: string}
 */
function omochix_core_glossary_import_write( $id, $slug, array $values ) {
	$post = array(
		'post_type'    => 'omx_glossary',
		'post_name'    => $slug,
		'post_title'   => $values['post_title'],
		'post_content' => $values['post_content'],
	);
	if ( $id > 0 ) {
		$cur = get_post( $id );
		if ( ! $cur instanceof WP_Post || 'omx_glossary' !== $cur->post_type || 'draft' !== $cur->post_status || ! current_user_can( 'edit_post', $id ) ) {
			return array( 'id' => $id, 'error' => '更新の直前に、下書きでなくなったか、権限がなくなりました' );
		}
		$post['ID'] = $id;
		$res        = wp_update_post( wp_slash( $post ), true ); // No post_status: a draft stays a draft.
	} else {
		$post['post_status'] = 'draft';
		$res                 = wp_insert_post( wp_slash( $post ), true );
	}
	if ( is_wp_error( $res ) || ! $res ) {
		return array( 'id' => (int) $id, 'error' => '投稿を保存できませんでした' );
	}
	$id = (int) $res;
	foreach ( $values['meta'] as $k => $v ) {
		if ( 'related_glossary_ids' === $k ) {
			continue; // Second pass, once every term of the file exists.
		}
		update_post_meta( $id, $k, wp_slash( $v ) );
	}
	wp_set_object_terms( $id, array( (int) $values['category'] ), 'omx_glossary_category' );
	return array( 'id' => $id, 'error' => omochix_core_glossary_import_verify( $id, $slug, $values, true ) );
}

/**
 * Read a term back and compare it with the plan.
 *
 * @param int    $id          Post ID.
 * @param string $slug        Slug.
 * @param array  $values      Planned values.
 * @param bool   $skip_glossary True before the related glossary IDs are written.
 * @return string Empty when everything is as planned.
 */
function omochix_core_glossary_import_verify( $id, $slug, array $values, $skip_glossary = false ) {
	if ( function_exists( 'clean_post_cache' ) ) {
		clean_post_cache( $id );
	}
	$p = get_post( $id );
	if ( ! $p instanceof WP_Post || 'omx_glossary' !== $p->post_type ) {
		return '保存後に用語が見つかりません';
	}
	if ( 'draft' !== $p->post_status ) {
		return sprintf( '保存後の状態が下書きではありません（%s）', $p->post_status );
	}
	if ( $slug !== $p->post_name ) {
		return sprintf( 'slug が WordPress によって「%s」に変えられました', $p->post_name );
	}
	if ( $values['post_title'] !== (string) $p->post_title || $values['post_content'] !== (string) $p->post_content ) {
		return '保存後のタイトルか本文が、ファイルと違います（WordPress のフィルターで変わった可能性があります）';
	}
	$cats = wp_get_object_terms( $id, 'omx_glossary_category', array( 'fields' => 'ids' ) );
	if ( array( (int) $values['category'] ) !== ( is_array( $cats ) ? array_map( 'intval', $cats ) : array() ) ) {
		return '保存後のカテゴリが計画と違います';
	}
	foreach ( $values['meta'] as $k => $v ) {
		if ( $skip_glossary && 'related_glossary_ids' === $k ) {
			continue;
		}
		if ( omochix_core_glossary_sanitize_meta( get_post_meta( $id, $k, true ), $k ) !== $v ) {
			return '保存後の ' . $k . ' が計画と違います';
		}
	}
	return '';
}

/**
 * Backup of the drafts an import is about to change (and the list of what it will create).
 *
 * @param array $rows Plan rows.
 * @return array
 */
function omochix_core_glossary_import_backup( array $rows ) {
	$items = array();
	foreach ( $rows as $r ) {
		if ( 'update' !== $r['action'] ) {
			continue;
		}
		$p      = get_post( (int) $r['id'] );
		$meta   = array();
		$absent = array();
		foreach ( array_keys( omochix_core_glossary_meta_fields() ) as $k ) {
			if ( metadata_exists( 'post', $p->ID, $k ) ) {
				$meta[ $k ] = get_post_meta( $p->ID, $k, true );
			} else {
				$absent[] = $k;
			}
		}
		$cats    = wp_get_object_terms( $p->ID, 'omx_glossary_category', array( 'fields' => 'ids' ) );
		$items[] = array(
			'id'       => (int) $p->ID,
			'slug'     => (string) $p->post_name,
			'status'   => (string) $p->post_status,
			'title'    => (string) $p->post_title,
			'content'  => (string) $p->post_content,
			'meta'     => $meta,
			'absent'   => $absent,
			'category' => is_array( $cats ) ? array_map( 'intval', $cats ) : array(),
			'before'   => omochix_core_glossary_import_post_state( $p->ID ),
		);
	}
	return array(
		'schema'  => OMOCHIX_CORE_GLOSSARY_IMPORT_BACKUP_SCHEMA,
		'site'    => hash( 'sha256', (string) home_url( '/' ) ),
		'made'    => gmdate( 'Y-m-d H:i:s', omochix_core_glossary_clock() ),
		'user'    => (int) get_current_user_id(),
		'updated' => $items,
		'created' => array(),
	);
}

/**
 * Sign or check a backup (so that only a backup this site wrote can be restored).
 *
 * @param array $backup Backup without the signature.
 * @return string
 */
function omochix_core_glossary_import_sign( array $backup ) {
	unset( $backup['sig'] );
	return function_exists( 'wp_hash' ) ? wp_hash( wp_json_encode( $backup ), 'auth' ) : '';
}

/**
 * Apply an approved import. Stops at the first failure and says what was done.
 *
 * @param string $bytes The file uploaded again.
 * @param mixed  $token Approval token.
 * @return array{ok: bool, message: string, done: array, failed: array, manual: array, backup: array|null, plan: array|null}
 */
function omochix_core_glossary_import_apply( $bytes, $token ) {
	$res = array( 'ok' => false, 'message' => '', 'done' => array(), 'failed' => array(), 'manual' => array(), 'backup' => null, 'plan' => null );
	$ap  = omochix_core_glossary_import_claim( 'import', $token );
	if ( ! is_array( $ap ) ) {
		$res['message'] = $ap;
		return $res;
	}
	if ( ! hash_equals( (string) $ap['sha'], hash( 'sha256', (string) $bytes ) ) ) {
		$res['message'] = 'dry-run のファイルと SHA-256 が一致しません。適用していません';
		return $res;
	}
	if ( ! omochix_core_glossary_import_lock( true ) ) {
		$res['message'] = '別の取り込みが進行中です。終わってから dry-run からやり直してください';
		return $res;
	}
	try {
		$plan        = omochix_core_glossary_import_plan( $bytes );
		$res['plan'] = $plan;
		if ( ! $plan['ok'] || ! hash_equals( (string) $ap['fp'], (string) $plan['fingerprint'] ) ) {
			$res['message'] = 'dry-run のあとで、用語・カテゴリ・関連先のどれかが変わりました。適用していません。dry-run からやり直してください';
			return $res;
		}
		$backup = omochix_core_glossary_import_backup( $plan['rows'] );
		$ids    = array();
		foreach ( $plan['rows'] as $r ) {
			if ( 'unchanged' === $r['action'] ) {
				$ids[ $r['slug'] ] = (int) $r['id'];
				continue;
			}
			$w = omochix_core_glossary_import_write( 'update' === $r['action'] ? (int) $r['id'] : 0, $r['slug'], $r['values'] );
			if ( '' !== $w['error'] ) {
				$res['failed'] = array( 'slug' => $r['slug'], 'id' => $w['id'], 'reason' => $w['error'] );
				if ( $w['id'] > 0 ) {
					$res['manual'][] = array( 'slug' => $r['slug'], 'id' => $w['id'], 'action' => $r['action'] );
				}
				break;
			}
			$ids[ $r['slug'] ] = $w['id'];
			$res['done'][]     = array( 'slug' => $r['slug'], 'id' => $w['id'], 'action' => $r['action'] );
			if ( 'create' === $r['action'] ) {
				$backup['created'][] = array( 'id' => $w['id'], 'slug' => $r['slug'] );
			}
		}
		if ( ! $res['failed'] ) {
			// Second pass: related glossary IDs, now that every term of the file exists.
			foreach ( $plan['rows'] as $r ) {
				if ( 'unchanged' === $r['action'] ) {
					continue;
				}
				$want = array();
				foreach ( (array) $r['values']['meta']['related_glossary_ids'] as $v ) {
					$want[] = is_string( $v ) && 0 === strpos( $v, '@' ) ? (int) ( $ids[ substr( $v, 1 ) ] ?? 0 ) : (int) $v;
				}
				$want = array_values( array_filter( array_unique( $want ) ) );
				update_post_meta( $ids[ $r['slug'] ], 'related_glossary_ids', $want );
				$vals                                 = $r['values'];
				$vals['meta']['related_glossary_ids'] = $want;
				$err                                  = omochix_core_glossary_import_verify( $ids[ $r['slug'] ], $r['slug'], $vals );
				if ( '' !== $err ) {
					$res['failed']   = array( 'slug' => $r['slug'], 'id' => $ids[ $r['slug'] ], 'reason' => $err );
					$res['manual'][] = array( 'slug' => $r['slug'], 'id' => $ids[ $r['slug'] ], 'action' => $r['action'] );
					break;
				}
			}
		}
		foreach ( $backup['updated'] as $i => $item ) {
			$backup['updated'][ $i ]['after'] = omochix_core_glossary_import_post_state( $item['id'] );
		}
		$backup['sig']  = omochix_core_glossary_import_sign( $backup );
		$res['backup']  = $backup;
		$res['ok']      = ! $res['failed'];
		$res['message'] = $res['ok'] ? '取り込みました（すべて下書き）' : '途中で止めました。下の「止まった行」と「手で確かめる用語」を見てください。済んだ行はそのままです（データベース全体を元に戻す処理ではありません）';
		set_transient( 'omochix_core_glossary_import_backup_' . get_current_user_id(), $backup, 30 * DAY_IN_SECONDS );
		return $res;
	} finally {
		omochix_core_glossary_import_lock( false );
	}
}

/**
 * Decode and check a backup file, and plan its restore. Reads only.
 *
 * Restorable: an updated draft that is still a draft and has not changed since the import
 * (its state equals the one recorded after the import). Terms the import created are listed
 * for a manual decision; nothing is deleted.
 *
 * @param string $bytes Raw backup file.
 * @return array{ok: bool, sha: string, errors: string[], rows: array, created: array, fingerprint: string, backup: array}
 */
function omochix_core_glossary_import_restore_plan( $bytes ) {
	$out = array( 'ok' => false, 'sha' => hash( 'sha256', (string) $bytes ), 'errors' => array(), 'rows' => array(), 'created' => array(), 'fingerprint' => '', 'backup' => array() );
	$b   = is_string( $bytes ) && strlen( $bytes ) <= OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_BYTES * 2 ? json_decode( $bytes, true, 32 ) : null;
	if ( ! is_array( $b ) || OMOCHIX_CORE_GLOSSARY_IMPORT_BACKUP_SCHEMA !== ( $b['schema'] ?? null ) || ! is_string( $b['sig'] ?? null ) || ! is_array( $b['updated'] ?? null ) || ! is_array( $b['created'] ?? null ) ) {
		$out['errors'][] = 'バックアップのファイルとして読めません';
		return $out;
	}
	if ( '' === $b['sig'] || ! hash_equals( omochix_core_glossary_import_sign( $b ), $b['sig'] ) || ( $b['site'] ?? '' ) !== hash( 'sha256', (string) home_url( '/' ) ) ) {
		$out['errors'][] = 'このサイトが作ったバックアップではないか、書き換えられています';
		return $out;
	}
	$out['backup'] = $b;
	$state         = array();
	foreach ( $b['updated'] as $item ) {
		$id  = (int) ( $item['id'] ?? 0 );
		$p   = get_post( $id );
		$row = array( 'id' => $id, 'slug' => (string) ( $item['slug'] ?? '' ), 'action' => 'restore', 'errors' => array() );
		if ( ! $p instanceof WP_Post || 'omx_glossary' !== $p->post_type ) {
			$row['errors'][] = '用語が見つかりません';
		} elseif ( 'draft' !== $p->post_status || 'draft' !== ( $item['status'] ?? '' ) ) {
			$row['errors'][] = '下書きではありません（復元できるのは下書きだけです）';
		} elseif ( ! current_user_can( 'edit_post', $id ) ) {
			$row['errors'][] = '編集する権限がありません';
		} else {
			$now = omochix_core_glossary_import_post_state( $id );
			if ( ! hash_equals( (string) ( $item['after'] ?? '' ), $now ) ) {
				$row['errors'][] = '取り込みのあとで変更されています。上書きしないため、手で確かめてください';
			}
			$state[] = array( $id, $now );
		}
		if ( $row['errors'] ) {
			$row['action'] = 'conflict';
		}
		$out['rows'][] = $row;
	}
	foreach ( $b['created'] as $c ) {
		$out['created'][] = array( 'id' => (int) ( $c['id'] ?? 0 ), 'slug' => (string) ( $c['slug'] ?? '' ) );
	}
	$out['ok']          = ! array_filter( $out['rows'], static function ( $r ) {
		return 'conflict' === $r['action'];
	} );
	$out['fingerprint'] = hash( 'sha256', wp_json_encode( array( $out['sha'], $state ) ) );
	return $out;
}

/**
 * Apply an approved restore: put the backed-up title, content, meta and category back on each
 * draft that has not changed since the import. Status is never changed; nothing is deleted.
 *
 * @param string $bytes Backup uploaded again.
 * @param mixed  $token Approval token.
 * @return array{ok: bool, message: string, done: array, failed: array}
 */
function omochix_core_glossary_import_restore_apply( $bytes, $token ) {
	$res = array( 'ok' => false, 'message' => '', 'done' => array(), 'failed' => array() );
	$ap  = omochix_core_glossary_import_claim( 'restore', $token );
	if ( ! is_array( $ap ) ) {
		$res['message'] = $ap;
		return $res;
	}
	if ( ! hash_equals( (string) $ap['sha'], hash( 'sha256', (string) $bytes ) ) ) {
		$res['message'] = 'dry-run のファイルと SHA-256 が一致しません。復元していません';
		return $res;
	}
	if ( ! omochix_core_glossary_import_lock( true ) ) {
		$res['message'] = '別の取り込みか復元が進行中です';
		return $res;
	}
	try {
		$plan = omochix_core_glossary_import_restore_plan( $bytes );
		if ( ! $plan['ok'] || ! hash_equals( (string) $ap['fp'], (string) $plan['fingerprint'] ) ) {
			$res['message'] = 'dry-run のあとで用語が変わりました。復元していません。dry-run からやり直してください';
			return $res;
		}
		foreach ( $plan['backup']['updated'] as $item ) {
			$id  = (int) $item['id'];
			$cur = get_post( $id );
			if ( ! $cur instanceof WP_Post || 'draft' !== $cur->post_status || ! current_user_can( 'edit_post', $id ) ) {
				$res['failed'] = array( 'slug' => (string) $item['slug'], 'id' => $id, 'reason' => '復元の直前に下書きでなくなったか、権限がなくなりました' );
				break;
			}
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_title' => (string) $item['title'], 'post_content' => (string) $item['content'], 'post_name' => (string) $item['slug'] ) ), true );
			foreach ( (array) $item['meta'] as $k => $v ) {
				if ( array_key_exists( $k, omochix_core_glossary_meta_fields() ) ) {
					update_post_meta( $id, $k, wp_slash( $v ) );
				}
			}
			foreach ( (array) ( $item['absent'] ?? array() ) as $k ) {
				if ( is_string( $k ) && array_key_exists( $k, omochix_core_glossary_meta_fields() ) ) {
					delete_post_meta( $id, $k ); // It had no value before the import.
				}
			}
			wp_set_object_terms( $id, array_map( 'intval', (array) $item['category'] ), 'omx_glossary_category' );
			if ( ! hash_equals( (string) $item['before'], omochix_core_glossary_import_post_state( $id ) ) ) {
				$res['failed'] = array( 'slug' => (string) $item['slug'], 'id' => $id, 'reason' => '復元後の内容が、取り込み前と一致しません。手で確かめてください' );
				break;
			}
			$res['done'][] = array( 'slug' => (string) $item['slug'], 'id' => $id, 'action' => 'restore' );
		}
		$res['ok']      = ! $res['failed'];
		$res['message'] = $res['ok'] ? '取り込み前の内容に戻しました（下書きのまま）。取り込みで作った用語は削除していません' : '途中で止めました';
		return $res;
	} finally {
		omochix_core_glossary_import_lock( false );
	}
}

/**
 * The menu entry (manage_options; the page checks the glossary capability too).
 *
 * @return void
 */
function omochix_core_glossary_import_menu() {
	add_submenu_page( 'edit.php?post_type=omx_glossary', __( 'JSON インポート', 'omochix-core' ), __( 'JSON インポート', 'omochix-core' ), 'manage_options', 'omochix-glossary-import', 'omochix_core_glossary_import_page' );
}
add_action( 'admin_menu', 'omochix_core_glossary_import_menu', 20 );

/**
 * Read an uploaded JSON file (the one place $_FILES is read). Neither the file name nor the
 * MIME type is trusted: the content is decoded and checked afterwards.
 *
 * @param string $field Field name.
 * @return string|WP_Error
 */
function omochix_core_glossary_import_handle_upload( $field ) {
	$f = isset( $_FILES[ $field ] ) && is_array( $_FILES[ $field ] ) ? $_FILES[ $field ] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked below, content decoded and validated.
	if ( ! $f || ! isset( $f['tmp_name'], $f['error'], $f['size'], $f['name'] ) || ! is_string( $f['tmp_name'] ) ) {
		return new WP_Error( 'upload', 'JSON ファイルを選んでください' );
	}
	if ( UPLOAD_ERR_OK !== (int) $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
		return new WP_Error( 'upload', 'アップロードに失敗しました' );
	}
	if ( (int) $f['size'] <= 0 || (int) $f['size'] > OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_BYTES * 2 || (int) filesize( $f['tmp_name'] ) > OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_BYTES * 2 ) {
		return new WP_Error( 'upload', 'ファイルが大きすぎます' );
	}
	if ( 'json' !== strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) ) ) {
		return new WP_Error( 'upload', '拡張子 .json のファイルを選んでください' );
	}
	$bytes = file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the uploaded temporary file only.
	return is_string( $bytes ) ? $bytes : new WP_Error( 'upload', 'ファイルを読めませんでした' );
}

/**
 * Handle the posted form (the one place $_POST is read), after the nonce and the capability.
 *
 * @return array|null What to show, or null when nothing was posted.
 */
function omochix_core_glossary_import_handle_post() {
	if ( ! isset( $_POST['omochix_glossary_import_step'] ) || ! is_string( $_POST['omochix_glossary_import_step'] ) ) {
		return null;
	}
	$step = sanitize_key( wp_unslash( $_POST['omochix_glossary_import_step'] ) );
	if ( ! in_array( $step, array( 'dryrun', 'apply', 'restore_dryrun', 'restore_apply' ), true ) ) {
		return null;
	}
	$nonce = isset( $_POST['omochix_glossary_import_nonce'] ) && is_string( $_POST['omochix_glossary_import_nonce'] ) ? sanitize_key( wp_unslash( $_POST['omochix_glossary_import_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'omochix_glossary_import_' . $step ) || ! omochix_core_glossary_import_can() ) {
		return array( 'step' => $step, 'error' => '確認に失敗しました（nonce または権限）。画面を読み込み直してください' );
	}
	$token = isset( $_POST['omochix_glossary_import_token'] ) && is_string( $_POST['omochix_glossary_import_token'] ) ? sanitize_text_field( wp_unslash( $_POST['omochix_glossary_import_token'] ) ) : '';
	$bytes = omochix_core_glossary_import_handle_upload( 'omochix_glossary_import_file' );
	if ( is_wp_error( $bytes ) ) {
		return array( 'step' => $step, 'error' => $bytes->get_error_message() );
	}
	switch ( $step ) {
		case 'dryrun':
			$plan = omochix_core_glossary_import_plan( $bytes );
			return array( 'step' => $step, 'plan' => $plan, 'token' => $plan['ok'] ? omochix_core_glossary_import_approve( 'import', $plan['sha'], $plan['fingerprint'], $plan['counts'] ) : '' );
		case 'apply':
			return array( 'step' => $step, 'result' => omochix_core_glossary_import_apply( $bytes, $token ) );
		case 'restore_dryrun':
			$plan = omochix_core_glossary_import_restore_plan( $bytes );
			return array( 'step' => $step, 'restore' => $plan, 'token' => $plan['ok'] && $plan['rows'] ? omochix_core_glossary_import_approve( 'restore', $plan['sha'], $plan['fingerprint'], array( 'restore' => count( $plan['rows'] ) ) ) : '' );
		default:
			return array( 'step' => $step, 'result' => omochix_core_glossary_import_restore_apply( $bytes, $token ) );
	}
}

/**
 * One upload form.
 *
 * @param string $step  Step.
 * @param string $label Button label.
 * @param string $token Approval token ('' for a dry-run).
 * @return void
 */
function omochix_core_glossary_import_form( $step, $label, $token = '' ) {
	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field( 'omochix_glossary_import_' . $step, 'omochix_glossary_import_nonce' );
	echo '<input type="hidden" name="omochix_glossary_import_step" value="' . esc_attr( $step ) . '" />';
	if ( '' !== $token ) {
		echo '<input type="hidden" name="omochix_glossary_import_token" value="' . esc_attr( $token ) . '" />';
	}
	echo '<p><input type="file" name="omochix_glossary_import_file" accept=".json,application/json" required /> ';
	echo '<button type="submit" class="button button-primary">' . esc_html( $label ) . '</button></p></form>';
}

/**
 * Print a list (escaped).
 *
 * @param string[] $items Items.
 * @return void
 */
function omochix_core_glossary_import_list( array $items ) {
	if ( ! $items ) {
		return;
	}
	echo '<ul>';
	foreach ( $items as $m ) {
		echo '<li>' . esc_html( (string) $m ) . '</li>';
	}
	echo '</ul>';
}

/**
 * The importer page.
 *
 * @return void
 */
function omochix_core_glossary_import_page() {
	if ( ! omochix_core_glossary_import_can() ) {
		wp_die( esc_html__( 'この画面を使う権限がありません。', 'omochix-core' ), 403 );
	}
	$got    = omochix_core_glossary_import_handle_post();
	$labels = array( 'create' => '新規作成', 'update' => '既存の下書きを更新', 'unchanged' => '変更なし', 'conflict' => '衝突（更新しない）', 'error' => 'エラー' );
	echo '<div class="wrap"><h1>' . esc_html__( '用語集：JSON インポート（下書きだけ）', 'omochix-core' ) . '</h1>';
	echo '<p>' . esc_html( sprintf( 'ファイルは %s、用語は %d 件まで、%s バイトまでです。取り込んだ用語はすべて下書きです。公開中・予約・非公開などの用語は更新しません。', OMOCHIX_CORE_GLOSSARY_IMPORT_SCHEMA, OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_TERMS, number_format( OMOCHIX_CORE_GLOSSARY_IMPORT_MAX_BYTES ) ) ) . '</p>';
	if ( is_array( $got ) && isset( $got['error'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $got['error'] ) . '</p></div>';
	}
	if ( is_array( $got ) && isset( $got['plan'] ) ) {
		$plan = $got['plan'];
		echo '<h2>' . esc_html__( 'dry-run の結果（まだ何も書き込んでいません）', 'omochix-core' ) . '</h2>';
		echo '<p><code>SHA-256: ' . esc_html( $plan['sha'] ) . '</code></p>';
		omochix_core_glossary_import_list( $plan['errors'] );
		$c = $plan['counts'];
		echo '<p>' . esc_html( sprintf( '新規 %d／更新 %d／変更なし %d／公開中などとの衝突 %d／エラー %d／警告 %d', $c['create'], $c['update'], $c['unchanged'], $c['conflict'], $c['error'], $c['warnings'] ) ) . '</p>';
		if ( $plan['rows'] ) {
			echo '<table class="widefat striped"><thead><tr><th>slug</th><th>' . esc_html__( '処理', 'omochix-core' ) . '</th><th>' . esc_html__( '変わる項目', 'omochix-core' ) . '</th><th>' . esc_html__( '公開の条件', 'omochix-core' ) . '</th><th>' . esc_html__( 'エラー・警告', 'omochix-core' ) . '</th></tr></thead><tbody>';
			foreach ( $plan['rows'] as $r ) {
				echo '<tr><td><code>' . esc_html( $r['slug'] ) . '</code></td><td>' . esc_html( $labels[ $r['action'] ] ?? $r['action'] ) . '</td><td>' . esc_html( implode( ', ', $r['changes'] ) ) . '</td><td>';
				echo esc_html( $r['errors'] ? '—' : ( $r['publish'] ? sprintf( '未達（%d件。下書きのまま取り込みます）', count( $r['publish'] ) ) : '満たしています（取り込みは下書き）' ) );
				omochix_core_glossary_import_list( $r['publish'] );
				echo '</td><td>';
				omochix_core_glossary_import_list( array_merge( $r['errors'], $r['warnings'] ) );
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( '' !== $got['token'] ) {
			echo '<h2>' . esc_html__( '適用', 'omochix-core' ) . '</h2><p>' . esc_html__( '同じファイルをもう一度選んで適用してください（15分以内、1回だけ）。ファイルか、用語・カテゴリ・関連先が変わっていたら、適用しません。', 'omochix-core' ) . '</p>';
			omochix_core_glossary_import_form( 'apply', __( '同じファイルで適用する', 'omochix-core' ), $got['token'] );
		} elseif ( ! $plan['errors'] ) {
			echo '<p><strong>' . esc_html__( 'エラーか衝突があるため、適用できません。ファイルを直してから dry-run をやり直してください。', 'omochix-core' ) . '</strong></p>';
		}
	}
	if ( is_array( $got ) && isset( $got['restore'] ) ) {
		$rp = $got['restore'];
		echo '<h2>' . esc_html__( '復元の dry-run（まだ何も書き込んでいません）', 'omochix-core' ) . '</h2>';
		omochix_core_glossary_import_list( $rp['errors'] );
		foreach ( $rp['rows'] as $r ) {
			echo '<p><code>' . esc_html( $r['slug'] ) . '</code>（ID ' . esc_html( (string) $r['id'] ) . '）：' . esc_html( 'restore' === $r['action'] ? '取り込み前に戻せます' : implode( ' ', $r['errors'] ) ) . '</p>';
		}
		if ( $rp['created'] ) {
			echo '<p>' . esc_html__( '取り込みで作った用語（復元では削除しません。不要なら手でゴミ箱へ）：', 'omochix-core' ) . '</p>';
			omochix_core_glossary_import_list( array_map( static function ( $c ) {
				return $c['slug'] . '（ID ' . $c['id'] . '）';
			}, $rp['created'] ) );
		}
		if ( '' !== $got['token'] ) {
			omochix_core_glossary_import_form( 'restore_apply', __( '同じバックアップで復元する', 'omochix-core' ), $got['token'] );
		}
	}
	if ( is_array( $got ) && isset( $got['result'] ) ) {
		$res = $got['result'];
		echo '<div class="notice ' . esc_attr( $res['ok'] ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $res['message'] ) . '</p></div>';
		$done = array();
		foreach ( $res['done'] as $d ) {
			$done[] = sprintf( '%s：%s（ID %d）', $labels[ $d['action'] ] ?? $d['action'], $d['slug'], $d['id'] );
		}
		omochix_core_glossary_import_list( $done );
		if ( ! empty( $res['plan']['counts'] ) ) {
			echo '<p>' . esc_html( sprintf( '変更なし %d 件', (int) $res['plan']['counts']['unchanged'] ) ) . '</p>';
		}
		if ( $res['failed'] ) {
			echo '<p><strong>' . esc_html__( '止まった行', 'omochix-core' ) . '</strong>：' . esc_html( sprintf( '%s（ID %d）：%s', $res['failed']['slug'], $res['failed']['id'], $res['failed']['reason'] ) ) . '</p>';
		}
		if ( ! empty( $res['manual'] ) ) {
			echo '<p><strong>' . esc_html__( '手で確かめる用語', 'omochix-core' ) . '</strong></p>';
			omochix_core_glossary_import_list( array_map( static function ( $m ) {
				return $m['slug'] . '（ID ' . $m['id'] . '）';
			}, $res['manual'] ) );
		}
		if ( ! empty( $res['backup'] ) ) {
			echo '<h2>' . esc_html__( 'バックアップ', 'omochix-core' ) . '</h2><p>' . esc_html__( '更新前の下書きの内容です。.json で手元に保存してください（公開の場所や GitHub には置かないでください）。30日間はこのサイトにも残ります。', 'omochix-core' ) . '</p>';
			echo '<textarea readonly rows="8" class="large-text code">' . esc_textarea( (string) wp_json_encode( $res['backup'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</textarea>';
		}
	}
	echo '<h2>' . esc_html__( '1. dry-run', 'omochix-core' ) . '</h2>';
	omochix_core_glossary_import_form( 'dryrun', __( 'dry-run（書き込まない）', 'omochix-core' ) );
	echo '<h2>' . esc_html__( 'バックアップから戻す（dry-run）', 'omochix-core' ) . '</h2>';
	omochix_core_glossary_import_form( 'restore_dryrun', __( '復元の dry-run（書き込まない）', 'omochix-core' ) );
	echo '</div>';
}
// omochix-glossary-version: 1 (end)
