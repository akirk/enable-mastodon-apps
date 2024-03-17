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
class TimelineEndpoint_Test extends Mastodon_API_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/timelines/(home)', $routes );
	}

	public function test_timelines_home() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertArrayHasKey( 0, $data );
		$this->assertTrue( property_exists( $data[0], 'id' ) );
		$this->assertIsString( $data[0]->id );
		$this->assertEquals( $data[0]->id, strval( $this->friend_post ) );

		$this->assertTrue( property_exists( $data[0], 'media_attachments' ) );
		$this->assertArrayHasKey( 0, $data[0]->media_attachments );
		$this->assertEquals( '513722435', $data[0]->media_attachments[0]->id );

		$this->assertArrayHasKey( 1, $data );
		$this->assertArrayHasKey( 'id', $data[1] );
		$this->assertIsString( $data[1]->id );
		$this->assertEquals( $data[1]->id, strval( $this->post ) );
	}

	public function test_timelines_segmentation() {
		$post = get_post( $this->post );
		$friend_post = get_post( $this->friend_post );

		$third_post_id = wp_insert_post(
			array(
				'post_author'  => $this->friend,
				'post_content' => '',
				'post_title'   => 'Third title',
				'post_status'  => 'publish',
				'post_type'    => 'friend_post_cache',
				'post_date'    => '2023-01-05 00:00:00',
			)
		);
		set_post_format( $third_post_id, 'status' );
		$third_post = get_post( $third_post_id );

		// We now have three posts from oldest to newest: post, friend_post, third_post.

		$this->assertLessThan( $friend_post->ID, $post->ID );
		$this->assertLessThan( $friend_post->post_date, $post->post_date );
		$this->assertLessThan( $third_post->post_date, $friend_post->post_date );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;

		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertCount( 3, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $friend_post->ID ) );
		$this->assertEquals( $data[2]['id'], strval( $post->ID ) );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$request->set_param( 'min_id', $post->ID );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertCount( 2, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$request->set_param( 'max_id', $friend_post->ID );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $post->ID ) );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$request->set_param( 'min_id', $post->ID );
		$request->set_param( 'max_id', $third_post->ID );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $friend_post->ID ) );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$request->set_param( 'limit', 1 );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$request->set_param( 'limit', 2 );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertCount( 2, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $friend_post->ID ) );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$request->set_param( 'limit', 3 );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertCount( 3, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $friend_post->ID ) );
		$this->assertEquals( $data[2]['id'], strval( $post->ID ) );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/timelines/home' );
		$request->set_param( 'limit', 5 );
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertCount( 3, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $friend_post->ID ) );
		$this->assertEquals( $data[2]['id'], strval( $post->ID ) );
	}
}
