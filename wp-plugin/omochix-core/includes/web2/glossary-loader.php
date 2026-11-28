<?php
// omochix-web2-module: glossary api=1
/**
 * AI glossary (Knowledge Hub) module, Core side: entry file.
 *
 * Loaded only by the Web 2.0 loader (includes/web2-loader.php, hooks
 * stream) once the hooks stream lists it; nothing else requires it. It loads
 * the glossary files all or nothing: each carries
 * "// omochix-glossary-version: <N>" on its second and last lines, and all
 * are checked before any is loaded, so a half-done upload or a file from
 * another version switches the glossary off instead of breaking the site.
 * The marker follows OMOCHIX_CORE_GLOSSARY_VERSION, not the plugin version
 * (integration branches cannot change version numbers).
 *
 * Closed by default: until a reviewed release makes the
 * omochix_core_glossary_public filter return true, the post type and the
 * category taxonomy are registered for editors only (no URL, no archive, no
 * query var, no search, no feed, no REST for anyone else, no sitemap).
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OMOCHIX_CORE_GLOSSARY_VERSION' ) ) {
	define( 'OMOCHIX_CORE_GLOSSARY_VERSION', 1 );
}

/**
 * Glossary files, in load order (paths relative to the plugin directory).
 *
 * @return string[]
 */
function omochix_core_glossary_files() {
	return array(
		'includes/web2/glossary-validate.php',
		'includes/web2/glossary-post-type.php',
		'includes/web2/glossary-caps.php',
		'includes/web2/glossary-meta.php',
		'includes/web2/glossary-visibility.php',
		'includes/web2/glossary-guard.php',
		'admin/web2/glossary-admin.php',
	);
}

/**
 * Whether a glossary file is present, a regular file below the plugin, and
 * completely written for this version (second and last line markers).
 *
 * @param string $dir  Plugin directory (trailing slash).
 * @param string $file Relative path.
 * @return bool
 */
function omochix_core_glossary_file_ok( $dir, $file ) {
	$root = realpath( $dir );
	$path = $root ? $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $file ) : '';
	if ( '' === $path || is_link( $path ) || realpath( $path ) !== $path || ! is_file( $path ) || ! is_readable( $path ) ) {
		return false;
	}
	$code = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! is_string( $code ) || strlen( $code ) > 524288 ) {
		return false;
	}
	$code = str_replace( "\r\n", "\n", $code );
	$mark = '// omochix-glossary-version: ' . OMOCHIX_CORE_GLOSSARY_VERSION;
	return 0 === strpos( $code, "<?php\n" . $mark . "\n" ) && 1 === preg_match( '#\n' . preg_quote( $mark, '#' ) . ' \(end\)\s*$#', $code );
}

/**
 * Remove every hook callback defined in the given files, and any glossary
 * callback that can no longer be called (its file failed before defining it).
 * Callbacks of anyone else are never touched, even when their function does
 * not exist yet (WordPress defines its pluggable functions after loading the
 * plugins; removing wp_validate_auth_cookie logged every user out).
 *
 * @param string[] $paths Absolute paths.
 * @return void
 */
function omochix_core_glossary_unhook( array $paths ) {
	if ( ! function_exists( 'remove_filter' ) ) {
		return;
	}
	$paths = array_filter( array_map( 'realpath', $paths ) );
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
				} catch ( Throwable $e ) {
					// Only the glossary's own functions: a WordPress function that is not defined yet
					// (pluggable.php loads after the plugins: wp_validate_auth_cookie ...) must stay hooked.
					if ( is_string( $fn ) && 0 === strpos( $fn, 'omochix_core_glossary_' ) && ! is_callable( $fn ) ) {
						remove_filter( $hook, $fn, $priority );
					}
					continue;
				}
				if ( in_array( $ref->getFileName(), $paths, true ) ) {
					remove_filter( $hook, $fn, $priority );
				}
			}
		}
	}
}

/**
 * Load the glossary (all or nothing). The state is kept for this request.
 *
 * @param string|null $dir Plugin directory with a trailing slash (tests).
 * @return array{loaded: bool, problems: string[]}
 */
function omochix_core_glossary_load( $dir = null ) {
	static $state = null;
	if ( null === $dir && null !== $state ) {
		return $state;
	}
	$dir      = null === $dir ? dirname( __DIR__, 2 ) . '/' : $dir;
	$problems = array();
	foreach ( omochix_core_glossary_files() as $file ) {
		if ( ! omochix_core_glossary_file_ok( $dir, $file ) ) {
			$problems[] = $file;
		}
	}
	if ( ! $problems ) {
		$loaded = array();
		$level  = ob_get_level();
		ob_start();
		try {
			foreach ( omochix_core_glossary_files() as $file ) {
				$loaded[] = realpath( $dir ) . '/' . $file;
				require_once realpath( $dir ) . '/' . $file;
			}
			if ( '' !== (string) ob_get_contents() ) {
				$problems[] = 'output';
			}
		} catch ( Throwable $e ) {
			$problems[] = $file;
		}
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}
		if ( $problems ) {
			omochix_core_glossary_unhook( $loaded );
		}
	}
	$state = array(
		'loaded'   => ! $problems,
		'problems' => $problems,
	);
	return $state;
}

/** Whether the glossary module is loaded (false after a half-done upload). */
function omochix_core_glossary_loaded() {
	return ! empty( omochix_core_glossary_load()['loaded'] );
}

/** Tell administrators why the glossary is off (file names only, no paths). */
function omochix_core_glossary_incomplete_notice() {
	if ( omochix_core_glossary_loaded() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html(
		sprintf(
			/* translators: %s: file names */
			__( 'OmochiX Core：AI用語集のファイルが不足しているか、更新の途中です（%s）。AI用語集を停止しています。プラグインのファイルをすべてアップロードし直してください。', 'omochix-core' ),
			implode( ', ', array_map( 'basename', omochix_core_glossary_load()['problems'] ) )
		)
	) . '</p></div>';
}

if ( ! omochix_core_glossary_load()['loaded'] ) {
	add_action( 'admin_notices', 'omochix_core_glossary_incomplete_notice' );
	return;
}

/**
 * Register the glossary data model (after Core's init 10 and Industry Hub's 11).
 *
 * @return void
 */
function omochix_core_glossary_init() {
	omochix_core_glossary_register_post_type();
	omochix_core_glossary_register_taxonomy();
	omochix_core_glossary_register_meta();
	omochix_core_glossary_maybe_flush_rewrite_rules();
}
add_action( 'init', 'omochix_core_glossary_init', 12 );
// omochix-web2-module: glossary api=1 (end)
