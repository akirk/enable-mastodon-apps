<?php
/**
 * Class NotificationsEndpoint_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * A testcase for the notifications endpoint.
 *
 * @package
 */
class NotificationsEndpoint_Test extends Mastodon_API_TestCase {
	public function set_up() {
		parent::set_up();
		$user_id = $this->factory->user->create(
			array(
				'role' => 'read',
			)
		);
		add_filter(
			'mastodon_api_new_app_post_formats',
			function () {
				return array( 'standard' );
			}
		);
	}
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/notifications', $routes );
	}
}
