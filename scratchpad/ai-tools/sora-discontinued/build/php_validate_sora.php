<?php
/**
 * Offline harness: run the REAL Content Updater row validator
 * (omochix_core_tool_update_validate_row) against the batch JSON.
 *
 * No WordPress install, no database and no network are involved. WordPress'
 * own sanitizers (formatting.php / kses.php) are loaded from a local copy of
 * wp-includes; post and meta lookups are answered from the recovered
 * read-only production REST snapshot, so the diff approximates what the
 * production dry-run will show.
 *
 * Usage: php php_validate.php <path-to-wp-root-containing-wp-includes>
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$base    = dirname( __DIR__ );
$repo    = '/Users/omochi/Documents/omochix';
$wp_root = rtrim( $argv[1] ?? '', '/' ) . '/';
if ( ! is_file( $wp_root . 'wp-includes/formatting.php' ) ) {
	fwrite( STDERR, "wp-includes not found under {$wp_root}\n" );
	exit( 2 );
}

define( 'ABSPATH', $wp_root );
define( 'WPINC', 'wp-includes' );
define( 'MB_IN_BYTES', 1024 * 1024 );
define( 'WP_CONTENT_DIR', $wp_root . 'wp-content' );

// functions.php pulls in option.php (database access), so the handful of
// helpers the sanitizers need are declared here instead.
function absint( $maybeint ) { return abs( (int) $maybeint ); }
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags, $depth ); }
function wp_allowed_protocols() { return array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function _deprecated_function( ...$args ) {}
function _deprecated_argument( ...$args ) {}
function _doing_it_wrong( ...$args ) {}
function is_utf8_charset( $charset = null ) { return true; }
function wp_load_alloptions() { return array(); }

require ABSPATH . WPINC . '/compat.php';
foreach ( array( 'compat-utf8.php', 'utf8.php' ) as $utf8_file ) {
	if ( is_file( ABSPATH . WPINC . '/' . $utf8_file ) ) {
		require_once ABSPATH . WPINC . '/' . $utf8_file;
	}
}
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/formatting.php';
foreach ( array( 'attribute-token', 'span', 'text-replacement', 'decoder', 'tag-processor' ) as $html_api ) {
	$html_api_file = ABSPATH . WPINC . '/html-api/class-wp-html-' . $html_api . '.php';
	if ( is_file( $html_api_file ) ) {
		require $html_api_file;
	}
}
require ABSPATH . WPINC . '/kses.php';

// --- Minimal stubs for the WordPress runtime the validator touches. ---------
function __( $text, $domain = 'default' ) { return $text; }
function _x( $text, $context, $domain = 'default' ) { return $text; }
function get_option( $name, $default = false ) { return 'blog_charset' === $name ? 'UTF-8' : $default; }
function is_admin() { return true; }
function current_user_can( $cap ) { return true; }

$snapshot = array();
foreach ( glob( $base . '/prod-snapshot/*_full.json' ) as $file ) {
	$row = json_decode( file_get_contents( $file ), true );
	$row = isset( $row[0] ) ? $row[0] : $row;
	$snapshot[ (int) $row['id'] ] = $row;
}
$all_tools = json_decode( file_get_contents( $base . '/prod-snapshot/all_tools_page1.json' ), true );

function get_post( $id ) {
	global $snapshot;
	if ( ! isset( $snapshot[ $id ] ) ) {
		return null;
	}
	// REST "rendered" content of these tools is an empty block scaffold; the
	// raw post_content is not exposed publicly, so treat it as empty.
	return (object) array( 'ID' => $id, 'post_type' => 'ai_tool', 'post_content' => '', 'post_title' => $snapshot[ $id ]['title']['rendered'] );
}
function get_the_title( $id ) {
	global $snapshot;
	return $snapshot[ $id ]['title']['rendered'] ?? '';
}
function get_post_meta( $id, $key, $single = false ) {
	global $snapshot;
	return $snapshot[ $id ]['meta'][ $key ] ?? '';
}
function get_post_type( $id ) { return get_post( $id ) ? 'ai_tool' : false; }
function wp_attachment_is_image( $id ) { return false; }
function get_post_thumbnail_id( $id ) { return 0; }
function omochix_core_existing_tool_slugs() {
	global $all_tools;
	$out = array();
	foreach ( $all_tools as $tool ) {
		$out[ $tool['slug'] ] = (int) $tool['id'];
	}
	return $out;
}

require $repo . '/wp-plugin/omochix-core/includes/meta-schema.php';
require $repo . '/wp-plugin/omochix-core/admin/ai-tool-content-updater.php';

$file     = $base . '/sora-discontinued-update.json';
$contents = file_get_contents( $file );
$decoded  = json_decode( $contents, true );
if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $decoded['tools'] ) || ! is_array( $decoded['tools'] ) ) {
	echo "JSON_VALID no\n";
	exit( 1 );
}
echo "JSON_VALID yes\n";
echo 'SIZE ' . strlen( $contents ) . ' / limit ' . OMOCHIX_CORE_TOOL_UPDATE_MAX_BYTES . "\n";
echo 'ROWS ' . count( $decoded['tools'] ) . ' / limit ' . OMOCHIX_CORE_TOOL_UPDATE_MAX_ROWS . "\n";
echo 'SHA256 ' . hash( 'sha256', $contents ) . "\n";

$existing = omochix_core_existing_tool_slugs();
$errors   = 0;
$seen     = array();
foreach ( $decoded['tools'] as $i => $tool ) {
	$result = omochix_core_tool_update_validate_row( $tool, $i + 1, $existing );
	$slug   = $tool['slug'] ?? '?';
	if ( isset( $seen[ $slug ] ) ) {
		$result['errors'][] = 'duplicate slug';
	}
	$seen[ $slug ] = true;
	if ( $result['errors'] ) {
		$errors += count( $result['errors'] );
		echo "ERROR {$slug}\n";
		foreach ( $result['errors'] as $e ) {
			echo "    {$e}\n";
		}
		continue;
	}
	$row     = $result['row'];
	$fields  = array_column( $row['changes'], 'field' );
	$altered = array();
	// Did a sanitizer silently change what we wrote?
	foreach ( $tool as $key => $raw ) {
		if ( 'slug' === $key || ! array_key_exists( $key, $row ) ) {
			continue;
		}
		$clean = $row[ $key ];
		if ( 'post_content' === $key ) {
			if ( trim( $clean ) !== trim( $raw ) ) {
				$altered[] = $key;
			}
		} elseif ( $clean !== $raw ) {
			$altered[] = $key;
		}
	}
	printf( "OK    %-12s ID %-4d action=%-9s changes=%2d  sanitizer-altered=%s\n", $slug, $row['post_id'], $row['action'], count( $fields ), $altered ? implode( ',', $altered ) : 'none' );
	echo '      ' . implode( ', ', $fields ) . "\n";
}
$GLOBALS['shortcode_tags'] = array();
foreach ( $decoded['tools'] as $tool ) { $t = wptexturize( $tool['post_content'] ); echo 'WPTEXTURIZE_CHANGES ' . ( $t === $tool['post_content'] ? 0 : 1 ) . "\n"; }
echo "VALIDATOR_ERRORS {$errors}\n";
exit( $errors ? 1 : 0 );
