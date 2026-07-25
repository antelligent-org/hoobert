<?php
/**
 * Hoobert_Tools plus contract checks over the shipped tools.json.
 *
 * The tool set is hand-edited and kept in sync with the Fernfly training copy, so
 * these assert the invariants the executor and the front-end silently rely on: a
 * dispatchable x-woo mapping, path tokens that match their declared params, and
 * display formats the executor actually implements.
 *
 * @package Hoobert
 */

class ToolsTest extends HoobertTestCase {

	private const METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

	/**
	 * The formatters Hoobert_Executor::apply_format() implements. Anything else
	 * falls through to the raw value, which is a silent display bug.
	 */
	private const FORMATS = array( 'currency', 'date', 'status', 'stock', 'bool', 'count' );

	public function test_the_shipped_tool_set_loads(): void {
		$tools = Hoobert_Tools::all();

		$this->assertNotEmpty( $tools, 'tools.json should decode to a non-empty tool list.' );
	}

	public function test_find_matches_by_function_name(): void {
		$tool = Hoobert_Tools::find( 'get_order' );

		$this->assertNotNull( $tool );
		$this->assertSame( 'get_order', $tool['function']['name'] );
	}

	public function test_find_returns_null_for_an_unknown_tool(): void {
		$this->assertNull( Hoobert_Tools::find( 'definitely_not_a_tool' ) );
	}

	public function test_tool_names_are_unique(): void {
		$names = array_map( static fn( $tool ) => $tool['function']['name'] ?? '', Hoobert_Tools::all() );

		$this->assertSame( array(), array_diff_assoc( $names, array_unique( $names ) ) );
	}

	/**
	 * @dataProvider shipped_tools
	 */
	public function test_every_tool_is_dispatchable( string $name, array $tool ): void {
		$this->assertNotSame( '', $name, 'Every tool needs a function name.' );
		$this->assertNotEmpty( $tool['function']['description'] ?? '', "$name has no description." );

		$mapping = $tool['x-woo'] ?? array();
		$this->assertNotEmpty( $mapping, "$name has no x-woo mapping, so the executor cannot dispatch it." );
		$this->assertContains( strtoupper( $mapping['method'] ?? '' ), self::METHODS, "$name has an unsupported method." );
		$this->assertStringStartsWith( '/', $mapping['path'] ?? '', "$name has a path that is not rooted." );
	}

	/**
	 * @dataProvider shipped_tools
	 */
	public function test_path_tokens_and_path_params_agree( string $name, array $tool ): void {
		$path     = $tool['x-woo']['path'] ?? '';
		$declared = $tool['x-woo']['path_params'] ?? array();

		preg_match_all( '/\{([a-z0-9_]+)\}/i', $path, $matches );
		$in_path = $matches[1];

		sort( $in_path );
		sort( $declared );
		$this->assertSame( $in_path, $declared, "$name declares path_params that do not match its path tokens." );
	}

	/**
	 * Only a write may ask the merchant to confirm. Which writes are gated is a
	 * judgement call per tool (adding an order note is not gated), but a read that
	 * prompts is always wrong.
	 *
	 * @dataProvider shipped_tools
	 */
	public function test_only_writes_are_confirm_flagged( string $name, array $tool ): void {
		$confirm = $tool['x-woo']['confirm'] ?? false;
		$this->assertIsBool( $confirm, "$name has a non-boolean x-woo.confirm." );

		if ( 'GET' === strtoupper( $tool['x-woo']['method'] ?? 'GET' ) ) {
			$this->assertFalse( $confirm, "$name is a read and should not prompt for confirmation." );
		}
	}

	/**
	 * @dataProvider shipped_tools
	 */
	public function test_display_specs_are_renderable( string $name, array $tool ): void {
		$spec = $tool['x-woo']['display'] ?? null;
		if ( null === $spec ) {
			$this->assertNull( $spec );
			return;
		}

		$type = $spec['type'] ?? 'object';
		$this->assertContains( $type, array( 'object', 'list' ), "$name has an unknown display type." );
		if ( 'list' === $type ) {
			$this->assertNotEmpty( $spec['empty'] ?? '', "$name is a list display and needs an empty message." );
		}

		$this->assertNotEmpty( $spec['fields'] ?? array(), "$name has a display with no fields." );
		foreach ( $spec['fields'] as $field ) {
			$this->assertNotEmpty( $field['label'] ?? '', "$name has a display field without a label." );
			$this->assertTrue(
				isset( $field['path'] ) || isset( $field['paths'] ),
				"$name has a display field with neither path nor paths."
			);
			if ( isset( $field['format'] ) ) {
				$this->assertContains( $field['format'], self::FORMATS, "$name uses a formatter the executor does not implement." );
			}
		}
	}

	/**
	 * Every tool in the shipped tools.json, keyed by name so failures name it.
	 *
	 * @return array<string,array{0:string,1:array}>
	 */
	public static function shipped_tools(): array {
		$cases = array();
		foreach ( Hoobert_Tools::all() as $index => $tool ) {
			$name           = $tool['function']['name'] ?? "tool #$index";
			$cases[ $name ] = array( $name, $tool );
		}
		return $cases;
	}
}
