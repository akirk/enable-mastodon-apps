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
class InstanceEndpoint_Test extends MastoAPI_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . MastoAPI::PREFIX . '/api/v1/instance', $routes );
	}

	public function test_apps_instance() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . MastoAPI::PREFIX . '/api/v1/instance' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'uri', $data );
		$this->assertIsString( $data['uri'] );
		$this->assertEquals( $data['uri'], home_url() );

		$this->assertArrayHasKey( 'account_domain', $data );
		$this->assertIsString( $data['account_domain'] );
		$this->assertEquals( $data['account_domain'], \wp_parse_url( \home_url(), \PHP_URL_HOST ) );

		$this->assertArrayHasKey( 'title', $data );
		$this->assertIsString( $data['title'] );
		$this->assertEquals( $data['title'], get_bloginfo( 'name' ) );

		$this->assertArrayHasKey( 'description', $data );
		$this->assertIsString( $data['description'] );
		$this->assertEquals( $data['description'], get_bloginfo( 'description' ) );

		$this->assertArrayHasKey( 'email', $data );
		$this->assertIsString( $data['email'] );
		$this->assertEquals( $data['email'], 'not@public.example' );

		$this->assertArrayHasKey( 'version', $data );
		$this->assertIsString( $data['version'] );
		$this->assertEquals( $data['version'], MASTOAPI_VERSION );

		$this->assertFalse( $data['registrations'], false );

		$this->assertFalse( $data['approval_required'], false );
	}

}
