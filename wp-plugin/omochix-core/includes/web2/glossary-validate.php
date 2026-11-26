<?php
// omochix-glossary-version: 1
/**
 * AI glossary (Knowledge Hub): the data rules, in one place.
 *
 * One term is checked the same way everywhere it can come from: the JSON
 * files of the seo stream (content/glossary/, checked in CI by
 * tests/glossary/validate-content.php), the importer, and the publish guard.
 * Plain PHP: no WordPress function is called, so the CLI and the tests can
 * load this file alone. docs/web2/kh/glossary-schema.json describes the same
 * rules for people.
 *
 * A term that does not pass is never published (the publish guard keeps it a
 * draft). Errors block; warnings are shown to the editor and do not block.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The fixed limits and lists.
 *
 * @return array<string, mixed>
 */
function omochix_core_glossary_rules() {
	return array(
		'schema'            => 'omochix-glossary/1',
		'categories_schema' => 'omochix-glossary-categories/1',
		'category_count'    => 10,
		'term_target'       => 50,
		'category_min'      => 3,
		'title_max'         => 40,
		'reading_max'       => 60,
		'en_max'            => 80,
		'alias_max'         => 10,
		'alias_len'         => 40,
		'summary_min'       => 40,
		'summary_max'       => 120,
		'content_min'       => 400,
		'content_max'       => 2000,
		'content_hard_max'  => 6000,
		'source_min'        => 1,
		'source_max'        => 8,
		'source_title_max'  => 200,
		'publisher_max'     => 100,
		'url_max'           => 2048,
		'review_days'       => 180,
		'review_days_min'   => 30,
		'review_days_max'   => 365,
		'levels'            => array( 'beginner', 'intermediate', 'advanced' ),
		'kinds'             => array( 'official', 'paper', 'standard', 'docs', 'media' ),
		'primary_kinds'     => array( 'official', 'paper', 'standard', 'docs' ),
		'related'           => array(
			'glossary'    => 8,
			'tools'       => 6,
			'learn_pages' => 6,
			'practice'    => 6,
			'industries'  => 4,
		),
		'reserved_slugs'    => array( 'category', 'page', 'feed', 'rss', 'rss2', 'rdf', 'atom', 'embed', 'trackback', 'comments', 'amp', 'search', 'index', 'glossary', 'wp-admin', 'wp-json' ),
		'allowed_tags'      => array( 'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'code', 'pre', 'blockquote', 'a', 'br', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'sup', 'sub' ),
		// Parameter names that carry a credential, compared after lowercasing and dropping every character but a-z and 0-9 (access-token, Access_Token and accessToken are the same name).
		'secret_query_keys' => array( 'token', 'key', 'sig', 'auth', 'authorization', 'bearer', 'jwt', 'session', 'jsessionid', 'phpsessid', 'pass', 'pwd', 'pw', 'otp', 'hmac', 'code' ),
		// A name that ends with one of these is a credential too (csrf_token, client-secret, X-Amz-Signature ...). Not "contains": secretary= and credentials=include are ordinary.
		'secret_key_ends'   => array( 'token', 'secret', 'signature', 'password', 'passwd', 'credential', 'apikey', 'accesskey', 'secretkey', 'privatekey', 'sessionid' ),
		// A name that contains one of these, anywhere, is one as well (x_api_key_v2, access_token_2 ...).
		'secret_key_parts'  => array( 'apikey', 'accesstoken', 'refreshtoken', 'clientsecret', 'secretkey', 'privatekey' ),
		// Raw content larger than this is refused unread. The visible text may be 6,000 characters (18 KB as
		// Japanese text); with the block editor's markup a full term stays well below this. The limit also
		// bounds what one term can cost: every page that lists terms checks each of them.
		'content_bytes_max' => 60000,
		// Dates in a term are calendar dates of the site. Used only where WordPress is not loaded (CLI, CI).
		'timezone'          => 'Asia/Tokyo',
		// Block editor: the only blocks a term may use, with the only settings each may carry.
		'blocks'            => array(
			'paragraph'    => array(),
			'heading'      => array( 'level' ),
			'list'         => array( 'ordered' ),
			'list-item'    => array(),
			'quote'        => array(),
			'code'         => array(),
			'preformatted' => array(),
			'table'        => array( 'hasFixedLayout' ),
		),
		// Block editor: the class names it writes on those blocks' own tags. Nothing else may carry a class.
		'block_classes'     => array(
			'h2'         => array( 'wp-block-heading' ),
			'h3'         => array( 'wp-block-heading' ),
			'h4'         => array( 'wp-block-heading' ),
			'ul'         => array( 'wp-block-list' ),
			'ol'         => array( 'wp-block-list' ),
			'blockquote' => array( 'wp-block-quote' ),
			'pre'        => array( 'wp-block-code', 'wp-block-preformatted' ),
			'table'      => array( 'has-fixed-layout' ),
			'figure'     => array( 'wp-block-table' ),
		),
		'term_keys'         => array( 'schema', 'slug', 'title', 'reading', 'en', 'aliases', 'category', 'level', 'summary', 'content_html', 'sources', 'verified_on', 'review_days', 'ai_assisted', 'related' ),
	);
}

/**
 * Number of characters (UTF-8), without mbstring.
 *
 * @param string $s Text.
 * @return int
 */
function omochix_core_glossary_len( $s ) {
	$s = (string) $s;
	if ( '' === $s ) {
		return 0;
	}
	$n = preg_match_all( '/./us', $s );
	return false === $n ? -1 : (int) $n; // -1: not valid UTF-8.
}

/**
 * Whether a slug has the allowed form and is not reserved.
 *
 * @param mixed $slug Slug.
 * @return string Empty when valid, else the reason.
 */
function omochix_core_glossary_slug_problem( $slug ) {
	if ( ! is_string( $slug ) || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ) {
		return 'ASCII の小文字・数字・ハイフンだけにしてください（先頭と末尾はハイフン以外）';
	}
	$len = strlen( $slug );
	if ( $len < 2 || $len > 60 ) {
		return '2〜60文字にしてください';
	}
	if ( in_array( $slug, omochix_core_glossary_rules()['reserved_slugs'], true ) ) {
		return '予約語です';
	}
	return '';
}

/**
 * Today's date (YYYY-MM-DD) where the site is: the WordPress timezone setting
 * when WordPress is loaded, else the fixed timezone of the rules. Never UTC by
 * accident: between 00:00 and 09:00 in Japan the UTC date is still yesterday,
 * and a term checked "today" would count as checked in the future.
 *
 * @param int|null $now Unix time (tests); null for the current time.
 * @return string
 */
function omochix_core_glossary_today( $now = null ) {
	$now = null === $now ? omochix_core_glossary_clock() : (int) $now;
	try {
		$zone = function_exists( 'wp_timezone' ) ? wp_timezone() : null;
		if ( ! $zone instanceof DateTimeZone ) {
			$zone = new DateTimeZone( omochix_core_glossary_rules()['timezone'] );
		}
		return ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $zone )->format( 'Y-m-d' );
	} catch ( Throwable $e ) {
		return gmdate( 'Y-m-d', $now + 9 * 3600 ); // The fixed zone of the rules (UTC+9, no summer time).
	}
}

/**
 * The time the rules take as "now". Always the real time, unless a test has
 * set another one: tests fix it, so that they do not start failing when the
 * dates in their fixtures grow old.
 *
 * @param int|false|null $set Unix time to use from now on (tests); false goes back to the real time; null only asks.
 * @return int
 */
function omochix_core_glossary_clock( $set = null ) {
	static $fixed = null;
	if ( false === $set ) {
		$fixed = null;
	} elseif ( null !== $set ) {
		$fixed = (int) $set;
	}
	return null === $fixed ? time() : $fixed;
}

/**
 * Whether a string is a real calendar date in YYYY-MM-DD, not after $today.
 *
 * @param mixed  $date  Date.
 * @param string $today Today (YYYY-MM-DD).
 * @return string Empty when valid, else the reason.
 */
function omochix_core_glossary_date_problem( $date, $today ) {
	if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return 'YYYY-MM-DD の実在する日付にしてください';
	}
	if ( strcmp( $date, $today ) > 0 ) {
		return '未来の日付は使えません';
	}
	return '';
}

/**
 * Why a source URL is not acceptable, or '' when it is.
 *
 * https only, a real host name (no IP address, no localhost or internal
 * names), no user name or password, and nothing that looks like a credential
 * in the path, the query or the fragment (omochix_core_glossary_url_secret_problem).
 *
 * @param mixed $url URL.
 * @return string
 */
function omochix_core_glossary_url_problem( $url ) {
	$rules = omochix_core_glossary_rules();
	if ( ! is_string( $url ) || '' === $url || strlen( $url ) > $rules['url_max'] ) {
		return 'URL が空か、長すぎます';
	}
	if ( preg_match( '/[\x00-\x20\x7f<>"\'`\\\\]/', $url ) ) {
		return 'URL に空白・制御文字・引用符・山かっこ・バックスラッシュは使えません';
	}
	$p = parse_url( $url );
	if ( ! is_array( $p ) || 'https' !== strtolower( (string) ( $p['scheme'] ?? '' ) ) || empty( $p['host'] ) ) {
		return 'https の URL にしてください';
	}
	if ( isset( $p['user'] ) || isset( $p['pass'] ) ) {
		return 'URL にユーザー名やパスワードを含めないでください';
	}
	if ( isset( $p['port'] ) && 443 !== (int) $p['port'] ) {
		return '443 以外のポートは使えません';
	}
	$host = strtolower( rtrim( (string) $p['host'], '.' ) );
	if ( filter_var( trim( $host, '[]' ), FILTER_VALIDATE_IP ) || preg_match( '/^[0-9.]+$/', $host ) || preg_match( '/^0x/i', $host ) ) {
		return 'IP アドレスの URL は使えません';
	}
	if ( 'localhost' === $host || false === strpos( $host, '.' ) || preg_match( '/\.(local|localhost|internal|lan|home|corp|test|invalid|example)$/', $host ) ) {
		return '公開されていないホスト名は使えません';
	}
	if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host ) ) {
		return 'ホスト名の形式が正しくありません（日本語ドメインは punycode で書いてください）';
	}
	return omochix_core_glossary_url_secret_problem( $url );
}

/**
 * Whether a parameter name is one that carries a credential.
 *
 * @param string $name Name as written (any case, with - _ . or brackets).
 * @return bool
 */
function omochix_core_glossary_is_secret_key( $name ) {
	return '' !== omochix_core_glossary_secret_key_match( $name );
}

/**
 * Which listed credential name a parameter name is, or '' when none.
 *
 * @param string $name Name as written.
 * @return string An entry of the rules (never part of the input).
 */
function omochix_core_glossary_secret_key_match( $name ) {
	$rules = omochix_core_glossary_rules();
	$name  = (string) preg_replace( '/[^a-z0-9]+/', '', strtolower( (string) $name ) );
	if ( '' === $name ) {
		return '';
	}
	if ( in_array( $name, $rules['secret_query_keys'], true ) ) {
		return $name;
	}
	foreach ( $rules['secret_key_ends'] as $end ) {
		if ( strlen( $name ) >= strlen( $end ) && substr( $name, -strlen( $end ) ) === $end ) {
			return $end;
		}
	}
	foreach ( $rules['secret_key_parts'] as $part ) {
		if ( false !== strpos( $name, $part ) ) {
			return $part;
		}
	}
	return '';
}

/**
 * Why a URL looks like it carries a credential, or '' when it does not.
 *
 * Looks at the whole URL, as written and URL-decoded (up to three times, for
 * double encoding): a string shaped like a key or a token anywhere (path,
 * query, fragment), and a parameter whose name is a credential's, in the
 * query (also nested: a[token]=), in the path (;jsessionid=) and in the
 * fragment (#access_token=). A fragment that is only an anchor (#api-key) is
 * fine. The answer names the parameter or the kind, never the value.
 *
 * @param mixed $url URL.
 * @return string
 */
function omochix_core_glossary_url_secret_problem( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return '';
	}
	$forms = array( $url );
	$form  = $url;
	for ( $i = 0; $i < 3; $i++ ) {
		$next = rawurldecode( $form );
		if ( $next === $form ) {
			break;
		}
		$form    = $next;
		$forms[] = $form;
	}
	foreach ( $forms as $form ) {
		$found = omochix_core_glossary_secret_findings( $form );
		if ( $found ) {
			return 'URL に秘密情報らしい文字列（' . implode( '・', $found ) . '）があります';
		}
		$hash   = strpos( $form, '#' );
		$before = false === $hash ? $form : substr( $form, 0, $hash );
		$after  = false === $hash ? '' : substr( $form, $hash + 1 );
		// Before the fragment: everything after the first ? or ; is parameters.
		$pieces = preg_split( '/[?&;]/', $before );
		array_shift( $pieces );
		// In the fragment only name=value counts: a bare word is an anchor.
		foreach ( preg_split( '/[?&;#]/', $after ) as $piece ) {
			if ( false !== strpos( $piece, '=' ) ) {
				$pieces[] = $piece;
			}
		}
		foreach ( $pieces as $piece ) {
			$name = explode( '=', $piece, 2 )[0];
			foreach ( preg_split( '/[\[\]]+/', $name ) as $part ) {
				$match = omochix_core_glossary_secret_key_match( $part );
				if ( '' === $match ) {
					continue;
				}
				// The name as written only when it is nothing but the listed name (API_KEY, access-token):
				// in ?apikey%3DVALUE or ?secretVALUE the "name" holds the value, so the listed name is shown.
				$same = preg_replace( '/[^a-z0-9]+/', '', strtolower( $part ) ) === $match && 1 === preg_match( '/^[A-Za-z0-9_.-]{1,40}$/D', $part );
				return 'URL に認証情報らしい項目（' . ( $same ? $part : $match ) . '）があります';
			}
		}
	}
	return '';
}

/**
 * Take a credential out of a message before it is shown, stored or logged.
 *
 * @param string $message Message.
 * @return string
 */
function omochix_core_glossary_redact( $message ) {
	$message = (string) $message;
	if ( ! omochix_core_glossary_secret_findings( $message ) && ! omochix_core_glossary_secret_findings( rawurldecode( $message ) ) ) {
		return $message;
	}
	$at = explode( '：', $message, 2 )[0];
	return ( strlen( $at ) <= 60 && $at !== $message ? $at . '：' : '' ) . '秘密情報らしい文字列を含むため、内容は表示しません';
}

/**
 * Strings that look like credentials (API keys, tokens, private keys).
 *
 * @param string $text Text.
 * @return string[] What was found (kind names only, never the value).
 */
function omochix_core_glossary_secret_findings( $text ) {
	$patterns = array(
		// A key has a long unbroken run somewhere; "sk-hynix-begins-volume-production" (a slug in a URL) has none.
		'OpenAI 形式の API キー'      => '/(?<![A-Za-z0-9])sk-[A-Za-z0-9_-]{0,200}?[A-Za-z0-9_]{20,}/',
		'AWS アクセスキー'           => '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
		'GitHub トークン'            => '/\bgh[pousr]_[A-Za-z0-9]{30,}/',
		'GitHub トークン（新形式）'  => '/\bgithub_pat_[A-Za-z0-9_]{40,}/',
		'Slack トークン'             => '/\bxox[baprs]-[A-Za-z0-9-]{10,}/',
		'Google API キー'            => '/\bAIza[0-9A-Za-z_-]{35}\b/',
		'GitLab トークン'            => '/\bglpat-[A-Za-z0-9_-]{20,}/',
		'Hugging Face トークン'      => '/\bhf_[A-Za-z0-9]{30,}/',
		'Webhook の URL'             => '#(?:hooks\.slack\.com/services|discord(?:app)?\.com/api/webhooks)/[A-Za-z0-9/_-]{8,}#i',
		'Stripe キー'                => '/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{16,}/',
		'秘密鍵'                     => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
		'JWT'                        => '/\beyJ[A-Za-z0-9_-]{10,2000}\.eyJ[A-Za-z0-9_-]{10,4000}\.[A-Za-z0-9_-]{10}/',
	);
	$found = array();
	foreach ( $patterns as $name => $re ) {
		$hit = preg_match( $re, (string) $text );
		if ( $hit ) {
			$found[] = $name;
		} elseif ( false === $hit ) {
			$found[] = '検査できなかった文字列'; // The pattern could not run: a refusal, not "nothing found".
		}
	}
	return array_values( array_unique( $found ) );
}

/**
 * The explanation without what the block editor adds around plain HTML.
 *
 * The block editor saves <p>, <h2>, <ul> ... wrapped in comments
 * (<!-- wp:paragraph -->) and writes a few class names of its own
 * (class="wp-block-heading"). Those, and only those, are taken off here, so
 * that the rules below and the page see the same small HTML as a term written
 * by hand:
 * - a block comment of a listed block (rules: blocks), with no setting but
 *   the listed ones (heading level 2-4, ordered list, fixed table layout);
 * - a class attribute whose every name is listed for that tag (rules:
 *   block_classes), written as the editor writes it (class="...");
 * - the <figure class="wp-block-table"> around a table.
 * Everything else is left exactly as it is, and the rules then refuse it:
 * another block, another setting (style, className, anchor, bindings ...),
 * another class, any other attribute, any other comment. Nothing is rendered
 * as a block: the page prints this HTML through wp_kses.
 *
 * @param string $html Stored content.
 * @return string
 */
function omochix_core_glossary_normalize_content( $html ) {
	$rules = omochix_core_glossary_rules();
	$html  = (string) $html;
	$raw   = $html;
	if ( strlen( $html ) > $rules['content_bytes_max'] ) {
		return $html; // Not read: the rules refuse it by its size, the page prints it through wp_kses only.
	}
	$html  = preg_replace_callback(
		'/<!--\s+(\/)?wp:((?:core\/)?[a-z][a-z0-9-]*)\s+(?:(\{[^<>]*?\})\s+)?(\/)?-->/',
		static function ( $m ) use ( $rules ) {
			$name = 0 === strpos( $m[2], 'core/' ) ? substr( $m[2], 5 ) : $m[2];
			$json = (string) ( $m[3] ?? '' );
			if ( ! isset( $rules['blocks'][ $name ] ) || '' !== (string) ( $m[4] ?? '' ) || ( '' !== $m[1] && '' !== $json ) ) {
				return $m[0];
			}
			if ( '' !== $json ) {
				$attrs = json_decode( $json, true );
				if ( ! is_array( $attrs ) || ( $attrs && array_values( $attrs ) === $attrs ) ) {
					return $m[0];
				}
				foreach ( $attrs as $k => $v ) {
					$ok = in_array( $k, $rules['blocks'][ $name ], true ) && ( 'level' === $k ? in_array( $v, array( 2, 3, 4 ), true ) : is_bool( $v ) );
					if ( ! $ok ) {
						return $m[0];
					}
				}
			}
			return '';
		},
		$html
	);
	if ( ! is_string( $html ) ) {
		return $raw; // The pattern engine gave up: nothing is taken off, and the rules refuse what is left.
	}
	$figures = 0;
	$html    = preg_replace_callback(
		'#<([a-zA-Z][a-zA-Z0-9]*)\s+class="([^"<>]*)"\s*>#',
		static function ( $m ) use ( $rules, &$figures ) {
			$tag     = strtolower( $m[1] );
			$names   = preg_split( '/\s+/', trim( $m[2] ), -1, PREG_SPLIT_NO_EMPTY );
			$allowed = $rules['block_classes'][ $tag ] ?? array();
			if ( ! $names || array_diff( $names, $allowed ) ) {
				return $m[0];
			}
			if ( 'figure' === $tag ) {
				++$figures;
				return '';
			}
			return '<' . $m[1] . '>';
		},
		$html
	);
	if ( is_string( $html ) && $figures ) {
		$html = preg_replace( '#</figure\s*>#i', '', $html, $figures );
	}
	return is_string( $html ) ? $html : $raw;
}

/**
 * Problems in the explanation HTML: only a small set of tags, no scripts,
 * styles, event handlers or non-https links. What the block editor adds is
 * taken off first (omochix_core_glossary_normalize_content); the page prints
 * the same normalized HTML through wp_kses with the same small allow-list.
 *
 * @param string $html HTML.
 * @return string[]
 */
function omochix_core_glossary_html_problems( $html ) {
	$rules = omochix_core_glossary_rules();
	$out   = array();
	$html  = (string) $html;
	if ( strlen( $html ) > $rules['content_bytes_max'] ) {
		return array( sprintf( '本文のデータが大きすぎます（%d バイト、上限 %d）', strlen( $html ), $rules['content_bytes_max'] ) );
	}
	$html = omochix_core_glossary_normalize_content( $html );
	// Every check below must have run: a pattern that could not be evaluated is a refusal, not "nothing found".
	$ran = static function ( $result ) use ( &$out ) {
		if ( false === $result ) {
			$out[] = '本文を検査できませんでした（内容が複雑すぎます）';
		}
		return $result;
	};
	if ( $ran( preg_match( '/<\s*(script|style|iframe|object|embed|form|input|button|svg|math|link|meta|base|img|video|audio|source|frame|frameset|template)\b/i', $html, $m ) ) ) {
		$out[] = '使えないタグ <' . strtolower( $m[1] ) . '> があります';
	}
	if ( $ran( preg_match( '/\son[a-z]+\s*=/i', $html ) ) ) {
		$out[] = 'on〜 属性（イベントハンドラー）は使えません';
	}
	if ( $ran( preg_match( '/\s(style|class|id|srcset|formaction|xlink:href|data-[a-z0-9-]+)\s*=/i', $html, $m ) ) ) {
		$out[] = strtolower( $m[1] ) . ' 属性は使えません';
	}
	if ( $ran( preg_match( '/(?:javascript|vbscript|data)\s*:/i', $html ) ) ) {
		$out[] = 'javascript:・data: などの URL は使えません';
	}
	if ( $ran( preg_match( '/<!--\s*\/?wp:/', $html ) ) ) {
		$out[] = '使えないブロック、またはブロックの設定があります（使えるのは、段落・見出し2〜4・リスト・引用・コード・整形済みテキスト・表で、色や配置などの設定なしのものだけです）';
	} elseif ( false !== strpos( $html, '<!--' ) ) {
		$out[] = 'コメント・CDATA は使えません';
	}
	if ( false !== stripos( $html, '<![CDATA[' ) ) {
		$out[] = 'コメント・CDATA は使えません';
	}
	if ( $ran( preg_match_all( '#<\s*/?\s*([a-zA-Z][a-zA-Z0-9]*)([^>]*)>#', $html, $tags, PREG_SET_ORDER ) ) ) {
		foreach ( $tags as $t ) {
			$name = strtolower( $t[1] );
			if ( ! in_array( $name, $rules['allowed_tags'], true ) ) {
				$out[] = '使えないタグ <' . $name . '> があります';
				continue;
			}
			$attrs = trim( (string) $t[2], " \t\n/" );
			if ( '' === $attrs ) {
				continue;
			}
			if ( 'a' !== $name ) {
				$out[] = '<' . $name . '> に属性は付けられません';
				continue;
			}
			if ( ! preg_match( '/^href\s*=\s*"([^"]*)"$/D', $attrs, $h ) ) {
				$out[] = '<a> は href="…" だけにしてください';
				continue;
			}
			$href = html_entity_decode( $h[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$why  = omochix_core_glossary_url_problem( $href );
			if ( '' !== $why ) {
				// The link itself is never repeated in the message (a user name, a password or a key may be in it): its host only.
				$host  = strtolower( (string) parse_url( $href, PHP_URL_HOST ) );
				$out[] = ( preg_match( '/^[a-z0-9.-]{1,80}$/D', $host ) ? 'リンク（' . $host . '）：' : 'リンク：' ) . $why;
			}
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Plain text of the explanation, for its length.
 *
 * @param string $html HTML.
 * @return string
 */
function omochix_core_glossary_text( $html ) {
	$text = html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/\s+/u', ' ', $text );
	// null: not valid UTF-8. Give back something that is not UTF-8 either, so the length check refuses it (not '' = "empty and fine").
	return null === $text ? "\xff" : trim( $text );
}

/**
 * Check one term (the JSON form of content/glossary/terms/{slug}.json).
 *
 * @param mixed $t    Decoded term.
 * @param array $opts today (YYYY-MM-DD), categories (allowed slugs).
 * @return array{errors: string[], warnings: string[]}
 */
function omochix_core_glossary_validate_term( $t, array $opts = array() ) {
	$rules = omochix_core_glossary_rules();
	$today = isset( $opts['today'] ) ? (string) $opts['today'] : omochix_core_glossary_today();
	$cats  = isset( $opts['categories'] ) ? (array) $opts['categories'] : array();
	$err   = array();
	$warn  = array();
	if ( ! is_array( $t ) || array_values( $t ) === $t ) {
		return array(
			'errors'   => array( '用語は JSON のオブジェクトにしてください' ),
			'warnings' => array(),
		);
	}
	foreach ( array_diff( array_keys( $t ), $rules['term_keys'] ) as $k ) {
		$err[] = $k . '：未知の項目です';
	}
	if ( ( $t['schema'] ?? null ) !== $rules['schema'] ) {
		$err[] = 'schema：' . $rules['schema'] . ' にしてください';
	}
	$slug = $t['slug'] ?? null;
	$why  = omochix_core_glossary_slug_problem( $slug );
	if ( '' !== $why ) {
		$err[] = 'slug：' . $why;
	}
	$strings = array();
	foreach ( array(
		'title'   => array( 1, $rules['title_max'], true ),
		'reading' => array( 1, $rules['reading_max'], true ),
		'en'      => array( 1, $rules['en_max'], false ),
		'summary' => array( $rules['summary_min'], $rules['summary_max'], true ),
	) as $k => $spec ) {
		if ( ! array_key_exists( $k, $t ) ) {
			if ( $spec[2] ) {
				$err[] = $k . '：必須です';
			}
			continue;
		}
		$v = $t[ $k ];
		if ( ! is_string( $v ) ) {
			$err[] = $k . '：文字列にしてください';
			continue;
		}
		$strings[] = $v;
		$n         = omochix_core_glossary_len( $v );
		if ( $n < 0 ) {
			$err[] = $k . '：UTF-8 ではありません';
		} elseif ( $n < $spec[0] || $n > $spec[1] ) {
			$err[] = sprintf( '%s：%d〜%d文字にしてください（%d文字）', $k, $spec[0], $spec[1], $n );
		}
		if ( $v !== trim( $v ) || preg_match( '/[\x00-\x1f\x7f<>]/', $v ) ) {
			$err[] = $k . '：前後の空白・改行・制御文字・山かっこは使えません';
		}
	}
	if ( isset( $t['reading'] ) && is_string( $t['reading'] ) && ! preg_match( '/^[\x{3041}-\x{3096}\x{30FC}]+$/uD', $t['reading'] ) ) {
		$err[] = 'reading：ひらがなと長音（ー）だけにしてください';
	}
	if ( isset( $t['en'] ) && is_string( $t['en'] ) && ! preg_match( '/^[\x20-\x7e]+$/D', $t['en'] ) ) {
		$err[] = 'en：半角英数字と記号だけにしてください';
	}
	if ( array_key_exists( 'aliases', $t ) ) {
		$a = $t['aliases'];
		if ( ! is_array( $a ) || array_values( $a ) !== $a ) {
			$err[] = 'aliases：文字列の配列にしてください';
		} else {
			if ( count( $a ) > $rules['alias_max'] ) {
				$err[] = 'aliases：' . $rules['alias_max'] . '件までです';
			}
			$seen = array();
			foreach ( $a as $alias ) {
				$n = is_string( $alias ) ? omochix_core_glossary_len( $alias ) : -1;
				if ( $n < 1 || $n > $rules['alias_len'] || $alias !== trim( $alias ) || preg_match( '/[\x00-\x1f\x7f<>]/', $alias ) ) {
					$err[] = 'aliases：1〜' . $rules['alias_len'] . '文字の文字列にしてください';
					continue;
				}
				$key = strtolower( $alias );
				if ( isset( $seen[ $key ] ) || ( isset( $t['title'] ) && is_string( $t['title'] ) && strtolower( $t['title'] ) === $key ) ) {
					$err[] = 'aliases：「' . $alias . '」が重複しています（見出しと同じものも不可）';
				}
				$seen[ $key ] = true;
				$strings[]    = $alias;
			}
		}
	}
	if ( isset( $t['summary'] ) && is_string( $t['summary'] ) && preg_match( '/&[a-z#0-9]+;/i', $t['summary'] ) ) {
		$err[] = 'summary：HTML の文字参照は使えません（そのまま書いてください）';
	}
	$category = $t['category'] ?? null;
	if ( ! is_string( $category ) || '' !== omochix_core_glossary_slug_problem( $category ) ) {
		$err[] = 'category：カテゴリの slug を1つ指定してください';
	} elseif ( $cats && ! in_array( $category, $cats, true ) ) {
		$err[] = 'category：「' . $category . '」は categories.json にありません';
	}
	if ( array_key_exists( 'level', $t ) && ! in_array( $t['level'], $rules['levels'], true ) ) {
		$err[] = 'level：' . implode( '・', $rules['levels'] ) . ' のどれかにしてください';
	}
	$html = $t['content_html'] ?? null;
	if ( ! is_string( $html ) || '' === trim( $html ) ) {
		$err[] = 'content_html：必須です';
	} else {
		foreach ( omochix_core_glossary_html_problems( $html ) as $p ) {
			$err[] = 'content_html：' . $p;
		}
		$n = omochix_core_glossary_len( omochix_core_glossary_text( $html ) );
		if ( $n < 0 ) {
			$err[] = 'content_html：UTF-8 ではありません';
		} elseif ( $n > $rules['content_hard_max'] ) {
			$err[] = sprintf( 'content_html：本文が長すぎます（%d文字、上限 %d）', $n, $rules['content_hard_max'] );
		} elseif ( $n < $rules['content_min'] || $n > $rules['content_max'] ) {
			$warn[] = sprintf( 'content_html：本文の目安は %d〜%d文字です（%d文字）', $rules['content_min'], $rules['content_max'], $n );
		}
		$strings[] = $html;
	}
	$sources = $t['sources'] ?? null;
	$latest  = '';
	if ( ! is_array( $sources ) || array_values( $sources ) !== $sources || count( $sources ) < $rules['source_min'] || count( $sources ) > $rules['source_max'] ) {
		$err[] = sprintf( 'sources：%d〜%d件の配列にしてください', $rules['source_min'], $rules['source_max'] );
	} else {
		$primary = false;
		$urls    = array();
		foreach ( $sources as $i => $s ) {
			$at = 'sources[' . $i . ']';
			if ( ! is_array( $s ) || array_values( $s ) === $s ) {
				$err[] = $at . '：オブジェクトにしてください';
				continue;
			}
			foreach ( array_diff( array_keys( $s ), array( 'url', 'title', 'publisher', 'kind', 'checked_on' ) ) as $k ) {
				$err[] = $at . '.' . $k . '：未知の項目です';
			}
			$why = omochix_core_glossary_url_problem( $s['url'] ?? null );
			if ( '' !== $why ) {
				$err[] = $at . '.url：' . $why;
			} elseif ( isset( $urls[ $s['url'] ] ) ) {
				$err[] = $at . '.url：同じ URL が2回あります';
			} else {
				$urls[ $s['url'] ] = true;
			}
			foreach ( array( 'title' => $rules['source_title_max'], 'publisher' => $rules['publisher_max'] ) as $k => $max ) {
				$v = $s[ $k ] ?? null;
				$n = is_string( $v ) ? omochix_core_glossary_len( $v ) : -1;
				if ( $n < 1 || $n > $max || $v !== trim( $v ) || preg_match( '/[\x00-\x1f\x7f<>]/', $v ) ) {
					$err[] = sprintf( '%s.%s：1〜%d文字にしてください', $at, $k, $max );
				} else {
					$strings[] = $v;
				}
			}
			if ( ! in_array( $s['kind'] ?? null, $rules['kinds'], true ) ) {
				$err[] = $at . '.kind：' . implode( '・', $rules['kinds'] ) . ' のどれかにしてください';
			} elseif ( in_array( $s['kind'], $rules['primary_kinds'], true ) ) {
				$primary = true;
			}
			$why = omochix_core_glossary_date_problem( $s['checked_on'] ?? null, $today );
			if ( '' !== $why ) {
				$err[] = $at . '.checked_on：' . $why;
			} elseif ( strcmp( $s['checked_on'], $latest ) > 0 ) {
				$latest = $s['checked_on'];
			}
		}
		if ( ! $primary ) {
			$err[] = 'sources：一次情報（official・paper・standard・docs）を最低1件含めてください';
		}
	}
	$verified = $t['verified_on'] ?? null;
	$why      = omochix_core_glossary_date_problem( $verified, $today );
	if ( '' !== $why ) {
		$err[] = 'verified_on：' . $why;
	} elseif ( '' !== $latest && strcmp( $verified, $latest ) < 0 ) {
		$err[] = 'verified_on：出典の確認日（最新 ' . $latest . '）より前にはできません';
	}
	if ( array_key_exists( 'review_days', $t ) && ( ! is_int( $t['review_days'] ) || $t['review_days'] < $rules['review_days_min'] || $t['review_days'] > $rules['review_days_max'] ) ) {
		$err[] = sprintf( 'review_days：%d〜%d の整数にしてください', $rules['review_days_min'], $rules['review_days_max'] );
	}
	if ( ! array_key_exists( 'ai_assisted', $t ) || ! is_bool( $t['ai_assisted'] ) ) {
		$err[] = 'ai_assisted：true か false を必ず入れてください';
	}
	if ( array_key_exists( 'related', $t ) ) {
		$r = $t['related'];
		if ( ! is_array( $r ) || ( $r && array_values( $r ) === $r ) ) {
			$err[] = 'related：オブジェクトにしてください';
		} else {
			foreach ( $r as $k => $list ) {
				if ( ! isset( $rules['related'][ $k ] ) ) {
					$err[] = 'related.' . $k . '：未知の項目です';
					continue;
				}
				if ( ! is_array( $list ) || array_values( $list ) !== $list || count( $list ) > $rules['related'][ $k ] ) {
					$err[] = sprintf( 'related.%s：%d件までの配列にしてください', $k, $rules['related'][ $k ] );
					continue;
				}
				if ( count( array_unique( array_map( 'strval', $list ) ) ) !== count( $list ) ) {
					$err[] = 'related.' . $k . '：重複があります';
				}
				foreach ( $list as $v ) {
					if ( 'learn_pages' === $k ) {
						$ok = is_string( $v ) && preg_match( '#^learn(?:/[a-z0-9]+(?:-[a-z0-9]+)*){1,4}$#D', $v );
					} else {
						$ok = is_string( $v ) && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $v ) && strlen( $v ) <= 200;
					}
					if ( ! $ok ) {
						$err[] = 'related.' . $k . '：「' . ( is_scalar( $v ) ? (string) $v : gettype( $v ) ) . '」は slug（learn_pages は learn/… のパス）ではありません';
					} elseif ( 'glossary' === $k && $v === $slug ) {
						$err[] = 'related.glossary：自分自身は指定できません';
					}
				}
			}
		}
	}
	$found = omochix_core_glossary_secret_findings( implode( "\n", array_map( 'strval', $strings ) ) );
	if ( $found ) {
		$err[] = '秘密情報らしい文字列があります（' . implode( '・', $found ) . '）。削除してください';
	}
	return array(
		'errors'   => array_values( array_unique( array_map( 'omochix_core_glossary_redact', $err ) ) ),
		'warnings' => $warn,
	);
}

/**
 * Check content/glossary/categories.json.
 *
 * @param mixed $data Decoded file.
 * @return array{errors: string[], warnings: string[], slugs: string[]}
 */
function omochix_core_glossary_validate_categories( $data ) {
	$rules = omochix_core_glossary_rules();
	$err   = array();
	$slugs = array();
	if ( ! is_array( $data ) || ( $data['schema'] ?? null ) !== $rules['categories_schema'] || ! isset( $data['categories'] ) || ! is_array( $data['categories'] ) || array_values( $data['categories'] ) !== $data['categories'] ) {
		return array(
			'errors'   => array( 'categories.json：{"schema": "' . $rules['categories_schema'] . '", "categories": [...]} の形にしてください' ),
			'warnings' => array(),
			'slugs'    => array(),
		);
	}
	foreach ( array_diff( array_keys( $data ), array( 'schema', 'categories', 'status' ) ) as $k ) {
		$err[] = 'categories.json：' . $k . ' は未知の項目です';
	}
	if ( count( $data['categories'] ) !== $rules['category_count'] ) {
		$err[] = 'categories.json：カテゴリはちょうど ' . $rules['category_count'] . ' 件にしてください（' . count( $data['categories'] ) . ' 件）';
	}
	foreach ( $data['categories'] as $i => $c ) {
		$at = 'categories[' . $i . ']';
		if ( ! is_array( $c ) || array_diff( array_keys( $c ), array( 'slug', 'name', 'lead' ) ) ) {
			$err[] = $at . '：slug・name・lead だけのオブジェクトにしてください';
			continue;
		}
		$why = omochix_core_glossary_slug_problem( $c['slug'] ?? null );
		if ( '' !== $why ) {
			$err[] = $at . '.slug：' . $why;
		} elseif ( in_array( $c['slug'], $slugs, true ) ) {
			$err[] = $at . '.slug：重複しています';
		} else {
			$slugs[] = $c['slug'];
		}
		foreach ( array( 'name' => array( 1, 30 ), 'lead' => array( 20, 160 ) ) as $k => $range ) {
			$v = $c[ $k ] ?? null;
			$n = is_string( $v ) ? omochix_core_glossary_len( $v ) : -1;
			if ( $n < $range[0] || $n > $range[1] || preg_match( '/[\x00-\x1f\x7f<>]/', (string) $v ) ) {
				$err[] = sprintf( '%s.%s：%d〜%d文字にしてください', $at, $k, $range[0], $range[1] );
			}
		}
	}
	return array(
		'errors'   => $err,
		'warnings' => array(),
		'slugs'    => $slugs,
	);
}

/**
 * Check the whole set: every term, duplicates, related glossary links and
 * the spread over the categories.
 *
 * @param array $terms      slug-or-file => decoded term.
 * @param array $categories Decoded categories.json.
 * @param array $opts       today; release (true: 50 terms and every category filled are errors).
 * @return array{errors: string[], warnings: string[], counts: array<string, int>}
 */
function omochix_core_glossary_validate_collection( array $terms, $categories, array $opts = array() ) {
	$rules   = omochix_core_glossary_rules();
	$cat     = omochix_core_glossary_validate_categories( $categories );
	$err     = $cat['errors'];
	$warn    = array();
	$release = ! empty( $opts['release'] );
	$counts  = array_fill_keys( $cat['slugs'], 0 );
	$slugs   = array();
	$titles  = array();
	foreach ( $terms as $name => $t ) {
		$res = omochix_core_glossary_validate_term( $t, array( 'today' => $opts['today'] ?? omochix_core_glossary_today(), 'categories' => $cat['slugs'] ) );
		foreach ( $res['errors'] as $e ) {
			$err[] = $name . '：' . $e;
		}
		foreach ( $res['warnings'] as $w ) {
			$warn[] = $name . '：' . $w;
		}
		if ( ! is_array( $t ) ) {
			continue;
		}
		$slug = is_string( $t['slug'] ?? null ) ? $t['slug'] : '';
		if ( is_string( $name ) && preg_match( '/^(.+)\.json$/', basename( $name ), $m ) && $m[1] !== $slug ) {
			$err[] = $name . '：ファイル名と slug が違います';
		}
		if ( '' !== $slug ) {
			if ( isset( $slugs[ $slug ] ) ) {
				$err[] = $name . '：slug「' . $slug . '」が ' . $slugs[ $slug ] . ' と重複しています';
			}
			$slugs[ $slug ] = $name;
		}
		$title = is_string( $t['title'] ?? null ) ? strtolower( $t['title'] ) : '';
		if ( '' !== $title ) {
			if ( isset( $titles[ $title ] ) ) {
				$err[] = $name . '：見出し「' . $t['title'] . '」が ' . $titles[ $title ] . ' と重複しています';
			}
			$titles[ $title ] = $name;
		}
		if ( isset( $t['category'] ) && is_string( $t['category'] ) && isset( $counts[ $t['category'] ] ) ) {
			++$counts[ $t['category'] ];
		}
	}
	foreach ( $terms as $name => $t ) {
		foreach ( (array) ( is_array( $t ) ? ( $t['related']['glossary'] ?? array() ) : array() ) as $rel ) {
			if ( is_string( $rel ) && ! isset( $slugs[ $rel ] ) ) {
				$err[] = $name . '：related.glossary の「' . $rel . '」という用語がありません';
			}
		}
	}
	$n   = count( $terms );
	$msg = sprintf( '用語は %d 件です（目標 %d 件）', $n, $rules['term_target'] );
	if ( $n !== $rules['term_target'] ) {
		if ( $release ) {
			$err[] = $msg;
		} else {
			$warn[] = $msg;
		}
	}
	foreach ( $counts as $slug => $c ) {
		if ( $c < $rules['category_min'] ) {
			$m = sprintf( 'カテゴリ「%s」の用語が %d 件です（一覧を index にするには %d 件以上）', $slug, $c, $rules['category_min'] );
			if ( $release ) {
				$err[] = $m;
			} else {
				$warn[] = $m;
			}
		}
	}
	return array(
		'errors'   => array_values( array_unique( array_map( 'omochix_core_glossary_redact', $err ) ) ),
		'warnings' => $warn,
		'counts'   => $counts,
	);
}
// omochix-glossary-version: 1 (end)
