<?php
/**
 * Plugin Name:       Hoobert
 * Plugin URI:        https://hoobert.fernfly.com
 * Description:       Agentic command bar for WooCommerce merchants. Get stuff done at the speed of thought.
 * Version:           0.2.3
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Author:            Fernfly
 * Author URI:        https://fernfly.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hoobert
 * WC requires at least: 8.0
 *
 * @package Hoobert
 *
 * Press Cmd/Ctrl-K anywhere in wp-admin, type a request in plain English, and Hoobert
 * maps it to a WooCommerce REST API v3 tool call and runs it, server-side, under the
 * current admin's capabilities. The natural-language step is handled by a small
 * function-calling model (Fern); tool schemas live in tools.json.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'HOOBERT_VERSION', '0.2.3' );
define( 'HOOBERT_PATH', plugin_dir_path( __FILE__ ) );
define( 'HOOBERT_URL', plugin_dir_url( __FILE__ ) );

require_once HOOBERT_PATH . 'includes/class-tools.php';
require_once HOOBERT_PATH . 'includes/class-executor.php';
require_once HOOBERT_PATH . 'includes/class-fern-client.php';
require_once HOOBERT_PATH . 'includes/class-history.php';
require_once HOOBERT_PATH . 'includes/class-rest-proxy.php';
require_once HOOBERT_PATH . 'includes/class-settings.php';
require_once HOOBERT_PATH . 'includes/class-migrate.php';

/**
 * Create the history table on activation (and lazily on upgrade, see below),
 * then adopt anything left behind by the plugin's former name.
 */
register_activation_hook(
	__FILE__,
	function () {
		Hoobert_History::install();
		Hoobert_Migrate::maybe_adopt_legacy_data();
	}
);

/**
 * Boot the plugin once all plugins are loaded, so we can detect WooCommerce.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Hoobert requires WooCommerce to be active.', 'hoobert' ) . '</p></div>';
				}
			);
			return;
		}

		Hoobert_History::maybe_install();
		Hoobert_Settings::init();
		( new Hoobert_Rest_Proxy() )->register();
	}
);

/**
 * Enqueue the command-palette bundle on every wp-admin screen and expose the
 * config + page context the front-end needs (nonce, proxy route, current ids).
 */
add_action(
	'admin_enqueue_scripts',
	function () {
		// The command bar is a store-manager tool; everyone else pays nothing for it.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$asset_file = HOOBERT_PATH . 'build/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'wp-commands', 'wp-data', 'wp-element' ),
				'version'      => HOOBERT_VERSION,
			);

		wp_enqueue_script(
			'hoobert',
			HOOBERT_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'hoobert',
			HOOBERT_URL . 'build/style-index.css',
			array(),
			$asset['version']
		);

		wp_localize_script(
			'hoobert',
			'hoobert',
			array(
				'root'    => esc_url_raw( rest_url( 'hoobert/v1' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'context' => Hoobert_Rest_Proxy::current_page_context(),
			)
		);
	}
);
