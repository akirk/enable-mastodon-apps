<?php
/**
 * Class PublicSearch_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Testcases for public WordPress searches.
 *
 * @package
 */
class PublicSearch_Test extends \WP_UnitTestCase {
	public function test_ema_unread_is_not_included_in_public_search_queries() {
		$status = get_post_status_object( 'ema_unread' );

		$this->assertNotNull( $status );
		$this->assertFalse( $status->public );

		$query = new \WP_Query(
			array(
				's'         => 'Associate-Cloud-Engineer',
				'post_type' => array( 'post', 'page', 'attachment', Mastodon_API::POST_CPT ),
				'fields'    => 'ids',
			)
		);

		$this->assertStringNotContainsString( 'ema_unread', $query->request );
	}
}
