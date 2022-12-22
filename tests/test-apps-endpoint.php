<?php
/**
 * Class Test_Apps_Endpoint
 *
 * @package Friends
 */

namespace Friends;

/**
 * A testcase for the apps endpoint.
 *
 * @package
 */
class AppsEndpoint_Test extends \WP_Test_REST_Controller_Testcase {
	public function set_up() {
		parent::set_up();
		$this->administrator = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$this->endpoint = new Mastodon_API( Friends::get_instance() );
	}

	public function test_apps() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/apps' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'ok', $response->get_data() );
	}

	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/apps', $routes );
	}

	public function test_context_param() {}

	public function test_get_items() {}

	public function test_get_item() {}

	public function test_create_item() {}

	public function test_update_item() {}

	public function test_delete_item() {}

	public function test_prepare_item() {}

	public function test_get_item_schema() {}

}
