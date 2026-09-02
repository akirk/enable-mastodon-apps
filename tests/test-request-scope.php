<?php
/**
 * Class Request_Scope_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Tests that the plugin only acts on requests that are aimed at it.
 */
class Request_Scope_Test extends \WP_UnitTestCase {
	private $original_request_uri;
	private $original_authorization;

	public function set_up() {
		parent::set_up();

		$this->original_request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$this->original_authorization  = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : null;

		$this->reset_request();
		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		$this->reset_request();
		$this->set_permalink_structure( '' );

		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		if ( null === $this->original_authorization ) {
			unset( $_SERVER['HTTP_AUTHORIZATION'] );
		} else {
			$_SERVER['HTTP_AUTHORIZATION'] = $this->original_authorization;
		}

		parent::tear_down();
	}

	private function reset_request() {
		unset( $_GET['rest_route'] );
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		if ( isset( $GLOBALS['wp'] ) ) {
			unset( $GLOBALS['wp']->query_vars['rest_route'] );
		}
		$_SERVER['REQUEST_URI'] = '/';
	}

	/**
	 * Test which request URIs are recognized as plugin requests.
	 *
	 * @dataProvider data_request_uris
	 *
	 * @param string $request_uri    The request URI.
	 * @param bool   $is_api_request Whether it should be treated as a plugin request.
	 */
	public function test_is_mastodon_api_request_by_uri( $request_uri, $is_api_request ) {
		$_SERVER['REQUEST_URI'] = $request_uri;

		$this->assertSame( $is_api_request, Mastodon_API::is_mastodon_api_request(), $request_uri );
	}

	public function data_request_uris() {
		return array(
			'plugin rest route'        => array( '/wp-json/enable-mastodon-apps/api/v1/instance', true ),
			'plugin short path'        => array( '/api/v1/timelines/home', true ),
			'plugin short path v2'     => array( '/api/v2/search?q=test', true ),
			'plugin nodeinfo path'     => array( '/api/nodeinfo/2.0', true ),
			'core rest route'          => array( '/wp-json/wp/v2/posts', false ),
			'other plugin rest route'  => array( '/wp-json/activitypub/1.0/actors/1', false ),
			'admin'                    => array( '/wp-admin/index.php', false ),
			'front page'               => array( '/', false ),
			'a post'                   => array( '/hello-world/', false ),
			'a page starting with api' => array( '/apidocs/', false ),
		);
	}

	public function test_is_mastodon_api_request_by_query_var() {
		$GLOBALS['wp']->query_vars['rest_route'] = '/' . Mastodon_API::PREFIX . '/api/v1/instance';
		$this->assertTrue( Mastodon_API::is_mastodon_api_request() );

		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';
		$this->assertFalse( Mastodon_API::is_mastodon_api_request() );
	}

	public function test_is_mastodon_api_request_with_plain_permalinks() {
		$this->set_permalink_structure( '' );

		$_SERVER['REQUEST_URI'] = '/index.php?rest_route=/' . Mastodon_API::PREFIX . '/api/v1/instance';
		$_GET['rest_route']     = '/' . Mastodon_API::PREFIX . '/api/v1/instance';
		$this->assertTrue( Mastodon_API::is_mastodon_api_request() );

		$_GET['rest_route'] = '/wp/v2/posts';
		$this->assertFalse( Mastodon_API::is_mastodon_api_request() );
	}

	public function test_is_mastodon_api_request_can_be_filtered() {
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$this->assertFalse( Mastodon_API::is_mastodon_api_request() );

		add_filter( 'mastodon_api_is_api_request', '__return_true' );
		$this->assertTrue( Mastodon_API::is_mastodon_api_request() );
		remove_filter( 'mastodon_api_is_api_request', '__return_true' );
	}

	public function test_get_current_rest_route() {
		$_SERVER['REQUEST_URI'] = '/wp-json/' . Mastodon_API::PREFIX . '/api/v1/instance';
		$this->assertSame( Mastodon_API::PREFIX . '/api/v1/instance', Mastodon_API::get_current_rest_route() );

		$_SERVER['REQUEST_URI'] = '/hello-world/';
		$this->assertSame( '', Mastodon_API::get_current_rest_route() );
	}

	public function test_access_token_only_authenticates_plugin_requests() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$token   = 'ema-test-' . wp_generate_password( 12, false, false );

		$storage = new OAuth2\Access_Token_Storage();
		$storage->setAccessToken( $token, 'test-client', $user_id, time() + HOUR_IN_SECONDS, 'read write' );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		$oauth                         = new Mastodon_OAuth();

		$_SERVER['REQUEST_URI'] = '/wp-json/' . Mastodon_API::PREFIX . '/api/v1/accounts/verify_credentials';
		$this->assertSame( $user_id, $oauth->authenticate( false ) );

		// The same token must not log the user in on a request that is not ours.
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$this->assertFalse( $oauth->authenticate( false ) );

		$_SERVER['REQUEST_URI'] = '/wp-admin/index.php';
		$this->assertFalse( $oauth->authenticate( false ) );

		$storage->unsetAccessToken( $token );
	}

	public function test_the_health_check_diagnostic_route_is_not_authenticated() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$token   = 'ema-test-' . wp_generate_password( 12, false, false );

		$storage = new OAuth2\Access_Token_Storage();
		$storage->setAccessToken( $token, 'test-client', $user_id, time() + HOUR_IN_SECONDS, 'read write' );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		$_SERVER['REQUEST_URI']        = '/wp-json/' . Mastodon_API::PREFIX . '/' . WP_Admin\Health_Check::AUTH_HEADER_ROUTE;

		$oauth = new Mastodon_OAuth();
		$this->assertFalse( $oauth->authenticate( false ) );

		$storage->unsetAccessToken( $token );
	}
}
