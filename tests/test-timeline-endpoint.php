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
class TimelineEndpoint_Test extends MastoAPI_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . MastoAPI::PREFIX . '/api/v1/timelines/(home)', $routes );
	}

	public function test_timelines_home() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . MastoAPI::PREFIX . '/api/v1/timelines/home' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $this->post ) );

		$this->assertArrayHasKey( 1, $data );
		$this->assertArrayHasKey( 'id', $data[1] );
		$this->assertIsString( $data[1]['id'] );
		$this->assertEquals( $data[1]['id'], strval( $this->friend_post ) );
	}

}
