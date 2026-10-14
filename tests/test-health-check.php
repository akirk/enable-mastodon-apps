<?php
/**
 * Class Health_Check_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use Enable_Mastodon_Apps\WP_Admin\Health_Check;

/**
 * Tests for the Site Health diagnostics.
 */
class Health_Check_Test extends \WP_UnitTestCase {
	private $http_callback;
	private $http_requests = array();

	public function set_up() {
		parent::set_up();
		$this->http_requests = array();
	}

	public function tear_down() {
		if ( $this->http_callback ) {
			remove_filter( 'pre_http_request', $this->http_callback, 5 );
			$this->http_callback = null;
		}

		parent::tear_down();
	}

	public function test_add_tests() {
		$tests = Health_Check::add_tests( array() );

		$this->assertArrayHasKey( 'direct', $tests );
		$this->assertArrayHasKey( 'enable_mastodon_apps_rest_api', $tests['direct'] );
		$this->assertArrayHasKey( 'enable_mastodon_apps_short_api_paths', $tests['direct'] );
		$this->assertArrayHasKey( 'enable_mastodon_apps_oauth_endpoints', $tests['direct'] );
		$this->assertArrayHasKey( 'enable_mastodon_apps_authorization_header', $tests['direct'] );
		$this->assertArrayHasKey( 'enable_mastodon_apps_cors_preflight', $tests['direct'] );
		$this->assertSame( array( Health_Check::class, 'test_authorization_header' ), $tests['direct']['enable_mastodon_apps_authorization_header']['test'] );
	}

	public function test_debug_information() {
		$info = Health_Check::debug_information( array() );

		$this->assertArrayHasKey( 'enable_mastodon_apps', $info );
		$this->assertSame( 'Enable Mastodon Apps', $info['enable_mastodon_apps']['label'] );
		$this->assertArrayHasKey( 'version', $info['enable_mastodon_apps']['fields'] );
		$this->assertArrayHasKey( 'rest_instance_url', $info['enable_mastodon_apps']['fields'] );
		$this->assertArrayHasKey( 'oauth_token_url', $info['enable_mastodon_apps']['fields'] );
	}

	public function test_authorization_header_diagnostic_reports_presence_without_value() {
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/' . Health_Check::AUTH_HEADER_ROUTE );
		$request->set_header( 'authorization', 'Bearer secret-token' );

		$response = Health_Check::authorization_header_diagnostic( $request );

		$this->assertTrue( $response['authorization_header_present'] );
		$this->assertSame( 'bearer', $response['authorization_scheme'] );
		$this->assertStringNotContainsString( 'secret-token', wp_json_encode( $response ) );
	}

	public function test_rest_api_check_good() {
		$this->mock_http_response(
			$this->http_response(
				200,
				array( 'uri' => 'example.org' ),
				array( 'content-type' => 'application/json' )
			)
		);

		$result = Health_Check::test_rest_api();

		$this->assertSame( 'good', $result['status'] );
	}

	public function test_rest_api_check_critical_on_html_404() {
		$this->mock_http_response(
			$this->http_response(
				404,
				'<html><body>Not found</body></html>',
				array( 'content-type' => 'text/html' )
			)
		);

		$result = Health_Check::test_rest_api();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'HTTP 404', $result['description'] );
	}

	public function test_oauth_endpoint_check_good_with_oauth_errors() {
		$this->mock_http_response(
			$this->http_response(
				400,
				array(
					'error'             => 'invalid_client',
					'error_description' => 'The client credentials are invalid.',
				),
				array( 'content-type' => 'application/json' )
			)
		);

		$result = Health_Check::test_oauth_endpoints();

		$this->assertSame( 'good', $result['status'] );
	}

	public function test_oauth_endpoint_check_critical_on_html_redirect() {
		$this->mock_http_response(
			$this->http_response(
				302,
				'<html><body>Redirect</body></html>',
				array(
					'content-type' => 'text/html',
					'location'     => home_url( '/wp-admin/' ),
				)
			)
		);

		$result = Health_Check::test_oauth_endpoints();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'redirected', $result['description'] );
	}

	public function test_authorization_header_check_good() {
		$this->mock_http_response(
			$this->http_response(
				200,
				array(
					'authorization_header_present' => true,
					'authorization_scheme'         => 'bearer',
				),
				array( 'content-type' => 'application/json' )
			)
		);

		$result = Health_Check::test_authorization_header();

		$this->assertSame( 'good', $result['status'] );
	}

	public function test_authorization_header_check_critical_when_missing() {
		$this->mock_http_response(
			$this->http_response(
				200,
				array(
					'authorization_header_present' => false,
					'authorization_scheme'         => '',
				),
				array( 'content-type' => 'application/json' )
			)
		);

		$result = Health_Check::test_authorization_header();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'Authorization header was missing', $result['description'] );
		$this->assertStringContainsString( 'HTTP_AUTHORIZATION', $result['actions'] );
	}

	public function test_authorization_header_check_recommended_when_route_is_unreachable() {
		$this->mock_http_response(
			$this->http_response(
				403,
				array(
					'code'    => 'rest_forbidden',
					'message' => 'Sorry, you are not allowed to do that.',
				),
				array( 'content-type' => 'application/json' )
			)
		);

		$result = Health_Check::test_authorization_header();

		// A test that could not run must not be reported as a stripped Authorization header.
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'HTTP 403', $result['description'] );
		$this->assertStringContainsString( 'rest_forbidden', $result['description'] );
		$this->assertStringNotContainsString( 'was missing', $result['description'] );
		$this->assertStringNotContainsString( 'HTTP_AUTHORIZATION', $result['actions'] );
	}

	public function test_authorization_header_check_recommended_when_response_is_not_the_diagnostic() {
		$this->mock_http_response(
			$this->http_response(
				200,
				array( 'hello' => 'world' ),
				array( 'content-type' => 'application/json' )
			)
		);

		$result = Health_Check::test_authorization_header();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringNotContainsString( 'HTTP_AUTHORIZATION', $result['actions'] );
	}

	public function test_authorization_header_check_sends_an_authorization_header() {
		$this->mock_http_response(
			$this->http_response(
				200,
				array( 'authorization_header_present' => true ),
				array( 'content-type' => 'application/json' )
			)
		);

		Health_Check::test_authorization_header();

		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( Health_Check::AUTH_HEADER_TEST_VALUE, $this->http_requests[0]['args']['headers']['Authorization'] );
	}

	public function test_diagnostic_permission_accepts_the_token_the_test_sends() {
		$this->mock_http_response(
			$this->http_response(
				200,
				array( 'authorization_header_present' => true ),
				array( 'content-type' => 'application/json' )
			)
		);

		Health_Check::test_authorization_header();

		$this->assertCount( 1, $this->http_requests );
		$query = array();
		parse_str( (string) wp_parse_url( $this->http_requests[0]['url'], PHP_URL_QUERY ), $query );
		$this->assertArrayHasKey( 'check', $query );

		$this->assertTrue( Health_Check::diagnostic_permission( $this->diagnostic_request( $query['check'] ) ) );
	}

	public function test_diagnostic_permission_rejects_an_invalid_token() {
		$this->assertWPError( Health_Check::diagnostic_permission( $this->diagnostic_request( '' ) ) );
		$this->assertWPError( Health_Check::diagnostic_permission( $this->diagnostic_request( 'not-a-token' ) ) );
		$this->assertWPError( Health_Check::diagnostic_permission( $this->diagnostic_request( ( time() + 300 ) . ':nonce:signature' ) ) );
	}

	private function diagnostic_request( $check ) {
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/' . Health_Check::AUTH_HEADER_ROUTE );
		$request->set_param( 'check', $check );

		return $request;
	}

	public function test_cors_preflight_check_good() {
		$this->mock_http_response(
			$this->http_response(
				200,
				'',
				array(
					'access-control-allow-origin'  => '*',
					'access-control-allow-headers' => 'content-type, authorization',
				)
			)
		);

		$result = Health_Check::test_cors_preflight();

		$this->assertSame( 'good', $result['status'] );
	}

	public function test_cors_preflight_check_recommended_when_headers_missing() {
		$this->mock_http_response(
			$this->http_response(
				200,
				'',
				array(
					'access-control-allow-origin'  => '*',
					'access-control-allow-headers' => 'content-type',
				)
			)
		);

		$result = Health_Check::test_cors_preflight();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'authorization', $result['description'] );
	}

	private function mock_http_response( $response ) {
		if ( $this->http_callback ) {
			remove_filter( 'pre_http_request', $this->http_callback, 5 );
		}

		$this->http_callback = function ( $preempt, $args, $url ) use ( $response ) {
			$this->http_requests[] = array(
				'url'  => $url,
				'args' => $args,
			);
			return $response;
		};

		add_filter( 'pre_http_request', $this->http_callback, 5, 3 );
	}

	private function http_response( $code, $body, $headers = array() ) {
		if ( is_array( $body ) ) {
			$body = wp_json_encode( $body );
		}

		return array(
			'headers'  => $headers,
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
