<?php
/**
 * Hoobert_Executor: how a model-emitted tool call becomes a WooCommerce REST
 * request, and how the response becomes a merchant-facing display payload.
 *
 * @package Hoobert
 */

class ExecutorTest extends HoobertTestCase {

	private function run_tool( string $name, array $arguments = array() ): array {
		return ( new Hoobert_Executor() )->run( $name, $arguments );
	}

	public function test_unknown_tool_fails_without_dispatching(): void {
		self::set_tools( array() );

		$result = $this->run_tool( 'no_such_tool' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 400, $result['status'] );
		$this->assertStringContainsString( 'no_such_tool', $result['error'] );
		$this->assertSame( array(), Hoobert_Test_State::$rest_requests );
	}

	public function test_get_builds_route_from_path_params_and_queries_the_rest(): void {
		self::set_tools(
			array(
				self::tool(
					'list_order_notes',
					array( 'method' => 'GET', 'path' => '/orders/{id}/notes', 'path_params' => array( 'id' ) )
				),
			)
		);
		self::rest_returns( array() );

		$result = $this->run_tool( 'list_order_notes', array( 'id' => 42, 'type' => 'customer' ) );

		$request = self::last_rest_request();
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( '/wc/v3/orders/42/notes', $request->get_route() );
		// The path param is consumed by the route, everything else is query string.
		$this->assertSame( array( 'type' => 'customer' ), $request->get_query_params() );
		$this->assertSame( array(), $request->get_body_params() );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( array( 'type' => 'customer' ), $result['request']['params'] );
	}

	public function test_write_methods_send_arguments_as_body_params(): void {
		self::set_tools(
			array(
				self::tool(
					'update_order_status',
					array( 'method' => 'post', 'path' => '/orders/{id}', 'path_params' => array( 'id' ) )
				),
			)
		);
		self::rest_returns( array( 'id' => 7 ) );

		$this->run_tool( 'update_order_status', array( 'id' => 7, 'status' => 'completed' ) );

		$request = self::last_rest_request();
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( array( 'status' => 'completed' ), $request->get_body_params() );
		$this->assertSame( array(), $request->get_query_params() );
	}

	public function test_namespace_override_is_honoured(): void {
		self::set_tools(
			array(
				self::tool(
					'get_top_customers',
					array( 'method' => 'GET', 'path' => '/leaderboards/customers', 'namespace' => 'wc-analytics' )
				),
			)
		);
		self::rest_returns( array() );

		$this->run_tool( 'get_top_customers' );

		$this->assertSame( '/wc-analytics/leaderboards/customers', self::last_rest_request()->get_route() );
	}

	public function test_path_param_values_are_url_encoded(): void {
		self::set_tools(
			array(
				self::tool( 'get_thing', array( 'method' => 'GET', 'path' => '/things/{slug}', 'path_params' => array( 'slug' ) ) ),
			)
		);
		self::rest_returns( array() );

		$this->run_tool( 'get_thing', array( 'slug' => 'a b/c' ) );

		$this->assertSame( '/wc/v3/things/a%20b%2Fc', self::last_rest_request()->get_route() );
	}

	public function test_missing_path_param_leaves_an_empty_segment(): void {
		self::set_tools(
			array(
				self::tool( 'get_order', array( 'method' => 'GET', 'path' => '/orders/{id}', 'path_params' => array( 'id' ) ) ),
			)
		);
		self::rest_returns( array( 'code' => 'rest_no_route' ), 404 );

		$result = $this->run_tool( 'get_order' );

		$this->assertSame( '/wc/v3/orders/', self::last_rest_request()->get_route() );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 404, $result['status'] );
	}

	public function test_non_2xx_response_is_not_ok_and_carries_no_display(): void {
		self::set_tools(
			array(
				self::tool(
					'get_order',
					array(
						'method'  => 'GET',
						'path'    => '/orders/{id}',
						'display' => array( 'type' => 'object', 'fields' => array( array( 'label' => 'Status', 'path' => 'status' ) ) ),
					)
				),
			)
		);
		self::rest_returns( array( 'message' => 'Invalid ID.' ), 400 );

		$result = $this->run_tool( 'get_order', array( 'id' => 999 ) );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 400, $result['status'] );
		$this->assertArrayNotHasKey( 'display', $result );
		$this->assertSame( array( 'message' => 'Invalid ID.' ), $result['data'] );
	}

	public function test_tool_without_a_display_spec_returns_none(): void {
		self::set_tools( array( self::tool( 'ping', array( 'method' => 'GET', 'path' => '/ping' ) ) ) );
		self::rest_returns( array( 'id' => 1 ) );

		$this->assertArrayNotHasKey( 'display', $this->run_tool( 'ping' ) );
	}

	public function test_object_display_interpolates_the_title_and_formats_fields(): void {
		self::set_tools(
			array(
				self::tool(
					'get_order',
					array(
						'method'  => 'GET',
						'path'    => '/orders/{id}',
						'display' => array(
							'type'   => 'object',
							'title'  => 'Order #{number}',
							'fields' => array(
								array( 'label' => 'Status', 'path' => 'status', 'format' => 'status' ),
								array( 'label' => 'Date', 'path' => 'date_created', 'format' => 'date' ),
								array( 'label' => 'Customer', 'paths' => array( 'billing.first_name', 'billing.last_name' ) ),
								array( 'label' => 'Items', 'path' => 'line_items', 'format' => 'count' ),
								array( 'label' => 'Total', 'path' => 'total', 'format' => 'currency' ),
								array( 'label' => 'Note', 'path' => 'customer_note' ),
							),
						),
					)
				),
			)
		);
		Hoobert_Test_State::$options['date_format'] = 'Y-m-d';
		self::rest_returns(
			array(
				'number'        => '1042',
				'status'        => 'on-hold',
				'date_created'  => '2026-03-04T10:15:00',
				'billing'       => array( 'first_name' => 'Ada', 'last_name' => 'Lovelace' ),
				'line_items'    => array( array( 'id' => 1 ), array( 'id' => 2 ), array( 'id' => 3 ) ),
				'total'         => '25.5',
				'customer_note' => '',
			)
		);

		$display = $this->run_tool( 'get_order', array( 'id' => 1042 ) )['display'];

		$this->assertSame( 'object', $display['type'] );
		$this->assertSame( 'Order #1042', $display['title'] );
		$this->assertSame(
			array(
				array( 'label' => 'Status', 'value' => 'On Hold' ),
				array( 'label' => 'Date', 'value' => '2026-03-04' ),
				array( 'label' => 'Customer', 'value' => 'Ada Lovelace' ),
				array( 'label' => 'Items', 'value' => '3' ),
				array( 'label' => 'Total', 'value' => '$25.50' ),
				// Empty values render as a dash rather than a blank cell.
				array( 'label' => 'Note', 'value' => '-' ),
			),
			$display['rows']
		);
	}

	public function test_list_display_builds_columns_rows_and_a_count(): void {
		self::set_tools( array( $this->list_tool() ) );
		self::rest_returns(
			array(
				array( 'number' => '1', 'stock_status' => 'instock', 'on_sale' => true ),
				array( 'number' => '2', 'stock_status' => 'onbackorder', 'on_sale' => false ),
			)
		);

		$display = $this->run_tool( 'list_products' )['display'];

		$this->assertSame( 'list', $display['type'] );
		$this->assertSame( 2, $display['count'] );
		$this->assertSame( array( 'Ref', 'Stock', 'On sale' ), $display['columns'] );
		$this->assertSame(
			array(
				array( '1', 'In stock', 'Yes' ),
				array( '2', 'On backorder', 'No' ),
			),
			$display['rows']
		);
	}

	public function test_empty_list_keeps_the_tools_empty_message(): void {
		self::set_tools( array( $this->list_tool() ) );
		self::rest_returns( array() );

		$display = $this->run_tool( 'list_products' )['display'];

		$this->assertSame( 0, $display['count'] );
		$this->assertSame( array(), $display['rows'] );
		$this->assertSame( 'No products found.', $display['empty'] );
	}

	public function test_unknown_format_falls_back_to_the_raw_value(): void {
		self::set_tools(
			array(
				self::tool(
					'get_thing',
					array(
						'method'  => 'GET',
						'path'    => '/things',
						'display' => array(
							'type'   => 'object',
							'fields' => array( array( 'label' => 'Sku', 'path' => 'sku', 'format' => 'no-such-format' ) ),
						),
					)
				),
			)
		);
		self::rest_returns( array( 'sku' => 'ABC-1' ) );

		$this->assertSame( 'ABC-1', $this->run_tool( 'get_thing' )['display']['rows'][0]['value'] );
	}

	/**
	 * A list-shaped tool exercising the stock, bool and plain formatters.
	 */
	private function list_tool(): array {
		return self::tool(
			'list_products',
			array(
				'method'  => 'GET',
				'path'    => '/products',
				'display' => array(
					'type'   => 'list',
					'empty'  => 'No products found.',
					'fields' => array(
						array( 'label' => 'Ref', 'path' => 'number' ),
						array( 'label' => 'Stock', 'path' => 'stock_status', 'format' => 'stock' ),
						array( 'label' => 'On sale', 'path' => 'on_sale', 'format' => 'bool' ),
					),
				),
			)
		);
	}
}
