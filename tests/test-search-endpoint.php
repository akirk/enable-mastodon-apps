<?php
/**
 * Class Test_Search_Endpoint
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * A testcase for the search endpoint.
 *
 * @package
 */
class Test_Search_Endpoint extends Mastodon_API_TestCase {

	/**
	 * Test that search results have numeric IDs for statuses.
	 */
	public function test_search_status_numeric_id() {
		// Create a status with a non-numeric ID for testing
		$post_id = wp_insert_post(
			array(
				'post_author'  => $this->administrator,
				'post_content' => 'Test search status',
				'post_title'   => '',
				'post_status'  => 'publish',
				'post_type'    => Mastodon_API::POST_CPT,
				'post_date'    => '2023-01-03 00:00:00',
			)
		);
		set_post_format( $post_id, 'status' );

		// Create a mock status entity with non-numeric IDs
		$status = new Entity\Status();
		$status->id = 'https://example.com/status/123'; // Non-numeric ID
		$status->account = new Entity\Account();
		$status->account->id = 'https://example.com/users/456'; // Non-numeric account ID
		$status->media_attachments = array();

		// Mock the search handler
		$search_handler = new Handler\Search();

		// Test the ensure numeric ID method
		$processed_status = $search_handler->api_status_ensure_numeric_id( $status );

		$this->assertIsNumeric( $processed_status->id, 'Status ID should be numeric after processing' );
		$this->assertIsNumeric( $processed_status->account->id, 'Account ID should be numeric after processing' );
	}

	/**
	 * Test that search results process all statuses for numeric IDs.
	 */
	public function test_search_results_numeric_ids() {
		// Create mock search results with non-numeric IDs
		$status1 = new Entity\Status();
		$status1->id = 'https://example.com/status/111';
		$status1->account = new Entity\Account();
		$status1->account->id = 'https://example.com/users/222';
		$status1->media_attachments = array();

		$status2 = new Entity\Status();
		$status2->id = 'https://example.com/status/333';
		$status2->account = new Entity\Account();
		$status2->account->id = 'https://example.com/users/444';
		$status2->media_attachments = array();

		$search_results = array(
			'accounts' => array(),
			'statuses' => array( $status1, $status2 ),
			'hashtags' => array(),
		);

		$search_handler = new Handler\Search();
		$processed_results = $search_handler->api_search_status_ensure_numeric_id( $search_results );

		$this->assertCount( 2, $processed_results['statuses'], 'Should have 2 statuses in results' );

		foreach ( $processed_results['statuses'] as $status ) {
			$this->assertIsNumeric( $status->id, 'Each status ID should be numeric after processing' );
			$this->assertIsNumeric( $status->account->id, 'Each account ID should be numeric after processing' );
		}
	}

	/**
	 * Test that search results with no statuses are handled correctly.
	 */
	public function test_search_results_no_statuses() {
		$search_results = array(
			'accounts' => array(),
			'statuses' => array(),
			'hashtags' => array(),
		);

		$search_handler = new Handler\Search();
		$processed_results = $search_handler->api_search_status_ensure_numeric_id( $search_results );

		$this->assertEmpty( $processed_results['statuses'], 'Empty statuses array should remain empty' );
		$this->assertSame( $search_results, $processed_results, 'Results without statuses should be unchanged' );
	}

	/**
	 * Test that non-Status_Entity objects are not processed.
	 */
	public function test_non_status_entity_ignored() {
		$search_handler = new Handler\Search();

		$non_status = new \stdClass();
		$non_status->id = 'should-not-be-changed';

		$processed = $search_handler->api_status_ensure_numeric_id( $non_status );

		$this->assertSame( $non_status, $processed, 'Non-Status_Entity objects should not be modified' );
		$this->assertEquals( 'should-not-be-changed', $processed->id, 'Non-Status_Entity ID should remain unchanged' );
	}

	/**
	 * Test that reblog account IDs are also converted to numeric.
	 */
	public function test_reblog_account_numeric_id() {
		$status = new Entity\Status();
		$status->id = 'https://example.com/status/123';
		$status->account = new Entity\Account();
		$status->account->id = 'https://example.com/users/456';

		// Add reblog with non-numeric account ID
		$status->reblog = new Entity\Status();
		$status->reblog->account = new Entity\Account();
		$status->reblog->account->id = 'https://example.com/users/789';
		$status->media_attachments = array();

		$search_handler = new Handler\Search();
		$processed_status = $search_handler->api_status_ensure_numeric_id( $status );

		$this->assertIsNumeric( $processed_status->reblog->account->id, 'Reblog account ID should be numeric after processing' );
	}

	/**
	 * Test that media attachment IDs are converted to numeric.
	 */
	public function test_media_attachment_numeric_id() {
		$status = new Entity\Status();
		$status->id = 'https://example.com/status/123';
		$status->account = new Entity\Account();
		$status->account->id = 'https://example.com/users/456';

		// Add media attachment with non-numeric ID
		$media = new \stdClass();
		$media->id = 'https://example.com/media/999';
		$status->media_attachments = array( $media );

		$search_handler = new Handler\Search();
		$processed_status = $search_handler->api_status_ensure_numeric_id( $status );

		$this->assertIsNumeric( $processed_status->media_attachments[0]->id, 'Media attachment ID should be numeric after processing' );
	}

	/**
	 * Test that already numeric IDs are preserved.
	 */
	public function test_numeric_ids_preserved() {
		$status = new Entity\Status();
		$status->id = '12345'; // Already numeric string
		$status->account = new Entity\Account();
		$status->account->id = 67890; // Already numeric
		$status->media_attachments = array();

		$search_handler = new Handler\Search();
		$processed_status = $search_handler->api_status_ensure_numeric_id( $status );

		$this->assertEquals( '12345', $processed_status->id, 'Numeric string ID should be preserved' );
		$this->assertEquals( 67890, $processed_status->account->id, 'Numeric ID should be preserved' );
	}

	/**
	 * Test that in_reply_to_id parameter correctly handles remapped numeric IDs.
	 */
	public function test_in_reply_to_id_remap_handling() {
		// Create a test URL
		$original_url = 'https://example.com/status/test123';

		// Get the numeric ID that would be generated for this URL
		$numeric_id = Mastodon_API::remap_url( $original_url );

		$this->assertIsNumeric( $numeric_id, 'Remapped URL should produce numeric ID' );
		$this->assertGreaterThan( 2e10, $numeric_id, 'URL remapped ID should be greater than 2e10' );

		// Test that maybe_get_remapped_url can reverse the mapping
		$reversed_url = Mastodon_API::maybe_get_remapped_url( $numeric_id );
		$this->assertEquals( $original_url, $reversed_url, 'Numeric ID should be correctly reversed to original URL' );

		// Test that non-remapped IDs pass through unchanged
		$regular_id = '12345';
		$unchanged_id = Mastodon_API::maybe_get_remapped_url( $regular_id );
		$this->assertEquals( $regular_id, $unchanged_id, 'Regular numeric IDs should pass through unchanged' );
	}

	/**
	 * Test the in_reply_to_id filter hook integration.
	 */
	public function test_in_reply_to_id_filter_hook() {
		// Test URL that would be converted to numeric by search results
		$status_url = 'https://remote.example/status/456';
		$numeric_id = Mastodon_API::remap_url( $status_url );

		// Test that the filter correctly reverses the mapping
		$result = apply_filters( 'mastodon_api_in_reply_to_id', $numeric_id );
		$this->assertEquals( $status_url, $result, 'in_reply_to_id filter should reverse numeric ID back to URL' );

		// Test that regular IDs are not affected
		$regular_id = '123';
		$result = apply_filters( 'mastodon_api_in_reply_to_id', $regular_id );
		$this->assertEquals( $regular_id, $result, 'Regular IDs should pass through in_reply_to_id filter unchanged' );
	}

	/**
	 * Test integration: search result numeric ID can be used as in_reply_to_id.
	 */
	public function test_search_to_reply_integration() {
		// Simulate getting a status from search results with remapped numeric ID
		$original_status_url = 'https://remote.example/posts/789';
		$numeric_id = Mastodon_API::remap_url( $original_status_url );

		// Create a mock status as would appear in search results
		$search_status = new Entity\Status();
		$search_status->id = $original_status_url; // Original URL
		$search_status->account = new Entity\Account();
		$search_status->account->id = 'user123';
		$search_status->media_attachments = array();

		// Process it through the search handler (simulating search results)
		$search_handler = new Handler\Search();
		$processed_status = $search_handler->api_status_ensure_numeric_id( $search_status );

		// Verify the ID is now numeric
		$this->assertIsNumeric( $processed_status->id, 'Search result should have numeric ID' );
		$this->assertEquals( $numeric_id, $processed_status->id, 'Search result should have the expected numeric ID' );

		// Now test that this numeric ID can be used as in_reply_to_id
		$reply_to_id = apply_filters( 'mastodon_api_in_reply_to_id', $processed_status->id );
		$this->assertEquals( $original_status_url, $reply_to_id, 'Numeric ID from search should be correctly reversed for reply' );
	}
}
