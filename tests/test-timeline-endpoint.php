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
		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $this->friend_post ) );

		$this->assertArrayHasKey( 'media_attachments', $data[0] );
		$this->assertEquals( $this->friend_attachment_id, $data[0]['media_attachments'][0]['id'] );

		$this->assertArrayHasKey( 1, $data );
		$this->assertArrayHasKey( 'id', $data[1] );
		$this->assertIsString( $data[1]['id'] );
		$this->assertEquals( $data[1]['id'], strval( $this->post ) );
	}

	public function test_timelines_segmentation() {
		$first_post = get_post( $this->post );
		$second_post = get_post( $this->friend_post );

		$third_post_id = wp_insert_post(
			array(
				'post_author'  => $this->friend,
				'post_content' => '',
				'post_title'   => 'Third title',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => '2023-01-05 00:00:00',
			)
		);
		set_post_format( $third_post_id, 'status' );
		$third_post = get_post( $third_post_id );

		// We now have three posts from oldest to newest: post, friend_post, third_post.

		$this->assertLessThan( $second_post->ID, $first_post->ID );
		$this->assertLessThan( $second_post->post_date, $first_post->post_date );
		$this->assertLessThan( $third_post->post_date, $second_post->post_date );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertArrayHasKey( 'prev', $response->get_links() );
		$this->assertStringContainsString( 'min_id=' . $third_post->ID, $response->get_links()['prev'][0]['href'] );
		$this->assertArrayHasKey( 'next', $response->get_links() );
		$this->assertStringContainsString( 'max_id=' . $first_post->ID, $response->get_links()['next'][0]['href'] );
		$this->assertCount( 3, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $second_post->ID ) );
		$this->assertEquals( $data[2]['id'], strval( $first_post->ID ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$request->set_param( 'min_id', $first_post->ID );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertArrayHasKey( 'next', $response->get_links() );
		$this->assertStringContainsString( 'max_id=' . $second_post->ID, $response->get_links()['next'][0]['href'] );
		$this->assertArrayHasKey( 'prev', $response->get_links() );
		$this->assertStringContainsString( 'min_id=' . $third_post->ID, $response->get_links()['prev'][0]['href'] );
		$this->assertCount( 2, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $second_post->ID ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$request->set_param( 'max_id', $second_post->ID );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertArrayHasKey( 'next', $response->get_links() );
		$this->assertStringContainsString( 'max_id=' . $first_post->ID, $response->get_links()['next'][0]['href'] );

		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $first_post->ID ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$request->set_param( 'min_id', $first_post->ID );
		$request->set_param( 'max_id', $third_post->ID );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertArrayHasKey( 'next', $response->get_links() );
		$this->assertStringContainsString( 'max_id=' . $second_post->ID, $response->get_links()['next'][0]['href'] );
		$this->assertArrayHasKey( 'prev', $response->get_links() );
		$this->assertStringContainsString( 'min_id=' . $second_post->ID, $response->get_links()['prev'][0]['href'] );
		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $second_post->ID ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$request->set_param( 'limit', 1 );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertArrayHasKey( 'next', $response->get_links() );
		$this->assertStringContainsString( 'max_id=' . $third_post->ID, $response->get_links()['next'][0]['href'] );
		$this->assertArrayHasKey( 'prev', $response->get_links() );
		$this->assertStringContainsString( 'min_id=' . $third_post->ID, $response->get_links()['prev'][0]['href'] );
		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$request->set_param( 'limit', 2 );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertCount( 2, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $second_post->ID ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$request->set_param( 'limit', 3 );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertArrayHasKey( 'next', $response->get_links() );
		$this->assertStringContainsString( 'max_id=' . $first_post->ID, $response->get_links()['next'][0]['href'] );
		$this->assertArrayHasKey( 'prev', $response->get_links() );
		$this->assertStringContainsString( 'min_id=' . $third_post->ID, $response->get_links()['prev'][0]['href'] );

		$this->assertCount( 3, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $second_post->ID ) );
		$this->assertEquals( $data[2]['id'], strval( $first_post->ID ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$request->set_param( 'limit', 5 );
		$response = $this->dispatch_authenticated( $request );
		$data = json_decode( json_encode( $response->get_data() ), true );

		$this->assertArrayHasKey( 'next', $response->get_links() );
		$this->assertStringContainsString( 'max_id=' . $first_post->ID, $response->get_links()['next'][0]['href'] );
		$this->assertArrayHasKey( 'prev', $response->get_links() );
		$this->assertStringContainsString( 'min_id=' . $third_post->ID, $response->get_links()['prev'][0]['href'] );

		$this->assertCount( 3, $data );
		$this->assertArrayHasKey( 0, $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertIsString( $data[0]['id'] );
		$this->assertEquals( $data[0]['id'], strval( $third_post->ID ) );
		$this->assertEquals( $data[1]['id'], strval( $second_post->ID ) );
		$this->assertEquals( $data[2]['id'], strval( $first_post->ID ) );

		$pages = array( $third_post->ID, $second_post->ID, $first_post->ID );
		$response = false;
		do {
			$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
			$request->set_param( 'limit', 1 );
			if ( $response && isset( $response->get_links()['next'] ) ) {
				parse_str( parse_url( $response->get_links()['next'][0]['href'], PHP_URL_QUERY ), $query );
				foreach ( $query as $key => $value ) {
					$request->set_param( $key, $value );
				}
			}
			$response = $this->dispatch_authenticated( $request );
			$data = json_decode( json_encode( $response->get_data() ), true );

			if ( empty( $pages ) ) {
				$this->assertCount( 0, $data );
			} else {
				$current_page = array_shift( $pages );

				$this->assertCount( 1, $data, 'Page: ' . $current_page );
				$this->assertArrayHasKey( 0, $data, 'Page: ' . $current_page );
				$this->assertArrayHasKey( 'id', $data[0], 'Page: ' . $current_page );
				$this->assertIsString( $data[0]['id'], 'Page: ' . $current_page );
				$this->assertEquals( $data[0]['id'], strval( $current_page ), 'Page: ' . $current_page );
			}
		} while ( isset( $response->get_links()['next'] ) );

		$pages = array( $third_post->ID, $second_post->ID, $first_post->ID );
		$response = false;
		do {
			$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
			$request->set_param( 'limit', 1 );
			if ( $response && isset( $response->get_links()['prev'] ) ) {
				parse_str( parse_url( $response->get_links()['prev'][0]['href'], PHP_URL_QUERY ), $query );
				foreach ( $query as $key => $value ) {
					$request->set_param( $key, $value );
				}
			} else {
				$request->set_param( 'max_id', $second_post->ID );
			}
			$response = $this->dispatch_authenticated( $request );
			$data = json_decode( json_encode( $response->get_data() ), true );

			if ( empty( $pages ) ) {
				$this->assertCount( 0, $data );
			} else {
				$current_page = array_pop( $pages );

				$this->assertCount( 1, $data, 'Page: ' . $current_page );
				$this->assertArrayHasKey( 0, $data, 'Page: ' . $current_page );
				$this->assertArrayHasKey( 'id', $data[0], 'Page: ' . $current_page );
				$this->assertIsString( $data[0]['id'], 'Page: ' . $current_page );
				$this->assertEquals( $data[0]['id'], strval( $current_page ), 'Page: ' . $current_page );
			}
		} while ( isset( $response->get_links()['prev'] ) );
	}
}
