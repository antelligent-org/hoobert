<?php
/**
 * Removes everything Hoobert stored when the plugin is deleted from the Plugins
 * screen: its settings (endpoint, API key, shared-model install token), the
 * schema and legacy-adoption markers, and the command-history table.
 *
 * Deactivating leaves all of it in place. The data the plugin's former name,
 * Woobert, left behind is not Hoobert's to delete, so it stays too.
 *
 * @package Hoobert
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-history.php';
require_once __DIR__ . '/includes/class-migrate.php';

/**
 * Drop the current site's Hoobert options and history table.
 */
function hoobert_uninstall_site(): void {
	global $wpdb;

	delete_option( Hoobert_Settings::OPTION_NAME );
	delete_option( Hoobert_History::VERSION_OPT );
	delete_option( Hoobert_Migrate::DONE_OPTION );

	$table = $wpdb->prefix . Hoobert_History::TABLE;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dropping the plugin's own table on uninstall; the name is the site prefix plus a class constant.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $hoobert_site_id ) {
		switch_to_blog( $hoobert_site_id );
		hoobert_uninstall_site();
		restore_current_blog();
	}
} else {
	hoobert_uninstall_site();
}
