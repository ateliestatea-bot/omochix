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
		'@type'        => 'SoftwareApplication',
		'@id'          => $url . '#softwareapplication',
		'name'         => $name,
		'url'          => $url,
		'dateModified' => get_post_modified_time( DATE_W3C, true, $post ),
		'inLanguage'   => 'ja-JP',
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

	$platforms = omochix_core_get_schema_term_names( $post->ID, 'ai_tool_platform' );
	if ( $platforms ) {
		$schema['operatingSystem'] = $platforms;
	}

	$official_url = esc_url_raw( (string) get_post_meta( $post->ID, 'official_url', true ), array( 'http', 'https' ) );
	if ( $official_url && untrailingslashit( $official_url ) !== untrailingslashit( $url ) ) {
		$schema['sameAs'] = $official_url;
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

	$graph[] = $schema;
	$GLOBALS['omochix_core_software_application_in_slim_seo'] = true;

	return $graph;
}
add_filter( 'slim_seo_schema_graph', 'omochix_core_add_software_application_to_slim_seo', PHP_INT_MAX );

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
