<?php
/**
 * TrustScript Template Loader
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Template_Loader {

	/**
	 * Base directory for simple review templates, relative to the plugin root.
	 */
	const VIEWS_BASE = 'includes/simple-review/views/';

	/**
	 * Render a template by name, passing data variables to it.
	 */
	public static function render( $template, array $data = array() ) {
		$path = self::resolve( $template );
		if ( ! $path ) {
			return;
		}
		self::load( $path, $data );
	}

	/**
	 * Capture the output of a template as a string, instead of echoing it directly.
	 */
	public static function capture( $template, array $data = array() ) {
		$path = self::resolve( $template );
		if ( ! $path ) {
			return '';
		}
		ob_start();
		self::load( $path, $data );
		return ob_get_clean();
	}

	/**
	 * Resolve a template name to an absolute file path. Returns false if the template doesn't exist.
	 */
	private static function resolve( $template ) {
		$template = ltrim( str_replace( '.php', '', $template ), '/' );

		$path = TRUSTSCRIPT_PLUGIN_PATH . self::VIEWS_BASE . $template . '.php';

		if ( ! file_exists( $path ) ) {
			return false;
		}

		return $path;
	}

	/**
	 * Load a template file and make the provided data variables available within its scope.
	 */
	private static function load( $path, array $data ) {
		( static function ( $__path, $data ) {
			include $__path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- path validated in resolve().
		} )( $path, $data );
	}
}
