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

	/**
	 * Test that we are not negatively interacting with FluentCRM.
	 *
	 * See https://github.com/akirk/enable-mastodon-apps/issues/145
	 */
	public function test_fluentcrm() {
		register_rest_route(
			'fluentcrm/v2',
			'tags',
			array(
				'methods'  => 'GET',
				'callback' => function () {
					return new \WP_REST_Response( 'FluentCRM', 200 );
				},
			)
		);

		$request  = $this->api_request( 'GET', '/fluentcrm/v2/tags' );
		$response = $this->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'FluentCRM', $response->get_data() );
	}
}
