<?php
/**
 * Web 2.0 module loader, Core side (docs/web2/hooks/README.md).
 *
 * Loads the Core half of each Web 2.0 module (glossary, ...) from a fixed
 * list in this file, one module at a time, so a broken or half-uploaded
 * module switches only itself off. omochix-core.php requires this file last,
 * guarded; the theme has its own loader (inc/web2-loader.php) and never
 * loads plugin files, and this loader never loads theme files.
 *
 * What it does NOT do: register routes, rewrite rules, REST endpoints, admin
 * pages, capabilities or options; read request data; write to the database;
 * decide whether anything is public (each module's own gate does, default
 * off). With the list empty it calls no WordPress function and adds no hook.
 *
 * A module is loaded only when ALL of these hold (otherwise it is skipped and
 * its reason recorded):
 * - OMOCHIX_WEB2_DISABLED is not true (stops every module, Core and theme);
 * - its list entry says 'enabled' => true (the default is off) and the
 *   omochix_web2_module_enabled filter still returns exactly true (a filter
 *   can only switch a module off, never add or switch one on);
 * - the id is [a-z][a-z0-9-]* and the file is includes/web2/<name>.php: no
 *   other folder, no "..", no symbolic link anywhere below the plugin folder;
 * - it is a regular, readable file, at most 512 KB;
 * - its second line is "// omochix-web2-module: <id> api=<N>" and it ends
 *   with "// omochix-web2-module: <id> api=<N> (end)" (a file still being
 *   uploaded, or one for another module, fails), and N is this loader's API;
 * - it parses (token_get_all with TOKEN_PARSE: a syntax error is found
 *   before the file runs), declares no namespace and prints nothing outside
 *   PHP code;
 * - every function and class it declares starts with "omochix_", is
 *   declared once, and does not exist yet. PHP cannot recover from
 *   redeclaring a function (a fatal error, not an exception, even inside
 *   try/catch), so this is checked BEFORE the file is loaded.
 * The file is then required inside try/catch and an output buffer. When it
 * throws or prints, every hook callback defined in it is removed again (the
 * functions stay defined; PHP cannot undefine them, and nothing calls them).
 *
 * Not caught by any of this (PHP stops the request): compile errors that
 * parse (a class declaring one method twice ...; Lint PHP in CI catches them
 * in the repository), exit/die, memory or time limits while the file runs.
 * Module files only declare functions and add hooks while loading.
 *
 * @package OmochiXCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A second copy of this file (another path, e.g. a stray plugin folder) must
// not redeclare the functions below: they are declared only inside this block,
// which PHP binds at run time, not when it compiles the file.
if ( ! defined( 'OMOCHIX_CORE_WEB2_LOADER_API' ) ) {

	/** Module API this loader supports; a module's marker must name it. */
	define( 'OMOCHIX_CORE_WEB2_LOADER_API', 1 );

	/**
	 * The fixed list of Core modules: id => array( 'file' => relative path,
	 * 'enabled' => bool ). Edited only by a reviewed hooks pull request, after
	 * the module's own files are on the integration branch. Nothing can add to
	 * it at run time.
	 *
	 * @return array<string, array{file: string, enabled: bool}>
	 */
	function omochix_core_web2_modules() {
		return array();
	}

	/**
	 * Loader state (one per request).
	 *
	 * @param array|null $set New state (internal).
	 * @return array{ran: bool, disabled: bool, modules: array<string, array{state: string, file: string}>}
	 */
	function omochix_core_web2_state( $set = null ) {
		static $state = array(
			'ran'      => false,
			'disabled' => false,
			'modules'  => array(),
		);
		if ( is_array( $set ) ) {
			$state = $set;
		}
		return $state;
	}

	/**
	 * Why a module file must not be loaded, or '' when it may.
	 *
	 * @param string $id   Module id.
	 * @param string $file Path relative to $dir.
	 * @param string $dir  Plugin directory.
	 * @return string Reason code.
	 */
	function omochix_core_web2_check( $id, $file, $dir ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-z][a-z0-9-]{0,39}$/D', $id ) ) {
			return 'bad-id';
		}
		if ( ! is_string( $file ) || ! preg_match( '#^includes/web2/[a-z0-9][a-z0-9-]{0,63}\.php$#D', $file ) ) {
			return 'bad-path';
		}
		$root = realpath( (string) $dir );
		if ( false === $root || ! is_dir( $root ) ) {
			return 'bad-path';
		}
		$path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $file );
		if ( ! file_exists( $path ) && ! is_link( $path ) ) {
			return 'missing';
		}
		// realpath() resolves every link: equal only for a plain path below the plugin.
		if ( is_link( $path ) || realpath( $path ) !== $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return 'bad-path';
		}
		$size = filesize( $path );
		if ( false === $size || $size > 524288 ) {
			return 'too-large';
		}
		$code = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $code ) ) {
			return 'missing';
		}
		$code = str_replace( "\r\n", "\n", $code );
		if ( ! preg_match( '#^<\?php\n// omochix-web2-module: ([a-z][a-z0-9-]{0,39}) api=([0-9]{1,4})\n#', $code, $m ) || $m[1] !== $id ) {
			return 'bad-marker';
		}
		if ( ! preg_match( '#\n// omochix-web2-module: ' . preg_quote( $id, '#' ) . ' api=' . $m[2] . ' \(end\)\s*$#', $code ) ) {
			return 'bad-marker';
		}
		if ( (int) $m[2] !== OMOCHIX_CORE_WEB2_LOADER_API ) {
			return 'api-mismatch';
		}
		$names = omochix_core_web2_declarations( $code );
		if ( ! is_array( $names ) ) {
			return $names;
		}
		foreach ( $names['functions'] as $name ) {
			if ( function_exists( $name ) ) {
				return 'redeclare';
			}
		}
		foreach ( $names['classes'] as $name ) {
			if ( class_exists( $name, false ) || interface_exists( $name, false ) || trait_exists( $name, false ) || ( function_exists( 'enum_exists' ) && enum_exists( $name, false ) ) ) {
				return 'redeclare';
			}
		}
		return '';
	}

	/**
	 * Functions and classes a module file declares, read with the tokenizer
	 * (nothing runs). A reason code instead when the file must not load.
	 *
	 * @param string $code File contents.
	 * @return array{functions: string[], classes: string[]}|string
	 */
	function omochix_core_web2_declarations( $code ) {
		try {
			$tokens = token_get_all( $code, TOKEN_PARSE );
		} catch ( Throwable $e ) {
			return 'syntax';
		}
		$skip      = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );
		$functions = array();
		$classes   = array();
		$depth     = 0;
		$bodies    = array(); // Brace depths at which a class-like body opened.
		$pending   = false;   // A class-like name was read; its body opens at the next "{".
		$count     = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$t  = $tokens[ $i ];
			$id = is_array( $t ) ? $t[0] : $t;
			if ( '{' === $id || T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id ) {
				++$depth;
				if ( $pending ) {
					$bodies[] = $depth;
					$pending  = false;
				}
				continue;
			}
			if ( '}' === $id ) {
				if ( $bodies && end( $bodies ) === $depth ) {
					array_pop( $bodies );
				}
				--$depth;
				continue;
			}
			if ( T_NAMESPACE === $id ) {
				return 'namespace';
			}
			if ( T_INLINE_HTML === $id && 0 === $depth ) {
				return 'output';
			}
			if ( ! in_array( $id, array( T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM ), true ) ) {
				continue;
			}
			// Previous and next meaningful tokens.
			$prev = null;
			for ( $j = $i - 1; $j >= 0; $j-- ) {
				$p = is_array( $tokens[ $j ] ) ? $tokens[ $j ][0] : $tokens[ $j ];
				if ( ! in_array( $p, $skip, true ) ) {
					$prev = $p;
					break;
				}
			}
			$name = null;
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$n = $tokens[ $j ];
				$k = is_array( $n ) ? $n[0] : $n;
				if ( in_array( $k, $skip, true ) || '&' === $k || T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG === $k || T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG === $k ) {
					continue;
				}
				$name = ( T_STRING === $k ) ? $n[1] : null;
				break;
			}
			if ( T_FUNCTION === $id ) {
				// Methods (inside a class-like body), closures and "use function" are not declarations.
				if ( $bodies || null === $name || T_USE === $prev ) {
					continue;
				}
				$functions[] = $name;
				continue;
			}
			// T_CLASS: skip Foo::class and anonymous "new class".
			if ( T_DOUBLE_COLON === $prev || T_NEW === $prev || null === $name ) {
				if ( T_NEW === $prev ) {
					$pending = true;
				}
				continue;
			}
			$classes[] = $name;
			$pending   = true;
		}
		$lower = array_map( 'strtolower', array_merge( $functions, $classes ) );
		if ( count( $lower ) !== count( array_unique( $lower ) ) ) {
			return 'redeclare';
		}
		foreach ( $lower as $name ) {
			if ( 0 !== strpos( $name, 'omochix_' ) ) {
				return 'prefix';
			}
		}
		return array(
			'functions' => $functions,
			'classes'   => $classes,
		);
	}

	/**
	 * Remove every hook callback whose function is defined in $path, and any
	 * callback that can no longer be called.
	 *
	 * @param string $path Absolute path of the module file.
	 * @return void
	 */
	function omochix_core_web2_unhook( $path ) {
		if ( ! function_exists( 'remove_filter' ) ) {
			return;
		}
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
						if ( ! is_callable( $fn ) ) {
							remove_filter( $hook, $fn, $priority );
						}
						continue;
					}
					if ( $ref->getFileName() === $path ) {
						remove_filter( $hook, $fn, $priority );
					}
				}
			}
		}
	}

	/**
	 * Load the given modules (each at most once per request).
	 *
	 * Called with omochix_core_web2_modules() by omochix_core_web2_boot();
	 * tests call it with fixtures. Never pass request data.
	 *
	 * @param array  $modules id => array( 'file' => ..., 'enabled' => ... ).
	 * @param string $dir     Plugin directory.
	 * @return array Loader state.
	 */
	function omochix_core_web2_load_modules( array $modules, $dir ) {
		$state        = omochix_core_web2_state();
		$state['ran'] = true;
		if ( defined( 'OMOCHIX_WEB2_DISABLED' ) && OMOCHIX_WEB2_DISABLED ) {
			$state['disabled'] = true;
			foreach ( $modules as $id => $entry ) {
				if ( ! isset( $state['modules'][ (string) $id ] ) ) {
					$state['modules'][ (string) $id ] = array(
						'state' => 'disabled',
						'file'  => '',
					);
				}
			}
			return omochix_core_web2_state( $state );
		}
		foreach ( $modules as $id => $entry ) {
			$id = (string) $id;
			if ( isset( $state['modules'][ $id ] ) ) {
				continue; // Tried already this request: never load twice.
			}
			$file   = is_array( $entry ) && isset( $entry['file'] ) ? $entry['file'] : null;
			$on     = is_array( $entry ) && isset( $entry['enabled'] ) && true === $entry['enabled'];
			$reason = '';
			if ( ! $on ) {
				$reason = 'off';
			} elseif ( function_exists( 'apply_filters' ) && true !== apply_filters( 'omochix_web2_module_enabled', true, $id, 'core' ) ) {
				$reason = 'off';
			} else {
				$reason = omochix_core_web2_check( $id, $file, $dir );
			}
			if ( '' === $reason ) {
				$path = realpath( (string) $dir ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $file );
				$level = ob_get_level();
				ob_start();
				try {
					require_once $path;
					if ( '' !== (string) ob_get_contents() ) {
						$reason = 'output';
					}
				} catch ( Throwable $e ) {
					$reason = 'error';
				}
				while ( ob_get_level() > $level ) {
					ob_end_clean();
				}
				if ( '' !== $reason ) {
					omochix_core_web2_unhook( $path );
				}
			}
			$state['modules'][ $id ] = array(
				'state' => '' === $reason ? 'loaded' : $reason,
				'file'  => is_string( $file ) ? $file : '',
			);
		}
		$state = omochix_core_web2_state( $state );
		foreach ( $state['modules'] as $module ) {
			if ( ! in_array( $module['state'], array( 'loaded', 'off', 'disabled' ), true ) && function_exists( 'add_action' ) ) {
				add_action( 'admin_notices', 'omochix_core_web2_notice' );
				break;
			}
		}
		return $state;
	}

	/**
	 * Load the Core modules once per request.
	 *
	 * @return array Loader state.
	 */
	function omochix_core_web2_boot() {
		$state = omochix_core_web2_state();
		if ( $state['ran'] ) {
			return $state;
		}
		return omochix_core_web2_load_modules( omochix_core_web2_modules(), dirname( __DIR__ ) );
	}

	/**
	 * Whether a Core module is loaded. Theme modules that need their Core half
	 * check function_exists( 'omochix_core_web2_module_loaded' ) first.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	function omochix_core_web2_module_loaded( $id ) {
		return 'loaded' === omochix_core_web2_module_status( $id );
	}

	/**
	 * State of a Core module: loaded, off, disabled, unknown, or why it was
	 * skipped (missing, bad-id, bad-path, too-large, bad-marker, api-mismatch,
	 * syntax, namespace, output, prefix, redeclare, error).
	 *
	 * @param string $id Module id.
	 * @return string
	 */
	function omochix_core_web2_module_status( $id ) {
		$state = omochix_core_web2_state();
		return (string) ( $state['modules'][ (string) $id ]['state'] ?? 'unknown' );
	}

	/** Tell administrators which Core modules were skipped (ids and reasons only, no paths). */
	function omochix_core_web2_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$list = array();
		foreach ( omochix_core_web2_state()['modules'] as $id => $module ) {
			if ( ! in_array( $module['state'], array( 'loaded', 'off', 'disabled' ), true ) ) {
				$list[] = $id . ' (' . $module['state'] . ')';
			}
		}
		if ( ! $list ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html(
			sprintf(
				/* translators: %s: module ids and reasons */
				__( 'OmochiX Core：Web 2.0 のモジュールを読み込めなかったため停止しています（%s）。プラグインのファイルをすべてアップロードし直してください。', 'omochix-core' ),
				implode( ', ', $list )
			)
		) . '</p></div>';
	}

	omochix_core_web2_boot();
}
