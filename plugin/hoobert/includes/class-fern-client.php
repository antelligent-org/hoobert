<?php
/**
 * Thin client for the Hoobert inference endpoint (a Fernfly project infer route).
 *
 * Given a natural-language merchant utterance, the endpoint returns the tool
 * call(s) its fine-tuned model chose. The project owns the registered tool set,
 * so this client sends only the utterance (plus page context as meta) and reads
 * back Fernfly's `{ calls: [{name, arguments}] }` response shape directly.
 *
 * Endpoint URL and API key are configured on the settings page
 * (WooCommerce -> Hoobert).
 *
 * @package Hoobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hoobert_Fern_Client {

	/**
	 * Ask the model to translate a natural-language query into tool calls.
	 *
	 * @param string $query   Merchant's natural-language request.
	 * @param array  $context Page context (current_order_id, current_product_id, ...).
	 * @return array{ok:bool, calls:array<int,array{name:string,arguments:array}>, out_of_scope?:bool, reply?:string, error?:string, raw?:mixed}
	 */
	public function infer( string $query, array $context = array() ): array {
		$endpoint = Hoobert_Settings::endpoint();
		$api_key  = Hoobert_Settings::api_key();

		if ( ! $endpoint || ! $api_key ) {
			return array(
				'ok'    => false,
				'calls' => array(),
				'error' => __( 'Inference endpoint or API key is not configured. Set it under WooCommerce → Hoobert.', 'hoobert' ),
			);
		}

		// The project model owns the tool set; we send the utterance (with context
		// folded in so "this order/product" resolves) plus context as meta.
		$body = array(
			'utterance' => $query,
			'meta'      => (object) $context,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'X-Api-Key'    => $api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'calls' => array(),
				'error' => $response->get_error_message(),
			);
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $decoded ) ) {
			$message = is_array( $decoded ) && isset( $decoded['error'] )
				? (string) $decoded['error']
				/* translators: %d: HTTP status code returned by the inference endpoint. */
				: sprintf( __( 'Inference endpoint returned HTTP %d.', 'hoobert' ), (int) $code );
			return array(
				'ok'    => false,
				'calls' => array(),
				'error' => $message,
				'raw'   => $decoded,
			);
		}

		return array(
			'ok'           => true,
			'calls'        => self::extract_calls( $decoded ),
			'out_of_scope' => ! empty( $decoded['out_of_scope'] ),
			'reply'        => isset( $decoded['reply'] ) ? (string) $decoded['reply'] : '',
			'raw'          => $decoded,
		);
	}

	/**
	 * Fold the page context into the utterance so the model can resolve
	 * "this order" / "current product" to concrete ids.
	 */
	private static function compose_prompt( string $query, array $context ): string {
		$context = array_filter(
			$context,
			static function ( $v ) {
				return null !== $v && '' !== $v;
			}
		);
		if ( empty( $context ) ) {
			return $query;
		}
		return $query . "\n\nContext: " . wp_json_encode( $context );
	}

	/**
	 * Normalize the endpoint's `calls` array into [{name, arguments}], tolerating
	 * arguments delivered either as an object or a JSON string.
	 */
	private static function extract_calls( array $decoded ): array {
		$raw_calls = $decoded['calls'] ?? array();
		$calls     = array();

		foreach ( $raw_calls as $call ) {
			$name = $call['name'] ?? '';
			if ( '' === $name ) {
				continue;
			}
			$raw_args = $call['arguments'] ?? array();
			$args     = is_array( $raw_args ) ? $raw_args : json_decode( (string) $raw_args, true );
			$calls[]  = array(
				'name'      => $name,
				'arguments' => is_array( $args ) ? $args : array(),
			);
		}

		return $calls;
	}
}
