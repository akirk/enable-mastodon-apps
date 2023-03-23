<?php
/**
 * Class Test_Apps_Endpoint
 *
 * @package MastoAPI
 */

namespace MastoAPI;

/**
 * A testcase for the apps endpoint.
 *
 * @package
 */
class AppsEndpoint_Test extends MastoAPI_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . MastoAPI::PREFIX . '/api/v1/apps', $routes );
	}

	public function test_apps_success() {
		global $wp_rest_server;
		$name = 'test123';
		$redirect_uri = 'https://test';
		$website = 'https://mastodon.local';
		$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
		$request->set_param( 'client_name', $name );
		$request->set_param( 'redirect_uris', $redirect_uri );
		$request->set_param( 'website', $website );
		$request->set_param( 'scopes', 'read write follow push' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'client_id', $data );
		$this->assertIsString( $data['client_id'] );

		$this->assertArrayHasKey( 'client_secret', $data );
		$this->assertIsString( $data['client_secret'] );

		$this->assertArrayHasKey( 'redirect_uris', $data );
		$this->assertIsArray( $data['redirect_uris'] );
		$this->assertContains( $redirect_uri, $data['redirect_uris'] );

		$this->assertArrayHasKey( 'name', $data );
		$this->assertEquals( $name, $data['name'] );

		$this->assertArrayHasKey( 'website', $data );
		$this->assertEquals( $website, $data['website'] );

	}

	public function test_apps_failures() {
		global $wp_rest_server;
		update_option( 'mastodon_api_disable_logins', 1 );

		$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'code', $data );
		$this->assertIsString( $data['code'] );
		$this->assertEquals( $data['code'], 'registation-disabled' );

		delete_option( 'mastodon_api_disable_logins' );

		foreach ( array(
			'',
			'test',
		) as $invalid_client_name ) {
			$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' );
			$request->set_param( 'client_name', $invalid_client_name );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 422, $response->get_status() );

			$data = $response->get_data();

			$this->assertArrayHasKey( 'code', $data, $invalid_client_name );
			$this->assertIsString( $data['code'], $invalid_client_name );
			$this->assertEquals( $data['code'], 'invalid-client_name', $invalid_client_name );
		}

		foreach ( array(
			'test123',
		) as $valid_client_name ) {
			$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' );
			$request->set_param( 'client_name', $valid_client_name );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 422, $response->get_status() );

			$data = $response->get_data();

			$this->assertArrayHasKey( 'code', $data, $valid_client_name );
			$this->assertIsString( $data['code'], $valid_client_name );
			$this->assertEquals( $data['code'], 'invalid-redirect_uris', $valid_client_name );
		}

		foreach ( array(
			'hello',
		) as $invalid_redirect_uris ) {
			$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' );
			$request->set_param( 'redirect_uris', $invalid_redirect_uris );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 422, $response->get_status() );

			$data = $response->get_data();

			$this->assertArrayHasKey( 'code', $data, $invalid_redirect_uris );
			$this->assertIsString( $data['code'], $invalid_redirect_uris );
			$this->assertEquals( $data['code'], 'invalid-redirect_uris', $invalid_redirect_uris );
		}

		foreach ( array(
			'https://test',
		) as $valid_redirect_uris ) {
			$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' );
			$request->set_param( 'redirect_uris', $valid_redirect_uris );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 422, $response->get_status() );

			$data = $response->get_data();

			$this->assertArrayHasKey( 'code', $data, $valid_redirect_uris );
			$this->assertIsString( $data['code'], $valid_redirect_uris );
			$this->assertEquals( $data['code'], 'invalid-scopes', $valid_redirect_uris );
		}

		foreach ( array(
			'',
			'hello',
		) as $invalid_scopes ) {
			$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' );
			$request->set_param( 'redirect_uris', 'https://test' );
			$request->set_param( 'scopes', $invalid_scopes );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 422, $response->get_status() );

			$data = $response->get_data();

			$this->assertArrayHasKey( 'code', $data, $invalid_scopes );
			$this->assertIsString( $data['code'], $invalid_scopes );
			$this->assertEquals( $data['code'], 'invalid-scopes', $invalid_scopes );
		}

		foreach ( array(
			'hello',
		) as $invalid_website ) {
			$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' );
			$request->set_param( 'redirect_uris', 'https://test' );
			$request->set_param( 'website', $invalid_website );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 422, $response->get_status() );

			$data = $response->get_data();

			$this->assertArrayHasKey( 'code', $data, $invalid_website );
			$this->assertIsString( $data['code'], $invalid_website );
			$this->assertEquals( $data['code'], 'invalid-scopes', $invalid_website );
		}

		foreach ( array(
			'',
			'https://test',
			'protocol:test',
		) as $valid_website ) {
			$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' );
			$request->set_param( 'redirect_uris', 'https://test' );
			$request->set_param( 'website', $valid_website );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 422, $response->get_status() );

			$data = $response->get_data();

			$this->assertArrayHasKey( 'code', $data, $valid_website );
			$this->assertIsString( $data['code'], $valid_website );
			$this->assertEquals( $data['code'], 'invalid-scopes', $valid_website );
		}

		foreach ( array(
			'read',
			'read write',
			'read write follow',
			'read write follow push',
			'write',
			'write follow',
			'write follow push',
		) as $i => $valid_scope ) {
			$request = new \WP_REST_Request( 'POST', '/' . MastoAPI::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' . $i );
			$request->set_param( 'redirect_uris', 'https://test' );
			$request->set_param( 'scopes', $valid_scope );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 200, $response->get_status() );
		}
	}

}
