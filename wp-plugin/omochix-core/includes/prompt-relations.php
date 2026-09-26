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
 * @param int $limit     Maximum tools to return.
 * @return WP_Post[]
 */
function omochix_core_get_prompt_related_tools( $prompt_id, $limit = 6 ) {
	return omochix_core_get_related_content(
		$prompt_id,
		'related_tool_ids',
		'ai_tool',
		array( 'posts_per_page' => max( 1, absint( $limit ) ) )
	);
}

/**
 * Return the IDs of every published prompt whose related_tool_ids includes
 * a tool, in newest-first order, verified exactly.
 *
 * The serialized-array LIKE match only narrows candidates; every candidate is
 * then verified against the unserialized meta value, so a matching array
 * index (e.g. `i:42;` used as a key) can never produce a false relation. This
 * has no display cap — it is the shared, exact source of truth for both the
 * capped tool-page display below and the Prompt Library archive's
 * `related_tool` filter (archive-prompt.php), which applies its own
 * pagination on top of the returned ID list.
 *
 * @param int $tool_id AI tool post ID.
 * @return int[]
 */
function omochix_core_get_tool_related_prompt_ids( $tool_id ) {
	$tool_id = absint( $tool_id );
	if ( ! $tool_id || ! post_type_exists( 'prompt' ) ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'prompt',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
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

	$verified = array();
	foreach ( $query->posts as $prompt_id ) {
		$ids = get_post_meta( $prompt_id, 'related_tool_ids', true );
		if ( is_array( $ids ) && in_array( $tool_id, array_map( 'absint', $ids ), true ) ) {
			$verified[] = (int) $prompt_id;
		}
	}

	return $verified;
}

/**
 * Return published prompts that list an AI tool in their related_tool_ids,
 * capped for display on the AI tool page.
 *
 * @param int      $tool_id  AI tool post ID.
 * @param int      $limit    Maximum prompts to return.
 * @param bool|null $has_more Set by reference: true when more than $limit
 *                            published prompts relate to this tool, so the
 *                            caller can show a "see all" link.
 * @return WP_Post[]
 */
function omochix_core_get_tool_related_prompts( $tool_id, $limit = 6, &$has_more = null ) {
	$has_more = false;
	$limit    = max( 1, absint( $limit ) );

	$ids = omochix_core_get_tool_related_prompt_ids( $tool_id );
	if ( ! $ids ) {
		return array();
	}

	$has_more   = count( $ids ) > $limit;
	$capped_ids = array_slice( $ids, 0, $limit );

	$query = new WP_Query(
		array(
			'post_type'      => 'prompt',
			'post_status'    => 'publish',
			'post__in'       => $capped_ids,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $capped_ids ),
			'no_found_rows'  => true,
		)
	);

	return $query->posts;
}
