<?php
// omochix-glossary-version: 1
/**
 * AI glossary: capabilities, mapped at run time.
 *
 * Nothing is written to the roles in the database. Users who can
 * edit_others_posts (editors and administrators) get the glossary's
 * primitive capabilities for this request only; authors, contributors,
 * subscribers and visitors get none, so they cannot read drafts, edit or
 * publish terms. Switching the plugin off removes the mapping with it.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The glossary's primitive capabilities.
 *
 * @return string[]
 */
function omochix_core_glossary_primitive_caps() {
	return array(
		'edit_omx_glossaries',
		'edit_others_omx_glossaries',
		'edit_private_omx_glossaries',
		'edit_published_omx_glossaries',
		'publish_omx_glossaries',
		'delete_omx_glossaries',
		'delete_others_omx_glossaries',
		'delete_private_omx_glossaries',
		'delete_published_omx_glossaries',
		'read_private_omx_glossaries',
	);
}

/**
 * user_has_cap: grant the glossary capabilities to editors and administrators.
 *
 * @param array $allcaps All capabilities of the user (name => bool).
 * @return array
 */
function omochix_core_glossary_user_has_cap( $allcaps ) {
	if ( ! is_array( $allcaps ) ) {
		return $allcaps;
	}
	$editor = ! empty( $allcaps['edit_others_posts'] );
	foreach ( omochix_core_glossary_primitive_caps() as $cap ) {
		if ( $editor ) {
			$allcaps[ $cap ] = true;
		} else {
			unset( $allcaps[ $cap ] ); // Never from a role, a plugin or a stale grant.
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'omochix_core_glossary_user_has_cap', 10, 1 );

/**
 * Whether the current user may see the glossary's non-public data.
 *
 * @return bool
 */
function omochix_core_glossary_current_user_is_editor() {
	return function_exists( 'current_user_can' ) && current_user_can( 'edit_others_omx_glossaries' );
}
// omochix-glossary-version: 1 (end)
