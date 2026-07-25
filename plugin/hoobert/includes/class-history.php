<?php
/**
 * History of executed Hoobert commands, stored in a custom table.
 *
 * Each run of a resolved tool call through /execute is recorded: which admin ran
 * it, the natural-language query, the WooCommerce REST request that ran, whether
 * it succeeded, and any error. A dedicated table keeps the
 * log unbounded so the settings page can show the full store-wide audit trail; the
 * command palette panel reads just the current user's most recent entries.
 *
 * @package Hoobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hoobert_History {

	const TABLE       = 'hoobert_history';
	const DB_VERSION  = '1';
	const VERSION_OPT = 'hoobert_db_version';
	const PANEL_LIMIT = 25;

	/**
	 * Fully-qualified table name (with the site's table prefix).
	 */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or upgrade the history table. Safe to call repeatedly; dbDelta only
	 * applies diffs.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			query TEXT NOT NULL,
			tool VARCHAR(191) NOT NULL DEFAULT '',
			request LONGTEXT NOT NULL,
			status SMALLINT NOT NULL DEFAULT 0,
			ok TINYINT(1) NOT NULL DEFAULT 0,
			error TEXT NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset;";

		dbDelta( $sql );
		update_option( self::VERSION_OPT, self::DB_VERSION );
	}

	/**
	 * Install the table when it is missing or out of date. Cheap enough to call on
	 * every boot: it is a single option read until the version actually changes.
	 */
	public static function maybe_install(): void {
		if ( get_option( self::VERSION_OPT ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Record one executed command against the current user.
	 *
	 * @param array $entry {
	 *     @type string $query   The merchant utterance.
	 *     @type string $tool    Tool name the model chose.
	 *     @type array  $request The REST request that ran (method, route, params).
	 *     @type int    $status  HTTP status returned by WooCommerce.
	 *     @type bool   $ok      Whether the request succeeded.
	 *     @type string $error   Error message when it failed ('' otherwise).
	 * }
	 */
	public static function record( array $entry ): void {
		global $wpdb;
		self::maybe_install();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- writing to the plugin's own audit table.
		$wpdb->insert(
			self::table(),
			array(
				'user_id'    => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
				'query'      => (string) ( $entry['query'] ?? '' ),
				'tool'       => (string) ( $entry['tool'] ?? '' ),
				'request'    => (string) wp_json_encode( (array) ( $entry['request'] ?? array() ) ),
				'status'     => (int) ( $entry['status'] ?? 0 ),
				'ok'         => empty( $entry['ok'] ) ? 0 : 1,
				'error'      => (string) ( $entry['error'] ?? '' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * The current user's most recent entries, newest first, for the palette panel.
	 *
	 * @param int $limit   Max entries.
	 * @param int $user_id Optional user id; 0 uses the current user.
	 * @return array<int,array>
	 */
	public static function recent( int $limit = self::PANEL_LIMIT, int $user_id = 0 ): array {
		global $wpdb;
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned audit table with no core API; the only interpolation is the table name, which is the site prefix plus a class constant.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
				"SELECT * FROM `{$table}` WHERE user_id = %d ORDER BY id DESC LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'format_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Every entry across all users, newest first, for the settings audit log.
	 *
	 * @param int $limit Optional cap; 0 means no limit.
	 * @return array<int,array>
	 */
	public static function all( int $limit = 0 ): array {
		global $wpdb;
		$table = self::table();
		// A 0 limit means "everything"; MySQL has no unbounded LIMIT, so use the
		// max row count instead of branching into a second, unprepared query.
		$limit = $limit > 0 ? $limit : PHP_INT_MAX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned audit table with no core API; the only interpolation is the table name, which is the site prefix plus a class constant.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
				"SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'format_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Shape a raw DB row into the entry structure the front-end consumes: unix
	 * timestamp, decoded request, and typed scalars.
	 */
	private static function format_row( array $row ): array {
		$request = json_decode( (string) $row['request'], true );
		return array(
			'time'    => strtotime( $row['created_at'] . ' UTC' ),
			'user_id' => (int) $row['user_id'],
			'query'   => (string) $row['query'],
			'tool'    => (string) $row['tool'],
			'request' => is_array( $request ) ? $request : array(),
			'status'  => (int) $row['status'],
			'ok'      => (bool) (int) $row['ok'],
			'error'   => (string) $row['error'],
		);
	}
}
