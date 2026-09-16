<?php
/**
 * Class MastodonApp_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Testcases for the Mastodon App.
 *
 * @package
 */
class MastodonApp_Test extends \WP_UnitTestCase {
	public function test_create_app() {
		$app = Mastodon_App::save( 'test', array( Mastodon_OAuth::OOB_REDIRECT_URI ), 'read', '' );
		$this->assertInstanceOf( Mastodon_App::class, $app );
	}

	public function test_same_app_gets_existing_app_settings() {
		$app = Mastodon_App::save( 'test', array( 'https://test/callback', 'https://test/alternate' ), 'read', 'https://mastodon.local' );
		$app->set_post_formats( array( 'aside' ) );
		$app->set_create_post_type( 'page' );
		$app->set_create_post_format( 'aside' );
		$app->set_view_post_types( array( 'post', 'page' ) );
		$app->set_disable_blocks( true );
		$app->set_first_line_as_excerpt( true );
		$app->set_media_only( true );

		$same_app = Mastodon_App::save( 'test', array( 'https://test/alternate', 'https://test/callback' ), 'read write', 'https://mastodon.local' );

		$this->assertNotEquals( $app->get_client_id(), $same_app->get_client_id() );
		$this->assertNotEquals( $app->get_client_secret(), $same_app->get_client_secret() );
		$this->assertEquals( 'read write', $same_app->get_scopes() );
		$this->assertEquals( array( 'aside' ), $same_app->get_post_formats() );
		$this->assertEquals( 'page', $same_app->get_create_post_type() );
		$this->assertEquals( 'aside', $same_app->get_create_post_format( true ) );
		$this->assertContains( 'page', $same_app->get_view_post_types() );
		$this->assertTrue( $same_app->get_disable_blocks() );
		$this->assertTrue( $same_app->get_first_line_as_excerpt() );
		$this->assertTrue( $same_app->get_media_only() );
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
