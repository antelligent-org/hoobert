<?php
/**
 * Hoobert_Fern_Client: the request it sends to the inference endpoint and how it
 * normalizes the response into { ok, calls }.
 *
 * @package Hoobert
 */

class FernClientTest extends HoobertTestCase {

	private const ENDPOINT = 'https://fernfly.example/api/p/27/infer';

	protected function setUp(): void {
		parent::setUp();
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = array(
			'endpoint' => self::ENDPOINT,
			'api_key'  => 'test-key',
		);
	}

	/**
	 * Make the endpoint return this JSON body with this status.
	 *
	 * @param mixed $body   Payload, encoded to JSON, or a raw string.
	 * @param int   $status HTTP status.
	 */
	private function endpoint_returns( $body, int $status = 200 ): void {
		$encoded                          = is_string( $body ) ? $body : json_encode( $body );
		Hoobert_Test_State::$http_handler = static fn() => array(
			'response' => array( 'code' => $status ),
			'body'     => $encoded,
		);
	}

	private function infer( string $query = 'show my last 10 orders', array $context = array() ): array {
		return ( new Hoobert_Fern_Client() )->infer( $query, $context );
	}

	public function test_missing_configuration_fails_without_a_request(): void {
		Hoobert_Test_State::$options = array();

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( array(), $result['calls'] );
		$this->assertNotEmpty( $result['error'] );
		$this->assertSame( array(), Hoobert_Test_State::$http_requests );
	}

	public function test_missing_api_key_fails_without_a_request(): void {
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = array( 'endpoint' => self::ENDPOINT );

		$this->assertFalse( $this->infer()['ok'] );
		$this->assertSame( array(), Hoobert_Test_State::$http_requests );
	}

	public function test_request_carries_the_key_header_and_the_utterance_body(): void {
		$this->endpoint_returns( array( 'calls' => array() ) );

		$this->infer( 'refund order 42', array( 'current_order_id' => 42 ) );

		[ $url, $args ] = Hoobert_Test_State::$http_requests[0];
		$this->assertSame( self::ENDPOINT, $url );
		$this->assertSame( 'test-key', $args['headers']['X-Api-Key'] );
		$this->assertSame( 'application/json', $args['headers']['Content-Type'] );

		$body = json_decode( $args['body'], true );
		$this->assertSame( 'refund order 42', $body['utterance'] );
		$this->assertSame( array( 'current_order_id' => 42 ), $body['meta'] );
		// The endpoint owns the tool set, so neither tools nor a model id is sent.
		$this->assertArrayNotHasKey( 'tools', $body );
		$this->assertArrayNotHasKey( 'model', $body );
	}

	public function test_empty_context_is_encoded_as_an_object_not_an_array(): void {
		$this->endpoint_returns( array( 'calls' => array() ) );

		$this->infer( 'list my products' );

		$body = Hoobert_Test_State::$http_requests[0][1]['body'];
		$this->assertStringContainsString( '"meta":{}', $body );
	}

	public function test_successful_response_yields_normalized_calls(): void {
		$this->endpoint_returns(
			array( 'calls' => array( array( 'name' => 'list_orders', 'arguments' => array( 'per_page' => 10 ) ) ) )
		);

		$result = $this->infer();

		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			array( array( 'name' => 'list_orders', 'arguments' => array( 'per_page' => 10 ) ) ),
			$result['calls']
		);
		$this->assertFalse( $result['out_of_scope'] );
	}

	public function test_arguments_delivered_as_a_json_string_are_decoded(): void {
		$this->endpoint_returns(
			array( 'calls' => array( array( 'name' => 'get_order', 'arguments' => '{"id":42}' ) ) )
		);

		$this->assertSame( array( 'id' => 42 ), $this->infer()['calls'][0]['arguments'] );
	}

	public function test_unusable_arguments_become_an_empty_array(): void {
		$this->endpoint_returns(
			array( 'calls' => array( array( 'name' => 'get_order', 'arguments' => 'not json' ) ) )
		);

		$this->assertSame( array(), $this->infer()['calls'][0]['arguments'] );
	}

	public function test_calls_without_a_name_are_dropped(): void {
		$this->endpoint_returns(
			array(
				'calls' => array(
					array( 'arguments' => array( 'id' => 1 ) ),
					array( 'name' => 'get_order', 'arguments' => array( 'id' => 2 ) ),
				),
			)
		);

		$calls = $this->infer()['calls'];

		$this->assertCount( 1, $calls );
		$this->assertSame( 'get_order', $calls[0]['name'] );
	}

	public function test_out_of_scope_response_passes_the_reply_through(): void {
		$this->endpoint_returns( array( 'calls' => array(), 'out_of_scope' => true, 'reply' => 'I can only help with your store.' ) );

		$result = $this->infer( 'what is the weather' );

		$this->assertTrue( $result['ok'] );
		$this->assertTrue( $result['out_of_scope'] );
		$this->assertSame( array(), $result['calls'] );
		$this->assertSame( 'I can only help with your store.', $result['reply'] );
	}

	public function test_transport_error_is_reported_with_its_message(): void {
		Hoobert_Test_State::$http_handler = static fn() => new WP_Error( 'http_request_failed', 'Connection timed out' );

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'Connection timed out', $result['error'] );
	}

	public function test_http_error_prefers_the_endpoints_own_message(): void {
		$this->endpoint_returns( array( 'error' => 'Project has no active deployment.' ), 503 );

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'Project has no active deployment.', $result['error'] );
	}

	public function test_http_error_without_a_message_falls_back_to_the_status(): void {
		$this->endpoint_returns( 'gateway timeout', 504 );

		$result = $this->infer();

		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( '504', $result['error'] );
	}
}
