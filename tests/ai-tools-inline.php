<?php
/** Regression checks for AI Tool quick and bulk edits. Run: php tests/ai-tools-inline.php */

define( 'ABSPATH', __DIR__ );
$GLOBALS['tool_meta'] = array();
$GLOBALS['tool_type'] = 'ai_tool';
$GLOBALS['tool_can_edit'] = true;

function add_action() {}
function add_filter() {}
function get_post_type( $id ) { return $GLOBALS['tool_type']; }
function current_user_can( $cap, $id = null ) { return $GLOBALS['tool_can_edit']; }
function wp_is_post_autosave( $id ) { return false; }
function wp_is_post_revision( $id ) { return false; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function wp_verify_nonce( $nonce, $action ) { return $nonce === 'test-' . $action; }
function omochix_core_get_meta_schema() {
	return array(
		'pricing_type' => array( 'options' => array( 'free', 'freemium', 'paid', 'trial', 'contact' ) ),
		'japanese_support' => array( 'options' => array( 'full', 'partial', 'none', 'unknown' ) ),
		'tool_status' => array( 'options' => array( 'active', 'beta', 'waitlist', 'discontinued' ) ),
		'is_featured' => array( 'type' => 'boolean' ),
	);
}
function omochix_core_sanitize_meta_value( $value, $key ) { return 'is_featured' === $key ? (bool) $value : $value; }
$GLOBALS['tool_writes'] = 0;
function update_post_meta( $id, $key, $value ) { ++$GLOBALS['tool_writes']; $GLOBALS['tool_meta'][ $key ] = $value; }
function delete_post_meta( $id, $key ) { ++$GLOBALS['tool_writes']; unset( $GLOBALS['tool_meta'][ $key ] ); }

require __DIR__ . '/../wp-plugin/omochix-core/admin/list-table.php';

function check( $message, $condition ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$_POST = array();
$_GET = array();
$_REQUEST = array(
	'_wpnonce' => 'test-bulk-posts',
	'bulk_edit' => 'Update',
	'omochix_bulk_meta' => array( 'pricing_type' => 'paid', 'japanese_support' => '', 'tool_status' => 'beta', 'is_featured' => '0' ),
);
$GLOBALS['tool_meta'] = array( 'pricing_type' => 'free', 'japanese_support' => 'full', 'tool_status' => 'active', 'is_featured' => true );
omochix_core_save_ai_tool_inline_meta( 12 );
check( 'GET bulk edits selected fields', 'paid' === $GLOBALS['tool_meta']['pricing_type'] && 'beta' === $GLOBALS['tool_meta']['tool_status'] );
check( 'GET bulk preserves no-change and applies false', 'full' === $GLOBALS['tool_meta']['japanese_support'] && false === $GLOBALS['tool_meta']['is_featured'] );

// Ask for a different value so a write would be visible, and count meta writes.
$_REQUEST['_wpnonce']                          = 'invalid';
$_REQUEST['omochix_bulk_meta']['pricing_type'] = 'contact';
$GLOBALS['tool_writes']                        = 0;
omochix_core_save_ai_tool_inline_meta( 12 );
check( 'invalid bulk nonce never writes', 'paid' === $GLOBALS['tool_meta']['pricing_type'] && 0 === $GLOBALS['tool_writes'] );

$_REQUEST['_wpnonce'] = 'test-bulk-posts';
unset( $_REQUEST['bulk_edit'] );
$GLOBALS['tool_writes'] = 0;
omochix_core_save_ai_tool_inline_meta( 12 );
check( 'bulk fields without bulk_edit never write', 'paid' === $GLOBALS['tool_meta']['pricing_type'] && 0 === $GLOBALS['tool_writes'] );

$_REQUEST = array();
$_POST = array( '_inline_edit' => 'test-inlineeditnonce', 'omochix_quick_meta' => array( 'pricing_type' => 'trial', 'tool_status' => 'active' ) );
omochix_core_save_ai_tool_inline_meta( 12 );
check( 'POST quick edit still saves', 'trial' === $GLOBALS['tool_meta']['pricing_type'] && 'active' === $GLOBALS['tool_meta']['tool_status'] );

$GLOBALS['tool_can_edit'] = false;
$_POST['omochix_quick_meta']['pricing_type'] = 'free';
omochix_core_save_ai_tool_inline_meta( 12 );
check( 'no edit capability never writes', 'trial' === $GLOBALS['tool_meta']['pricing_type'] );

// Both inputs valid at once ($_REQUEST also carries the POST fields): quick edit wins.
$GLOBALS['tool_can_edit'] = true;
$_POST                    = array( '_inline_edit' => 'test-inlineeditnonce', 'omochix_quick_meta' => array( 'pricing_type' => 'freemium' ) );
$_REQUEST                 = array_merge(
	$_POST,
	array(
		'_wpnonce'          => 'test-bulk-posts',
		'bulk_edit'         => 'Update',
		'omochix_bulk_meta' => array( 'pricing_type' => 'contact', 'tool_status' => 'waitlist' ),
	)
);
omochix_core_save_ai_tool_inline_meta( 12 );
check( 'quick edit takes precedence over bulk input', 'freemium' === $GLOBALS['tool_meta']['pricing_type'] && 'active' === $GLOBALS['tool_meta']['tool_status'] );

echo "AI Tools inline edit regression tests passed.\n";
