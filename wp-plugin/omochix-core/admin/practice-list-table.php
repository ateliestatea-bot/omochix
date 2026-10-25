<?php
/**
 * Admin list for Learn Practice (Phase 6A-S2): columns, sorting, filters
 * and a small quick edit, so a few hundred items can be triaged by field,
 * difficulty, verification date and missing required fields.
 *
 * Follows admin/list-table.php (ai_tool). Nothing here changes data except
 * the quick / bulk edit save, which goes through the schema sanitizers.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Query-string names of the list filters. */
const OMOCHIX_CORE_PRACTICE_LIST_FILTERS = array(
	'field'  => 'practice_field_filter',
	'format' => 'practice_format_filter',
	'level'  => 'practice_level_filter',
	'flag'   => 'practice_flag_filter',
);

/** Difficulty order for sorting (beginner first). */
function omochix_core_practice_level_rank( $level ) {
	$ranks = array( 'beginner' => 1, 'intermediate' => 2, 'advanced' => 3 );
	return $ranks[ (string) $level ] ?? 9;
}

/** The date before which a verification counts as stale (YYYY-MM-DD). */
function omochix_core_practice_stale_before( $now = 0 ) {
	$now = $now ? (int) $now : (int) current_time( 'timestamp' );
	return gmdate( 'Y-m-d', $now - omochix_core_get_practice_stale_days() * DAY_IN_SECONDS );
}

/**
 * Columns: title, 分野, 形式, 難易度, 所要時間, 検証日, 必須, slug, date.
 * The taxonomy columns WordPress may add are folded into the field / format ones.
 *
 * @param array<string, string> $columns Default columns.
 * @return array<string, string>
 */
function omochix_core_practice_list_columns( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		if ( 0 === strpos( (string) $key, 'taxonomy-learn_' ) ) {
			continue;
		}
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['practice_field']    = __( '分野', 'omochix-core' );
			$out['practice_format']   = __( '形式', 'omochix-core' );
			$out['practice_level']    = __( '難易度', 'omochix-core' );
			$out['practice_minutes']  = __( '所要', 'omochix-core' );
			$out['practice_verified'] = __( '検証日', 'omochix-core' );
			$out['practice_missing']  = __( '必須', 'omochix-core' );
			$out['practice_slug']     = __( 'slug', 'omochix-core' );
		}
	}
	return $out;
}
add_filter( 'manage_' . OMOCHIX_CORE_PRACTICE_POST_TYPE . '_posts_columns', 'omochix_core_practice_list_columns' );

/** Link to the list filtered by one term. */
function omochix_core_practice_list_term_link( $filter_key, $slug ) {
	return add_query_arg( array( 'post_type' => OMOCHIX_CORE_PRACTICE_POST_TYPE, OMOCHIX_CORE_PRACTICE_LIST_FILTERS[ $filter_key ] => $slug ), admin_url( 'edit.php' ) );
}

/** Term names of a post as filter links, or "—". */
function omochix_core_practice_list_term_cell( $post_id, $taxonomy, $filter_key ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! is_array( $terms ) || ! $terms ) {
		return '<span class="omochix-muted">—</span>';
	}
	$links = array();
	foreach ( $terms as $term ) {
		$links[] = sprintf( '<a href="%s">%s</a>', esc_url( omochix_core_practice_list_term_link( $filter_key, $term->slug ) ), esc_html( $term->name ) );
	}
	return implode( ', ', $links );
}

/**
 * Cell contents.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function omochix_core_practice_list_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'practice_field':
			echo omochix_core_practice_list_term_cell( $post_id, 'learn_field', 'field' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			break;
		case 'practice_format':
			echo omochix_core_practice_list_term_cell( $post_id, 'learn_format', 'format' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			break;
		case 'practice_level':
			$level    = (string) get_post_meta( $post_id, 'practice_difficulty', true );
			$minutes  = absint( get_post_meta( $post_id, 'practice_minutes', true ) );
			$verified = (string) get_post_meta( $post_id, 'practice_verified_at', true );
			printf(
				'<span class="omochix-practice-level omochix-practice-level--%1$s">%2$s</span><span class="omochix-practice-quick-data" hidden data-post-id="%3$d" data-level="%1$s" data-minutes="%4$d" data-verified="%5$s"></span>',
				esc_attr( '' === $level ? 'beginner' : $level ),
				esc_html( omochix_core_get_practice_difficulty_label( $level ) ),
				absint( $post_id ),
				$minutes,
				esc_attr( $verified )
			);
			break;
		case 'practice_minutes':
			$minutes = absint( get_post_meta( $post_id, 'practice_minutes', true ) );
			echo $minutes ? esc_html( sprintf( __( '約%d分', 'omochix-core' ), $minutes ) ) : '<span class="omochix-muted">—</span>';
			break;
		case 'practice_verified':
			$verified = (string) get_post_meta( $post_id, 'practice_verified_at', true );
			if ( '' === $verified ) {
				echo '<span class="omochix-badge omochix-badge--warn">' . esc_html__( '未検証', 'omochix-core' ) . '</span>';
			} elseif ( omochix_core_practice_is_stale( $verified ) ) {
				echo esc_html( $verified ) . ' <span class="omochix-badge omochix-badge--warn">' . esc_html__( '要再検証', 'omochix-core' ) . '</span>';
			} else {
				echo esc_html( $verified );
			}
			break;
		case 'practice_missing':
			$missing = omochix_core_get_practice_missing_meta( $post_id );
			if ( $missing ) {
				printf( '<span class="omochix-badge omochix-badge--error" title="%1$s">%2$s</span>', esc_attr( implode( ', ', $missing ) ), esc_html( sprintf( __( '欠落 %d', 'omochix-core' ), count( $missing ) ) ) );
			} else {
				echo '<span class="omochix-badge omochix-badge--ok">' . esc_html__( '揃っている', 'omochix-core' ) . '</span>';
			}
			break;
		case 'practice_slug':
			echo '<code>' . esc_html( (string) get_post_field( 'post_name', $post_id ) ) . '</code>';
			break;
	}
}
add_action( 'manage_' . OMOCHIX_CORE_PRACTICE_POST_TYPE . '_posts_custom_column', 'omochix_core_practice_list_column_content', 10, 2 );

/** Sortable: difficulty, minutes, verification date (all meta, posts without the meta sort last). */
function omochix_core_practice_list_sortable_columns( $columns ) {
	$columns['practice_level']    = array( 'practice_level', false );
	$columns['practice_minutes']  = array( 'practice_minutes', false );
	$columns['practice_verified'] = array( 'practice_verified', true );
	return $columns;
}
add_filter( 'manage_edit-' . OMOCHIX_CORE_PRACTICE_POST_TYPE . '_sortable_columns', 'omochix_core_practice_list_sortable_columns' );

/**
 * Current list filters, validated against the vocabularies.
 *
 * @param array<string, mixed> $source Usually $_GET.
 * @return array{field: string, format: string, level: string, flag: string}
 */
function omochix_core_practice_list_current_filters( array $source ) {
	$names = OMOCHIX_CORE_PRACTICE_LIST_FILTERS;
	$field  = isset( $source[ $names['field'] ] ) ? sanitize_title( wp_unslash( $source[ $names['field'] ] ) ) : '';
	$format = isset( $source[ $names['format'] ] ) ? sanitize_title( wp_unslash( $source[ $names['format'] ] ) ) : '';
	$level  = isset( $source[ $names['level'] ] ) ? sanitize_key( wp_unslash( $source[ $names['level'] ] ) ) : '';
	$flag   = isset( $source[ $names['flag'] ] ) ? sanitize_key( wp_unslash( $source[ $names['flag'] ] ) ) : '';
	return array(
		'field'  => '' !== $field && term_exists( $field, 'learn_field' ) ? $field : '',
		'format' => '' !== $format && term_exists( $format, 'learn_format' ) ? $format : '',
		'level'  => in_array( $level, array( 'beginner', 'intermediate', 'advanced' ), true ) ? $level : '',
		'flag'   => in_array( $flag, array( 'stale', 'missing' ), true ) ? $flag : '',
	);
}

/** Practice post IDs (any status) with at least one required field missing. */
function omochix_core_practice_missing_ids() {
	$ids = get_posts(
		array(
			'post_type'      => OMOCHIX_CORE_PRACTICE_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$missing = array();
	foreach ( (array) $ids as $id ) {
		if ( omochix_core_get_practice_missing_meta( (int) $id ) ) {
			$missing[] = (int) $id;
		}
	}
	return $missing;
}

/**
 * Filter dropdowns above the list.
 *
 * @param string $post_type Current post type.
 * @return void
 */
function omochix_core_practice_list_filters( $post_type ) {
	if ( OMOCHIX_CORE_PRACTICE_POST_TYPE !== $post_type ) {
		return;
	}
	$names   = OMOCHIX_CORE_PRACTICE_LIST_FILTERS;
	$current = omochix_core_practice_list_current_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters.
	foreach ( array( 'field' => array( 'learn_field', __( 'すべての分野', 'omochix-core' ) ), 'format' => array( 'learn_format', __( 'すべての形式', 'omochix-core' ) ) ) as $key => $config ) {
		wp_dropdown_categories(
			array(
				'show_option_all' => $config[1],
				'taxonomy'        => $config[0],
				'name'            => $names[ $key ],
				'orderby'         => 'name',
				'selected'        => $current[ $key ],
				'hide_empty'      => false,
				'value_field'     => 'slug',
			)
		);
	}
	echo '<select name="' . esc_attr( $names['level'] ) . '"><option value="">' . esc_html__( 'すべての難易度', 'omochix-core' ) . '</option>';
	foreach ( array( 'beginner', 'intermediate', 'advanced' ) as $level ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $level ), selected( $current['level'], $level, false ), esc_html( omochix_core_get_practice_difficulty_label( $level ) ) );
	}
	echo '</select>';
	echo '<select name="' . esc_attr( $names['flag'] ) . '"><option value="">' . esc_html__( 'すべての状態', 'omochix-core' ) . '</option>';
	printf( '<option value="stale"%s>%s</option>', selected( $current['flag'], 'stale', false ), esc_html__( '要再検証・未検証のみ', 'omochix-core' ) );
	printf( '<option value="missing"%s>%s</option>', selected( $current['flag'], 'missing', false ), esc_html__( '必須欠落のみ', 'omochix-core' ) );
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'omochix_core_practice_list_filters' );

/**
 * Apply the filters and the sort keys to the main admin list query.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function omochix_core_practice_list_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || OMOCHIX_CORE_PRACTICE_POST_TYPE !== $query->get( 'post_type' ) ) {
		return;
	}
	$current = omochix_core_practice_list_current_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$tax_query = array_filter( (array) $query->get( 'tax_query' ), 'is_array' );
	foreach ( array( 'field' => 'learn_field', 'format' => 'learn_format' ) as $key => $taxonomy ) {
		if ( '' !== $current[ $key ] ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $current[ $key ] );
		}
	}
	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}
	if ( $tax_query ) {
		$query->set( 'tax_query', $tax_query );
	}

	$meta_query = array_filter( (array) $query->get( 'meta_query' ), 'is_array' );
	if ( '' !== $current['level'] ) {
		$clause = array( 'key' => 'practice_difficulty', 'value' => $current['level'], 'compare' => '=' );
		if ( 'beginner' === $current['level'] ) {
			// "beginner" is the schema default, so posts without the meta count as beginner.
			$clause = array( 'relation' => 'OR', $clause, array( 'key' => 'practice_difficulty', 'compare' => 'NOT EXISTS' ), array( 'key' => 'practice_difficulty', 'value' => '', 'compare' => '=' ) );
		}
		$meta_query[] = $clause;
	}
	if ( 'stale' === $current['flag'] ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array( 'key' => 'practice_verified_at', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'practice_verified_at', 'value' => '', 'compare' => '=' ),
			array( 'key' => 'practice_verified_at', 'value' => omochix_core_practice_stale_before(), 'compare' => '<', 'type' => 'DATE' ),
		);
	}
	if ( count( $meta_query ) > 1 ) {
		$meta_query['relation'] = 'AND';
	}
	if ( $meta_query ) {
		$query->set( 'meta_query', $meta_query );
	}
	if ( 'missing' === $current['flag'] ) {
		$ids = omochix_core_practice_missing_ids();
		$query->set( 'post__in', $ids ? $ids : array( 0 ) );
	}
}
add_action( 'pre_get_posts', 'omochix_core_practice_list_query' );

/**
 * ORDER BY for the meta columns without dropping posts that lack the meta.
 *
 * @param array<string, string> $clauses SQL clauses.
 * @param WP_Query              $query   Query.
 * @return array<string, string>
 */
function omochix_core_practice_list_orderby_clauses( $clauses, $query ) {
	global $wpdb;
	if ( ! is_admin() || ! $query->is_main_query() || OMOCHIX_CORE_PRACTICE_POST_TYPE !== $query->get( 'post_type' ) ) {
		return $clauses;
	}
	$orderby = sanitize_key( (string) $query->get( 'orderby' ) );
	$keys    = array( 'practice_level' => 'practice_difficulty', 'practice_minutes' => 'practice_minutes', 'practice_verified' => 'practice_verified_at' );
	if ( ! isset( $keys[ $orderby ] ) ) {
		return $clauses;
	}
	$order = strtoupper( (string) $query->get( 'order' ) );
	$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'ASC';
	$value = $wpdb->prepare( "(SELECT omx_pm.meta_value FROM {$wpdb->postmeta} omx_pm WHERE omx_pm.post_id = {$wpdb->posts}.ID AND omx_pm.meta_key = %s LIMIT 1)", $keys[ $orderby ] );
	if ( 'practice_level' === $orderby ) {
		$sort = "CASE {$value} WHEN 'beginner' THEN 1 WHEN 'intermediate' THEN 2 WHEN 'advanced' THEN 3 ELSE 1 END";
	} elseif ( 'practice_minutes' === $orderby ) {
		$sort = "COALESCE(CAST({$value} AS UNSIGNED), 0)";
	} else {
		$sort = "COALESCE(NULLIF({$value}, ''), '0000-00-00')";
	}
	$clauses['orderby'] = "{$sort} {$order}, {$wpdb->posts}.post_title ASC";
	return $clauses;
}
add_filter( 'posts_clauses', 'omochix_core_practice_list_orderby_clauses', 10, 2 );

/**
 * Quick edit: difficulty, minutes, verification date. Bulk edit: difficulty.
 */
function omochix_core_practice_quick_edit( $column_name, $post_type ) {
	if ( OMOCHIX_CORE_PRACTICE_POST_TYPE !== $post_type || 'practice_level' !== $column_name ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-right omochix-quick-edit">
		<div class="inline-edit-col">
			<h4><?php esc_html_e( '実践教材', 'omochix-core' ); ?></h4>
			<label><span class="title"><?php esc_html_e( '難易度', 'omochix-core' ); ?></span><select name="omochix_practice_quick[practice_difficulty]">
				<?php foreach ( array( 'beginner', 'intermediate', 'advanced' ) as $level ) : ?><option value="<?php echo esc_attr( $level ); ?>"><?php echo esc_html( omochix_core_get_practice_difficulty_label( $level ) ); ?></option><?php endforeach; ?>
			</select></label>
			<label><span class="title"><?php esc_html_e( '所要時間（分）', 'omochix-core' ); ?></span><span class="input-text-wrap"><input type="number" name="omochix_practice_quick[practice_minutes]" min="0" step="1"></span></label>
			<label><span class="title"><?php esc_html_e( '最終検証日', 'omochix-core' ); ?></span><span class="input-text-wrap"><input type="date" name="omochix_practice_quick[practice_verified_at]"></span></label>
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'omochix_core_practice_quick_edit', 10, 2 );

function omochix_core_practice_bulk_edit( $column_name, $post_type ) {
	if ( OMOCHIX_CORE_PRACTICE_POST_TYPE !== $post_type || 'practice_level' !== $column_name ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-right omochix-bulk-edit"><div class="inline-edit-col">
		<h4><?php esc_html_e( '実践教材', 'omochix-core' ); ?></h4>
		<label><span class="title"><?php esc_html_e( '難易度', 'omochix-core' ); ?></span><select name="omochix_practice_bulk[practice_difficulty]"><option value=""><?php esc_html_e( '— 変更しない —', 'omochix-core' ); ?></option><?php foreach ( array( 'beginner', 'intermediate', 'advanced' ) as $level ) : ?><option value="<?php echo esc_attr( $level ); ?>"><?php echo esc_html( omochix_core_get_practice_difficulty_label( $level ) ); ?></option><?php endforeach; ?></select></label>
	</div></fieldset>
	<?php
}
add_action( 'bulk_edit_custom_box', 'omochix_core_practice_bulk_edit', 10, 2 );

/**
 * Save quick / bulk edit values through the schema sanitizers. Only the
 * submitted keys change; an empty quick-edit value clears the field.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_practice_inline_meta( $post_id ) {
	if ( OMOCHIX_CORE_PRACTICE_POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	// Quick edit arrives as an AJAX POST; bulk edit is the list form, which WordPress submits as GET
	// (core itself reads it from $_REQUEST), so the bulk values are read from $_REQUEST.
	$is_quick = isset( $_POST['_inline_edit'], $_POST['omochix_practice_quick'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ), 'inlineeditnonce' );
	$is_bulk  = ! $is_quick && isset( $_REQUEST['_wpnonce'], $_REQUEST['omochix_practice_bulk'], $_REQUEST['bulk_edit'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-posts' );
	if ( ! $is_quick && ! $is_bulk ) {
		return;
	}
	$submitted = $is_quick ? $_POST['omochix_practice_quick'] : $_REQUEST['omochix_practice_bulk']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.
	if ( ! is_array( $submitted ) ) {
		return;
	}
	omochix_core_practice_apply_inline_meta( $post_id, wp_unslash( $submitted ), $is_bulk );
}
add_action( 'save_post_' . OMOCHIX_CORE_PRACTICE_POST_TYPE, 'omochix_core_save_practice_inline_meta', 20 );

/**
 * Write the inline-edit keys. Separated from the request handling so it can be tested.
 *
 * @param int                  $post_id   Post ID.
 * @param array<string, mixed> $submitted Submitted values (unslashed).
 * @param bool                 $is_bulk   Bulk edit: '' means "leave unchanged" and only difficulty is allowed.
 * @return string[] Keys written or cleared.
 */
function omochix_core_practice_apply_inline_meta( $post_id, array $submitted, $is_bulk ) {
	$allowed = $is_bulk ? array( 'practice_difficulty' ) : array( 'practice_difficulty', 'practice_minutes', 'practice_verified_at' );
	$written = array();
	foreach ( $allowed as $key ) {
		if ( ! array_key_exists( $key, $submitted ) || ( $is_bulk && '' === $submitted[ $key ] ) ) {
			continue;
		}
		$raw   = $submitted[ $key ];
		$value = omochix_core_sanitize_practice_meta_value( $raw, $key );
		if ( 'practice_verified_at' === $key && is_string( $raw ) && '' !== trim( $raw ) && '' === $value ) {
			continue; // an unparsable date never clears the stored one
		}
		if ( '' === $value || ( 0 === $value && 'practice_minutes' === $key && '' === trim( (string) $raw ) ) ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
		$written[] = $key;
	}
	return $written;
}

/** Assets on the practice list screen (quick-edit populator lives in admin-v2.js). */
function omochix_core_practice_list_assets( $hook_suffix ) {
	if ( 'edit.php' !== $hook_suffix ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || OMOCHIX_CORE_PRACTICE_POST_TYPE !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'omochix-core-admin', OMOCHIX_CORE_URL . 'admin/assets/admin.css', array(), OMOCHIX_CORE_VERSION );
	wp_enqueue_script( 'omochix-core-admin-v2', OMOCHIX_CORE_URL . 'admin/assets/admin-v2.js', array(), OMOCHIX_CORE_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'omochix_core_practice_list_assets' );
