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
class AccountsEndpoint_Test extends Mastodon_TestCase {
	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/accounts/verify_credentials', $routes );
	}

	public function test_accounts_verify_credentials() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/accounts/verify_credentials' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/accounts/verify_credentials' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$userdata = get_userdata( $this->administrator );

		$this->assertIsString( $data['id'] );
		$this->assertEquals( $data['id'], strval( $userdata->ID ) );

		$this->assertIsString( $data['username'] );
		$this->assertEquals( $data['username'], strval( $userdata->user_login ) );

	}

	public function test_accounts_id() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/accounts/' . $this->administrator );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/accounts/' . $this->administrator );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$userdata = get_userdata( $this->administrator );

		$this->assertIsString( $data['id'] );
		$this->assertEquals( $data['id'], strval( $userdata->ID ) );

		$this->assertIsString( $data['username'] );
		$this->assertEquals( $data['username'], strval( $userdata->user_login ) );

		$this->assertIsString( $data['acct'] );
		$this->assertIsString( $data['url'] );
		$this->assertIsString( $data['display_name'] );
		$this->assertIsString( $data['note'] );
		$this->assertIsString( $data['avatar'] );
		$this->assertIsString( $data['avatar_static'] );
		$this->assertIsString( $data['header'] );
		$this->assertIsString( $data['header_static'] );
		$this->assertIsBool( $data['locked'] );
		$this->assertIsArray( $data['fields'] );
		$this->assertIsArray( $data['emojis'] );
		$this->assertIsBool( $data['bot'] );
		if ( ! empty( $data['discoverable'] ) ) {
			$this->assertIsBool( $data['discoverable'] );
		}
		$this->assertIsBool( $data['group'] );
		$this->assertIsString( $data['created_at'] );
		$this->assertIsInt( $data['statuses_count'] );
		$this->assertIsInt( $data['followers_count'] );
		$this->assertIsInt( $data['following_count'] );


	}

}
