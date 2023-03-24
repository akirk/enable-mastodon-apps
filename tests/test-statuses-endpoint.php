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
		$this->assertIsString( $data['url'] );
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

}