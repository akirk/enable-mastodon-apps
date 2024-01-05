<?php
/**
 * Class Test_Apps_Endpoint
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * A testcase for the apps endpoint.
 *
 * @package
 */
class AppsEndpoint_Test extends Mastodon_API_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/apps', $routes );
	}

	public function test_apps_success() {
		global $wp_rest_server;
		$name = 'test123';
		$redirect_uri = 'https://test';
		$website = 'https://mastodon.local';
		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
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

		$this->assertArrayHasKey( 'redirect_uri', $data );
		$this->assertIsString( $data['redirect_uri'] );
		$this->assertEquals( $redirect_uri, $data['redirect_uri'] );

		$this->assertArrayHasKey( 'name', $data );
		$this->assertEquals( $name, $data['name'] );

		$this->assertArrayHasKey( 'website', $data );
		$this->assertEquals( $website, $data['website'] );
	}

	public function test_apps_failures() {
		global $wp_rest_server;
		update_option( 'mastodon_api_disable_logins', 1 );

		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'code', $data );
		$this->assertIsString( $data['code'] );
		$this->assertEquals( $data['code'], 'registation-disabled' );

		delete_option( 'mastodon_api_disable_logins' );

		foreach ( array(
			'',
			'te',
		) as $invalid_client_name ) {
			$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
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
			$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
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
			$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
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
			$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
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
			$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
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
			$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
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
			$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
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
			'write:statuses',
			'write follow',
			'write follow push',
		) as $i => $valid_scope ) {
			$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
			$request->set_param( 'client_name', 'test123' . $i );
			$request->set_param( 'redirect_uris', 'https://test' );
			$request->set_param( 'scopes', $valid_scope );
			$response = $wp_rest_server->dispatch( $request );
			$this->assertEquals( 200, $response->get_status() );
		}
	}


	public function test_apps_auto_reregister() {
		global $wp_rest_server;
		$old_get = $_GET;
		$old_post = $_POST;
		$old_request = $_REQUEST;
		$old_request_method = $_SERVER['REQUEST_METHOD'];
		$old_request_uri = $_SERVER['REQUEST_URI'];

		$client_name = 'newapp1';

		// Create a new app.
		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
		$request->set_param( 'client_name', $client_name );
		$request->set_param( 'redirect_uris', 'https://test' );
		$request->set_param( 'scopes', 'read write follow push' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		// Access the authorize endpoint.
		$_REQUEST = array(
			'client_id'     => $data['client_id'],
			'redirect_uri'  => 'https://test',
			'response_type' => 'code',
			'scope'         => 'read write follow push',
		);
		$_GET = $_REQUEST;
		$_POST = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = add_query_arg( $_REQUEST, '/oauth/authorize' );
		$oauth = new Mastodon_OAuth();
		$response = $oauth->handle_oauth( 'response' );
		$this->assertEquals( 302, $response->getStatusCode() );

		// And pretend the user clicked authorize.
		$handler = $oauth->handle_oauth( 'handler' );
		$request  = \OAuth2\Request::createFromGlobals();
		$response = $handler->test_authorize( $request, $this->administrator );
		$this->assertEquals( 302, $response->getStatusCode() );

		// Now use the code to get an access token.
		$location = $response->getHttpHeader( 'Location' );
		$_REQUEST = array(
			'client_id'     => $data['client_id'],
			'client_secret' => $data['client_secret'],
			'grant_type'    => 'authorization_code',
			'code'          => wp_parse_args( wp_parse_url( $location, PHP_URL_QUERY ) )['code'],
			'redirect_uri'  => 'https://test',
		);
		$_POST = $_REQUEST;
		$_GET = array();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/oauth/token';

		$response = $oauth->handle_oauth( 'response' );
		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertNotEmpty( $response->getParameter( 'access_token' ) );

		// Now delete the app.
		$app = Mastodon_App::get_by_client_id( $data['client_id'] );
		$app->delete();

		delete_option( 'mastodon_api_auto_app_reregister' );

		// Ensure that we no longer are able to authorize.
		$_REQUEST = array(
			'client_id'     => $data['client_id'],
			'redirect_uri'  => 'https://test',
			'response_type' => 'code',
			'scope'         => 'read write follow push',
		);
		$_GET = $_REQUEST;
		$_POST = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = add_query_arg( $_REQUEST, '/oauth/authorize' );
		$response = $oauth->handle_oauth( 'response' );
		$this->assertEquals( 400, $response->getStatusCode() );

		// Now enable the admin setting to allow adding the app upon authorize.
		$this->assertFalse( get_option( 'mastodon_api_auto_app_reregister' ) );
		update_option( 'mastodon_api_auto_app_reregister', 1 );
		$this->assertEquals( 1, get_option( 'mastodon_api_auto_app_reregister' ) );

		// Ensure that we now able to authorize again.
		$oauth = new Mastodon_OAuth();
		$response = $oauth->handle_oauth( 'response' );
		$this->assertEquals( 302, $response->getStatusCode() );

		// The option has been updated so that the next token request will set the secret.
		$this->assertEquals( $data['client_id'], get_option( 'mastodon_api_auto_app_reregister' ) );

		// The app now exists (again).
		$app = Mastodon_App::get_by_client_id( $data['client_id'] );
		$this->assertNotEmpty( $app );
		$this->assertEquals( array( 'https://test' ), $app->get_redirect_uris() );
		$this->assertEmpty( $app->get_client_secret() );

		// The app name cannot be the same.
		$this->assertNotEquals( $client_name, $app->get_client_name() );

		// And pretend the user clicked authorize.
		$handler = $oauth->handle_oauth( 'handler' );
		$request  = \OAuth2\Request::createFromGlobals();
		$response = $handler->test_authorize( $request, $this->administrator );
		$this->assertEquals( 302, $response->getStatusCode() );

		// Now use the code to get an access token.
		$location = $response->getHttpHeader( 'Location' );
		$_REQUEST = array(
			'client_id'     => $data['client_id'],
			'client_secret' => $data['client_secret'],
			'grant_type'    => 'authorization_code',
			'code'          => wp_parse_args( wp_parse_url( $location, PHP_URL_QUERY ) )['code'],
			'redirect_uri'  => 'https://test',
		);
		$_POST = $_REQUEST;
		$_GET = array();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/oauth/token';

		$response = $oauth->handle_oauth( 'response' );
		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertNotEmpty( $response->getParameter( 'access_token' ) );

		// Ensur that the app now has the secret again.
		$this->assertEquals( $data['client_secret'], $app->get_client_secret() );
		$this->assertFalse( get_option( 'mastodon_api_auto_app_reregister' ) );

		$_GET = $old_get;
		$_POST = $old_post;
		$_REQUEST = $old_request;
		$_SERVER['REQUEST_METHOD'] = $old_request_method;
		$_SERVER['REQUEST_URI'] = $old_request_uri;
	}
}
