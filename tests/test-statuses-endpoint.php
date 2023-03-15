<?php
/**
 * Class Test_Apps_Endpoint
 *
 * @package Mastodon_API
 */

namespace Mastodon_API;

/**
 * A testcase for the apps endpoint.
 *
 * @package
 */
class StatusesEndpoint_Test extends Mastodon_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/statuses', $routes );
	}
}
