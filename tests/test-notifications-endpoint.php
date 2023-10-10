<?php
/**
 * Class Test_Notifications_Endpoint
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
		$user = new \Friends\User( $user_id );
		add_filter(
			'mastodon_api_external_mentions_user',
			function () use ( $user ) {
				return $user;
			}
		);
		add_action(
			'default_option_mastodon_api_default_post_formats',
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

	public function test_notifications() {
		$date = '2023-01-01T12:00:00Z';
		$external_user = apply_filters( 'mastodon_api_external_mentions_user', null );
		$external_user->insert_post(
			array(
				'post_title'  => 'External user',
				'post_status' => 'publish',
				'post_date'   => $date,
				'meta_input'  => array(
					'activitypub' => array(
						'test' => 'test',
					),
				),
			)
		);

		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/notifications' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertStringStartsWith( preg_replace( '/[^0-9]/', '', $date ), $data[0]['id'] );
	}

	public function test_notifications_paging() {
		$external_user = apply_filters( 'mastodon_api_external_mentions_user', null );
		$post1_id = '202301011200000000000' . $external_user->insert_post(
			array(
				'post_title'  => 'Post 1',
				'post_status' => 'publish',
				'post_date'   => '2023-01-01T12:00:00Z',
				'meta_input'  => array(
					'activitypub' => array(
						'test' => 'test',
					),
				),
			)
		);

		$post2_id = '202301021200000000000' . $external_user->insert_post(
			array(
				'post_title'  => 'Post 2',
				'post_status' => 'publish',
				'post_date'   => '2023-01-02T12:00:00Z',
				'meta_input'  => array(
					'activitypub' => array(
						'test' => 'test',
					),
				),
			)
		);

		$post3_id = '202301031200000000000' . $external_user->insert_post(
			array(
				'post_title'  => 'Post 3',
				'post_status' => 'publish',
				'post_date'   => '2023-01-03T12:00:00Z',
				'meta_input'  => array(
					'activitypub' => array(
						'test' => 'test',
					),
				),
			)
		);

		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/notifications' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;

		$request->set_param( 'max_id', $post3_id );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( $post2_id, $data[0]['id'] );
		$this->assertCount( 2, $data );

		$request->set_param( 'max_id', $post2_id );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( $post1_id, $data[0]['id'] );
		$this->assertCount( 1, $data );

		$request->set_param( 'max_id', $post1_id );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEmpty( $data );

		$request->set_param( 'max_id', $post3_id );
		$request->set_param( 'limit', 1 );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( $post2_id, $data[0]['id'] );
		$this->assertCount( 1, $data );

		$request->set_param( 'max_id', $post3_id );
		$request->set_param( 'min_id', $post1_id );
		$request->set_param( 'limit', 15 );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( $post2_id, $data[0]['id'] );
		$this->assertCount( 1, $data );
	}
}
