<?php
/**
 * Class AccountsEndpoint_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Testcase for the accounts endpoints.
 *
 * @package
 */
class AccountsEndpoint_Test extends Mastodon_API_TestCase {
	private $external_account = 'alex@kirk.at';

	public function mastodon_api_webfinger( $body, $url ) {
		if ( $url !== $this->external_account ) {
			return $body;
		}

		return array(
			'subject' => 'acct:' . $this->external_account,
			'aliases' => array(
				'https://alex.kirk.at/author/alex/',
			),
			'links'   => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => 'https://alex.kirk.at/author/alex/',
				),
			),
		);
	}

	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/accounts/verify_credentials', $routes );
	}

	public function test_accounts_verify_credentials() {
		$request = $this->api_request( 'GET', '/api/v1/accounts/verify_credentials' );
		$response = $this->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		$response = $this->dispatch_authenticated( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$userdata = get_userdata( $this->administrator );

		$this->assertIsString( $data['id'] );
		$this->assertEquals( $data['id'], strval( $userdata->ID ) );

		$this->assertIsString( $data['username'] );
		$this->assertEquals( $data['username'], strval( $userdata->user_login ) );
		$this->assertArrayHasKey( 'header', $data );
		$this->assertIsString( $data['header'] );
		$this->assertArrayHasKey( 'header_static', $data );
		$this->assertIsString( $data['header_static'] );
	}

	public function test_account_filter_adds_missing_header_fields() {
		$account = apply_filters( 'mastodon_api_account', null, $this->administrator, null, null );
		unset( $account->header, $account->header_static );

		$account = Handler\Account::api_account_ensure_numeric_id( $account, $this->administrator );

		$this->assertSame( '', $account->header );
		$this->assertSame( '', $account->header_static );
	}

	public function test_account_statuses_uses_route_user_id_over_query_user_id() {
		$request = $this->api_request( 'GET', '/api/v1/accounts/' . $this->friend . '/statuses' );
		$request->set_query_params(
			array(
				'user_id' => $this->administrator,
				'limit'   => 30,
			)
		);

		$response = $this->dispatch_authenticated( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertEquals( strval( $this->friend_post ), $data[0]->id );
		$this->assertEquals( strval( $this->friend ), $data[0]->account->id );
	}

	public function test_account_statuses_filters_generated_statuses_by_account_id() {
		$filter = function ( $status, $post_id ) {
			if ( intval( $post_id ) === intval( $this->post ) ) {
				$status->account->id = strval( $this->friend );
			}
			return $status;
		};
		add_filter( 'mastodon_api_status', $filter, 20, 2 );

		$request  = $this->api_request( 'GET', '/api/v1/accounts/' . $this->administrator . '/statuses' );
		$response = $this->dispatch_authenticated( $request );
		$data     = $response->get_data();

		remove_filter( 'mastodon_api_status', $filter, 20 );

		$this->assertEquals( 200, $response->get_status() );
		foreach ( $data as $status ) {
			$this->assertEquals( strval( $this->administrator ), $status->account->id );
			$this->assertNotEquals( strval( $this->post ), $status->id );
		}
	}

	public function xtest_accounts_external() {
		wp_cache_flush();
		$request = $this->api_request( 'GET', '/api/v1/accounts/' . $this->external_account );
		$response = $this->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		$response = $this->dispatch_authenticated( $request );
		$data = $response->get_data();
		$this->assertEquals( 404, $response->get_status() );

		wp_cache_flush();
		add_filter( 'mastodon_api_webfinger', array( $this, 'mastodon_api_webfinger' ), 10, 2 );

		$response = $this->dispatch_authenticated( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$this->assertIsString( $data['id'] );

		$this->assertIsString( $data['username'] );
		$this->assertEquals( 'alex', $data['username'] );

		$this->assertIsString( $data['acct'] );
		$this->assertIsString( $data['url'] );
		$this->assertIsString( $data['display_name'] );
		$this->assertIsString( $data['note'] );
		$this->assertIsString( $data['avatar'] );
		$this->assertIsString( $data['header'] );
		$this->assertIsBool( $data['locked'] );
		$this->assertIsArray( $data['fields'] );
		$this->assertIsArray( $data['emojis'] );
		$this->assertIsBool( $data['bot'] );
		if ( ! empty( $data['discoverable'] ) ) {
			$this->assertIsBool( $data['discoverable'] );
		}
		$this->assertIsString( $data['created_at'] );
		$this->assertTrue( false !== new \DateTime( $data['created_at'] ) );
		$this->assertIsInt( $data['statuses_count'] );
		$this->assertIsInt( $data['followers_count'] );
		$this->assertIsInt( $data['following_count'] );
	}
}
