<?php
/**
 * Dispatches a model-emitted tool call against the WooCommerce REST API v3.
 *
 * The model returns a tool name + arguments; the executor reads that tool's
 * `x-woo` mapping (method, path, path_params, namespace), substitutes path
 * parameters, splits the remaining arguments into query string (GET) or JSON
 * body (write methods), and runs the request in-process via WooCommerce's own
 * rest_do_request, so it inherits the current admin user's capabilities and
 * needs no consumer key/secret round-trip.
 *
 * @package Hoobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hoobert_Executor {

	/**
	 * Execute a tool call.
	 *
	 * @param string $tool_name Function name emitted by the model.
	 * @param array  $arguments Arguments emitted by the model.
	 * @return array{ok:bool, status:int, data:mixed, request:array, error?:string}
	 */
	public function run( string $tool_name, array $arguments ): array {
		$tool = Hoobert_Tools::find( $tool_name );
		if ( ! $tool ) {
			return array(
				'ok'     => false,
				'status' => 400,
				'error'  => sprintf( 'Unknown tool: %s', $tool_name ),
				'data'   => null,
			);
		}

		$mapping   = $tool['x-woo'] ?? array();
		$method    = strtoupper( $mapping['method'] ?? 'GET' );
		$namespace = $mapping['namespace'] ?? 'wc/v3';
		$path_tpl  = $mapping['path'] ?? '';
		$path_keys = $mapping['path_params'] ?? array();

		// Substitute {id} style path params and remove them from the payload.
		$path    = $path_tpl;
		$payload = $arguments;
		foreach ( $path_keys as $key ) {
			$value = isset( $payload[ $key ] ) ? rawurlencode( (string) $payload[ $key ] ) : '';
			$path  = str_replace( '{' . $key . '}', $value, $path );
			unset( $payload[ $key ] );
		}

		$route   = '/' . trim( $namespace, '/' ) . $path;
		$request = new WP_REST_Request( $method, $route );

		if ( 'GET' === $method ) {
			$request->set_query_params( $payload );
		} else {
			$request->set_body_params( $payload );
		}

		$response = rest_do_request( $request );
		$status   = $response->get_status();
		$data     = $response->get_data();
		$ok       = $status >= 200 && $status < 300;

		$result = array(
			'ok'      => $ok,
			'status'  => $status,
			'data'    => $data,
			'request' => array(
				'method' => $method,
				'route'  => $route,
				'params' => $payload,
			),
		);

		// A merchant-facing view of the response, when the tool defines one.
		$display = $ok ? self::build_display( $tool, $data ) : null;
		if ( null !== $display ) {
			$result['display'] = $display;
		}

		return $result;
	}

	/**
	 * Build a merchant-facing display payload from a tool's x-woo.display spec.
	 * Returns null when the tool has no spec, so the front-end falls back to the
	 * raw JSON. Object specs yield labeled rows; list specs yield a column table.
	 *
	 * @param array $tool The tool definition.
	 * @param mixed $data The REST response data.
	 * @return array|null
	 */
	private static function build_display( array $tool, $data ): ?array {
		$spec = $tool['x-woo']['display'] ?? null;
		if ( ! $spec || ! is_array( $data ) ) {
			return null;
		}

		$fields = $spec['fields'] ?? array();

		if ( 'list' === ( $spec['type'] ?? 'object' ) ) {
			$rows = array();
			foreach ( $data as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$row = array();
				foreach ( $fields as $field ) {
					$row[] = self::format_field( $field, $item );
				}
				$rows[] = $row;
			}
			return array(
				'type'    => 'list',
				'count'   => count( $rows ),
				'empty'   => $spec['empty'] ?? __( 'No results.', 'hoobert' ),
				'columns' => array_map( static fn( $f ) => $f['label'], $fields ),
				'rows'    => $rows,
			);
		}

		$rows = array();
		foreach ( $fields as $field ) {
			$rows[] = array(
				'label' => $field['label'],
				'value' => self::format_field( $field, $data ),
			);
		}
		return array(
			'type'  => 'object',
			'title' => isset( $spec['title'] ) ? self::interpolate( $spec['title'], $data ) : '',
			'rows'  => $rows,
		);
	}

	/**
	 * Resolve a field's value (single `path` or space-joined `paths`) and format it.
	 */
	private static function format_field( array $field, array $item ): string {
		if ( isset( $field['paths'] ) ) {
			$parts = array();
			foreach ( $field['paths'] as $path ) {
				$value = self::dot_get( $item, $path );
				if ( null !== $value && '' !== $value ) {
					$parts[] = (string) $value;
				}
			}
			$value = implode( ' ', $parts );
		} else {
			$value = self::dot_get( $item, $field['path'] ?? '' );
		}

		return self::apply_format( $field['format'] ?? '', $value );
	}

	/**
	 * Resolve a dotted path (e.g. "billing.first_name") against nested array data.
	 */
	private static function dot_get( $data, string $path ) {
		if ( '' === $path ) {
			return null;
		}
		$current = $data;
		foreach ( explode( '.', $path ) as $key ) {
			if ( is_array( $current ) && array_key_exists( $key, $current ) ) {
				$current = $current[ $key ];
			} else {
				return null;
			}
		}
		return $current;
	}

	/**
	 * Apply a named formatter to a resolved value, producing a display string.
	 */
	private static function apply_format( string $format, $value ): string {
		if ( is_array( $value ) ) {
			return 'count' === $format ? (string) count( $value ) : '';
		}
		if ( null === $value || '' === $value ) {
			return '-';
		}

		switch ( $format ) {
			case 'currency':
				return html_entity_decode( wp_strip_all_tags( wc_price( (float) $value ) ) );
			case 'date':
				$timestamp = strtotime( (string) $value );
				return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : (string) $value;
			case 'status':
				return self::humanize( (string) $value );
			case 'stock':
				$map = array(
					'instock'     => __( 'In stock', 'hoobert' ),
					'outofstock'  => __( 'Out of stock', 'hoobert' ),
					'onbackorder' => __( 'On backorder', 'hoobert' ),
				);
				return $map[ $value ] ?? self::humanize( (string) $value );
			case 'bool':
				return $value ? __( 'Yes', 'hoobert' ) : __( 'No', 'hoobert' );
			default:
				return (string) $value;
		}
	}

	/**
	 * Turn a slug/enum like "on-hold" or "fixed_cart" into "On Hold" / "Fixed Cart".
	 */
	private static function humanize( string $value ): string {
		return ucwords( str_replace( array( '-', '_' ), ' ', $value ) );
	}

	/**
	 * Substitute {token} placeholders in a title from the response data.
	 */
	private static function interpolate( string $template, array $data ): string {
		return preg_replace_callback(
			'/\{([a-z0-9_.]+)\}/i',
			static function ( $token ) use ( $data ) {
				$value = self::dot_get( $data, $token[1] );
				return is_scalar( $value ) ? (string) $value : '';
			},
			$template
		);
	}
}
