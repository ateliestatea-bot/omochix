<?php
/**
 * AI tool administration list table enhancements.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return administration labels for tool status.
 *
 * @return array<string, string>
 */
function omochix_core_get_tool_status_labels() {
	return array(
		'active'       => __( '提供中', 'omochix-core' ),
		'beta'         => __( 'ベータ', 'omochix-core' ),
		'waitlist'     => __( 'ウェイトリスト', 'omochix-core' ),
		'discontinued' => __( '提供終了', 'omochix-core' ),
	);
}

/**
 * Return a safe tool status label.
 *
 * @param mixed $value Stored enum.
 * @return string
 */
function omochix_core_get_tool_status_label( $value ) {
	$labels = omochix_core_get_tool_status_labels();
	$value  = is_scalar( $value ) ? (string) $value : '';
	return isset( $labels[ $value ] ) ? $labels[ $value ] : __( '未設定', 'omochix-core' );
}

/**
 * Arrange useful list table columns while retaining core controls.
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function omochix_core_ai_tool_columns( $columns ) {
	return array(
		'cb'                => $columns['cb'],
		'tool_logo'         => __( 'ロゴ', 'omochix-core' ),
		'title'             => $columns['title'],
		'company_name'      => __( '運営会社', 'omochix-core' ),
		'tool_category'     => __( 'カテゴリー', 'omochix-core' ),
		'pricing_type'      => __( '料金タイプ', 'omochix-core' ),
		'japanese_support'  => __( '日本語対応', 'omochix-core' ),
		'rating_overall'    => __( '編集部評価', 'omochix-core' ),
		'is_featured'       => __( '注目', 'omochix-core' ),
		'display_order'     => __( '表示順', 'omochix-core' ),
		'tool_status'       => __( '提供状況', 'omochix-core' ),
		'publication_state' => __( '公開状態', 'omochix-core' ),
		'author'            => isset( $columns['author'] ) ? $columns['author'] : __( '投稿者', 'omochix-core' ),
		'modified'          => __( '最終更新日', 'omochix-core' ),
	);
}
add_filter( 'manage_ai_tool_posts_columns', 'omochix_core_ai_tool_columns' );

/**
 * Render one AI tool list cell using primed post meta and term caches.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function omochix_core_ai_tool_column_content( $column, $post_id ) {
	$unset = __( '未設定', 'omochix-core' );

	switch ( $column ) {
		case 'tool_logo':
			$logo_id = absint( get_post_meta( $post_id, 'tool_logo', true ) );
			$title   = get_the_title( $post_id );
			echo '<span class="omochix-list-logo">';
			if ( $logo_id ) {
				echo wp_kses_post( wp_get_attachment_image( $logo_id, array( 40, 40 ), false, array( 'alt' => '', 'loading' => 'lazy' ) ) );
			} else {
				$initial = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
				echo '<span aria-hidden="true">' . esc_html( $initial ?: '—' ) . '</span>';
			}
			echo '</span>';
			break;

		case 'company_name':
			$company = get_post_meta( $post_id, 'company_name', true );
			echo $company ? esc_html( $company ) : '<span class="omochix-list-muted">' . esc_html( $unset ) . '</span>';
			printf(
				'<span class="omochix-quick-data" hidden data-post-id="%1$d" data-pricing="%2$s" data-japanese="%3$s" data-status="%4$s" data-rating="%5$s" data-featured="%6$s" data-order="%7$s"></span>',
				absint( $post_id ),
				esc_attr( omochix_core_normalize_pricing_type( get_post_meta( $post_id, 'pricing_type', true ) ) ),
				esc_attr( omochix_core_normalize_japanese_support( get_post_meta( $post_id, 'japanese_support', true ) ) ),
				esc_attr( get_post_meta( $post_id, 'tool_status', true ) ?: 'active' ),
				esc_attr( get_post_meta( $post_id, 'rating_overall', true ) ),
				get_post_meta( $post_id, 'is_featured', true ) ? '1' : '0',
				esc_attr( absint( get_post_meta( $post_id, 'display_order', true ) ) )
			);
			break;

		case 'tool_category':
			$terms = get_the_terms( $post_id, 'ai_tool_category' );
			if ( ! $terms || is_wp_error( $terms ) ) {
				echo '<span class="omochix-list-muted">' . esc_html( $unset ) . '</span>';
				break;
			}
			$visible = array_slice( $terms, 0, 2 );
			echo '<span class="omochix-list-terms">' . esc_html( implode( '、', wp_list_pluck( $visible, 'name' ) ) ) . '</span>';
			if ( count( $terms ) > 2 ) {
				echo '<small class="omochix-list-more">' . esc_html( sprintf( __( 'ほか%d件', 'omochix-core' ), count( $terms ) - 2 ) ) . '</small>';
			}
			break;

		case 'pricing_type':
			echo '<span class="omochix-admin-label">' . esc_html( omochix_core_get_pricing_type_label( get_post_meta( $post_id, 'pricing_type', true ) ) ) . '</span>';
			break;

		case 'japanese_support':
			echo '<span class="omochix-admin-label">' . esc_html( omochix_core_get_japanese_support_label( get_post_meta( $post_id, 'japanese_support', true ) ) ) . '</span>';
			break;

		case 'rating_overall':
			$rating = get_post_meta( $post_id, 'rating_overall', true );
			if ( '' !== $rating && is_numeric( $rating ) && (float) $rating > 0 ) {
				echo '<strong class="omochix-list-rating">' . esc_html( number_format_i18n( (float) $rating, 1 ) ) . '</strong><small> / 5.0</small>';
			} else {
				echo '<span class="omochix-list-muted">' . esc_html__( '未評価', 'omochix-core' ) . '</span>';
			}
			break;

		case 'is_featured':
			$featured = (bool) get_post_meta( $post_id, 'is_featured', true );
			echo '<span class="omochix-admin-label ' . ( $featured ? 'is-featured' : '' ) . '">' . esc_html( $featured ? __( 'Yes', 'omochix-core' ) : __( 'No', 'omochix-core' ) ) . '</span>';
			break;

		case 'display_order':
			echo esc_html( number_format_i18n( absint( get_post_meta( $post_id, 'display_order', true ) ) ) );
			break;

		case 'tool_status':
			$status = get_post_meta( $post_id, 'tool_status', true ) ?: 'active';
			echo '<span class="omochix-admin-label status-' . esc_attr( $status ) . '">' . esc_html( omochix_core_get_tool_status_label( $status ) ) . '</span>';
			break;

		case 'publication_state':
			$status_object = get_post_status_object( get_post_status( $post_id ) );
			$label         = $status_object ? $status_object->label : $unset;
			echo '<span class="omochix-publication-state">' . esc_html( $label ) . '</span>';
			break;

		case 'modified':
			printf(
				'<time datetime="%1$s">%2$s<br><small>%3$s</small></time>',
				esc_attr( get_post_modified_time( DATE_W3C, false, $post_id ) ),
				esc_html( get_post_modified_time( get_option( 'date_format' ), false, $post_id ) ),
				esc_html( get_post_modified_time( get_option( 'time_format' ), false, $post_id ) )
			);
			break;
	}
}
add_action( 'manage_ai_tool_posts_custom_column', 'omochix_core_ai_tool_column_content', 10, 2 );

/**
 * Register safe sortable columns.
 *
 * @param array<string, string> $columns Sortable columns.
 * @return array<string, string>
 */
function omochix_core_ai_tool_sortable_columns( $columns ) {
	$columns['title']          = 'title';
	$columns['rating_overall'] = array( 'rating_overall', true );
	$columns['display_order']  = array( 'display_order', false );
	$columns['modified']       = array( 'modified', true );
	return $columns;
}
add_filter( 'manage_edit-ai_tool_sortable_columns', 'omochix_core_ai_tool_sortable_columns' );

/**
 * Prime logo attachment caches in one batch before list cells are rendered.
 *
 * @param WP_Post[] $posts Query results.
 * @param WP_Query  $query Current query.
 * @return WP_Post[]
 */
function omochix_core_prime_ai_tool_logo_cache( $posts, $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'ai_tool' !== $query->get( 'post_type' ) || ! $posts ) {
		return $posts;
	}
	$logo_ids = array();
	foreach ( $posts as $post ) {
		$logo_id = absint( get_post_meta( $post->ID, 'tool_logo', true ) );
		if ( $logo_id ) {
			$logo_ids[] = $logo_id;
		}
	}
	if ( $logo_ids ) {
		get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post__in'               => array_values( array_unique( $logo_ids ) ),
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
	}
	return $posts;
}
add_filter( 'the_posts', 'omochix_core_prime_ai_tool_logo_cache', 10, 2 );

/**
 * Render list filters using GET-compatible WordPress controls.
 *
 * @param string $post_type Current post type.
 * @return void
 */
function omochix_core_ai_tool_filters( $post_type ) {
	if ( 'ai_tool' !== $post_type ) {
		return;
	}

	$category = isset( $_GET['ai_tool_category_filter'] ) ? sanitize_title( wp_unslash( $_GET['ai_tool_category_filter'] ) ) : '';
	wp_dropdown_categories(
		array(
			'show_option_all' => __( 'すべてのカテゴリー', 'omochix-core' ),
			'taxonomy'        => 'ai_tool_category',
			'name'            => 'ai_tool_category_filter',
			'orderby'         => 'name',
			'selected'        => $category,
			'hierarchical'    => true,
			'hide_empty'      => false,
			'value_field'     => 'slug',
		)
	);

	$filters = array(
		'pricing_type_filter' => array(
			'label'   => __( 'すべての料金タイプ', 'omochix-core' ),
			'options' => array( 'free', 'freemium', 'paid', 'trial', 'contact' ),
			'callback' => 'omochix_core_get_pricing_type_label',
		),
		'japanese_support_filter' => array(
			'label'   => __( 'すべての日本語対応', 'omochix-core' ),
			'options' => array( 'full', 'partial', 'none', 'unknown' ),
			'callback' => 'omochix_core_get_japanese_support_label',
		),
		'tool_status_filter' => array(
			'label'   => __( 'すべての提供状況', 'omochix-core' ),
			'options' => array_keys( omochix_core_get_tool_status_labels() ),
			'callback' => 'omochix_core_get_tool_status_label',
		),
	);

	foreach ( $filters as $name => $config ) {
		$current = isset( $_GET[ $name ] ) ? sanitize_key( wp_unslash( $_GET[ $name ] ) ) : '';
		if ( ! in_array( $current, $config['options'], true ) ) {
			$current = '';
		}
		printf( '<select name="%1$s"><option value="">%2$s</option>', esc_attr( $name ), esc_html( $config['label'] ) );
		foreach ( $config['options'] as $value ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( call_user_func( $config['callback'], $value ) ) );
		}
		echo '</select>';
	}

	$featured = isset( $_GET['is_featured_filter'] ) ? sanitize_key( wp_unslash( $_GET['is_featured_filter'] ) ) : '';
	if ( ! in_array( $featured, array( 'yes', 'no' ), true ) ) {
		$featured = '';
	}
	?>
	<select name="is_featured_filter">
		<option value=""><?php esc_html_e( 'すべての注目状態', 'omochix-core' ); ?></option>
		<option value="yes" <?php selected( $featured, 'yes' ); ?>><?php esc_html_e( '注目のみ', 'omochix-core' ); ?></option>
		<option value="no" <?php selected( $featured, 'no' ); ?>><?php esc_html_e( '注目以外', 'omochix-core' ); ?></option>
	</select>
	<?php
}
add_action( 'restrict_manage_posts', 'omochix_core_ai_tool_filters' );

/**
 * Apply validated list filters only to the main AI tool admin query.
 *
 * @param WP_Query $query Current query.
 * @return void
 */
function omochix_core_ai_tool_admin_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'ai_tool' !== $query->get( 'post_type' ) ) {
		return;
	}

	$meta_query = array();
	$category   = isset( $_GET['ai_tool_category_filter'] ) ? sanitize_title( wp_unslash( $_GET['ai_tool_category_filter'] ) ) : '';
	if ( $category && term_exists( $category, 'ai_tool_category' ) ) {
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'ai_tool_category',
					'field'    => 'slug',
					'terms'    => $category,
				),
			)
		);
	}

	$pricing = isset( $_GET['pricing_type_filter'] ) ? sanitize_key( wp_unslash( $_GET['pricing_type_filter'] ) ) : '';
	$pricing_values = array(
		'free' => array( 'free', '無料' ), 'freemium' => array( 'freemium', '無料・有料', '無料プランあり' ),
		'paid' => array( 'paid', '有料' ), 'trial' => array( 'trial', '無料体験', '無料体験あり' ),
		'contact' => array( 'contact', '要問い合わせ', '料金情報なし' ),
	);
	if ( isset( $pricing_values[ $pricing ] ) ) {
		$pricing_clause = array( 'key' => 'pricing_type', 'value' => $pricing_values[ $pricing ], 'compare' => 'IN' );
		if ( 'contact' === $pricing ) {
			$pricing_clause = array( 'relation' => 'OR', $pricing_clause, array( 'key' => 'pricing_type', 'compare' => 'NOT EXISTS' ) );
		}
		$meta_query[] = $pricing_clause;
	}

	$japanese = isset( $_GET['japanese_support_filter'] ) ? sanitize_key( wp_unslash( $_GET['japanese_support_filter'] ) ) : '';
	$japanese_values = array(
		'full' => array( 'full', '1', 'true', 'yes', 'on' ), 'partial' => array( 'partial' ),
		'none' => array( 'none', '0', 'false', 'no', 'off', '' ), 'unknown' => array( 'unknown' ),
	);
	if ( isset( $japanese_values[ $japanese ] ) ) {
		$japanese_clause = array( 'key' => 'japanese_support', 'value' => $japanese_values[ $japanese ], 'compare' => 'IN' );
		if ( 'unknown' === $japanese ) {
			$japanese_clause = array( 'relation' => 'OR', $japanese_clause, array( 'key' => 'japanese_support', 'compare' => 'NOT EXISTS' ) );
		}
		$meta_query[] = $japanese_clause;
	}

	$status  = isset( $_GET['tool_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['tool_status_filter'] ) ) : '';
	$allowed = array_keys( omochix_core_get_tool_status_labels() );
	if ( in_array( $status, $allowed, true ) ) {
		$status_clause = array( 'key' => 'tool_status', 'value' => $status, 'compare' => '=' );
		if ( 'active' === $status ) {
			$status_clause = array( 'relation' => 'OR', $status_clause, array( 'key' => 'tool_status', 'compare' => 'NOT EXISTS' ) );
		}
		$meta_query[] = $status_clause;
	}

	$featured = isset( $_GET['is_featured_filter'] ) ? sanitize_key( wp_unslash( $_GET['is_featured_filter'] ) ) : '';
	if ( 'yes' === $featured ) {
		$meta_query[] = array( 'key' => 'is_featured', 'value' => '1', 'compare' => '=' );
	} elseif ( 'no' === $featured ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array( 'key' => 'is_featured', 'value' => '1', 'compare' => '!=' ),
			array( 'key' => 'is_featured', 'compare' => 'NOT EXISTS' ),
		);
	}

	if ( $meta_query ) {
		$query->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $meta_query ) );
	}

	$orderby = sanitize_key( (string) $query->get( 'orderby' ) );
	if ( 'modified' === $orderby ) {
		$query->set( 'orderby', 'modified' );
	}
}
add_action( 'pre_get_posts', 'omochix_core_ai_tool_admin_query' );

/**
 * Provide stable numeric ordering without excluding posts that lack meta.
 *
 * @param array<string, string> $clauses SQL clauses.
 * @param WP_Query              $query   Current query.
 * @return array<string, string>
 */
function omochix_core_ai_tool_orderby_clauses( $clauses, $query ) {
	global $wpdb;

	if ( ! is_admin() || ! $query->is_main_query() || 'ai_tool' !== $query->get( 'post_type' ) ) {
		return $clauses;
	}
	$orderby = sanitize_key( (string) $query->get( 'orderby' ) );
	if ( ! in_array( $orderby, array( 'rating_overall', 'display_order' ), true ) ) {
		return $clauses;
	}
	$requested_order = strtoupper( (string) $query->get( 'order' ) );
	$order = in_array( $requested_order, array( 'ASC', 'DESC' ), true )
		? $requested_order
		: ( 'rating_overall' === $orderby ? 'DESC' : 'ASC' );
	$key   = 'rating_overall' === $orderby ? 'rating_overall' : 'display_order';
	$value_sql = $wpdb->prepare(
		"(SELECT CAST(omx_pm.meta_value AS DECIMAL(10,2)) FROM {$wpdb->postmeta} omx_pm WHERE omx_pm.post_id = {$wpdb->posts}.ID AND omx_pm.meta_key = %s LIMIT 1)",
		$key
	);
	if ( 'rating_overall' === $orderby ) {
		$clauses['orderby'] = "CASE WHEN {$value_sql} IS NULL OR {$value_sql} = 0 THEN 1 ELSE 0 END ASC, {$value_sql} {$order}, {$wpdb->posts}.post_modified DESC";
	} else {
		$clauses['orderby'] = "COALESCE({$value_sql}, 0) {$order}, {$wpdb->posts}.post_modified DESC";
	}
	return $clauses;
}
add_filter( 'posts_clauses', 'omochix_core_ai_tool_orderby_clauses', 10, 2 );

/**
 * Extend standard admin search to two lightweight metadata fields.
 *
 * @param string   $search Existing search SQL.
 * @param WP_Query $query  Current query.
 * @return string
 */
function omochix_core_ai_tool_admin_search( $search, $query ) {
	global $wpdb;

	if ( ! is_admin() || ! $query->is_main_query() || 'ai_tool' !== $query->get( 'post_type' ) || ! $query->is_search() ) {
		return $search;
	}
	$term = sanitize_text_field( (string) $query->get( 's' ) );
	if ( '' === $term ) {
		return $search;
	}
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	return $wpdb->prepare(
		" AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} omx_search_pm WHERE omx_search_pm.post_id = {$wpdb->posts}.ID AND omx_search_pm.meta_key IN ('company_name', 'short_description') AND omx_search_pm.meta_value LIKE %s)) ",
		$like,
		$like,
		$like,
		$like
	);
}
add_filter( 'posts_search', 'omochix_core_ai_tool_admin_search', 10, 2 );

/**
 * Render quick edit fields once.
 *
 * @param string $column_name Current column.
 * @param string $post_type   Post type.
 * @return void
 */
function omochix_core_ai_tool_quick_edit( $column_name, $post_type ) {
	if ( 'ai_tool' !== $post_type || 'company_name' !== $column_name ) {
		return;
	}
	$pricing = array( 'free', 'freemium', 'paid', 'trial', 'contact' );
	$japanese = array( 'full', 'partial', 'none', 'unknown' );
	?>
	<fieldset class="inline-edit-col-right omochix-quick-edit">
		<div class="inline-edit-col">
			<h4><?php esc_html_e( 'OmochiX ツール情報', 'omochix-core' ); ?></h4>
			<label><span class="title"><?php esc_html_e( '料金タイプ', 'omochix-core' ); ?></span><select name="omochix_quick_meta[pricing_type]">
				<?php foreach ( $pricing as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( omochix_core_get_pricing_type_label( $value ) ); ?></option><?php endforeach; ?>
			</select></label>
			<label><span class="title"><?php esc_html_e( '日本語対応', 'omochix-core' ); ?></span><select name="omochix_quick_meta[japanese_support]">
				<?php foreach ( $japanese as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( omochix_core_get_japanese_support_label( $value ) ); ?></option><?php endforeach; ?>
			</select></label>
			<label><span class="title"><?php esc_html_e( '提供状況', 'omochix-core' ); ?></span><select name="omochix_quick_meta[tool_status]">
				<?php foreach ( omochix_core_get_tool_status_labels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
			</select></label>
			<label><span class="title"><?php esc_html_e( '編集部評価', 'omochix-core' ); ?></span><span class="input-text-wrap"><input type="number" name="omochix_quick_meta[rating_overall]" min="1" max="5" step="0.1"></span></label>
			<label><span class="title"><?php esc_html_e( '表示順', 'omochix-core' ); ?></span><span class="input-text-wrap"><input type="number" name="omochix_quick_meta[display_order]" min="0" step="1"></span></label>
			<label class="inline-edit-featured"><span class="title"><?php esc_html_e( '注目ツール', 'omochix-core' ); ?></span><input type="hidden" name="omochix_quick_meta[is_featured]" value="0"><input type="checkbox" name="omochix_quick_meta[is_featured]" value="1"> <span><?php esc_html_e( '注目に設定', 'omochix-core' ); ?></span></label>
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'omochix_core_ai_tool_quick_edit', 10, 2 );

/**
 * Render bulk edit controls once with explicit no-change options.
 *
 * @param string $column_name Current column.
 * @param string $post_type   Post type.
 * @return void
 */
function omochix_core_ai_tool_bulk_edit( $column_name, $post_type ) {
	if ( 'ai_tool' !== $post_type || 'company_name' !== $column_name ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-right omochix-bulk-edit"><div class="inline-edit-col">
		<h4><?php esc_html_e( 'OmochiX ツール情報', 'omochix-core' ); ?></h4>
		<label><span class="title"><?php esc_html_e( '料金タイプ', 'omochix-core' ); ?></span><select name="omochix_bulk_meta[pricing_type]"><option value=""><?php esc_html_e( '— 変更しない —', 'omochix-core' ); ?></option><?php foreach ( array( 'free', 'freemium', 'paid', 'trial', 'contact' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( omochix_core_get_pricing_type_label( $value ) ); ?></option><?php endforeach; ?></select></label>
		<label><span class="title"><?php esc_html_e( '日本語対応', 'omochix-core' ); ?></span><select name="omochix_bulk_meta[japanese_support]"><option value=""><?php esc_html_e( '— 変更しない —', 'omochix-core' ); ?></option><?php foreach ( array( 'full', 'partial', 'none', 'unknown' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( omochix_core_get_japanese_support_label( $value ) ); ?></option><?php endforeach; ?></select></label>
		<label><span class="title"><?php esc_html_e( '提供状況', 'omochix-core' ); ?></span><select name="omochix_bulk_meta[tool_status]"><option value=""><?php esc_html_e( '— 変更しない —', 'omochix-core' ); ?></option><?php foreach ( omochix_core_get_tool_status_labels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
		<label><span class="title"><?php esc_html_e( '注目ツール', 'omochix-core' ); ?></span><select name="omochix_bulk_meta[is_featured]"><option value=""><?php esc_html_e( '— 変更しない —', 'omochix-core' ); ?></option><option value="1"><?php esc_html_e( 'Yes', 'omochix-core' ); ?></option><option value="0"><?php esc_html_e( 'No', 'omochix-core' ); ?></option></select></label>
	</div></fieldset>
	<?php
}
add_action( 'bulk_edit_custom_box', 'omochix_core_ai_tool_bulk_edit', 10, 2 );

/**
 * Save quick and bulk edit metadata through the shared contract sanitizer.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_core_save_ai_tool_inline_meta( $post_id ) {
	if ( 'ai_tool' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$is_quick = isset( $_POST['_inline_edit'], $_POST['omochix_quick_meta'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ), 'inlineeditnonce' );
	$is_bulk  = isset( $_POST['_wpnonce'], $_POST['omochix_bulk_meta'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-posts' );
	if ( ! $is_quick && ! $is_bulk ) {
		return;
	}

	$submitted = $is_quick ? $_POST['omochix_quick_meta'] : $_POST['omochix_bulk_meta'];
	if ( ! is_array( $submitted ) ) {
		return;
	}
	$submitted = wp_unslash( $submitted );
	$allowed   = $is_quick
		? array( 'pricing_type', 'japanese_support', 'tool_status', 'rating_overall', 'is_featured', 'display_order' )
		: array( 'pricing_type', 'japanese_support', 'tool_status', 'is_featured' );
	$schema    = omochix_core_get_meta_schema();

	foreach ( $allowed as $key ) {
		if ( ! array_key_exists( $key, $submitted ) || ( $is_bulk && '' === $submitted[ $key ] ) ) {
			continue;
		}
		if ( isset( $schema[ $key ]['options'] ) && ! in_array( $submitted[ $key ], $schema[ $key ]['options'], true ) ) {
			continue;
		}
		if ( 'is_featured' === $key && ! in_array( $submitted[ $key ], array( '0', '1', 0, 1, false, true ), true ) ) {
			continue;
		}
		$value = omochix_core_sanitize_meta_value( $submitted[ $key ], $key );
		if ( 'rating_overall' === $key && 0 === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
add_action( 'save_post_ai_tool', 'omochix_core_save_ai_tool_inline_meta', 20 );
