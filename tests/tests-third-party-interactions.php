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
					'/tags',
					array(
						'methods'             => 'GET',
						'callback'            => function () {
							return 'FluentCRM';
						},
						'permission_callback' => '__return_true',
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
		$request  = $this->api_request( 'GET', '/fluentcrm/v2/tags' );
		$response = $this->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'FluentCRM', $response->get_data() );
	}
}
