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
class InstanceEndpoint_Test extends Mastodon_API_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/instance', $routes );
	}

	public function test_apps_instance() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/instance' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertTrue( property_exists( $data, 'uri' ) );
		$this->assertIsString( $data->uri );
		$this->assertEquals( $data->uri, \wp_parse_url( \home_url(), \PHP_URL_HOST ) );

		$this->assertTrue( property_exists( $data, 'account_domain' ) );
		$this->assertIsString( $data->account_domain );
		$this->assertEquals( $data->qccount_domain, \wp_parse_url( \home_url(), \PHP_URL_HOST ) );

		$this->assertTrue( property_exists( $data, 'title' ) );
		$this->assertIsString( $data->title );
		$this->assertEquals( $data->title, get_bloginfo( 'name' ) );

		$this->assertTrue( property_exists( $data, 'description' ) );
		$this->assertIsString( $data->description );
		$this->assertEquals( $data->description, get_bloginfo( 'description' ) );

		$this->assertTrue( property_exists( $data, 'email' ) );
		$this->assertIsString( $data->email );
		$this->assertEquals( $data->email, 'not@public.example' );

		$this->assertTrue( property_exists( $data, 'version' ) );
		$this->assertIsString( $data->version );
		$this->assertStringContainsString( ENABLE_MASTODON_APPS_VERSION, $data->version );

		$this->assertFalse( $data->registrations, false );

		$this->assertFalse( $data->approval_required, false );
	}
}
