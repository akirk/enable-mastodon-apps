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

	public function test_account_uses_saved_mastodon_header_image() {
		update_user_meta( $this->administrator, 'mastodon_api_header_id', $this->friend_attachment_id );

		$account = apply_filters( 'mastodon_api_account', null, $this->administrator, null, null );
		$url     = wp_get_attachment_url( $this->friend_attachment_id );

		$this->assertSame( $url, $account->header );
		$this->assertSame( $url, $account->header_static );
	}

	public function test_update_credentials_updates_simple_local_avatar() {
		global $simple_local_avatars;

		$previous_simple_local_avatars = $simple_local_avatars;
		$simple_local_avatars          = new class() {
			public function assign_new_user_avatar( $attachment_id, $user_id ) {
				update_user_meta(
					$user_id,
					'simple_local_avatar',
					array(
						'media_id' => $attachment_id,
						'full'     => wp_get_attachment_url( $attachment_id ),
						'blog_id'  => get_current_blog_id(),
					)
				);
			}
		};
		$update_credentials = function ( $data ) {
			$data['avatar'] = $this->friend_attachment_id;
			return $data;
		};
		add_filter( 'mastodon_api_update_credentials', $update_credentials );

		$request  = $this->api_request( 'PATCH', '/api/v1/accounts/update_credentials' );
		$response = $this->dispatch_authenticated( $request );

		remove_filter( 'mastodon_api_update_credentials', $update_credentials );
		$simple_local_avatars = $previous_simple_local_avatars;

		$this->assertEquals( 200, $response->get_status() );
		$avatar = get_user_meta( $this->administrator, 'simple_local_avatar', true );
		$this->assertSame( $this->friend_attachment_id, (int) $avatar['media_id'] );
	}

	public function test_update_credentials_uses_mastodon_avatar_fallback_without_avatar_provider() {
		global $simple_local_avatars;

		$previous_simple_local_avatars = $simple_local_avatars;
		$simple_local_avatars          = null;
		$update_credentials            = function ( $data ) {
			$data['avatar'] = $this->friend_attachment_id;
			return $data;
		};
		add_filter( 'mastodon_api_update_credentials', $update_credentials );

		$request  = $this->api_request( 'PATCH', '/api/v1/accounts/update_credentials' );
		$response = $this->dispatch_authenticated( $request );

		remove_filter( 'mastodon_api_update_credentials', $update_credentials );
		$simple_local_avatars = $previous_simple_local_avatars;

		$this->assertEquals( 200, $response->get_status() );
		$avatar = get_user_meta( $this->administrator, 'mastodon_api_avatar', true );
		$this->assertSame( $this->friend_attachment_id, (int) $avatar['media_id'] );

		$account = apply_filters( 'mastodon_api_account', null, $this->administrator, null, null );
		$url     = wp_get_attachment_url( $this->friend_attachment_id );

		$this->assertSame( $url, $account->avatar );
		$this->assertSame( $url, $account->avatar_static );
	}

	public function test_update_credentials_persists_header_attachment_id() {
		$update_credentials = function ( $data ) {
			$data['header'] = $this->friend_attachment_id;
			return $data;
		};
		add_filter( 'mastodon_api_update_credentials', $update_credentials );

		$request  = $this->api_request( 'PATCH', '/api/v1/accounts/update_credentials' );
		$response = $this->dispatch_authenticated( $request );

		remove_filter( 'mastodon_api_update_credentials', $update_credentials );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $this->friend_attachment_id, (int) get_user_meta( $this->administrator, 'mastodon_api_header_id', true ) );
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

	public function test_local_blog_users_are_following_without_following_integration() {
		$disable_following_integration = '__return_false';
		add_filter( 'mastodon_api_has_following_integration', $disable_following_integration );

		$request  = $this->api_request( 'GET', '/api/v1/accounts/' . $this->administrator . '/following' );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		remove_filter( 'mastodon_api_has_following_integration', $disable_following_integration );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertContains( strval( $this->friend ), wp_list_pluck( $data, 'id' ) );
		$this->assertNotContains( strval( $this->administrator ), wp_list_pluck( $data, 'id' ) );
	}

	public function test_local_blog_users_are_followers_without_following_integration() {
		$disable_following_integration = '__return_false';
		add_filter( 'mastodon_api_has_following_integration', $disable_following_integration );

		$request  = $this->api_request( 'GET', '/api/v1/accounts/' . $this->friend . '/followers' );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		remove_filter( 'mastodon_api_has_following_integration', $disable_following_integration );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertContains( strval( $this->administrator ), wp_list_pluck( $data, 'id' ) );
		$this->assertNotContains( strval( $this->friend ), wp_list_pluck( $data, 'id' ) );
	}

	public function test_local_blog_user_relationship_is_following_without_following_integration() {
		$disable_following_integration = '__return_false';
		add_filter( 'mastodon_api_has_following_integration', $disable_following_integration );

		$request = $this->api_request( 'GET', '/api/v1/accounts/relationships' );
		$request->set_query_params(
			array(
				'id' => array( strval( $this->friend ) ),
			)
		);

		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		remove_filter( 'mastodon_api_has_following_integration', $disable_following_integration );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data[0]['following'] );
		$this->assertTrue( $data[0]['followed_by'] );
	}

	public function test_local_blog_account_counts_include_automatic_follows_without_following_integration() {
		$disable_following_integration = '__return_false';
		add_filter( 'mastodon_api_has_following_integration', $disable_following_integration );

		$request  = $this->api_request( 'GET', '/api/v1/accounts/verify_credentials' );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		remove_filter( 'mastodon_api_has_following_integration', $disable_following_integration );

		$local_follow_count = max(
			count(
				get_users(
					array(
						'blog_id' => get_current_blog_id(),
						'fields'  => 'ID',
					)
				)
			) - 1,
			0
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $local_follow_count, $data['followers_count'] );
		$this->assertSame( $local_follow_count, $data['following_count'] );
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
