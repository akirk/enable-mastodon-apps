<?php
/**
 * Class StatusesEndpoint_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Testcases for the statuses endpoints.
 *
 * @package
 */
class StatusesEndpoint_Test extends Mastodon_API_TestCase {
	public function set_up() {
		add_theme_support( 'post-formats', array( 'status', 'aside', 'gallery', 'image' ) );
		parent::set_up();
	}

	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/statuses', $routes );
	}

	public function test_statuses_id() {
		$request = $this->api_request( 'GET', '/api/v1/statuses/' . $this->friend_post );
		$response = $this->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$userdata = get_userdata( $this->administrator );

		$this->assertIsString( $data->id );
		$this->assertEquals( $data->id, strval( $this->friend_post ) );

		$this->assertInstanceOf( '\Enable_Mastodon_Apps\Entity\Account', $data->account );
		$this->assertIsString( $data->uri );
		$this->assertIsString( $data->content );
		if ( ! empty( $data->in_reply_to_id ) ) {
			$this->assertIsInt( $data->in_reply_to_id );
		}
		if ( ! empty( $data->in_reply_to_account_id ) ) {
			$this->assertIsInt( $data->in_reply_to_account_id );
		}
		$this->assertInstanceOf( '\DateTime', $data->created_at );
		$this->assertIsInt( $data->replies_count );
		$this->assertIsInt( $data->reblogs_count );
		$this->assertIsInt( $data->favourites_count );
	}

	public function test_statuses_private_id() {
		$request = $this->api_request( 'GET', '/api/v1/statuses/' . $this->private_post );
		$response = $this->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		$request = $this->api_request( 'GET', '/api/v1/statuses/' . $this->private_post );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_statuses_delete() {
		$request = $this->api_request( 'DELETE', '/api/v1/statuses/' . $this->post );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$this->assertEquals( 'trash', get_post_status( $this->post ) );
	}

	public function test_submit_status_empty() {
		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 422, $response->get_status() );
	}

	public function test_submit_status_basic_status() {
		add_filter(
			'mastodon_api_new_post_format',
			function ( $format ) {
				return 'status';
			}
		);

		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', 'test' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data->id );
		$this->assertIsNumeric( $data->id );
		$p = get_post( $data->id );
		$this->assertEquals( $p->post_content, 'test' );
	}

	public function test_submit_status_multiline_status() {
		add_filter(
			'mastodon_api_new_post_format',
			function ( $format ) {
				return 'status';
			}
		);

		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', 'headline' . PHP_EOL . 'post_content' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data->id );
		$this->assertIsNumeric( $data->id );
		$p = get_post( $data->id );

		$this->assertEquals( 'status', get_post_format( $p->ID ) );

		$this->assertEquals( $p->post_title, '' );
		$this->assertEquals( $p->post_content, 'headline' . PHP_EOL . 'post_content' );
	}

	public function test_submit_status_multiline_standard() {
		$this->app->set_post_formats( 'standard' );
		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', 'headline' . PHP_EOL . 'post_content' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data->id );
		$this->assertIsNumeric( $data->id );
		$p = get_post( $data->id );
		$this->assertFalse( get_post_format( $p->ID ) );

		$this->assertEquals( $p->post_title, 'headline' );
		$this->assertEquals( $p->post_content, 'post_content' );
	}

	public function test_submit_status_multiline_standard_html() {
		$this->app->set_post_formats( 'standard' );
		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', '<p>headline</p>' . PHP_EOL . '<p>post_content</p>' );

		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data->id );
		$this->assertIsNumeric( $data->id );
		$p = get_post( $data->id );
		$this->assertFalse( get_post_format( $p->ID ) );

		$this->assertEquals( $p->post_title, '<p>headline</p>' );
		$this->assertEquals( $p->post_content, '<p>post_content</p>' );
	}

	public function test_submit_status_reply() {
		$query = new \WP_Comment_Query();
		$count = $query->query(
			array(
				'count' => true,
			)
		);

		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', 'reply' );
		$request->set_param( 'in_reply_to_id', $this->post );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data->id );
		$this->assertIsNumeric( $data->id );

		$new_count = $query->query(
			array(
				'count' => true,
			)
		);

		$this->assertTrue( $new_count > $count );
	}
}
