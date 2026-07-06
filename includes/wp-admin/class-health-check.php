<?php
/**
 * Site Health diagnostics.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\WP_Admin;

use Enable_Mastodon_Apps\Mastodon_API;
use WP_Error;
use WP_REST_Request;

/**
 * Site Health diagnostics for common hosting and proxy issues.
 */
class Health_Check {
	const AUTH_HEADER_ROUTE       = 'diagnostics/authorization-header';
	const AUTH_HEADER_TRANSIENT   = 'ema_health_check_auth_header_';
	const AUTH_HEADER_TEST_VALUE  = 'Bearer ema-health-check';
	const REQUEST_TIMEOUT_SECONDS = 10;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_filter( 'site_status_tests', array( self::class, 'add_tests' ) );
		add_filter( 'debug_information', array( self::class, 'debug_information' ) );
	}

	/**
	 * Register diagnostic REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			Mastodon_API::PREFIX,
			self::AUTH_HEADER_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'authorization_header_diagnostic' ),
				'permission_callback' => array( self::class, 'diagnostic_permission' ),
				'args'                => array(
					'check' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check whether a diagnostic route request has a valid short-lived token.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function diagnostic_permission( WP_REST_Request $request ) {
		$check = $request->get_param( 'check' );
		if ( $check && get_transient( self::AUTH_HEADER_TRANSIENT . $check ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'The diagnostic token is invalid or expired.', 'enable-mastodon-apps' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Report whether the Authorization header reached WordPress.
	 *
	 * This intentionally returns booleans only and never returns the header value.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array Diagnostic data.
	 */
	public static function authorization_header_diagnostic( WP_REST_Request $request ) {
		$authorization = $request->get_header( 'authorization' );

		$scheme = '';
		if ( $authorization && preg_match( '/^\s*([a-z]+)\s+/i', $authorization, $matches ) ) {
			$scheme = strtolower( $matches[1] );
		}

		return array(
			'authorization_header_present'          => (bool) $authorization || ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) || ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ),
			'authorization_scheme'                  => $scheme,
			'server_http_authorization_present'     => ! empty( $_SERVER['HTTP_AUTHORIZATION'] ),
			'server_redirect_authorization_present' => ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ),
		);
	}

	/**
	 * Add Site Health tests.
	 *
	 * @param array $tests Site Health tests.
	 * @return array Site Health tests.
	 */
	public static function add_tests( $tests ) {
		$tests['direct']['enable_mastodon_apps_rest_api'] = array(
			'label' => __( 'Mastodon Apps REST API Test', 'enable-mastodon-apps' ),
			'test'  => array( self::class, 'test_rest_api' ),
		);

		$tests['direct']['enable_mastodon_apps_short_api_paths'] = array(
			'label' => __( 'Mastodon API Path Test', 'enable-mastodon-apps' ),
			'test'  => array( self::class, 'test_short_api_paths' ),
		);

		$tests['direct']['enable_mastodon_apps_oauth_endpoints'] = array(
			'label' => __( 'Mastodon Apps OAuth Endpoint Test', 'enable-mastodon-apps' ),
			'test'  => array( self::class, 'test_oauth_endpoints' ),
		);

		$tests['direct']['enable_mastodon_apps_authorization_header'] = array(
			'label' => __( 'Mastodon Apps Authorization Header Test', 'enable-mastodon-apps' ),
			'test'  => array( self::class, 'test_authorization_header' ),
		);

		$tests['direct']['enable_mastodon_apps_cors_preflight'] = array(
			'label' => __( 'Mastodon Apps CORS Preflight Test', 'enable-mastodon-apps' ),
			'test'  => array( self::class, 'test_cors_preflight' ),
		);

		return $tests;
	}

	/**
	 * Add plugin diagnostics to Site Health debug information.
	 *
	 * @param array $info Debug information.
	 * @return array Debug information.
	 */
	public static function debug_information( $info ) {
		$info['enable_mastodon_apps'] = array(
			'label'  => __( 'Enable Mastodon Apps', 'enable-mastodon-apps' ),
			'fields' => array(
				'version'             => array(
					'label'   => __( 'Version', 'enable-mastodon-apps' ),
					'value'   => ENABLE_MASTODON_APPS_VERSION,
					'private' => false,
				),
				'home_url'            => array(
					'label'   => __( 'Home URL', 'enable-mastodon-apps' ),
					'value'   => home_url(),
					'private' => false,
				),
				'rest_instance_url'   => array(
					'label'   => __( 'REST Instance URL', 'enable-mastodon-apps' ),
					'value'   => self::get_rest_route_url( 'api/v1/instance' ),
					'private' => false,
				),
				'short_instance_url'  => array(
					'label'   => __( 'Short Instance URL', 'enable-mastodon-apps' ),
					'value'   => home_url( '/api/v1/instance' ),
					'private' => false,
				),
				'oauth_authorize_url' => array(
					'label'   => __( 'OAuth Authorize URL', 'enable-mastodon-apps' ),
					'value'   => home_url( '/oauth/authorize' ),
					'private' => false,
				),
				'oauth_token_url'     => array(
					'label'   => __( 'OAuth Token URL', 'enable-mastodon-apps' ),
					'value'   => home_url( '/oauth/token' ),
					'private' => false,
				),
				'debug_mode_active'   => array(
					'label'   => __( 'Debug Mode Active', 'enable-mastodon-apps' ),
					'value'   => get_option( 'mastodon_api_debug_mode' ) > time() ? 'yes' : 'no',
					'private' => false,
				),
			),
		);

		return $info;
	}

	/**
	 * Check the prefixed WordPress REST API route.
	 *
	 * @return array Site Health result.
	 */
	public static function test_rest_api() {
		$result = self::get_base_result(
			__( 'Mastodon Apps REST API is reachable', 'enable-mastodon-apps' ),
			__( 'The WordPress REST API route for the Mastodon instance endpoint is reachable.', 'enable-mastodon-apps' ),
			'test_rest_api'
		);

		$check = self::is_json_endpoint_reachable( self::get_rest_route_url( 'api/v1/instance' ) );
		if ( true === $check ) {
			return $result;
		}

		$result['status']         = 'critical';
		$result['label']          = __( 'Mastodon Apps REST API is not reachable', 'enable-mastodon-apps' );
		$result['badge']['color'] = 'red';
		$result['description']    = self::paragraphs(
			__( 'WordPress could not reach the plugin REST API route through an HTTP loopback request.', 'enable-mastodon-apps' ),
			$check->get_error_message()
		);
		$result['actions']        = self::paragraphs(
			__( 'Check whether a security plugin, maintenance mode plugin, firewall, or host-level rule is blocking WordPress REST API requests.', 'enable-mastodon-apps' )
		);

		return $result;
	}

	/**
	 * Check the short /api/v1/... paths used by Mastodon clients.
	 *
	 * @return array Site Health result.
	 */
	public static function test_short_api_paths() {
		$result = self::get_base_result(
			__( 'Mastodon API paths are reachable', 'enable-mastodon-apps' ),
			__( 'The short Mastodon API paths are reaching the plugin instead of a WordPress 404 page.', 'enable-mastodon-apps' ),
			'test_short_api_paths'
		);

		$check = self::is_json_endpoint_reachable( home_url( '/api/v1/instance' ) );
		if ( true === $check ) {
			return $result;
		}

		$result['status']         = 'critical';
		$result['label']          = __( 'Mastodon API paths are not reachable', 'enable-mastodon-apps' );
		$result['badge']['color'] = 'red';
		$result['description']    = self::paragraphs(
			__( 'Mastodon clients call short paths such as /api/v1/instance. This request did not reach the plugin correctly.', 'enable-mastodon-apps' ),
			$check->get_error_message()
		);
		$result['actions']        = self::paragraphs(
			__( 'Save your permalink settings once to flush rewrite rules. If WordPress is installed in a subdirectory, make sure your web server forwards /api/ requests to that WordPress installation.', 'enable-mastodon-apps' )
		);

		return $result;
	}

	/**
	 * Check the OAuth authorize and token endpoints.
	 *
	 * @return array Site Health result.
	 */
	public static function test_oauth_endpoints() {
		$result = self::get_base_result(
			__( 'OAuth endpoints are reachable', 'enable-mastodon-apps' ),
			__( 'The OAuth authorize and token endpoints are handled by the plugin.', 'enable-mastodon-apps' ),
			'test_oauth_endpoints'
		);

		$checks = array(
			self::is_oauth_authorize_endpoint_reachable(),
			self::is_oauth_token_endpoint_reachable(),
		);

		$errors = array_filter(
			$checks,
			function ( $check ) {
				return is_wp_error( $check );
			}
		);

		if ( empty( $errors ) ) {
			return $result;
		}

		$result['status']         = 'critical';
		$result['label']          = __( 'OAuth endpoints are not reachable', 'enable-mastodon-apps' );
		$result['badge']['color'] = 'red';
		$error_messages = array_map(
			function ( $error ) {
				return $error->get_error_message();
			},
			$errors
		);

		$result['description']    = self::paragraphs(
			__( 'Mastodon apps need /oauth/authorize and /oauth/token during sign-in. One or both endpoints did not behave like plugin OAuth endpoints.', 'enable-mastodon-apps' ),
			implode( ' ', $error_messages )
		);
		$result['actions']        = self::paragraphs(
			__( 'Check rewrite rules, host redirects, managed login layers, and plugins that alter WordPress login or canonical redirects.', 'enable-mastodon-apps' )
		);

		return $result;
	}

	/**
	 * Check whether Authorization headers reach WordPress.
	 *
	 * @return array Site Health result.
	 */
	public static function test_authorization_header() {
		$result = self::get_base_result(
			__( 'Authorization headers reach WordPress', 'enable-mastodon-apps' ),
			__( 'A loopback request with an Authorization header reached WordPress with the header intact.', 'enable-mastodon-apps' ),
			'test_authorization_header'
		);

		$check = self::is_authorization_header_forwarded();
		if ( true === $check ) {
			return $result;
		}

		$result['status']         = 'critical';
		$result['label']          = __( 'Authorization headers do not reach WordPress', 'enable-mastodon-apps' );
		$result['badge']['color'] = 'red';
		$result['description']    = self::paragraphs(
			__( 'The diagnostic request reached WordPress, but the Authorization header was missing. Mastodon clients will fail authenticated requests with token-required when this happens.', 'enable-mastodon-apps' ),
			$check->get_error_message()
		);
		$result['actions']        = sprintf(
			'<p>%s</p><pre>%s</pre><p>%s</p><pre>%s</pre>',
			__( 'For Apache or LiteSpeed, hosts often fix this with:', 'enable-mastodon-apps' ),
			esc_html( 'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]' ),
			__( 'For Nginx with PHP-FPM, hosts often fix this with:', 'enable-mastodon-apps' ),
			esc_html( 'fastcgi_param HTTP_AUTHORIZATION $http_authorization;' )
		);

		return $result;
	}

	/**
	 * Check whether CORS preflight requests work for web clients.
	 *
	 * @return array Site Health result.
	 */
	public static function test_cors_preflight() {
		$result = self::get_base_result(
			__( 'CORS preflight requests work', 'enable-mastodon-apps' ),
			__( 'The app registration endpoint responds to browser preflight requests with the expected CORS headers.', 'enable-mastodon-apps' ),
			'test_cors_preflight'
		);

		$check = self::is_cors_preflight_working();
		if ( true === $check ) {
			return $result;
		}

		$result['status']         = 'recommended';
		$result['label']          = __( 'CORS preflight requests may be blocked', 'enable-mastodon-apps' );
		$result['badge']['color'] = 'orange';
		$result['description']    = self::paragraphs(
			__( 'Browser-based clients such as Elk need CORS preflight requests to succeed before they can register or call the API.', 'enable-mastodon-apps' ),
			$check->get_error_message()
		);
		$result['actions']        = self::paragraphs(
			__( 'Check whether your host, CDN, firewall, or security plugin blocks OPTIONS requests or removes Access-Control-Allow-* response headers.', 'enable-mastodon-apps' )
		);

		return $result;
	}

	/**
	 * Check whether a JSON endpoint is reachable.
	 *
	 * @param string $url URL to request.
	 * @return true|WP_Error True on success, WP_Error otherwise.
	 */
	private static function is_json_endpoint_reachable( $url ) {
		$response = self::request(
			$url,
			array(
				'method'  => 'GET',
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'http_request_failed',
				sprintf(
					// translators: %1$s: URL, %2$s: error message.
					__( 'The request to %1$s failed: %2$s', 'enable-mastodon-apps' ),
					'<code>' . esc_html( $url ) . '</code>',
					esc_html( $response->get_error_message() )
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'unexpected_status',
				sprintf(
					// translators: %1$s: URL, %2$d: HTTP status code.
					__( 'The request to %1$s returned HTTP %2$d.', 'enable-mastodon-apps' ),
					'<code>' . esc_html( $url ) . '</code>',
					$code
				)
			);
		}

		if ( ! self::response_is_json( $response ) ) {
			return new WP_Error(
				'unexpected_content_type',
				sprintf(
					// translators: %1$s: URL, %2$s: content type.
					__( 'The request to %1$s did not return JSON. Content-Type was %2$s.', 'enable-mastodon-apps' ),
					'<code>' . esc_html( $url ) . '</code>',
					'<code>' . esc_html( self::get_content_type( $response ) ) . '</code>'
				)
			);
		}

		return true;
	}

	/**
	 * Check OAuth authorize endpoint reachability.
	 *
	 * @return true|WP_Error True on success, WP_Error otherwise.
	 */
	private static function is_oauth_authorize_endpoint_reachable() {
		$url = home_url( '/oauth/authorize' );

		$response = self::request(
			$url,
			array(
				'method'  => 'GET',
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		return self::is_oauth_error_response( $response, $url );
	}

	/**
	 * Check OAuth token endpoint reachability.
	 *
	 * @return true|WP_Error True on success, WP_Error otherwise.
	 */
	private static function is_oauth_token_endpoint_reachable() {
		$url = home_url( '/oauth/token' );

		$response = self::request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		return self::is_oauth_error_response( $response, $url );
	}

	/**
	 * Check whether a response looks like an OAuth error from the plugin.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @param string         $url      Requested URL.
	 * @return true|WP_Error True on success, WP_Error otherwise.
	 */
	private static function is_oauth_error_response( $response, $url ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'oauth_request_failed',
				sprintf(
					// translators: %1$s: URL, %2$s: error message.
					__( 'The OAuth request to %1$s failed: %2$s', 'enable-mastodon-apps' ),
					'<code>' . esc_html( $url ) . '</code>',
					esc_html( $response->get_error_message() )
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 300 && $code < 400 ) {
			return new WP_Error(
				'oauth_redirected',
				sprintf(
					// translators: %1$s: URL, %2$s: redirect location.
					__( 'The OAuth request to %1$s was redirected to %2$s.', 'enable-mastodon-apps' ),
					'<code>' . esc_html( $url ) . '</code>',
					'<code>' . esc_html( wp_remote_retrieve_header( $response, 'location' ) ) . '</code>'
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 && $code < 500 && is_array( $body ) && isset( $body['error'] ) ) {
			return true;
		}

		return new WP_Error(
			'oauth_unexpected_response',
			sprintf(
				// translators: %1$s: URL, %2$d: HTTP status code, %3$s: content type.
				__( 'The OAuth request to %1$s returned HTTP %2$d with Content-Type %3$s instead of a JSON OAuth error.', 'enable-mastodon-apps' ),
				'<code>' . esc_html( $url ) . '</code>',
				$code,
				'<code>' . esc_html( self::get_content_type( $response ) ) . '</code>'
			)
		);
	}

	/**
	 * Check whether Authorization headers are forwarded.
	 *
	 * @return true|WP_Error True on success, WP_Error otherwise.
	 */
	private static function is_authorization_header_forwarded() {
		$check = wp_generate_password( 32, false, false );
		set_transient( self::AUTH_HEADER_TRANSIENT . $check, 1, 5 * MINUTE_IN_SECONDS );

		$url = add_query_arg(
			'check',
			rawurlencode( $check ),
			self::get_rest_route_url( self::AUTH_HEADER_ROUTE )
		);

		$response = self::request(
			$url,
			array(
				'method'  => 'GET',
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => self::AUTH_HEADER_TEST_VALUE,
				),
			)
		);

		delete_transient( self::AUTH_HEADER_TRANSIENT . $check );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'authorization_header_request_failed',
				sprintf(
					// translators: %s: error message.
					__( 'The Authorization header diagnostic request failed: %s', 'enable-mastodon-apps' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'authorization_header_unexpected_status',
				sprintf(
					// translators: %d: HTTP status code.
					__( 'The Authorization header diagnostic route returned HTTP %d.', 'enable-mastodon-apps' ),
					$code
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'authorization_header_invalid_response',
				__( 'The Authorization header diagnostic route did not return JSON.', 'enable-mastodon-apps' )
			);
		}

		if ( ! empty( $body['authorization_header_present'] ) ) {
			return true;
		}

		return new WP_Error(
			'authorization_header_missing',
			__( 'The loopback request included an Authorization header, but WordPress did not receive it.', 'enable-mastodon-apps' )
		);
	}

	/**
	 * Check whether CORS preflight works.
	 *
	 * @return true|WP_Error True on success, WP_Error otherwise.
	 */
	private static function is_cors_preflight_working() {
		$response = self::request(
			self::get_rest_route_url( 'api/v1/apps' ),
			array(
				'method'  => 'OPTIONS',
				'headers' => array(
					'Origin'                         => home_url(),
					'Access-Control-Request-Method'  => 'POST',
					'Access-Control-Request-Headers' => 'content-type, authorization',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'cors_request_failed',
				sprintf(
					// translators: %s: error message.
					__( 'The CORS preflight request failed: %s', 'enable-mastodon-apps' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return new WP_Error(
				'cors_unexpected_status',
				sprintf(
					// translators: %d: HTTP status code.
					__( 'The CORS preflight request returned HTTP %d.', 'enable-mastodon-apps' ),
					$code
				)
			);
		}

		$allow_origin  = wp_remote_retrieve_header( $response, 'access-control-allow-origin' );
		$allow_headers = wp_remote_retrieve_header( $response, 'access-control-allow-headers' );

		if ( ! $allow_origin ) {
			return new WP_Error(
				'cors_missing_allow_origin',
				__( 'The CORS preflight response did not include Access-Control-Allow-Origin.', 'enable-mastodon-apps' )
			);
		}

		if ( false === stripos( $allow_headers, 'authorization' ) || false === stripos( $allow_headers, 'content-type' ) ) {
			return new WP_Error(
				'cors_missing_allow_headers',
				__( 'The CORS preflight response did not allow both authorization and content-type headers.', 'enable-mastodon-apps' )
			);
		}

		return true;
	}

	/**
	 * Make a loopback HTTP request.
	 *
	 * @param string $url  URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error HTTP response or error.
	 */
	private static function request( $url, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'timeout'     => self::REQUEST_TIMEOUT_SECONDS,
				'redirection' => 0,
				'user-agent'  => 'Enable Mastodon Apps/' . ENABLE_MASTODON_APPS_VERSION . '; ' . home_url(),
			)
		);

		return wp_safe_remote_request( $url, $args );
	}

	/**
	 * Build a route URL.
	 *
	 * @param string $route Route without the plugin prefix.
	 * @return string URL.
	 */
	private static function get_rest_route_url( $route ) {
		return get_rest_url( null, '/' . Mastodon_API::PREFIX . '/' . ltrim( $route, '/' ) );
	}

	/**
	 * Get a base Site Health result.
	 *
	 * @param string $label       Result label.
	 * @param string $description Result description.
	 * @param string $test        Test name.
	 * @return array Site Health result.
	 */
	private static function get_base_result( $label, $description, $test ) {
		return array(
			'label'       => $label,
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Mastodon Apps', 'enable-mastodon-apps' ),
				'color' => 'green',
			),
			'description' => self::paragraphs( $description ),
			'actions'     => '',
			'test'        => $test,
		);
	}

	/**
	 * Create one or more HTML paragraphs.
	 *
	 * @param string ...$paragraphs Paragraph text.
	 * @return string HTML paragraphs.
	 */
	private static function paragraphs( ...$paragraphs ) {
		$html = '';
		foreach ( array_filter( $paragraphs ) as $paragraph ) {
			$html .= sprintf( '<p>%s</p>', wp_kses_post( $paragraph ) );
		}
		return $html;
	}

	/**
	 * Check whether a HTTP response is JSON.
	 *
	 * @param array $response HTTP response.
	 * @return bool True if JSON.
	 */
	private static function response_is_json( $response ) {
		if ( false !== stripos( self::get_content_type( $response ), 'application/json' ) ) {
			return true;
		}

		json_decode( wp_remote_retrieve_body( $response ), true );
		return JSON_ERROR_NONE === json_last_error();
	}

	/**
	 * Get response content type.
	 *
	 * @param array $response HTTP response.
	 * @return string Content type.
	 */
	private static function get_content_type( $response ) {
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( ! $content_type ) {
			return __( 'none', 'enable-mastodon-apps' );
		}
		return $content_type;
	}
}
