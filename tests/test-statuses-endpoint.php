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

	public function submit_status_data_provider() {
		return array(
			'basic_status'              => array(
				'status'           => 'test',
				'new_format'       => 'status',
				'new_post_type'    => 'post',
				'disable_blocks'   => true,
				'expected_title'   => '',
				'expected_content' => 'test',
			),
			'basic_status_blocks'       => array(
				'status'           => 'test',
				'new_format'       => 'status',
				'new_post_type'    => 'post',
				'disable_blocks'   => false,
				'expected_title'   => '',
				'expected_content' => "<!-- wp:paragraph -->\n<p>test</p>\n<!-- /wp:paragraph -->",
			),
			'basic_standard'            => array(
				'status'           => 'test',
				'new_format'       => 'standard',
				'new_post_type'    => 'post',
				'disable_blocks'   => true,
				'expected_title'   => '',
				'expected_content' => 'test',
			),
			'basic_standard_blocks'     => array(
				'status'           => 'test',
				'new_format'       => 'standard',
				'new_post_type'    => 'post',
				'disable_blocks'   => false,
				'expected_title'   => '',
				'expected_content' => "<!-- wp:paragraph -->\n<p>test</p>\n<!-- /wp:paragraph -->",
			),
			'basic_cpt'                 => array(
				'status'           => 'test',
				'new_format'       => 'standard',
				'new_post_type'    => 'my_custom_post_type',
				'disable_blocks'   => true,
				'expected_title'   => '',
				'expected_content' => 'test',
			),
			'multiline_status'          => array(
				'status'           => 'headline' . PHP_EOL . 'post_content',
				'new_format'       => 'status',
				'new_post_type'    => 'post',
				'disable_blocks'   => true,
				'expected_title'   => '',
				'expected_content' => 'headline' . PHP_EOL . 'post_content',
			),
			'multiline_status_blocks'   => array(
				'status'           => 'headline' . PHP_EOL . 'post_content',
				'new_format'       => 'status',
				'new_post_type'    => 'post',
				'disable_blocks'   => false,
				'expected_title'   => '',
				'expected_content' => "<!-- wp:paragraph -->\n<p>headline</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>post_content</p>\n<!-- /wp:paragraph -->",
			),
			'multiline_standard'        => array(
				'status'           => 'headline' . PHP_EOL . 'post_content',
				'new_format'       => 'standard',
				'new_post_type'    => 'post',
				'disable_blocks'   => true,
				'expected_title'   => 'headline',
				'expected_content' => 'post_content',
			),
			'multiline_standard_blocks' => array(
				'status'           => 'headline' . PHP_EOL . 'post_content',
				'new_format'       => 'standard',
				'new_post_type'    => 'post',
				'disable_blocks'   => false,
				'expected_title'   => 'headline',
				'expected_content' => "<!-- wp:paragraph -->\n<p>post_content</p>\n<!-- /wp:paragraph -->",
			),
		);
	}

	/**
	 * Test submitting statuses.
	 *
	 * @dataProvider submit_status_data_provider
	 * @param string $status The status to submit.
	 * @param string $new_format The new post format.
	 * @param string $new_post_type The new post type.
	 * @param bool   $disable_blocks Whether to disable blocks.
	 * @param string $expected_title The expected post title.
	 * @param string $expected_content The expected post content.
	 * @return void
	 */
	public function test_submit_status( $status, $new_format, $new_post_type, $disable_blocks, $expected_title, $expected_content ) {
		$this->app->set_post_formats( $new_format );
		$this->app->set_create_post_type( $new_post_type );
		$this->app->set_disable_blocks( $disable_blocks );

		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', $status );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data->id );
		$this->assertIsNumeric( $data->id );
		$p = get_post( $data->id );
		$this->assertEquals( $p->post_title, $expected_title );
		$this->assertEquals( $p->post_content, $expected_content );
		$this->assertEquals( $p->post_type, $new_post_type );
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
