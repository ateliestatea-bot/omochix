<?php
// omochix-glossary-version: 1
/**
 * AI glossary: the editor's screens (KH-A).
 *
 * Boxes on the term editor (basics, sources, review, related content, publish
 * quality) and columns on the term list. Everything is checked with the rules
 * of glossary-validate.php and stored through the meta of glossary-meta.php
 * (its sanitize and auth callbacks still run). Nothing here publishes: the
 * publish guard (glossary-guard.php) decides that, on every path.
 *
 * Who: only users who may edit the term now (edit_post, which the glossary
 * maps to edit_others_posts at run time: editors and administrators).
 * Authors, contributors and subscribers get no box, no column data and no
 * save; a former editor keeps nothing.
 *
 * Saving: the boxes post with the classic form (also from the block editor,
 * which sends the boxes after its own REST save). A save is taken only with
 * this box's nonce for this post, for a user who may edit it, and never for an
 * autosave, a revision, a REST, Quick Edit or Bulk Edit request. Input that
 * the rules refuse never replaces a stored value that passes them; a source
 * with a URL the rules refuse is not stored; a live term never takes values
 * that would fail the publish rules. The reasons are shown to the editor on
 * the next screen (field names and rule texts only, never the input).
 *
 * Request data: $_POST is read in one place, omochix_core_glossary_admin_save(),
 * after the nonce and the capability are checked. Nothing else here reads
 * request data or the database handle.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form fields of the boxes: form key => meta key.
 *
 * @return array<string, string>
 */
function omochix_core_glossary_admin_fields() {
	return array(
		'reading'      => 'glossary_reading',
		'en'           => 'glossary_en',
		'aliases'      => 'glossary_aliases',
		'summary'      => 'glossary_summary',
		'level'        => 'glossary_level',
		'sources'      => 'glossary_sources',
		'verified_on'  => 'glossary_verified_on',
		'review_days'  => 'glossary_review_days',
		'ai_assisted'  => 'glossary_ai_assisted',
		'rel_glossary' => 'related_glossary_ids',
		'rel_tools'    => 'related_tool_ids',
		'rel_learn'    => 'related_learn_page_ids',
		'rel_practice' => 'related_practice_ids',
		'rel_industry' => 'related_industry_ids',
	);
}

/**
 * Related content: meta key => what an ID must be (post type, or taxonomy for industries).
 *
 * @return array<string, array{kind: string, type: string, label: string, limit: int}>
 */
function omochix_core_glossary_admin_related_kinds() {
	$r = omochix_core_glossary_rules()['related'];
	return array(
		'related_glossary_ids'   => array( 'kind' => 'post', 'type' => 'omx_glossary', 'label' => __( '関連用語', 'omochix-core' ), 'limit' => (int) $r['glossary'] ),
		'related_tool_ids'       => array( 'kind' => 'post', 'type' => 'ai_tool', 'label' => __( 'AI Tools', 'omochix-core' ), 'limit' => (int) $r['tools'] ),
		'related_learn_page_ids' => array( 'kind' => 'post', 'type' => 'page', 'label' => __( 'Learn（/learn/ の固定ページ）', 'omochix-core' ), 'limit' => (int) $r['learn_pages'] ),
		'related_practice_ids'   => array( 'kind' => 'post', 'type' => 'learn_practice', 'label' => __( 'Practice', 'omochix-core' ), 'limit' => (int) $r['practice'] ),
		'related_industry_ids'   => array( 'kind' => 'term', 'type' => 'omx_industry', 'label' => __( 'Industry Hub（業種）', 'omochix-core' ), 'limit' => (int) $r['industries'] ),
	);
}

/**
 * Whether the current user may use the glossary screens for this term now.
 *
 * @param mixed $post Post or ID.
 * @return bool
 */
function omochix_core_glossary_admin_can( $post ) {
	$post = $post instanceof WP_Post ? $post : ( ( is_int( $post ) && $post > 0 ) ? get_post( $post ) : null );
	return $post instanceof WP_Post && 'omx_glossary' === $post->post_type && current_user_can( 'edit_post', (int) $post->ID );
}

/**
 * Review state of a term: the due date and where today is against it.
 *
 * ok: before the due date (soon: within 30 days). review: past the due date
 * (要再確認, still indexable during the grace period). overdue: past the grace
 * period too (期限超過, not indexed). unknown: no valid verification date.
 *
 * @param array       $data  Term data (glossary-visibility.php shape).
 * @param string|null $today YYYY-MM-DD (tests).
 * @return array{due: string, state: string, days: int}
 */
function omochix_core_glossary_admin_review_state( array $data, $today = null ) {
	$today = null === $today ? omochix_core_glossary_today() : (string) $today;
	$v     = (string) ( $data['verified_on'] ?? '' );
	$ok    = 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $v, $p ) && checkdate( (int) $p[2], (int) $p[3], (int) $p[1] );
	$due   = $ok ? omochix_core_glossary_review_due( $data ) : '';
	if ( '' === $due ) {
		return array( 'due' => '', 'state' => 'unknown', 'days' => 0 );
	}
	$day   = static function ( $d ) {
		return (int) floor( (int) strtotime( $d . ' 00:00:00 UTC' ) / 86400 );
	};
	$days  = $day( $due ) - $day( $today ); // Days left (negative: past).
	$grace = defined( 'OMOCHIX_CORE_GLOSSARY_NOINDEX_GRACE_DAYS' ) ? (int) OMOCHIX_CORE_GLOSSARY_NOINDEX_GRACE_DAYS : 0;
	if ( $days >= 0 ) {
		$state = $days <= 30 ? 'soon' : 'ok';
	} else {
		$state = -$days > $grace ? 'overdue' : 'review';
	}
	return array( 'due' => $due, 'state' => $state, 'days' => $days );
}

/**
 * Label of a review state.
 *
 * @param string $state State.
 * @return string
 */
function omochix_core_glossary_admin_review_label( $state ) {
	$labels = array(
		'ok'      => __( '期限内', 'omochix-core' ),
		'soon'    => __( '期限が近い', 'omochix-core' ),
		'review'  => __( '要再確認', 'omochix-core' ),
		'overdue' => __( '期限超過', 'omochix-core' ),
		'unknown' => __( '最終確認日なし', 'omochix-core' ),
	);
	return $labels[ $state ] ?? $labels['unknown'];
}

/**
 * Errors of one form field among validator messages ("key：text").
 *
 * @param string[] $messages Messages.
 * @param string   $key      Validator key (reading, sources ...).
 * @return string[]
 */
function omochix_core_glossary_admin_errors_for( array $messages, $key ) {
	return array_values(
		array_filter(
			$messages,
			static function ( $m ) use ( $key ) {
				$at = explode( '：', (string) $m, 2 )[0];
				return $at === $key || 0 === strpos( $at, $key . '[' ) || 0 === strpos( $at, $key . '.' );
			}
		)
	);
}

/**
 * Parse the posted box values into the stored types (nothing is stored here).
 *
 * @param array $raw Unslashed form values (omx_gl[...]).
 * @return array{values: array<string, mixed>, errors: string[]}
 */
function omochix_core_glossary_admin_parse( array $raw ) {
	$rules  = omochix_core_glossary_rules();
	$values = array();
	$errors = array();
	$text   = static function ( $v ) {
		return is_string( $v ) ? trim( str_replace( array( "\r\n", "\r" ), "\n", $v ) ) : '';
	};
	foreach ( array( 'reading', 'en', 'summary' ) as $k ) {
		if ( array_key_exists( $k, $raw ) ) {
			if ( ! is_string( $raw[ $k ] ) ) {
				$errors[] = $k . '：文字列にしてください';
				continue;
			}
			$values[ 'glossary_' . $k ] = str_replace( "\n", ' ', $text( $raw[ $k ] ) );
		}
	}
	if ( array_key_exists( 'aliases', $raw ) ) {
		if ( ! is_string( $raw['aliases'] ) ) {
			$errors[] = 'aliases：1行に1つずつ書いてください';
		} else {
			$values['glossary_aliases'] = array_values( array_filter( array_map( 'trim', explode( "\n", $text( $raw['aliases'] ) ) ), 'strlen' ) );
		}
	}
	if ( array_key_exists( 'level', $raw ) ) {
		$level = is_string( $raw['level'] ) ? $raw['level'] : '';
		if ( '' !== $level && ! in_array( $level, $rules['levels'], true ) ) {
			$errors[] = 'level：' . implode( '・', $rules['levels'] ) . ' のどれかにしてください';
		} else {
			$values['glossary_level'] = $level;
		}
	}
	if ( array_key_exists( 'sources', $raw ) ) {
		$rows = is_array( $raw['sources'] ) ? $raw['sources'] : null;
		if ( null === $rows ) {
			$errors[] = 'sources：出典の形が正しくありません';
		} else {
			$list = array();
			$bad  = false;
			foreach ( array_values( $rows ) as $i => $row ) {
				$row  = is_array( $row ) ? $row : array();
				$item = array();
				foreach ( array( 'url', 'title', 'publisher', 'kind', 'checked_on' ) as $f ) {
					$item[ $f ] = $text( $row[ $f ] ?? '' );
				}
				if ( '' === implode( '', $item ) ) {
					continue; // An empty row is no source.
				}
				$n = count( $list );
				if ( $n >= $rules['source_max'] ) {
					$errors[] = sprintf( 'sources：出典は %d 件までです（%d 件目以降は保存していません）', $rules['source_max'], $rules['source_max'] + 1 );
					break;
				}
				$why = omochix_core_glossary_url_problem( $item['url'] );
				if ( '' !== $why ) {
					$errors[] = sprintf( 'sources：%d 件目の URL は保存できません（%s）', $i + 1, $why );
					$bad      = true;
					continue; // Never stored (the meta sanitize would drop it too).
				}
				$list[] = $item;
			}
			$values['glossary_sources'] = $list;
			if ( $bad ) {
				$values['__sources_bad'] = true;
			}
		}
	}
	if ( array_key_exists( 'verified_on', $raw ) ) {
		$values['glossary_verified_on'] = $text( $raw['verified_on'] );
	}
	if ( array_key_exists( 'review_days', $raw ) ) {
		$d = $text( $raw['review_days'] );
		if ( '' === $d ) {
			$values['glossary_review_days'] = (int) $rules['review_days'];
		} elseif ( ctype_digit( $d ) && (int) $d >= $rules['review_days_min'] && (int) $d <= $rules['review_days_max'] ) {
			$values['glossary_review_days'] = (int) $d;
		} else {
			$errors[] = sprintf( 'review_days：%d〜%d の整数にしてください', $rules['review_days_min'], $rules['review_days_max'] );
		}
	}
	$values['glossary_ai_assisted'] = isset( $raw['ai_assisted'] ) && '1' === $raw['ai_assisted'];
	foreach ( omochix_core_glossary_admin_fields() as $form => $meta ) {
		if ( 0 !== strpos( $form, 'rel_' ) || ! array_key_exists( $form, $raw ) ) {
			continue;
		}
		$ids = array();
		foreach ( is_array( $raw[ $form ] ) ? $raw[ $form ] : array() as $v ) {
			if ( is_string( $v ) && ctype_digit( $v ) && (int) $v > 0 ) {
				$ids[] = (int) $v;
			} elseif ( '' !== $v ) {
				$errors[] = $meta . '：ID は正の整数にしてください';
			}
		}
		$values[ $meta ] = array_values( array_unique( $ids ) );
	}
	return array(
		'values' => $values,
		'errors' => $errors,
	);
}

/**
 * Whether one related ID may be stored for this term by this user.
 *
 * A new ID must be of the right kind and published (glossary: any term but
 * this one, not in the trash; industries: an existing industry). An ID that is
 * already stored and still of the right kind is kept even if it is no longer
 * published (the public page shows only what a visitor may see).
 *
 * @param int    $id      ID.
 * @param string $meta    Meta key.
 * @param int    $self    The term being saved.
 * @param int[]  $stored  IDs stored now.
 * @return bool
 */
function omochix_core_glossary_admin_related_ok( $id, $meta, $self, array $stored ) {
	$kinds = omochix_core_glossary_admin_related_kinds();
	$k     = $kinds[ $meta ] ?? null;
	if ( null === $k || $id <= 0 ) {
		return false;
	}
	if ( 'term' === $k['kind'] ) {
		if ( ! taxonomy_exists( $k['type'] ) ) {
			return in_array( $id, $stored, true );
		}
		$t = get_term( $id, $k['type'] );
		return $t instanceof WP_Term && $k['type'] === $t->taxonomy;
	}
	$p = get_post( $id );
	if ( ! $p instanceof WP_Post || $k['type'] !== $p->post_type ) {
		return false;
	}
	if ( 'omx_glossary' === $k['type'] ) {
		return (int) $p->ID !== (int) $self && ! in_array( $p->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) && current_user_can( 'edit_post', (int) $p->ID );
	}
	if ( 'page' === $k['type'] && 0 !== strpos( (string) get_page_uri( $p ), 'learn/' ) ) {
		return false;
	}
	if ( in_array( $id, $stored, true ) ) {
		return true;
	}
	return 'publish' === $p->post_status && '' === (string) $p->post_password;
}

/**
 * Decide what a save stores. Pure: reads the stored term, returns the values
 * to write and the messages (nothing is written here).
 *
 * @param WP_Post $post   Term.
 * @param array   $parsed Result of omochix_core_glossary_admin_parse().
 * @param string|null $today YYYY-MM-DD (tests).
 * @return array{store: array<string, mixed>, messages: string[], rejected: bool}
 */
function omochix_core_glossary_admin_plan( WP_Post $post, array $parsed, $today = null ) {
	$today    = null === $today ? omochix_core_glossary_today() : (string) $today;
	$opts     = array( 'today' => $today );
	$values   = $parsed['values'];
	$messages = $parsed['errors'];
	$bad_src  = ! empty( $values['__sources_bad'] );
	unset( $values['__sources_bad'] );
	// Related IDs: only what this user may link, of the right kind.
	foreach ( omochix_core_glossary_admin_related_kinds() as $meta => $k ) {
		if ( ! array_key_exists( $meta, $values ) ) {
			continue;
		}
		$stored = array_map( 'intval', (array) get_post_meta( $post->ID, $meta, true ) );
		$keep   = array();
		foreach ( $values[ $meta ] as $id ) {
			if ( omochix_core_glossary_admin_related_ok( (int) $id, $meta, (int) $post->ID, $stored ) ) {
				$keep[] = (int) $id;
			} else {
				$messages[] = sprintf( '%s：ID %d は指定できません（種類が違う、公開されていない、または自分自身です）', $k['label'], (int) $id );
			}
		}
		if ( count( $keep ) > $k['limit'] ) {
			$messages[] = sprintf( '%s：%d件までです（%d件目以降は保存していません）', $k['label'], $k['limit'], $k['limit'] + 1 );
			$keep       = array_slice( $keep, 0, $k['limit'] );
		}
		$values[ $meta ] = $keep;
	}
	// Field by field: input the rules refuse never replaces a stored value that passes them.
	$old_data = omochix_core_glossary_term_data( $post );
	$old_errs = omochix_core_glossary_validate_term( $old_data, $opts )['errors'];
	$new_errs = omochix_core_glossary_validate_term( omochix_core_glossary_term_data( $post, $values ), $opts )['errors'];
	$keys     = array(
		'glossary_reading'     => 'reading',
		'glossary_en'          => 'en',
		'glossary_aliases'     => 'aliases',
		'glossary_summary'     => 'summary',
		'glossary_level'       => 'level',
		'glossary_sources'     => 'sources',
		'glossary_verified_on' => 'verified_on',
		'glossary_review_days' => 'review_days',
	);
	foreach ( $keys as $meta => $key ) {
		if ( ! array_key_exists( $meta, $values ) ) {
			continue;
		}
		$old     = get_post_meta( $post->ID, $meta, true );
		$changed = omochix_core_glossary_sanitize_meta( $values[ $meta ], $meta ) != $old; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- stored ints and arrays come back as stored.
		if ( ! $changed && ! ( 'glossary_sources' === $meta && $bad_src ) ) {
			continue;
		}
		$old_ok  = '' !== $old && array() !== $old && null !== $old && false !== $old && array() === omochix_core_glossary_admin_errors_for( $old_errs, $key );
		$new_bad = omochix_core_glossary_admin_errors_for( $new_errs, $key );
		if ( $old_ok && ( array() !== $new_bad || ( 'glossary_sources' === $meta && $bad_src ) ) ) {
			unset( $values[ $meta ] );
			foreach ( $new_bad as $m ) {
				$messages[] = $m;
			}
			$messages[] = $key . '：入力は保存していません（保存済みの値のままです）';
		}
	}
	// A live term never takes values that add a failing rule (it would disappear from the site). A live term
	// that fails already may still be corrected step by step: only new failures are refused.
	if ( 'publish' === $post->post_status ) {
		$final = array_values( array_diff( omochix_core_glossary_validate_term( omochix_core_glossary_term_data( $post, $values ), $opts )['errors'], $old_errs ) );
		if ( $final ) {
			foreach ( $final as $m ) {
				$messages[] = $m;
			}
			$messages[] = __( '公開中の用語は、公開の条件を満たさない値に変更できません。どの項目も保存していません。', 'omochix-core' );
			return array(
				'store'    => array(),
				'messages' => array_values( array_unique( array_map( 'omochix_core_glossary_redact', $messages ) ) ),
				'rejected' => true,
			);
		}
	}
	return array(
		'store'    => $values,
		'messages' => array_values( array_unique( array_map( 'omochix_core_glossary_redact', $messages ) ) ),
		'rejected' => false,
	);
}

/**
 * Save the boxes (save_post_omx_glossary). See the file comment for when a save is taken.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @return void
 */
function omochix_core_glossary_admin_save( $post_id, $post = null ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['omochix_glossary_admin_nonce'] ) || ! is_string( $_POST['omochix_glossary_admin_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['omochix_glossary_admin_nonce'] ) ), 'omochix_glossary_admin_save_' . $post_id ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! omochix_core_glossary_admin_can( $post ) ) {
		return;
	}
	$raw = isset( $_POST['omx_gl'] ) && is_array( $_POST['omx_gl'] ) ? wp_unslash( $_POST['omx_gl'] ) : null;
	if ( ! is_array( $raw ) ) {
		return;
	}
	$plan = omochix_core_glossary_admin_plan( $post, omochix_core_glossary_admin_parse( $raw ) );
	foreach ( $plan['store'] as $meta => $value ) {
		if ( array_key_exists( $meta, omochix_core_glossary_meta_fields() ) ) {
			update_post_meta( $post_id, $meta, $value ); // The registered sanitize callback still runs.
		}
	}
	omochix_core_glossary_admin_messages( $post_id, $plan['messages'] );
}
add_action( 'save_post_omx_glossary', 'omochix_core_glossary_admin_save', 10, 2 );

/**
 * Messages of the last save, for this user and this term (kept 10 minutes, shown once).
 *
 * @param int           $post_id  Post ID.
 * @param string[]|null $set      Messages to keep; null reads and forgets them.
 * @return string[]
 */
function omochix_core_glossary_admin_messages( $post_id, $set = null ) {
	$key = 'omochix_core_glossary_admin_' . (int) $post_id . '_' . (int) get_current_user_id();
	if ( is_array( $set ) ) {
		if ( $set ) {
			set_transient( $key, array_slice( array_map( 'strval', $set ), 0, 30 ), 10 * MINUTE_IN_SECONDS );
		} else {
			delete_transient( $key );
		}
		return $set;
	}
	$got = get_transient( $key );
	delete_transient( $key );
	return is_array( $got ) ? array_map( 'strval', $got ) : array();
}

/**
 * Add the boxes (only for a user who may edit the term now).
 *
 * @param WP_Post $post Post.
 * @return void
 */
function omochix_core_glossary_admin_boxes( $post ) {
	if ( ! omochix_core_glossary_admin_can( $post ) ) {
		return;
	}
	add_meta_box( 'omochix-glossary-basics', __( '用語集：基本情報', 'omochix-core' ), 'omochix_core_glossary_admin_box_basics', 'omx_glossary', 'normal', 'high' );
	add_meta_box( 'omochix-glossary-sources', __( '用語集：出典（1〜8件、一次情報を1件以上）', 'omochix-core' ), 'omochix_core_glossary_admin_box_sources', 'omx_glossary', 'normal', 'default' );
	add_meta_box( 'omochix-glossary-review', __( '用語集：更新管理', 'omochix-core' ), 'omochix_core_glossary_admin_box_review', 'omx_glossary', 'normal', 'default' );
	add_meta_box( 'omochix-glossary-related', __( '用語集：関連コンテンツ', 'omochix-core' ), 'omochix_core_glossary_admin_box_related', 'omx_glossary', 'normal', 'default' );
	add_meta_box( 'omochix-glossary-quality', __( '用語集：公開品質', 'omochix-core' ), 'omochix_core_glossary_admin_box_quality', 'omx_glossary', 'side', 'high' );
}
add_action( 'add_meta_boxes_omx_glossary', 'omochix_core_glossary_admin_boxes', 10, 1 );

/**
 * Print a list of messages (escaped).
 *
 * @param string[] $messages Messages.
 * @param string   $class    notice-error or notice-warning.
 * @return void
 */
function omochix_core_glossary_admin_list( array $messages, $class = 'notice-error' ) {
	if ( ! $messages ) {
		return;
	}
	echo '<div class="notice inline ' . esc_attr( $class ) . '"><ul>';
	foreach ( $messages as $m ) {
		echo '<li>' . esc_html( (string) $m ) . '</li>';
	}
	echo '</ul></div>';
}

/**
 * Print one text input row.
 *
 * @param string $name  Form key.
 * @param string $label Label.
 * @param string $value Value.
 * @param string $help  Help text.
 * @param string $type  Input type.
 * @return void
 */
function omochix_core_glossary_admin_input( $name, $label, $value, $help = '', $type = 'text' ) {
	$id = 'omx-gl-' . $name;
	echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label><br />';
	echo '<input type="' . esc_attr( $type ) . '" class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( 'omx_gl[' . $name . ']' ) . '" value="' . esc_attr( $value ) . '" />';
	if ( '' !== $help ) {
		echo '<span class="description">' . esc_html( $help ) . '</span>';
	}
	echo '</p>';
}

/**
 * Basics box (also prints the nonce and the messages of the last save).
 *
 * @param WP_Post $post Post.
 * @return void
 */
function omochix_core_glossary_admin_box_basics( $post ) {
	if ( ! omochix_core_glossary_admin_can( $post ) ) {
		return;
	}
	$rules = omochix_core_glossary_rules();
	wp_nonce_field( 'omochix_glossary_admin_save_' . (int) $post->ID, 'omochix_glossary_admin_nonce' );
	omochix_core_glossary_admin_list( omochix_core_glossary_admin_messages( (int) $post->ID ) );
	$data = omochix_core_glossary_term_data( $post );
	$errs = omochix_core_glossary_validate_term( $data )['errors'];
	omochix_core_glossary_admin_input( 'reading', __( '読み仮名（ひらがな）', 'omochix-core' ), (string) $data['reading'], sprintf( __( 'ひらがなと長音（ー）で、%d文字まで', 'omochix-core' ), $rules['reading_max'] ) );
	omochix_core_glossary_admin_list( omochix_core_glossary_admin_errors_for( $errs, 'reading' ) );
	omochix_core_glossary_admin_input( 'en', __( '英語表記（任意）', 'omochix-core' ), (string) ( $data['en'] ?? '' ), sprintf( __( '半角英数字と記号で、%d文字まで', 'omochix-core' ), $rules['en_max'] ) );
	omochix_core_glossary_admin_list( omochix_core_glossary_admin_errors_for( $errs, 'en' ) );
	echo '<p><label for="omx-gl-aliases"><strong>' . esc_html__( '別名（任意、1行に1つ）', 'omochix-core' ) . '</strong></label><br />';
	echo '<textarea class="widefat" rows="3" id="omx-gl-aliases" name="omx_gl[aliases]">' . esc_textarea( implode( "\n", (array) ( $data['aliases'] ?? array() ) ) ) . '</textarea>';
	echo '<span class="description">' . esc_html( sprintf( __( '%d件まで、各%d文字まで。見出しと同じものは使えません', 'omochix-core' ), $rules['alias_max'], $rules['alias_len'] ) ) . '</span></p>';
	omochix_core_glossary_admin_list( omochix_core_glossary_admin_errors_for( $errs, 'aliases' ) );
	$n = omochix_core_glossary_len( (string) $data['summary'] );
	echo '<p><label for="omx-gl-summary"><strong>' . esc_html__( '要約（1文の定義）', 'omochix-core' ) . '</strong></label><br />';
	echo '<textarea class="widefat" rows="2" maxlength="' . esc_attr( (string) $rules['summary_max'] ) . '" id="omx-gl-summary" name="omx_gl[summary]">' . esc_textarea( (string) $data['summary'] ) . '</textarea>';
	echo '<span class="description">' . esc_html( sprintf( __( '%1$d〜%2$d文字（いまは%3$d文字）。検索結果の説明文にも使います', 'omochix-core' ), $rules['summary_min'], $rules['summary_max'], $n ) ) . '</span></p>';
	omochix_core_glossary_admin_list( omochix_core_glossary_admin_errors_for( $errs, 'summary' ) );
	$level  = (string) ( $data['level'] ?? '' );
	$labels = array(
		''             => __( '（指定なし）', 'omochix-core' ),
		'beginner'     => __( '初級', 'omochix-core' ),
		'intermediate' => __( '中級', 'omochix-core' ),
		'advanced'     => __( '上級', 'omochix-core' ),
	);
	echo '<p><label for="omx-gl-level"><strong>' . esc_html__( '難易度', 'omochix-core' ) . '</strong></label><br /><select id="omx-gl-level" name="omx_gl[level]">';
	foreach ( $labels as $v => $l ) {
		echo '<option value="' . esc_attr( $v ) . '"' . selected( $level, $v, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></p>';
	$cat = (string) $data['category'];
	echo '<p><strong>' . esc_html__( 'カテゴリ', 'omochix-core' ) . '</strong><br />' . esc_html( '' === $cat ? __( '未設定', 'omochix-core' ) : $cat ) . '<br /><span class="description">' . esc_html__( 'カテゴリは、画面右の「カテゴリ」で、ちょうど1つ選んでください。', 'omochix-core' ) . '</span></p>';
	omochix_core_glossary_admin_list( omochix_core_glossary_admin_errors_for( $errs, 'category' ) );
}

/**
 * Sources box: eight fixed rows (no script needed); an empty row is ignored.
 *
 * @param WP_Post $post Post.
 * @return void
 */
function omochix_core_glossary_admin_box_sources( $post ) {
	if ( ! omochix_core_glossary_admin_can( $post ) ) {
		return;
	}
	$rules   = omochix_core_glossary_rules();
	$data    = omochix_core_glossary_term_data( $post );
	$errs    = omochix_core_glossary_validate_term( $data )['errors'];
	$sources = array_values( (array) $data['sources'] );
	$kinds   = array(
		'official' => __( '公式（一次情報）', 'omochix-core' ),
		'paper'    => __( '論文（一次情報）', 'omochix-core' ),
		'standard' => __( '規格（一次情報）', 'omochix-core' ),
		'docs'     => __( 'ドキュメント（一次情報）', 'omochix-core' ),
		'media'    => __( '報道・解説', 'omochix-core' ),
	);
	echo '<p class="description">' . esc_html__( 'URL は https で、公開されているページだけです。IP アドレス・社内のホスト・ログイン情報・トークンを含む URL は保存できません。空の行は無視します。', 'omochix-core' ) . '</p>';
	omochix_core_glossary_admin_list( array_values( array_filter( $errs, static function ( $m ) {
		return 0 === strpos( (string) $m, 'sources：' );
	} ) ) );
	echo '<table class="widefat striped"><thead><tr><th>#</th><th>' . esc_html__( 'タイトル', 'omochix-core' ) . '</th><th>' . esc_html__( '発行元', 'omochix-core' ) . '</th><th>URL</th><th>' . esc_html__( '種類', 'omochix-core' ) . '</th><th>' . esc_html__( '確認日', 'omochix-core' ) . '</th></tr></thead><tbody>';
	for ( $i = 0; $i < $rules['source_max']; $i++ ) {
		$s    = is_array( $sources[ $i ] ?? null ) ? $sources[ $i ] : array();
		$base = 'omx_gl[sources][' . $i . ']';
		echo '<tr><td>' . esc_html( (string) ( $i + 1 ) ) . '</td>';
		echo '<td><input type="text" class="widefat" name="' . esc_attr( $base . '[title]' ) . '" value="' . esc_attr( (string) ( $s['title'] ?? '' ) ) . '" maxlength="' . esc_attr( (string) $rules['source_title_max'] ) . '" /></td>';
		echo '<td><input type="text" class="widefat" name="' . esc_attr( $base . '[publisher]' ) . '" value="' . esc_attr( (string) ( $s['publisher'] ?? '' ) ) . '" maxlength="' . esc_attr( (string) $rules['publisher_max'] ) . '" /></td>';
		echo '<td><input type="url" class="widefat" name="' . esc_attr( $base . '[url]' ) . '" value="' . esc_attr( (string) ( $s['url'] ?? '' ) ) . '" maxlength="' . esc_attr( (string) $rules['url_max'] ) . '" placeholder="https://" /></td>';
		echo '<td><select name="' . esc_attr( $base . '[kind]' ) . '"><option value="">—</option>';
		foreach ( $kinds as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( (string) ( $s['kind'] ?? '' ), $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="date" name="' . esc_attr( $base . '[checked_on]' ) . '" value="' . esc_attr( (string) ( $s['checked_on'] ?? '' ) ) . '" /></td></tr>';
		$row = omochix_core_glossary_admin_errors_for( $errs, 'sources[' . $i . ']' );
		if ( $row ) {
			echo '<tr><td></td><td colspan="5">';
			omochix_core_glossary_admin_list( $row );
			echo '</td></tr>';
		}
	}
	echo '</tbody></table>';
}

/**
 * Review box: verification date, review interval, AI use and the review state.
 *
 * @param WP_Post $post Post.
 * @return void
 */
function omochix_core_glossary_admin_box_review( $post ) {
	if ( ! omochix_core_glossary_admin_can( $post ) ) {
		return;
	}
	$rules = omochix_core_glossary_rules();
	$data  = omochix_core_glossary_term_data( $post );
	$errs  = omochix_core_glossary_validate_term( $data )['errors'];
	omochix_core_glossary_admin_input( 'verified_on', __( '最終確認日', 'omochix-core' ), (string) $data['verified_on'], __( '内容をすべて確かめた日。出典の確認日より前にはできません', 'omochix-core' ), 'date' );
	omochix_core_glossary_admin_list( omochix_core_glossary_admin_errors_for( $errs, 'verified_on' ) );
	echo '<p><label for="omx-gl-review_days"><strong>' . esc_html__( '再確認までの日数', 'omochix-core' ) . '</strong></label><br />';
	echo '<input type="number" id="omx-gl-review_days" name="omx_gl[review_days]" min="' . esc_attr( (string) $rules['review_days_min'] ) . '" max="' . esc_attr( (string) $rules['review_days_max'] ) . '" step="1" value="' . esc_attr( (string) $data['review_days'] ) . '" />';
	echo ' <span class="description">' . esc_html( sprintf( __( '%1$d〜%2$d日（既定 %3$d日）', 'omochix-core' ), $rules['review_days_min'], $rules['review_days_max'], $rules['review_days'] ) ) . '</span></p>';
	omochix_core_glossary_admin_list( omochix_core_glossary_admin_errors_for( $errs, 'review_days' ) );
	echo '<p><label><input type="checkbox" name="omx_gl[ai_assisted]" value="1"' . checked( (bool) $data['ai_assisted'], true, false ) . ' /> ' . esc_html__( 'AI を使って下書きした（人が内容を確認済み）', 'omochix-core' ) . '</label></p>';
	$st = omochix_core_glossary_admin_review_state( $data );
	echo '<p><strong>' . esc_html__( '再確認の期限', 'omochix-core' ) . '：</strong>' . esc_html( '' === $st['due'] ? '—' : $st['due'] ) . ' <span class="omx-gl-state omx-gl-state-' . esc_attr( $st['state'] ) . '">' . esc_html( omochix_core_glossary_admin_review_label( $st['state'] ) ) . '</span></p>';
	if ( 'overdue' === $st['state'] ) {
		echo '<p class="description">' . esc_html__( '期限を過ぎて猶予期間も終わったため、検索エンジンのインデックスから外れます。内容を確かめ、最終確認日を更新してください。', 'omochix-core' ) . '</p>';
	} elseif ( 'review' === $st['state'] ) {
		echo '<p class="description">' . esc_html__( '再確認の期限を過ぎています。猶予期間のうちに内容を確かめてください。', 'omochix-core' ) . '</p>';
	}
}

/**
 * Candidates for one related kind that the current user may link (at most 200).
 *
 * @param string $meta Meta key.
 * @param int    $self The term being edited.
 * @return array<int, string> ID => label.
 */
function omochix_core_glossary_admin_candidates( $meta, $self ) {
	$k   = omochix_core_glossary_admin_related_kinds()[ $meta ] ?? null;
	$out = array();
	if ( null === $k ) {
		return $out;
	}
	if ( 'term' === $k['kind'] ) {
		if ( ! taxonomy_exists( $k['type'] ) ) {
			return $out;
		}
		$terms = get_terms( array( 'taxonomy' => $k['type'], 'hide_empty' => false, 'number' => 200 ) );
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			if ( $t instanceof WP_Term ) {
				$out[ (int) $t->term_id ] = (string) $t->name;
			}
		}
		return $out;
	}
	$args  = array(
		'post_type'        => $k['type'],
		'post_status'      => 'omx_glossary' === $k['type'] ? array( 'publish', 'draft', 'pending', 'future', 'private' ) : 'publish',
		'numberposts'      => 200,
		'orderby'          => 'title',
		'order'            => 'ASC',
		'exclude'          => array( (int) $self ),
		'suppress_filters' => false,
	);
	foreach ( get_posts( $args ) as $p ) {
		if ( $p instanceof WP_Post && omochix_core_glossary_admin_related_ok( (int) $p->ID, $meta, (int) $self, array() ) ) {
			$out[ (int) $p->ID ] = (string) get_the_title( $p ) . ( 'publish' !== $p->post_status ? '（' . $p->post_status . '）' : '' );
		}
	}
	return $out;
}

/**
 * Related content box: one multiple select per kind.
 *
 * @param WP_Post $post Post.
 * @return void
 */
function omochix_core_glossary_admin_box_related( $post ) {
	if ( ! omochix_core_glossary_admin_can( $post ) ) {
		return;
	}
	echo '<p class="description">' . esc_html__( 'Ctrl（Mac は ⌘）を押しながら選ぶと、複数を選べます。公開ページには、訪問者が見られるものだけが出ます。', 'omochix-core' ) . '</p>';
	foreach ( omochix_core_glossary_admin_related_kinds() as $meta => $k ) {
		$form   = array_search( $meta, omochix_core_glossary_admin_fields(), true );
		$stored = array_map( 'intval', (array) get_post_meta( $post->ID, $meta, true ) );
		$cands  = omochix_core_glossary_admin_candidates( $meta, (int) $post->ID );
		foreach ( $stored as $id ) {
			if ( $id > 0 && ! isset( $cands[ $id ] ) ) {
				$cands[ $id ] = sprintf( __( 'ID %d（いまは表示されません）', 'omochix-core' ), $id );
			}
		}
		$id_attr = 'omx-gl-' . $form;
		echo '<p><label for="' . esc_attr( $id_attr ) . '"><strong>' . esc_html( $k['label'] ) . '</strong> <span class="description">' . esc_html( sprintf( __( '%d件まで', 'omochix-core' ), $k['limit'] ) ) . '</span></label><br />';
		if ( ! $cands ) {
			echo '<span class="description">' . esc_html__( '選べるものがありません。', 'omochix-core' ) . '</span>';
		} else {
			// An empty hidden value lets a save clear the list.
			echo '<input type="hidden" name="' . esc_attr( 'omx_gl[' . $form . '][]' ) . '" value="" />';
			echo '<select multiple size="5" class="widefat" id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( 'omx_gl[' . $form . '][]' ) . '">';
			foreach ( $cands as $id => $label ) {
				echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( in_array( (int) $id, $stored, true ), true, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}
		echo '</p>';
	}
}

/**
 * Quality summary of a term: errors (publishing blocked), warnings, index state, SEO notes.
 *
 * @param WP_Post     $post  Term.
 * @param string|null $today YYYY-MM-DD (tests).
 * @return array{errors: string[], warnings: string[], index: string, seo: string[], review: array}
 */
function omochix_core_glossary_admin_quality( WP_Post $post, $today = null ) {
	$today = null === $today ? omochix_core_glossary_today() : (string) $today;
	$data  = omochix_core_glossary_term_data( $post );
	$check = omochix_core_glossary_validate_term( $data, array( 'today' => $today ) );
	$rev   = omochix_core_glossary_admin_review_state( $data, $today );
	if ( $check['errors'] ) {
		$index = __( 'インデックスされません：公開の条件を満たしていません', 'omochix-core' );
	} elseif ( ! omochix_core_glossary_public() ) {
		$index = __( 'インデックスされません：用語集の公開スイッチが閉じています', 'omochix-core' );
	} elseif ( 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
		$index = __( 'インデックスされません：公開されていません（パスワード付きを含む）', 'omochix-core' );
	} elseif ( 'overdue' === $rev['state'] ) {
		$index = __( 'インデックスされません：再確認の期限を過ぎています', 'omochix-core' );
	} else {
		$index = __( 'インデックスできます', 'omochix-core' );
	}
	$seo = array();
	$n   = omochix_core_glossary_len( (string) $data['summary'] );
	if ( $n > 0 && $n < 60 ) {
		$seo[] = __( '要約が短めです。検索結果の説明文として、60文字以上あると内容が伝わりやすくなります。', 'omochix-core' );
	}
	if ( '' === (string) ( $data['en'] ?? '' ) ) {
		$seo[] = __( '英語表記がありません。英語で検索する人に見つけてもらいにくくなります。', 'omochix-core' );
	}
	if ( array() === (array) ( $data['aliases'] ?? array() ) ) {
		$seo[] = __( '別名がありません。略語や言い換えがあれば足してください。', 'omochix-core' );
	}
	$related = 0;
	foreach ( array_keys( omochix_core_glossary_admin_related_kinds() ) as $meta ) {
		$related += count( (array) get_post_meta( $post->ID, $meta, true ) );
	}
	if ( 0 === $related ) {
		$seo[] = __( '関連コンテンツがありません。内部リンクがあると、読者も検索エンジンも次のページへ進みやすくなります。', 'omochix-core' );
	}
	return array(
		'errors'   => $check['errors'],
		'warnings' => $check['warnings'],
		'index'    => $index,
		'seo'      => $seo,
		'review'   => $rev,
	);
}

/**
 * Publish quality box (side).
 *
 * @param WP_Post $post Post.
 * @return void
 */
function omochix_core_glossary_admin_box_quality( $post ) {
	if ( ! omochix_core_glossary_admin_can( $post ) ) {
		return;
	}
	$q = omochix_core_glossary_admin_quality( $post );
	if ( $q['errors'] ) {
		echo '<p><strong>' . esc_html( sprintf( __( '公開できません（%d件）', 'omochix-core' ), count( $q['errors'] ) ) ) . '</strong></p>';
		omochix_core_glossary_admin_list( $q['errors'] );
	} else {
		echo '<p><strong>' . esc_html__( '公開の条件を満たしています', 'omochix-core' ) . '</strong></p>';
	}
	omochix_core_glossary_admin_list( $q['warnings'], 'notice-warning' );
	echo '<p>' . esc_html( $q['index'] ) . '</p>';
	echo '<p>' . esc_html( __( '再確認', 'omochix-core' ) . '：' . omochix_core_glossary_admin_review_label( $q['review']['state'] ) ) . '</p>';
	if ( $q['seo'] ) {
		echo '<p><strong>' . esc_html__( 'SEO の注意', 'omochix-core' ) . '</strong></p>';
		omochix_core_glossary_admin_list( $q['seo'], 'notice-warning' );
	}
	echo '<p class="description">' . esc_html__( 'ブロックエディターでは、この欄を保存したあと、画面を読み込み直すと結果が新しくなります。公開は、保存してから行ってください。', 'omochix-core' ) . '</p>';
}

/**
 * List columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function omochix_core_glossary_admin_columns( $columns ) {
	if ( ! is_array( $columns ) ) {
		return $columns;
	}
	$out = array();
	foreach ( $columns as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k ) {
			$out['omx_gl_category'] = __( 'カテゴリ', 'omochix-core' );
			$out['omx_gl_sources']  = __( '出典数', 'omochix-core' );
			$out['omx_gl_verified'] = __( '最終確認日', 'omochix-core' );
			$out['omx_gl_due']      = __( '更新期限', 'omochix-core' );
			$out['omx_gl_quality']  = __( '公開品質', 'omochix-core' );
		}
	}
	return $out;
}
add_filter( 'manage_omx_glossary_posts_columns', 'omochix_core_glossary_admin_columns', 10, 1 );

/**
 * One list cell (escaped; nothing for a user who may not edit the term).
 *
 * @param string $column  Column.
 * @param int    $post_id Post ID.
 * @return void
 */
function omochix_core_glossary_admin_cell( $column, $post_id ) {
	if ( 0 !== strpos( (string) $column, 'omx_gl_' ) ) {
		return;
	}
	$post = get_post( (int) $post_id );
	if ( ! omochix_core_glossary_admin_can( $post ) ) {
		return;
	}
	$data = omochix_core_glossary_term_data( $post );
	switch ( $column ) {
		case 'omx_gl_category':
			echo esc_html( '' === (string) $data['category'] ? '—' : (string) $data['category'] );
			break;
		case 'omx_gl_sources':
			echo esc_html( (string) count( (array) $data['sources'] ) );
			break;
		case 'omx_gl_verified':
			echo esc_html( '' === (string) $data['verified_on'] ? '—' : (string) $data['verified_on'] );
			break;
		case 'omx_gl_due':
			$st = omochix_core_glossary_admin_review_state( $data );
			echo esc_html( ( '' === $st['due'] ? '—' : $st['due'] ) . ' ' . omochix_core_glossary_admin_review_label( $st['state'] ) );
			break;
		case 'omx_gl_quality':
			$errors = omochix_core_glossary_validate_term( $data )['errors'];
			echo esc_html( $errors ? sprintf( __( '公開できません（%d件）', 'omochix-core' ), count( $errors ) ) : __( 'OK', 'omochix-core' ) );
			break;
	}
}
add_action( 'manage_omx_glossary_posts_custom_column', 'omochix_core_glossary_admin_cell', 10, 2 );
// omochix-glossary-version: 1 (end)
