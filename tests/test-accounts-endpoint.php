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
	private $external_account = 'alex@kirk.at';

	public function set_up() {
		parent::set_up();

	}

	public function friends_get_activitypub_metadata( $meta, $url ) {
		if ( $url !== $this->external_account) {
			return $meta;
		}

		return array(
			"id" => "https://alex.kirk.at/author/alex/",
			"type" => "Person",
			"name" => "Alex Kirk",
			"summary" => "",
			"preferredUsername" => "alex",
			"url" => "https://alex.kirk.at/author/alex/",
			"icon" => array(
			    "type" => "Image",
			    "url" => "https://secure.gravatar.com/avatar/cffe76c1914531ab0cd1a8c7aa9c9c09?s=120&d=mm&r=g"
			),
			"published" => "2022-04-27T02:42:42Z",
			"inbox" => "https://alex.kirk.at/wp-json/activitypub/1.0/users/2/inbox",
			"outbox" => "https://alex.kirk.at/wp-json/activitypub/1.0/users/2/outbox",
			"followers" => "https://alex.kirk.at/wp-json/activitypub/1.0/users/2/followers",
			"following" => "https://alex.kirk.at/wp-json/activitypub/1.0/users/2/following",
			"manuallyApprovesFollowers" => false,
			"publicKey" => array(
			    "id" => "https://alex.kirk.at/author/alex/#main-key",
			    "owner" => "https://alex.kirk.at/author/alex/",
			),
		);
	}
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

	public function test_accounts_external() {
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/accounts/' . $this->external_account );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/accounts/' . $this->external_account );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 200, $response->get_status() );

		$this->assertIsString( $data['id'] );
		$this->assertEquals( $data['id'], strval( $this->external_account ) );

		$this->assertIsString( $data['username'] );
		$this->assertEmpty( $data['username'] );

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
		$this->assertTrue( false !== new \DateTime( $data['created_at'] ) );
		$this->assertIsInt( $data['statuses_count'] );
		$this->assertIsInt( $data['followers_count'] );
		$this->assertIsInt( $data['following_count'] );

		wp_cache_flush();
		add_filter( 'friends_get_activitypub_metadata', array( $this, 'friends_get_activitypub_metadata' ), 10, 2 );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/accounts/' . $this->external_account );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$this->assertIsString( $data['id'] );
		$this->assertEquals( $data['id'], strval( $this->external_account ) );

		$this->assertIsString( $data['username'] );
		$this->assertEquals( 'alex', $data['username'] );

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
		$this->assertTrue( false !== new \DateTime( $data['created_at'] ) );
		$this->assertIsInt( $data['statuses_count'] );
		$this->assertIsInt( $data['followers_count'] );
		$this->assertIsInt( $data['following_count'] );

	}
}
