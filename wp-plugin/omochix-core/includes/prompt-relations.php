<?php
/**
 * Prompt ⇄ AI tool relation readers.
 *
 * The relation is stored once, on the prompt (`related_tool_ids`, a plain
 * post ID array like the ai_tool related_*_ids fields). The prompt side reads
 * it through the shared omochix_core_get_related_content() reader; the tool
 * side resolves the reverse direction here, so editors never have to keep
 * two lists in sync.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return published AI tools related to a prompt, in editorial order.
 *
 * @param int $prompt_id Prompt post ID.
 * @return WP_Post[]
 */
function omochix_core_get_prompt_related_tools( $prompt_id ) {
	return omochix_core_get_related_content( $prompt_id, 'related_tool_ids', 'ai_tool' );
}

/**
 * Return published prompts that list an AI tool in their related_tool_ids.
 *
 * The serialized-array LIKE match only narrows candidates; every candidate is
 * then verified against the unserialized meta value, so a matching array
 * index (e.g. `i:42;` used as a key) can never produce a false relation.
 *
 * @param int $tool_id AI tool post ID.
 * @param int $limit   Maximum prompts to return.
 * @return WP_Post[]
 */
function omochix_core_get_tool_related_prompts( $tool_id, $limit = 6 ) {
	$tool_id = absint( $tool_id );
	$limit   = max( 1, absint( $limit ) );
	if ( ! $tool_id || ! post_type_exists( 'prompt' ) ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'prompt',
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'     => 'related_tool_ids',
					'value'   => ';i:' . $tool_id . ';',
					'compare' => 'LIKE',
				),
			),
		)
	);

	$prompts = array();
	foreach ( $query->posts as $prompt ) {
		$ids = get_post_meta( $prompt->ID, 'related_tool_ids', true );
		if ( is_array( $ids ) && in_array( $tool_id, array_map( 'absint', $ids ), true ) ) {
			$prompts[] = $prompt;
			if ( count( $prompts ) >= $limit ) {
				break;
			}
		}
	}

	return $prompts;
}
