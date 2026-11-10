<?php
/**
 * SoftwareApplication structured data for public AI tool details.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * dateModified for an AI tool: the article date from tool-display-facts.php,
 * or post_modified when that file is not loaded (during a file-by-file upload).
 *
 * @param WP_Post $post AI tool post.
 * @return string ISO 8601.
 */
function omochix_core_get_schema_date_modified( $post ) {
	if ( function_exists( 'omochix_core_get_tool_content_modified_iso' ) ) {
		return omochix_core_get_tool_content_modified_iso( $post->ID );
	}
	return get_post_modified_time( DATE_W3C, true, $post );
}

/**
 * Determine whether the current request may expose AI tool structured data.
 *
 * @return bool
 */
function omochix_core_should_output_software_application_schema() {
	if ( is_admin() || is_search() || is_preview() || is_feed() || ! is_singular( 'ai_tool' ) ) {
		return false;
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || 'ai_tool' !== $post->post_type || 'publish' !== $post->post_status ) {
		return false;
	}

	return '' === $post->post_password;
}

/**
 * Return clean term names in stable term-ID order.
 *
 * @param int    $post_id  AI tool post ID.
 * @param string $taxonomy Taxonomy name.
 * @return string[]
 */
function omochix_core_get_schema_term_names( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! is_array( $terms ) ) {
		return array();
	}

	usort(
		$terms,
		static function ( $left, $right ) {
			return (int) $left->term_id <=> (int) $right->term_id;
		}
	);

	$names = array_map(
		static function ( $term ) {
			return sanitize_text_field( wp_strip_all_tags( $term->name ) );
		},
		$terms
	);

	return array_values( array_filter( array_unique( $names ) ) );
}

/**
 * Return the operating system names for a tool's SoftwareApplication schema.
 *
 * @param int $post_id AI tool ID.
 * @return string[]
 */
function omochix_core_get_schema_operating_systems( $post_id ) {
	$terms = omochix_core_filter_platform_terms(
		get_the_terms( $post_id, 'ai_tool_platform' ),
		omochix_core_get_operating_system_platform_slugs()
	);

	usort(
		$terms,
		static function ( $left, $right ) {
			return (int) $left->term_id <=> (int) $right->term_id;
		}
	);

	$names = array_map(
		static function ( $term ) {
			return sanitize_text_field( wp_strip_all_tags( $term->name ) );
		},
		$terms
	);

	return array_values( array_filter( array_unique( $names ) ) );
}

/**
 * Build a SoftwareApplication node from verified Core data only.
 *
 * @return array<string, mixed>
 */
function omochix_core_get_software_application_schema() {
	if ( ! omochix_core_should_output_software_application_schema() ) {
		return array();
	}

	$post = get_queried_object();
	$url  = esc_url_raw( get_permalink( $post ), array( 'http', 'https' ) );
	$name = sanitize_text_field( wp_strip_all_tags( get_the_title( $post ) ) );

	if ( ! $url || ! $name ) {
		return array();
	}

	$schema = array(
		'@type'         => 'SoftwareApplication',
		'@id'           => $url . '#softwareapplication',
		'name'          => $name,
		'url'           => $url,
		'datePublished' => get_post_time( DATE_W3C, true, $post ),
		// The article's own update date, not post_modified (which also moves on
		// term-only changes); see omochix_core_get_tool_content_modified_gmt().
		'dateModified'  => omochix_core_get_schema_date_modified( $post ),
		'inLanguage'    => 'ja-JP',
	);

	$description = get_post_meta( $post->ID, 'short_description', true );
	if ( ! is_string( $description ) || '' === trim( $description ) ) {
		$description = $post->post_excerpt;
	}
	$description = sanitize_text_field( wp_strip_all_tags( (string) $description ) );
	if ( '' !== $description ) {
		$schema['description'] = $description;
	}

	$image = esc_url_raw( (string) get_the_post_thumbnail_url( $post, 'full' ), array( 'http', 'https' ) );
	if ( $image ) {
		$schema['image'] = $image;
	}

	$categories = omochix_core_get_schema_term_names( $post->ID, 'ai_tool_category' );
	if ( $categories ) {
		$schema['applicationCategory'] = reset( $categories );
	}

	// Only real operating systems (matched by slug) belong in operatingSystem;
	// Web, API and other platform terms are omitted.
	$platforms = omochix_core_get_schema_operating_systems( $post->ID );
	if ( $platforms ) {
		$schema['operatingSystem'] = $platforms;
	}

	$official_url = esc_url_raw( (string) get_post_meta( $post->ID, 'official_url', true ), array( 'http', 'https' ) );
	if ( $official_url && untrailingslashit( $official_url ) !== untrailingslashit( $url ) ) {
		$schema['sameAs'] = $official_url;
	}

	$company_name = sanitize_text_field( wp_strip_all_tags( (string) get_post_meta( $post->ID, 'company_name', true ) ) );
	if ( '' !== $company_name ) {
		$schema['provider'] = array(
			'@type' => 'Organization',
			'name'  => $company_name,
		);
	}

	// A single editorial rating is represented as a Review (one identified
	// author, OmochiX), never as AggregateRating: AggregateRating implies an
	// aggregation of many independent ratings, which a lone editorial score
	// is not. Only emitted when rating_overall is set and the post carries the
	// editorial review behind it (omochix_core_tool_rating_has_basis()).
	$rating = function_exists( 'omochix_core_get_tool_display_rating' ) ? omochix_core_get_tool_display_rating( $post->ID ) : 0;
	if ( $rating > 0 ) {
		$schema['review'] = array(
			'@type'        => 'Review',
			'reviewRating' => array(
				'@type'       => 'Rating',
				'ratingValue' => (float) $rating,
				'bestRating'  => 5,
				'worstRating' => 1,
			),
			'author'       => array(
				'@type' => 'Organization',
				'name'  => 'OmochiX',
			),
		);
	}

	return $schema;
}

/**
 * Add the Core-owned node to Slim SEO's graph without duplicating app nodes.
 *
 * @param array<int, array<string, mixed>> $graph Slim SEO schema graph.
 * @return array<int, array<string, mixed>>
 */
function omochix_core_add_software_application_to_slim_seo( $graph ) {
	if ( ! omochix_core_should_output_software_application_schema() ) {
		return $graph;
	}

	$schema = omochix_core_get_software_application_schema();
	if ( ! $schema ) {
		return $graph;
	}

	$reserved_types = array( 'SoftwareApplication', 'WebApplication', 'MobileApplication' );
	$graph          = array_values(
		array_filter(
			$graph,
			static function ( $entity ) use ( $reserved_types ) {
				if ( ! is_array( $entity ) ) {
					return true;
				}

				$types = isset( $entity['@type'] ) ? (array) $entity['@type'] : array();
				return ! array_intersect( $reserved_types, $types );
			}
		)
	);

	// Slim SEO dates its WebPage node with post_modified; use the same article
	// date as the SoftwareApplication node so the two never disagree.
	foreach ( $graph as $index => $entity ) {
		if ( is_array( $entity ) && in_array( 'WebPage', (array) ( $entity['@type'] ?? array() ), true ) && isset( $entity['dateModified'] ) ) {
			$graph[ $index ]['dateModified'] = $schema['dateModified'];
		}
	}

	$graph[] = $schema;
	$GLOBALS['omochix_core_software_application_in_slim_seo'] = true;

	return $graph;
}
add_filter( 'slim_seo_schema_graph', 'omochix_core_add_software_application_to_slim_seo', PHP_INT_MAX );

/**
 * Slim SEO's article:modified_time and og:updated_time are post_modified; on an
 * AI tool detail page use the same article date as the structured data.
 *
 * @param string $value Slim SEO's value.
 * @return string
 */
function omochix_core_slim_seo_tool_modified_time( $value ) {
	if ( '' === $value || ! omochix_core_should_output_software_application_schema() ) {
		return $value;
	}
	return omochix_core_get_schema_date_modified( get_queried_object() );
}
add_filter( 'slim_seo_open_graph_article_modified_time', 'omochix_core_slim_seo_tool_modified_time' );
add_filter( 'slim_seo_open_graph_updated_time', 'omochix_core_slim_seo_tool_modified_time' );

/**
 * Output one standalone node when Slim SEO did not generate its schema graph.
 *
 * @return void
 */
function omochix_core_output_software_application_fallback() {
	static $did_output = false;

	if ( $did_output || ! empty( $GLOBALS['omochix_core_software_application_in_slim_seo'] ) ) {
		return;
	}

	$schema = omochix_core_get_software_application_schema();
	if ( ! $schema ) {
		return;
	}

	$did_output = true;
	$flags      = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	$json       = wp_json_encode( array_merge( array( '@context' => 'https://schema.org' ), $schema ), $flags );
	if ( false === $json ) {
		return;
	}

	echo '<script type="application/ld+json" id="omochix-software-application-schema">';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded JSON must not be HTML-escaped.
	echo $json;
	echo '</script>';
}
add_action( 'wp_footer', 'omochix_core_output_software_application_fallback', PHP_INT_MAX );
