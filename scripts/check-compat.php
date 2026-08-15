<?php
/**
 * Verifies Hoobert against whatever WordPress the stack is running.
 *
 * This is the compatibility checklist from docs/wordpress-org-submission.md as a
 * script, so declaring a new "Tested up to" is one command instead of a round of
 * manual poking. Run it at both ends of the supported range whenever the ceiling
 * moves; the version header it prints is the evidence for that document's table.
 *
 * It asserts the things that fail *silently*. The 6.5 failure mode is the reason
 * the enqueue checks are split in two: wp_enqueue_script() succeeds and
 * wp_script_is( 'hoobert', 'enqueued' ) returns true even when one dependency is
 * unregistered, and do_items() then emits nothing. No warning, no notice, no
 * console error, no command bar. Asking only "is it enqueued?" would pass.
 *
 * Run it with:
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/check-compat.php
 *
 * Exits non-zero if any check fails.
 *
 * @package Hoobert
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	exit( "This script runs under WP-CLI (wp eval-file).\n" );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

$hoobert_failures = array();

/**
 * Record and print one check. `$detail` is appended in parentheses and should
 * carry the measured value, so a failing line says what it actually saw.
 */
function hoobert_check( string $label, bool $ok, string $detail = '' ): void {
	global $hoobert_failures;

	if ( ! $ok ) {
		$hoobert_failures[] = $label;
	}

	$suffix = '' === $detail ? '' : "  ({$detail})";
	WP_CLI::line( sprintf( '%s  %-46s%s', $ok ? '  ok  ' : ' FAIL ', $label, $suffix ) );
}

WP_CLI::line( '' );
WP_CLI::line( '=== Hoobert compatibility check ===' );
WP_CLI::line( '' );
WP_CLI::line( '  WordPress   ' . get_bloginfo( 'version' ) );
WP_CLI::line( '  WooCommerce ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'not active' ) );
WP_CLI::line( '  PHP         ' . PHP_VERSION );
WP_CLI::line( '  Hoobert     ' . ( defined( 'HOOBERT_VERSION' ) ? HOOBERT_VERSION : 'not loaded' ) );
WP_CLI::line( '' );

// --- The plugins are on -------------------------------------------------------

hoobert_check( 'WooCommerce is active', class_exists( 'WooCommerce' ) );
hoobert_check( 'Hoobert is active', is_plugin_active( 'hoobert/hoobert.php' ) );

// --- The tool set loads -------------------------------------------------------

$hoobert_tool_count = class_exists( 'Hoobert_Tools' ) ? count( Hoobert_Tools::all() ) : 0;
hoobert_check(
	'tools.json parses to 28 tools',
	28 === $hoobert_tool_count,
	$hoobert_tool_count . ' tools'
);

// --- Core still registers every script the bundle depends on ------------------

// Reading build/index.asset.php rather than a hard-coded list keeps this honest
// when the bundle's dependencies change.
$hoobert_asset_file = defined( 'HOOBERT_PATH' ) ? HOOBERT_PATH . 'build/index.asset.php' : '';
$hoobert_built      = $hoobert_asset_file && file_exists( $hoobert_asset_file );
hoobert_check( 'the bundle is built', $hoobert_built, $hoobert_built ? 'build/index.asset.php' : 'run npm run build' );

$hoobert_deps = $hoobert_built ? ( require $hoobert_asset_file )['dependencies'] : array();
foreach ( $hoobert_deps as $hoobert_dep ) {
	hoobert_check( "core registers {$hoobert_dep}", (bool) wp_scripts()->query( $hoobert_dep, 'registered' ) );
}

// --- The bundle enqueues, and is actually printed -----------------------------

// The enqueue is gated on manage_woocommerce and hangs off admin_enqueue_scripts,
// so stand in as an administrator and fire the hook the way an admin screen would.
$hoobert_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( $hoobert_admins ) {
	wp_set_current_user( (int) $hoobert_admins[0] );
}
hoobert_check( 'an administrator can manage_woocommerce', current_user_can( 'manage_woocommerce' ) );

// Firing the real hook is the faithful test, but it also runs every other
// plugin's listener, and those expect an admin screen. Stand one up first, or
// they warn their way through a run that has nothing to do with them.
set_current_screen( 'index.php' );
do_action( 'admin_enqueue_scripts', 'index.php' );

hoobert_check( 'the hoobert script enqueues', wp_script_is( 'hoobert', 'enqueued' ) );
hoobert_check( 'the hoobert style enqueues', wp_style_is( 'hoobert', 'enqueued' ) );

ob_start();
wp_scripts()->do_items( 'hoobert' );
$hoobert_script_html = ob_get_clean();

ob_start();
wp_styles()->do_items( 'hoobert' );
$hoobert_style_html = ob_get_clean();

hoobert_check(
	'the script tag is actually printed',
	false !== strpos( $hoobert_script_html, 'build/index.js' ),
	strlen( $hoobert_script_html ) . ' bytes'
);
hoobert_check(
	'the style tag is actually printed',
	false !== strpos( $hoobert_style_html, 'build/style-index.css' ),
	strlen( $hoobert_style_html ) . ' bytes'
);

// --- The plugin's own REST routes register ------------------------------------

$hoobert_routes = array_keys( rest_get_server()->get_routes() );
foreach ( array( '/hoobert/v1/resolve', '/hoobert/v1/execute', '/hoobert/v1/history' ) as $hoobert_route ) {
	hoobert_check( "route {$hoobert_route} registers", in_array( $hoobert_route, $hoobert_routes, true ) );
}

// --- The history table exists -------------------------------------------------

global $wpdb;
$hoobert_table = $wpdb->prefix . 'hoobert_history';
hoobert_check(
	'the history table exists',
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a dev-only existence probe, no cache to prime.
	(bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hoobert_table ) ),
	$hoobert_table
);

// --- Verdict ------------------------------------------------------------------

WP_CLI::line( '' );

if ( $hoobert_failures ) {
	WP_CLI::error( count( $hoobert_failures ) . ' check(s) failed: ' . implode( ', ', $hoobert_failures ) );
}

WP_CLI::success(
	sprintf(
		'All checks pass on WordPress %s / WooCommerce %s / PHP %s.',
		get_bloginfo( 'version' ),
		defined( 'WC_VERSION' ) ? WC_VERSION : '-',
		PHP_VERSION
	)
);
