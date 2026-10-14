<?php
/**
 * Class ThirdPartyInteraction_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Testcase to ensure that we are not negatively interacting with third party plugins.
 *
 * @package
 */
class ThirdPartyInteraction_Test extends Mastodon_API_TestCase {
	public function set_up() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'fluentcrm/v2',
					'tags',
					array(
						'methods'             => 'GET',
						'callback'            => function () {
							wp_get_current_user();
							return 'FluentCRM';
						},
						'permission_callback' => 'is_user_logged_in',
					)
				);
			}
		);
		parent::set_up();
	}
	/**
	 * Test that we are not negatively interacting with FluentCRM.
	 *
	 * See https://github.com/akirk/enable-mastodon-apps/issues/145
	 */
	public function test_fluentcrm() {
		global $current_user;
		$current_user = null; // phpcs:ignore
		$request = new \WP_REST_Request( 'GET', '/fluentcrm/v2/tags' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'a:b' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		global $wp_rest_server;
		ob_start();
		$response = $wp_rest_server->dispatch( $request );
		$out = ob_get_clean();
		$this->assertEquals( 401, $response->get_status() );
		$this->assertEmpty( $out );
	}
}
