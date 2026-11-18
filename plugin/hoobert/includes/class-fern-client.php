<?php
/**
 * Thin client for Hoobert's inference endpoint: Fernfly's shared model by
 * default, or the merchant's own Fernfly project infer route.
 *
 * Given a natural-language merchant utterance, the endpoint returns the tool
 * call(s) its fine-tuned model chose. The endpoint owns the registered tool set,
 * so this client sends only the utterance (plus page context as meta) and reads
 * back Fernfly's `{ calls: [{name, arguments}] }` response shape directly.
 *
 * The shared endpoint keys each site by an install token. The client registers
 * for one on the first command, stores it, and registers again when the endpoint
 * stops accepting it (an unknown token, or one cloned from another site).
 *
 * @package Hoobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hoobert_Fern_Client {

	const SHARED_BASE = 'https://fernfly.com/api/shared/woocommerce';

	/**
	 * Ask the model to translate a natural-language query into tool calls.
	 *
	 * @param string $query   Merchant's natural-language request.
	 * @param array  $context Page context (current_order_id, current_product_id, ...).
	 * @return array{ok:bool, calls:array<int,array{name:string,arguments:array}>, out_of_scope?:bool, reply?:string, usage?:array|null, error?:string, status?:int, upgrade?:array, raw?:mixed}
	 */
	public function infer( string $query, array $context = array() ): array {
		return Hoobert_Settings::uses_shared()
			? $this->infer_shared( $query, $context )
			: $this->infer_own( $query, $context );
	}

	/**
	 * Send the query to the merchant's own Fernfly project.
	 */
	private function infer_own( string $query, array $context ): array {
		$endpoint = Hoobert_Settings::endpoint();
		$api_key  = Hoobert_Settings::api_key();

		if ( ! $endpoint || ! $api_key ) {
			return array(
				'ok'    => false,
				'calls' => array(),
				'error' => __( 'Your own endpoint is selected, but its URL or API key is missing. Add both under WooCommerce → Hoobert, or switch to the free shared model.', 'hoobert' ),
			);
		}

		// The project model owns the tool set; we send the utterance plus context as meta.
		return self::result(
			self::post(
				$endpoint,
				$api_key,
				array(
					'utterance' => $query,
					'meta'      => (object) $context,
				)
			)
		);
	}

	/**
	 * Send the query to Fernfly's shared model, registering the site first when
	 * it has no token, and once more when the endpoint rejects the one it has.
	 */
	private function infer_shared( string $query, array $context ): array {
		$token = Hoobert_Settings::install_token();
		if ( '' === $token ) {
			$registered = $this->register();
			if ( ! $registered['ok'] ) {
				return $registered;
			}
			$token = $registered['token'];
		}

		$body = array(
			'utterance' => $query,
			'meta'      => (object) $context,
			'site_url'  => home_url(),
		);

		$response = self::post( self::SHARED_BASE . '/infer', $token, $body );

		if ( self::token_rejected( $response ) ) {
			$registered = $this->register();
			if ( ! $registered['ok'] ) {
				return $registered;
			}
			$response = self::post( self::SHARED_BASE . '/infer', $registered['token'], $body );
		}

		return self::result( $response );
	}

	/**
	 * Register this site with the shared endpoint and store the token it issues.
	 *
	 * @return array{ok:bool, token?:string, calls?:array, error?:string, status?:int, upgrade?:array}
	 */
	private function register(): array {
		$response = self::post( self::SHARED_BASE . '/installs', '', array( 'site_url' => home_url() ), 15 );
		$token    = is_array( $response['body'] ) ? (string) ( $response['body']['install_token'] ?? '' ) : '';

		if ( '' !== $response['error'] || $response['status'] < 200 || $response['status'] >= 300 ) {
			return self::failure( $response );
		}
		if ( '' === $token ) {
			return array(
				'ok'     => false,
				'calls'  => array(),
				'error'  => __( 'Fernfly\'s shared model did not return an install token.', 'hoobert' ),
				'status' => $response['status'],
			);
		}

		Hoobert_Settings::save_install_token( $token );
		return array(
			'ok'    => true,
			'token' => $token,
		);
	}

	/**
	 * Whether the shared endpoint refused the token, so registering again is the fix:
	 * 401 for an unknown token, 409 site_changed for one issued to another host.
	 */
	private static function token_rejected( array $response ): bool {
		if ( 401 === $response['status'] ) {
			return true;
		}
		return 409 === $response['status']
			&& is_array( $response['body'] )
			&& 'site_changed' === ( $response['body']['code'] ?? '' );
	}

	/**
	 * POST a JSON body and decode the reply.
	 *
	 * @param string $url     Endpoint URL.
	 * @param string $api_key Sent as X-Api-Key; omitted when empty.
	 * @param array  $body    Request body.
	 * @param int    $timeout Seconds to wait.
	 * @return array{status:int, body:array|null, error:string} Status 0 and an error on a transport failure.
	 */
	private static function post( string $url, string $api_key, array $body, int $timeout = 30 ): array {
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $api_key ) {
			$headers['X-Api-Key'] = $api_key;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 0,
				'body'   => null,
				'error'  => $response->get_error_message(),
			);
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'status' => wp_remote_retrieve_response_code( $response ),
			'body'   => is_array( $decoded ) ? $decoded : null,
			'error'  => '',
		);
	}

	/**
	 * Turn a decoded inference response into the client's result shape.
	 */
	private static function result( array $response ): array {
		$decoded = $response['body'];
		if ( '' !== $response['error'] || $response['status'] < 200 || $response['status'] >= 300 || null === $decoded ) {
			return self::failure( $response );
		}

		return array(
			'ok'           => true,
			'calls'        => self::extract_calls( $decoded ),
			'out_of_scope' => ! empty( $decoded['out_of_scope'] ),
			'reply'        => isset( $decoded['reply'] ) ? (string) $decoded['reply'] : '',
			'usage'        => self::extract_usage( $decoded ),
			'raw'          => $decoded,
		);
	}

	/**
	 * Failure result for a transport error or a non-2xx reply. The endpoint's own
	 * message wins, and an upsell it sends along is passed through for the UI.
	 */
	private static function failure( array $response ): array {
		$decoded = $response['body'];
		$status  = (int) $response['status'];

		if ( '' !== $response['error'] ) {
			$message = $response['error'];
		} elseif ( is_array( $decoded ) && isset( $decoded['error'] ) ) {
			$message = (string) $decoded['error'];
		} else {
			/* translators: %d: HTTP status code returned by the inference endpoint. */
			$message = sprintf( __( 'Inference endpoint returned HTTP %d.', 'hoobert' ), $status );
		}

		$result = array(
			'ok'     => false,
			'calls'  => array(),
			'error'  => $message,
			'status' => $status,
			'raw'    => $decoded,
		);

		$upgrade = is_array( $decoded ) ? self::extract_upgrade( $decoded ) : null;
		if ( $upgrade ) {
			$result['upgrade'] = $upgrade;
		}
		return $result;
	}

	/**
	 * The shared endpoint's upsell, worded by the server so it can change without
	 * a release. Dropped unless the signup link is a plain http(s) URL.
	 *
	 * @return array{message:string, signup_url:string}|null
	 */
	private static function extract_upgrade( array $decoded ): ?array {
		$upgrade = $decoded['upgrade'] ?? null;
		if ( ! is_array( $upgrade ) ) {
			return null;
		}

		$url = esc_url_raw( (string) ( $upgrade['signup_url'] ?? '' ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return null;
		}

		return array(
			'message'    => (string) ( $upgrade['message'] ?? '' ),
			'signup_url' => $url,
		);
	}

	/**
	 * The shared endpoint's daily usage counter. Null for the merchant's own
	 * endpoint, which sends none.
	 *
	 * @return array{limit:int, used:int, remaining:int, resets_at:int}|null
	 */
	private static function extract_usage( array $decoded ): ?array {
		$usage = $decoded['usage'] ?? null;
		if ( ! is_array( $usage ) || ! isset( $usage['remaining'] ) ) {
			return null;
		}

		return array(
			'limit'     => (int) ( $usage['limit'] ?? 0 ),
			'used'      => (int) ( $usage['used'] ?? 0 ),
			'remaining' => (int) $usage['remaining'],
			'resets_at' => (int) ( $usage['resets_at'] ?? 0 ),
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
