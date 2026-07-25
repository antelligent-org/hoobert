<?php
/**
 * Loads and indexes the WooCommerce tool set shipped with the plugin.
 *
 * The bundled tools.json lives in the plugin root and carries, per tool, an
 * `x-woo` extension describing how to dispatch it against the REST API; the
 * executor reads that mapping. The same tool set is registered with the Fern
 * inference project so the model and the executor stay in sync.
 *
 * @package Hoobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hoobert_Tools {

	/**
	 * Cached decoded tool set.
	 *
	 * @var array|null
	 */
	private static $tools = null;

	/**
	 * Return the full tool set as an associative array, or an empty array if the
	 * bundled JSON is missing/invalid.
	 */
	public static function all(): array {
		if ( null !== self::$tools ) {
			return self::$tools;
		}

		$path = self::tools_path();
		if ( ! $path || ! file_exists( $path ) ) {
			self::$tools = array();
			return self::$tools;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file shipped inside the plugin, not a remote URL.
		$decoded     = json_decode( (string) file_get_contents( $path ), true );
		self::$tools = ( is_array( $decoded ) && isset( $decoded['tools'] ) ) ? $decoded['tools'] : array();
		return self::$tools;
	}

	/**
	 * Look up a single tool definition by its function name.
	 */
	public static function find( string $name ): ?array {
		foreach ( self::all() as $tool ) {
			if ( isset( $tool['function']['name'] ) && $tool['function']['name'] === $name ) {
				return $tool;
			}
		}
		return null;
	}

	/**
	 * Locate the tools.json shipped in the plugin root.
	 */
	private static function tools_path(): ?string {
		$path = HOOBERT_PATH . 'tools.json';
		return file_exists( $path ) ? $path : null;
	}
}
