<?php
/**
 * The slice of WordPress and WooCommerce the plugin's PHP touches.
 *
 * The suite deliberately does not boot WordPress: the classes under test are
 * thin adapters over a handful of core functions, so stubbing those functions is
 * both faster and a sharper failure signal than standing up a database. Anything
 * the plugin calls at test time must be declared here; a missing stub surfaces as
 * a fatal error naming the function, which is the cue to decide whether that call
 * belongs in a unit test at all.
 *
 * Tests drive the stubs through Hoobert_Test_State.
 *
 * @package Hoobert
 */

/**
 * Mutable state behind the stubs: stored options plus the canned responses the
 * HTTP and REST entry points hand back. Reset between tests.
 */
class Hoobert_Test_State {

	/**
	 * Option name => value, read by get_option().
	 *
	 * @var array<string,mixed>
	 */
	public static array $options = array();

	/**
	 * Handler for wp_remote_post(): fn( string $url, array $args ) => array|WP_Error.
	 *
	 * @var callable|null
	 */
	public static $http_handler = null;

	/**
	 * Handler for rest_do_request(): fn( WP_REST_Request $request ) => WP_REST_Response.
	 *
	 * @var callable|null
	 */
	public static $rest_handler = null;

	/**
	 * Every WP_REST_Request passed to rest_do_request(), in order.
	 *
	 * @var array<int,WP_REST_Request>
	 */
	public static array $rest_requests = array();

	/**
	 * Every [ $url, $args ] pair passed to wp_remote_post(), in order.
	 *
	 * @var array<int,array>
	 */
	public static array $http_requests = array();

	/**
	 * Hook suffix add_submenu_page() hands back. False models a user who cannot
	 * see the page.
	 *
	 * @var string|false
	 */
	public static $submenu_hook = 'woocommerce_page_hoobert';

	/**
	 * Style handles passed to wp_enqueue_style(), in order.
	 *
	 * @var array<int,string>
	 */
	public static array $enqueued_styles = array();

	/**
	 * Clear all state. Call from setUp().
	 */
	public static function reset(): void {
		self::$options         = array();
		self::$http_handler    = null;
		self::$rest_handler    = null;
		self::$rest_requests   = array();
		self::$http_requests   = array();
		self::$submenu_hook    = 'woocommerce_page_hoobert';
		self::$enqueued_styles = array();
	}
}

/**
 * Minimal stand-in for WP_Error: enough for is_wp_error() plus the message.
 */
class WP_Error {

	/**
	 * Error code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Human-readable message.
	 *
	 * @var string
	 */
	private string $message;

	public function __construct( string $code = '', string $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code(): string {
		return $this->code;
	}

	public function get_error_message(): string {
		return $this->message;
	}
}

/**
 * Minimal stand-in for WP_REST_Request. Keeps query and body params in separate
 * buckets, which is exactly the split the executor is responsible for getting
 * right, so tests can assert on which bucket an argument landed in.
 */
class WP_REST_Request {

	private string $method;
	private string $route;
	private array $query_params = array();
	private array $body_params  = array();

	public function __construct( string $method = 'GET', string $route = '' ) {
		$this->method = strtoupper( $method );
		$this->route  = $route;
	}

	public function get_method(): string {
		return $this->method;
	}

	public function get_route(): string {
		return $this->route;
	}

	public function set_query_params( array $params ): void {
		$this->query_params = $params;
	}

	public function get_query_params(): array {
		return $this->query_params;
	}

	public function set_body_params( array $params ): void {
		$this->body_params = $params;
	}

	public function get_body_params(): array {
		return $this->body_params;
	}

	public function get_params(): array {
		return array_merge( $this->query_params, $this->body_params );
	}

	/**
	 * @return mixed
	 */
	public function get_param( string $key ) {
		return $this->get_params()[ $key ] ?? null;
	}
}

/**
 * Minimal stand-in for WP_REST_Response.
 */
class WP_REST_Response {

	/**
	 * Response payload.
	 *
	 * @var mixed
	 */
	private $data;

	private int $status;

	/**
	 * @param mixed $data   Response payload.
	 * @param int   $status HTTP status.
	 */
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	/**
	 * @return mixed
	 */
	public function get_data() {
		return $this->data;
	}

	public function get_status(): int {
		return $this->status;
	}
}

/**
 * Record the request and hand back whatever the test's handler returns.
 */
function rest_do_request( WP_REST_Request $request ): WP_REST_Response {
	Hoobert_Test_State::$rest_requests[] = $request;
	$handler                             = Hoobert_Test_State::$rest_handler;
	if ( ! $handler ) {
		return new WP_REST_Response( null, 200 );
	}
	return $handler( $request );
}

/**
 * @param string $url  Endpoint.
 * @param array  $args Request args.
 * @return array|WP_Error
 */
function wp_remote_post( string $url, array $args = array() ) {
	Hoobert_Test_State::$http_requests[] = array( $url, $args );
	$handler                             = Hoobert_Test_State::$http_handler;
	if ( ! $handler ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
	}
	return $handler( $url, $args );
}

/**
 * @param array|WP_Error $response Response.
 */
function wp_remote_retrieve_response_code( $response ): int {
	return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
}

/**
 * @param array|WP_Error $response Response.
 */
function wp_remote_retrieve_body( $response ): string {
	return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
}

/**
 * @param mixed $thing Value to test.
 */
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

/**
 * @param mixed $default_value Returned when the option is unset.
 * @return mixed
 */
function get_option( string $name, $default_value = false ) {
	return Hoobert_Test_State::$options[ $name ] ?? $default_value;
}

/**
 * @param mixed $value Option value.
 */
function update_option( string $name, $value ): bool {
	Hoobert_Test_State::$options[ $name ] = $value;
	return true;
}

/**
 * Registers the settings screen. Core returns the new page's hook suffix, or
 * false when the current user lacks the capability.
 *
 * @param callable|string $callback Render callback.
 * @return string|false
 */
function add_submenu_page( string $parent, string $page_title, string $menu_title, string $capability, string $slug, $callback = '' ) {
	return Hoobert_Test_State::$submenu_hook;
}

/**
 * @param string[] $deps Dependency handles.
 * @param string|bool|null $version Version string.
 */
function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $version = false, string $media = 'all' ): void {
	Hoobert_Test_State::$enqueued_styles[] = $handle;
}

/**
 * @param mixed $data Value to encode.
 * @return string|false
 */
function wp_json_encode( $data ) {
	return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

function __( string $text, string $domain = 'default' ): string { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.textFound
	return $text;
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return $text;
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

/**
 * Approximates core's behaviour for the case that matters here: a URL whose
 * scheme is not in the allowed protocol list comes back empty.
 */
function esc_url_raw( string $url ): string {
	$url    = trim( $url );
	$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
	return in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
}

function sanitize_text_field( string $value ): string {
	return trim( strip_tags( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
}

function untrailingslashit( string $value ): string {
	return rtrim( $value, '/\\' );
}

function wp_strip_all_tags( string $text ): string {
	return trim( strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
}

/**
 * Fixed UTC formatting, so date assertions do not depend on the host timezone.
 */
function wp_date( string $format, ?int $timestamp = null ): string {
	return gmdate( $format, $timestamp ?? time() );
}

/**
 * Stands in for WooCommerce's price formatter, entity-encoded currency symbol
 * and wrapping markup included, since the executor strips and decodes both.
 *
 * @param float|string $price Amount.
 */
function wc_price( $price ): string {
	return '<span class="woocommerce-Price-amount amount">&#36;' . number_format( (float) $price, 2 ) . '</span>';
}
