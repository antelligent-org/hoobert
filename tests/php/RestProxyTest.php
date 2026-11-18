<?php
/**
 * Hoobert_Rest_Proxy::resolve(): how an inference result becomes the response
 * the command bar reads, including the shared model's upsell and usage line.
 *
 * @package Hoobert
 */

class RestProxyTest extends HoobertTestCase {

	protected function setUp(): void {
		parent::setUp();
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = array(
			'install_token' => '11111111-1111-4111-8111-111111111111',
		);
	}

	/**
	 * Make the inference endpoint return this JSON body with this status.
	 */
	private function endpoint_returns( array $body, int $status = 200 ): void {
		Hoobert_Test_State::$http_handler = static fn() => array(
			'response' => array( 'code' => $status ),
			'body'     => json_encode( $body ),
		);
	}

	private function resolve( string $query = 'show my last 10 orders' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/hoobert/v1/resolve' );
		$request->set_body_params( array( 'query' => $query ) );
		return ( new Hoobert_Rest_Proxy() )->resolve( $request );
	}

	private static function usage( int $remaining ): array {
		return array(
			'calls' => array(),
			'usage' => array( 'limit' => 50, 'used' => 50 - $remaining, 'remaining' => $remaining, 'resets_at' => 1800000000 ),
		);
	}

	public function test_a_spent_allowance_answers_429_with_the_upsell(): void {
		$upgrade = array( 'message' => 'Create a free Fernfly account.', 'signup_url' => 'https://fernfly.com/signup' );
		$this->endpoint_returns(
			array( 'error' => 'This site has used its 50 free Hoobert requests for today.', 'quota_exceeded' => true, 'upgrade' => $upgrade ),
			429
		);

		$response = $this->resolve();

		$this->assertSame( 429, $response->get_status() );
		$this->assertSame(
			array(
				'ok'      => false,
				'error'   => 'This site has used its 50 free Hoobert requests for today.',
				'upgrade' => $upgrade,
			),
			$response->get_data()
		);
	}

	public function test_other_failures_answer_502_without_an_upsell(): void {
		$this->endpoint_returns( array( 'error' => 'This shared endpoint is not available.' ), 503 );

		$response = $this->resolve();

		$this->assertSame( 502, $response->get_status() );
		$this->assertSame(
			array(
				'ok'    => false,
				'error' => 'This shared endpoint is not available.',
			),
			$response->get_data()
		);
	}

	public function test_a_shared_reply_carries_the_remaining_allowance(): void {
		$this->endpoint_returns( self::usage( 12 ) );

		$this->assertSame( '12 free requests left today', $this->resolve()->get_data()['usage_note'] );
	}

	public function test_the_usage_line_is_singular_for_one_request(): void {
		$this->endpoint_returns( self::usage( 1 ) );

		$this->assertSame( '1 free request left today', $this->resolve()->get_data()['usage_note'] );
	}

	public function test_the_own_endpoint_shows_no_usage_line(): void {
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = array(
			'connection' => Hoobert_Settings::CONNECTION_OWN,
			'endpoint'   => 'https://fernfly.example/api/p/27/infer',
			'api_key'    => 'own-key',
		);
		$this->endpoint_returns( array( 'calls' => array() ) );

		$this->assertSame( '', $this->resolve()->get_data()['usage_note'] );
	}
}
