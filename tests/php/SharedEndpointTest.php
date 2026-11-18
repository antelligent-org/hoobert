<?php
/**
 * Hoobert_Fern_Client on Fernfly's shared endpoint: registering on the first
 * command, re-registering once when the token is refused, and passing the daily
 * usage and the upsell through.
 *
 * @package Hoobert
 */

class SharedEndpointTest extends HoobertTestCase {

	private const INSTALLS = Hoobert_Fern_Client::SHARED_BASE . '/installs';
	private const INFER    = Hoobert_Fern_Client::SHARED_BASE . '/infer';

	private const TOKEN     = '11111111-1111-4111-8111-111111111111';
	private const NEW_TOKEN = '22222222-2222-4222-8222-222222222222';

	private const UPGRADE = array(
		'message'    => 'Create a free Fernfly account for 500 requests a day.',
		'signup_url' => 'https://fernfly.com/signup?source=hoobert',
	);

	/**
	 * Answer successive wp_remote_post() calls with these [ status, body ] pairs,
	 * in order. Running out fails the test, so an unexpected extra request shows.
	 *
	 * @param array ...$replies Each a [ int $status, array $body ] pair.
	 */
	private function replies( array ...$replies ): void {
		Hoobert_Test_State::$http_handler = function () use ( &$replies ) {
			if ( ! $replies ) {
				$this->fail( 'Unexpected extra HTTP request.' );
			}
			[ $status, $body ] = array_shift( $replies );
			return array(
				'response' => array( 'code' => $status ),
				'body'     => json_encode( $body ),
			);
		};
	}

	private function stored_token( string $token ): void {
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = array( 'install_token' => $token );
	}

	private function saved_token(): string {
		return Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ]['install_token'] ?? '';
	}

	/**
	 * The URL of every request made, in order.
	 */
	private function urls(): array {
		return array_column( Hoobert_Test_State::$http_requests, 0 );
	}

	private function infer( string $query = 'show my last 10 orders', array $context = array() ): array {
		return ( new Hoobert_Fern_Client() )->infer( $query, $context );
	}

	private static function calls(): array {
		return array( 'calls' => array( array( 'name' => 'list_orders', 'arguments' => array( 'per_page' => 10 ) ) ) );
	}

	public function test_the_first_command_registers_the_site_then_infers(): void {
		$this->replies(
			array( 201, array( 'install_token' => self::TOKEN, 'daily_limit' => 50 ) ),
			array( 200, self::calls() )
		);

		$result = $this->infer( 'refund order 42', array( 'current_order_id' => 42 ) );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array( self::INSTALLS, self::INFER ), $this->urls() );
		$this->assertSame( self::TOKEN, $this->saved_token() );

		[ , $register ] = Hoobert_Test_State::$http_requests[0];
		$this->assertArrayNotHasKey( 'X-Api-Key', $register['headers'] );
		$this->assertSame( array( 'site_url' => 'https://store.example' ), json_decode( $register['body'], true ) );

		[ , $infer ] = Hoobert_Test_State::$http_requests[1];
		$this->assertSame( self::TOKEN, $infer['headers']['X-Api-Key'] );
		$this->assertSame(
			array(
				'utterance' => 'refund order 42',
				'meta'      => array( 'current_order_id' => 42 ),
				'site_url'  => 'https://store.example',
			),
			json_decode( $infer['body'], true )
		);
	}

	public function test_a_stored_token_skips_registration(): void {
		$this->stored_token( self::TOKEN );
		$this->replies( array( 200, self::calls() ) );

		$this->assertTrue( $this->infer()['ok'] );
		$this->assertSame( array( self::INFER ), $this->urls() );
	}

	public function test_registering_keeps_the_other_settings(): void {
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = array(
			'connection' => Hoobert_Settings::CONNECTION_SHARED,
			'endpoint'   => 'https://fernfly.example/api/p/27/infer',
			'api_key'    => 'own-key',
		);
		$this->replies(
			array( 200, array( 'install_token' => self::TOKEN ) ),
			array( 200, self::calls() )
		);

		$this->infer();

		$this->assertSame(
			array(
				'connection'    => Hoobert_Settings::CONNECTION_SHARED,
				'endpoint'      => 'https://fernfly.example/api/p/27/infer',
				'api_key'       => 'own-key',
				'install_token' => self::TOKEN,
			),
			Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ]
		);
	}

	public function test_an_explicit_shared_choice_ignores_a_saved_endpoint(): void {
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = array(
			'connection'    => Hoobert_Settings::CONNECTION_SHARED,
			'endpoint'      => 'https://fernfly.example/api/p/27/infer',
			'api_key'       => 'own-key',
			'install_token' => self::TOKEN,
		);
		$this->replies( array( 200, self::calls() ) );

		$this->infer();

		$this->assertSame( array( self::INFER ), $this->urls() );
	}

	public function test_an_unknown_token_registers_again_and_retries_once(): void {
		$this->stored_token( self::TOKEN );
		$this->replies(
			array( 401, array( 'error' => 'Unknown install token. Register this site again.' ) ),
			array( 200, array( 'install_token' => self::NEW_TOKEN ) ),
			array( 200, self::calls() )
		);

		$result = $this->infer();

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array( self::INFER, self::INSTALLS, self::INFER ), $this->urls() );
		$this->assertSame( self::NEW_TOKEN, Hoobert_Test_State::$http_requests[2][1]['headers']['X-Api-Key'] );
		$this->assertSame( self::NEW_TOKEN, $this->saved_token() );
	}

	public function test_a_token_from_another_site_registers_again_and_retries_once(): void {
		$this->stored_token( self::TOKEN );
		$this->replies(
			array( 409, array( 'error' => 'This install token belongs to another site.', 'code' => 'site_changed' ) ),
			array( 201, array( 'install_token' => self::NEW_TOKEN ) ),
			array( 200, self::calls() )
		);

		$this->assertTrue( $this->infer()['ok'] );
		$this->assertSame( self::NEW_TOKEN, $this->saved_token() );
	}

	public function test_a_conflict_for_another_reason_is_not_retried(): void {
		$this->stored_token( self::TOKEN );
		$this->replies( array( 409, array( 'error' => 'Conflict.', 'code' => 'something_else' ) ) );

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( array( self::INFER ), $this->urls() );
		$this->assertSame( self::TOKEN, $this->saved_token() );
	}

	public function test_a_token_refused_twice_gives_up_after_one_retry(): void {
		$this->stored_token( self::TOKEN );
		$this->replies(
			array( 401, array( 'error' => 'Unknown install token.' ) ),
			array( 200, array( 'install_token' => self::NEW_TOKEN ) ),
			array( 401, array( 'error' => 'Unknown install token.' ) )
		);

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 401, $result['status'] );
		$this->assertCount( 3, Hoobert_Test_State::$http_requests );
	}

	public function test_a_spent_allowance_passes_the_upsell_through(): void {
		$this->stored_token( self::TOKEN );
		$this->replies(
			array(
				429,
				array(
					'error'          => 'This site has used its 50 free Hoobert requests for today.',
					'quota_exceeded' => true,
					'limit'          => 50,
					'resets_at'      => 1800000000,
					'upgrade'        => self::UPGRADE,
				),
			)
		);

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 429, $result['status'] );
		$this->assertSame( 'This site has used its 50 free Hoobert requests for today.', $result['error'] );
		$this->assertSame( self::UPGRADE, $result['upgrade'] );
	}

	public function test_a_rate_limit_carries_no_upsell(): void {
		$this->stored_token( self::TOKEN );
		$this->replies( array( 429, array( 'error' => 'Too many requests.', 'rate_limited' => true ) ) );

		$result = $this->infer();

		$this->assertSame( 429, $result['status'] );
		$this->assertArrayNotHasKey( 'upgrade', $result );
	}

	public function test_an_upsell_with_a_non_http_link_is_dropped(): void {
		$this->stored_token( self::TOKEN );
		$this->replies(
			array(
				429,
				array(
					'error'   => 'Limit reached.',
					'upgrade' => array( 'message' => 'Sign up.', 'signup_url' => 'javascript:alert(1)' ),
				),
			)
		);

		$this->assertArrayNotHasKey( 'upgrade', $this->infer() );
	}

	public function test_a_refused_registration_stops_before_inferring(): void {
		$this->replies(
			array(
				429,
				array(
					'error'        => 'Too many new installs from this address today.',
					'rate_limited' => true,
					'upgrade'      => self::UPGRADE,
				),
			)
		);

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( array(), $result['calls'] );
		$this->assertSame( 'Too many new installs from this address today.', $result['error'] );
		$this->assertSame( self::UPGRADE, $result['upgrade'] );
		$this->assertSame( array( self::INSTALLS ), $this->urls() );
		$this->assertSame( '', $this->saved_token() );
	}

	public function test_a_registration_without_a_token_is_an_error(): void {
		$this->replies( array( 200, array( 'daily_limit' => 50 ) ) );

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertNotEmpty( $result['error'] );
		$this->assertSame( array( self::INSTALLS ), $this->urls() );
	}

	public function test_an_unreachable_endpoint_reports_the_transport_error(): void {
		Hoobert_Test_State::$http_handler = static fn() => new WP_Error( 'http_request_failed', 'Could not resolve host' );

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'Could not resolve host', $result['error'] );
	}

	public function test_usage_is_normalized_from_a_successful_reply(): void {
		$this->stored_token( self::TOKEN );
		$this->replies(
			array(
				200,
				self::calls() + array(
					'usage' => array( 'limit' => 50, 'used' => '38', 'remaining' => 12, 'resets_at' => 1800000000 ),
				),
			)
		);

		$this->assertSame(
			array( 'limit' => 50, 'used' => 38, 'remaining' => 12, 'resets_at' => 1800000000 ),
			$this->infer()['usage']
		);
	}
}
