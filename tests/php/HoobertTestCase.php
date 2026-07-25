<?php
/**
 * Base test case: resets the WordPress stubs between tests and lets a test swap
 * in its own tool set.
 *
 * @package Hoobert
 */

use PHPUnit\Framework\TestCase;

abstract class HoobertTestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Hoobert_Test_State::reset();
		self::set_tools( null );
	}

	protected function tearDown(): void {
		self::set_tools( null );
		parent::tearDown();
	}

	/**
	 * Overwrite Hoobert_Tools' private cache so a test can run against a
	 * purpose-built tool set instead of the shipped tools.json. Passing null
	 * clears the cache, restoring the real file.
	 *
	 * @param array|null $tools Tool definitions, or null to reset.
	 */
	protected static function set_tools( ?array $tools ): void {
		$property = new ReflectionProperty( Hoobert_Tools::class, 'tools' );
		$property->setAccessible( true );
		$property->setValue( null, $tools );
	}

	/**
	 * Build a tool definition around an x-woo mapping.
	 *
	 * @param string $name    Function name.
	 * @param array  $mapping The x-woo block.
	 */
	protected static function tool( string $name, array $mapping ): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => $name,
				'description' => sprintf( 'Test tool %s.', $name ),
				'parameters'  => array( 'type' => 'object', 'properties' => array() ),
			),
			'x-woo'    => $mapping,
		);
	}

	/**
	 * Make rest_do_request() return this payload and status for every call.
	 *
	 * @param mixed $data   Response payload.
	 * @param int   $status HTTP status.
	 */
	protected static function rest_returns( $data, int $status = 200 ): void {
		Hoobert_Test_State::$rest_handler = static fn() => new WP_REST_Response( $data, $status );
	}

	/**
	 * The last WP_REST_Request the executor handed to rest_do_request().
	 */
	protected static function last_rest_request(): WP_REST_Request {
		$requests = Hoobert_Test_State::$rest_requests;
		if ( ! $requests ) {
			throw new RuntimeException( 'No REST request was dispatched.' );
		}
		return end( $requests );
	}
}
