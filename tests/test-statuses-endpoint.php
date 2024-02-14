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
class StatusesEndpoint_Test extends Mastodon_API_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/statuses', $routes );
	}

	public function test_statuses_id() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/statuses/' . $this->friend_post );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$userdata = get_userdata( $this->administrator );

		$this->assertIsString( $data['id'] );
		$this->assertEquals( $data['id'], strval( $this->friend_post ) );

		$this->assertIsArray( $data['account'] );
		$this->assertIsString( $data['uri'] );
		$this->assertIsString( $data['content'] );
		if ( ! empty( $data['in_reply_to_id'] ) ) {
			$this->assertIsInt( $data['in_reply_to_id'] );
		}
		if ( ! empty( $data['in_reply_to_account_id'] ) ) {
			$this->assertIsInt( $data['in_reply_to_account_id'] );
		}
		$this->assertIsString( $data['created_at'] );
		$this->assertTrue( false !== \DateTime::createFromFormat( 'Y-m-d\TH:i:s.uP', $data['created_at'] ) );
		$this->assertIsInt( $data['replies_count'] );
		$this->assertIsInt( $data['reblogs_count'] );
		$this->assertIsInt( $data['favourites_count'] );
	}

	public function test_statuses_private_id() {
		global $wp_rest_server;

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/statuses/' . $this->private_post );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_statuses_delete() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'DELETE', '/' . Mastodon_API::PREFIX . '/api/v1/statuses/' . $this->post );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$this->assertEquals( 'trash', get_post_status( $this->post ) );
	}

	public function test_status_post_empty() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/statuses' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 422, $response->get_status() );
	}

	public function test_status_post_basic_status() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/statuses' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$request->set_param( 'status', 'test' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data['id'] );
		$this->assertIsNumeric( $data['id'] );
		$p = get_post( $data['id'] );
		$this->assertEquals( $p->post_content, 'test' );
	}

	public function test_status_post_multiline_status() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/statuses' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$request->set_param( 'status', 'headline' . PHP_EOL . 'post_content' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data['id'] );
		$this->assertIsNumeric( $data['id'] );
		$p = get_post( $data['id'] );
		$this->assertEquals( 'status', get_post_format( $p->ID ) );

		$this->assertEquals( $p->post_title, '' );
		$this->assertEquals( $p->post_content, 'headline' . PHP_EOL . 'post_content' );
	}

	public function test_status_post_multiline_standard() {
		$this->app->set_post_formats( 'standard' );
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/statuses' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$request->set_param( 'status', 'headline' . PHP_EOL . 'post_content' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data['id'] );
		$this->assertIsNumeric( $data['id'] );
		$p = get_post( $data['id'] );
		$this->assertFalse( get_post_format( $p->ID ) );

		$this->assertEquals( $p->post_title, 'headline' );
		$this->assertEquals( $p->post_content, 'post_content' );
	}

	public function test_status_post_multiline_standard_html() {
		$this->app->set_post_formats( 'standard' );
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v1/statuses' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$request->set_param( 'status', '<p>headline</p>' . PHP_EOL . '<p>post_content</p>' );

		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data['id'] );
		$this->assertIsNumeric( $data['id'] );
		$p = get_post( $data['id'] );
		$this->assertFalse( get_post_format( $p->ID ) );

		$this->assertEquals( $p->post_title, '<p>headline</p>' );
		$this->assertEquals( $p->post_content, '<p>post_content</p>' );
	}
}
