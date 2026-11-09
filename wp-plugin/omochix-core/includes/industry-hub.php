<?php
/**
 * Industry Hub loader (docs/Industry-Hub-Design.md).
 *
 * The only line the Industry Hub adds to omochix-core.php is the require of
 * this file, so the bootstrap and its existing init order stay untouched.
 * Everything registers on init priority 11: after omochix_core_init() (10)
 * has registered the existing post types the new taxonomies attach to.
 *
 * All or nothing: the module files depend on each other (constants at load
 * time, functions at run time), so they are loaded only when every one of
 * them is present, complete and from the same release. An FTP upload that
 * is half done (a file missing, still being written, or left over from an
 * older release) switches the Industry Hub off instead of breaking the
 * site; the rest of the plugin is not affected. Each module file carries
 * "// omochix-industry-hub-version: <OMOCHIX_CORE_VERSION>" on its second and
 * last lines, and the files are checked before any of them is loaded (a
 * left-over file could otherwise redeclare a function, which PHP cannot
 * recover from). The marker follows the plugin version, so a version bump
 * must update every module file; tests/industry-hub/partial-upload.php fails
 * when one is missed. Files replaced without a version bump are not
 * detected here: the release is checked against its SHA-256 manifest.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module files, in load order (a file may use at load time only constants of
 * the files before it).
 *
 * @return string[] Paths relative to the plugin directory.
 */
function omochix_core_industry_hub_files() {
	return array(
		'includes/industry-taxonomies.php',
		'includes/provider.php',
		'includes/industry-term-meta.php',
		'admin/industry-term-fields.php',
		'includes/resource-post-type.php',
		'includes/resource-meta-schema.php',
		'admin/resource-meta-boxes.php',
		'includes/workflow-meta-schema.php',
		'admin/workflow-meta-panel.php',
		'includes/industry-relations.php',
		'includes/industry-visibility.php',
	);
}

/**
 * Whether a module file is present, completely written and from this
 * release: its second line and its last line carry the version marker.
 * Reads only the first and last few bytes.
 *
 * @param string $path Absolute path.
 * @return bool
 */
function omochix_core_industry_hub_file_ok( $path ) {
	$handle = is_readable( $path ) ? fopen( $path, 'rb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $handle ) {
		return false;
	}
	$head = (string) fread( $handle, 160 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
	$stat = fstat( $handle );
	$size = is_array( $stat ) ? (int) $stat['size'] : 0;
	fseek( $handle, max( 0, $size - 80 ) );
	$tail = (string) fread( $handle, 80 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	$mark = '// omochix-industry-hub-version: ' . OMOCHIX_CORE_VERSION;
	return 0 === strpos( str_replace( "\r\n", "\n", $head ), "<?php\n" . $mark . "\n" ) && false !== strpos( $tail, $mark . ' (end)' );
}

/**
 * Remove every hook callback defined in the given files (a module file that
 * failed while loading leaves the earlier ones registered), and any callback
 * that cannot be called. Functions already defined stay defined (PHP cannot
 * undefine them); nothing calls them once their hooks are gone, and the
 * theme checks taxonomy_exists( 'omx_industry' ), which stays false.
 *
 * @param string[] $files Absolute paths.
 * @return void
 */
function omochix_core_industry_hub_unhook( array $files ) {
	$files = array_filter( array_map( 'realpath', $files ) );
	foreach ( (array) ( $GLOBALS['wp_filter'] ?? array() ) as $hook => $wp_hook ) {
		foreach ( (array) ( is_object( $wp_hook ) ? $wp_hook->callbacks : array() ) as $priority => $callbacks ) {
			foreach ( (array) $callbacks as $callback ) {
				$fn = $callback['function'] ?? null;
				try {
					if ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
						$ref = new ReflectionMethod( $fn );
					} elseif ( is_array( $fn ) && 2 === count( $fn ) ) {
						$ref = new ReflectionMethod( $fn[0], (string) $fn[1] );
					} elseif ( is_string( $fn ) || $fn instanceof Closure ) {
						$ref = new ReflectionFunction( $fn );
					} else {
						continue;
					}
				} catch ( ReflectionException $e ) {
					// A callback to a function that never got defined (its file
					// failed) would be a fatal error when the hook runs.
					if ( ! is_callable( $fn ) ) {
						remove_filter( $hook, $fn, $priority );
					}
					continue;
				}
				if ( in_array( $ref->getFileName(), $files, true ) ) {
					remove_filter( $hook, $fn, $priority );
				}
			}
		}
	}
}

/**
 * Load the module (all or nothing).
 *
 * @param string $dir Plugin directory with a trailing slash (tests).
 * @return array{loaded: bool, problems: string[]}
 */
function omochix_core_industry_hub_load( $dir = OMOCHIX_CORE_DIR ) {
	$problems = array();
	foreach ( omochix_core_industry_hub_files() as $file ) {
		if ( ! omochix_core_industry_hub_file_ok( $dir . $file ) ) {
			$problems[] = $file;
		}
	}
	if ( ! $problems ) {
		$loaded = array();
		try {
			foreach ( omochix_core_industry_hub_files() as $file ) {
				$loaded[] = $dir . $file;
				require_once $dir . $file;
			}
		} catch ( Throwable $e ) {
			omochix_core_industry_hub_unhook( $loaded );
			$problems[] = $file;
		}
	}
	$GLOBALS['omochix_core_industry_hub_status'] = array( 'loaded' => ! $problems, 'problems' => $problems );
	return $GLOBALS['omochix_core_industry_hub_status'];
}

/** Whether the Industry Hub module is loaded (false after a half-done upload). */
function omochix_core_industry_hub_loaded() {
	return ! empty( $GLOBALS['omochix_core_industry_hub_status']['loaded'] );
}

/** Tell administrators why the Industry Hub is off. */
function omochix_core_industry_hub_incomplete_notice() {
	if ( omochix_core_industry_hub_loaded() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$problems = (array) ( $GLOBALS['omochix_core_industry_hub_status']['problems'] ?? array() );
	echo '<div class="notice notice-error"><p>' . esc_html(
		sprintf(
			/* translators: %s: file names */
			__( 'OmochiX Core：Industry Hub のファイルが不足しているか、更新の途中です（%s）。Industry Hub を停止しています。プラグインのファイルをすべてアップロードし直してください。', 'omochix-core' ),
			implode( ', ', $problems )
		)
	) . '</p></div>';
}
add_action( 'admin_notices', 'omochix_core_industry_hub_incomplete_notice' );

if ( ! omochix_core_industry_hub_load()['loaded'] ) {
	return;
}

/**
 * Register the Industry Hub data model.
 *
 * @return void
 */
function omochix_core_industry_hub_init() {
	omochix_core_register_resource_post_type();
	omochix_core_register_resource_post_meta();
	omochix_core_register_industry_taxonomies();
	omochix_core_register_industry_term_meta();
	omochix_core_register_workflow_post_meta();
	omochix_core_industry_maybe_flush_rewrite_rules();
}
add_action( 'init', 'omochix_core_industry_hub_init', 11 );
