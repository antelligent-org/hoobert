<?php
/**
 * PHPUnit bootstrap for the Hoobert plugin.
 *
 * Defines the two constants the plugin's files guard on (ABSPATH, HOOBERT_PATH),
 * loads the WordPress stubs, then loads the plugin classes directly. hoobert.php
 * itself is not loaded: it registers hooks on include, which is the part that
 * needs a real WordPress.
 *
 * @package Hoobert
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOOBERT_PATH', dirname( __DIR__, 2 ) . '/plugin/hoobert/' );
define( 'HOOBERT_URL', 'https://example.test/wp-content/plugins/hoobert/' );
define( 'HOOBERT_VERSION', 'test' );

require_once __DIR__ . '/stubs/wordpress.php';

require_once HOOBERT_PATH . 'includes/class-tools.php';
require_once HOOBERT_PATH . 'includes/class-executor.php';
require_once HOOBERT_PATH . 'includes/class-settings.php';
require_once HOOBERT_PATH . 'includes/class-fern-client.php';

require_once __DIR__ . '/HoobertTestCase.php';
