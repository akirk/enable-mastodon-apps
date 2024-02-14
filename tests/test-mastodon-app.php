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
class MastodonApp_Test extends \WP_UnitTestCase {
	public function test_create_app() {
		$app = Mastodon_App::save( 'test', array( Mastodon_OAuth::OOB_REDIRECT_URI ), 'read', '' );
		$this->assertInstanceOf( Mastodon_App::class, $app );
	}

	public function test_create_app_with_empty_scope() {
		$this->expectException( \Exception::class );
		$app = Mastodon_App::save( 'test', array( Mastodon_OAuth::OOB_REDIRECT_URI ), '', '' );
	}

	/**
	 * Scopes to test
	 *
	 * @param      string $app_scopes     The application scopes.
	 * @param      string $scope_to_test  The scope to test.
	 * @param      bool   $has_scope      Indicates if the test should assume the scope to be existent.
	 * @dataProvider scopes
	 */
	public function test_scope_given( $app_scopes, $scope_to_test, $has_scope ) {
		$app = Mastodon_App::save( 'test', array( Mastodon_OAuth::OOB_REDIRECT_URI ), $app_scopes, '' );
		$this->assertEquals( $has_scope, $app->has_scope( $scope_to_test ) );
	}

	public function scopes() {
		return array(
			array( 'read', 'read', true ),
			array( 'read', 'read:accounts', true ),
			array( 'read:accounts', 'read:accounts', true ),
			array( 'read:accounts', 'read', false ),
			array( 'write', 'read', false ),
			array( 'read', 'write', false ),
			array( 'read write', 'write', true ),
			array( 'read write push', 'write', true ),
			array( 'read', 'write:accounts', false ),
		);
	}
}
