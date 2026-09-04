<?php
/**
 * Class NotificationsEndpoint_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * A testcase for the notifications endpoint.
 *
 * @package
 */
class NotificationsEndpoint_Test extends Mastodon_API_TestCase {
	private $test_notifications = array();

	public function set_up() {
		parent::set_up();
		$user_id = $this->factory->user->create(
			array(
				'role' => 'read',
			)
		);
		add_filter(
			'mastodon_api_new_app_post_formats',
			function () {
				return array( 'standard' );
			}
		);
	}

	/**
	 * Helper to inject test notifications into the filter chain.
	 *
	 * @param array $notifications Test notification arrays to inject.
	 */
	private function inject_test_notifications( array $notifications ) {
		$this->test_notifications = $notifications;
		add_filter(
			'mastodon_api_notifications_get',
			function ( $existing ) {
				return array_merge( $existing, $this->test_notifications );
			},
			50
		);
	}

	/**
	 * Helper to create a notification array matching the handler format.
	 *
	 * @param string $date   ISO 8601 date string.
	 * @param string $type   Notification type.
	 * @param string $suffix Optional ID suffix (e.g. status ID).
	 *
	 * @return array
	 */
	private function make_notification( string $date, string $type = 'mention', string $suffix = '' ): array {
		return array(
			'id'         => preg_replace( '/[^0-9]/', '', $date ) . $suffix,
			'created_at' => $date,
			'type'       => $type,
		);
	}

	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v1/notifications', $routes );
	}

	/**
	 * Collect the status IDs of the notifications in a response.
	 *
	 * @param array $data The decoded response data.
	 * @return array The status IDs.
	 */
	private function notification_status_ids( array $data ): array {
		$ids = array();
		foreach ( $data as $notification ) {
			if ( ! empty( $notification['status']['id'] ) ) {
				$ids[] = $notification['status']['id'];
			}
		}
		return $ids;
	}

	public function test_direct_message_creates_a_notification() {
		$dm = wp_insert_post(
			array(
				'post_author'  => $this->friend,
				'post_content' => 'A direct message for you',
				'post_status'  => 'ema_unread',
				'post_type'    => Mastodon_API::get_dm_cpt( $this->administrator ),
			)
		);

		$request  = $this->api_request( 'GET', '/api/v1/notifications' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertContains( strval( $dm ), $this->notification_status_ids( $data ) );
	}

	public function test_comment_creates_a_notification() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post,
				'comment_content'  => 'A comment on your post',
				'user_id'          => $this->friend,
				'comment_approved' => 1,
			)
		);
		$comment_post_id = Comment_CPT::comment_id_to_post_id( $comment_id );
		$this->assertNotEmpty( $comment_post_id );

		$request  = $this->api_request( 'GET', '/api/v1/notifications' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertContains( strval( $comment_post_id ), $this->notification_status_ids( $data ) );
	}

	/**
	 * A notification source that needs its own post statuses or taxonomy terms must not
	 * filter out the posts of the other sources.
	 */
	public function test_third_party_query_does_not_hide_other_notifications() {
		$dm = wp_insert_post(
			array(
				'post_author'  => $this->friend,
				'post_content' => 'A direct message for you',
				'post_status'  => 'ema_unread',
				'post_type'    => Mastodon_API::get_dm_cpt( $this->administrator ),
			)
		);

		add_filter(
			'mastodon_api_get_notifications_query_args',
			function ( $args, $type ) {
				if ( 'mention' !== $type ) {
					return $args;
				}
				$args['post_type'] = 'post';
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'post_tag',
						'field'    => 'name',
						'terms'    => 'mentions-of-someone-else',
					),
				);
				return $args;
			},
			10,
			2
		);

		$request  = $this->api_request( 'GET', '/api/v1/notifications' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertContains( strval( $dm ), $this->notification_status_ids( $data ) );
	}

	public function test_notification_sources_are_queried_separately() {
		$queries = apply_filters( 'mastodon_api_get_notifications_queries', array(), 'mention', $this->api_request( 'GET', '/api/v1/notifications' ) );

		$post_types = array();
		foreach ( $queries as $query ) {
			$this->assertArrayHasKey( 'post_type', $query );
			$post_types[] = $query['post_type'];
		}

		$this->assertContains( Comment_CPT::CPT, $post_types );
		$this->assertContains( Mastodon_API::get_dm_cpt( get_current_user_id() ), $post_types );
	}

	public function test_notifications_sorted_newest_first() {
		$this->inject_test_notifications(
			array(
				$this->make_notification( '2023-01-01T00:00:00.000+00:00' ),
				$this->make_notification( '2023-01-03T00:00:00.000+00:00' ),
				$this->make_notification( '2023-01-02T00:00:00.000+00:00' ),
			)
		);

		$request  = $this->api_request( 'GET', '/api/v1/notifications' );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		$this->assertCount( 3, $data );
		$this->assertGreaterThan( $data[1]['id'], $data[0]['id'] );
		$this->assertGreaterThan( $data[2]['id'], $data[1]['id'] );
	}

	public function test_notifications_limit() {
		$this->inject_test_notifications(
			array(
				$this->make_notification( '2023-01-01T00:00:00.000+00:00' ),
				$this->make_notification( '2023-01-02T00:00:00.000+00:00' ),
				$this->make_notification( '2023-01-03T00:00:00.000+00:00' ),
			)
		);

		$request = $this->api_request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'limit', 2 );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		$this->assertCount( 2, $data );
		// Should return the 2 newest.
		$this->assertStringContainsString( '20230103', $data[0]['id'] );
		$this->assertStringContainsString( '20230102', $data[1]['id'] );
	}

	public function test_notifications_max_id() {
		$n1 = $this->make_notification( '2023-01-01T00:00:00.000+00:00' );
		$n2 = $this->make_notification( '2023-01-02T00:00:00.000+00:00' );
		$n3 = $this->make_notification( '2023-01-03T00:00:00.000+00:00' );

		$this->inject_test_notifications( array( $n1, $n2, $n3 ) );

		$request = $this->api_request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'max_id', $n3['id'] );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		// max_id excludes the given ID, so we should get n2 and n1.
		$this->assertCount( 2, $data );
		$this->assertEquals( $n2['id'], $data[0]['id'] );
		$this->assertEquals( $n1['id'], $data[1]['id'] );
	}

	public function test_notifications_min_id() {
		$n1 = $this->make_notification( '2023-01-01T00:00:00.000+00:00' );
		$n2 = $this->make_notification( '2023-01-02T00:00:00.000+00:00' );
		$n3 = $this->make_notification( '2023-01-03T00:00:00.000+00:00' );

		$this->inject_test_notifications( array( $n1, $n2, $n3 ) );

		$request = $this->api_request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'min_id', $n1['id'] );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		// min_id excludes the given ID, so we should get n3 and n2.
		$this->assertCount( 2, $data );
		$this->assertEquals( $n3['id'], $data[0]['id'] );
		$this->assertEquals( $n2['id'], $data[1]['id'] );
	}

	public function test_notifications_since_id() {
		$n1 = $this->make_notification( '2023-01-01T00:00:00.000+00:00' );
		$n2 = $this->make_notification( '2023-01-02T00:00:00.000+00:00' );
		$n3 = $this->make_notification( '2023-01-03T00:00:00.000+00:00' );

		$this->inject_test_notifications( array( $n1, $n2, $n3 ) );

		$request = $this->api_request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'since_id', $n1['id'] );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		// since_id returns items greater than the given ID.
		$this->assertCount( 2, $data );
		$this->assertEquals( $n3['id'], $data[0]['id'] );
		$this->assertEquals( $n2['id'], $data[1]['id'] );
	}

	public function test_notifications_link_headers() {
		$n1 = $this->make_notification( '2023-01-01T00:00:00.000+00:00' );
		$n2 = $this->make_notification( '2023-01-02T00:00:00.000+00:00' );
		$n3 = $this->make_notification( '2023-01-03T00:00:00.000+00:00' );

		$this->inject_test_notifications( array( $n1, $n2, $n3 ) );

		$request  = $this->api_request( 'GET', '/api/v1/notifications' );
		$response = $this->dispatch_authenticated( $request );

		$links = $response->get_links();

		$this->assertArrayHasKey( 'prev', $links );
		$this->assertStringContainsString( 'min_id=' . $n3['id'], $links['prev'][0]['href'] );

		$this->assertArrayHasKey( 'next', $links );
		$this->assertStringContainsString( 'max_id=' . $n1['id'], $links['next'][0]['href'] );
	}

	public function test_notifications_pagination_walk_forward() {
		$notifications = array(
			$this->make_notification( '2023-01-01T00:00:00.000+00:00' ),
			$this->make_notification( '2023-01-02T00:00:00.000+00:00' ),
			$this->make_notification( '2023-01-03T00:00:00.000+00:00' ),
		);

		$this->inject_test_notifications( $notifications );

		// Walk forward through pages of 1 using next links.
		$expected_ids = array(
			$notifications[2]['id'], // newest first
			$notifications[1]['id'],
			$notifications[0]['id'],
		);

		$response = false;
		$page     = 0;
		do {
			$request = $this->api_request( 'GET', '/api/v1/notifications' );
			$request->set_param( 'limit', 1 );
			if ( $response && isset( $response->get_links()['next'] ) ) {
				parse_str( wp_parse_url( $response->get_links()['next'][0]['href'], PHP_URL_QUERY ), $query );
				foreach ( $query as $key => $value ) {
					$request->set_param( $key, $value );
				}
			}
			$response = $this->dispatch_authenticated( $request );
			$data     = json_decode( wp_json_encode( $response->get_data() ), true );

			if ( $page < count( $expected_ids ) ) {
				$this->assertCount( 1, $data, 'Page ' . $page );
				$this->assertEquals( $expected_ids[ $page ], $data[0]['id'], 'Page ' . $page );
			} else {
				$this->assertCount( 0, $data, 'Page ' . $page . ' should be empty' );
			}
			++$page;
		} while ( isset( $response->get_links()['next'] ) );

		$this->assertEquals( count( $expected_ids ), $page - 1, 'Should have exactly ' . count( $expected_ids ) . ' pages with data' );
	}

	public function test_notifications_empty_response_no_links() {
		// Don't inject any notifications.
		$request  = $this->api_request( 'GET', '/api/v1/notifications' );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		$this->assertEmpty( $data );
		$this->assertEmpty( $response->get_links() );
	}

	public function test_notifications_max_id_and_limit_combined() {
		$n1 = $this->make_notification( '2023-01-01T00:00:00.000+00:00' );
		$n2 = $this->make_notification( '2023-01-02T00:00:00.000+00:00' );
		$n3 = $this->make_notification( '2023-01-03T00:00:00.000+00:00' );
		$n4 = $this->make_notification( '2023-01-04T00:00:00.000+00:00' );

		$this->inject_test_notifications( array( $n1, $n2, $n3, $n4 ) );

		$request = $this->api_request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'max_id', $n4['id'] );
		$request->set_param( 'limit', 2 );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		$this->assertCount( 2, $data );
		$this->assertEquals( $n3['id'], $data[0]['id'] );
		$this->assertEquals( $n2['id'], $data[1]['id'] );
	}

	public function test_notifications_min_id_and_max_id_combined() {
		$n1 = $this->make_notification( '2023-01-01T00:00:00.000+00:00' );
		$n2 = $this->make_notification( '2023-01-02T00:00:00.000+00:00' );
		$n3 = $this->make_notification( '2023-01-03T00:00:00.000+00:00' );
		$n4 = $this->make_notification( '2023-01-04T00:00:00.000+00:00' );

		$this->inject_test_notifications( array( $n1, $n2, $n3, $n4 ) );

		$request = $this->api_request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'min_id', $n1['id'] );
		$request->set_param( 'max_id', $n4['id'] );
		$response = $this->dispatch_authenticated( $request );
		$data     = json_decode( wp_json_encode( $response->get_data() ), true );

		// Should return only n3 and n2 (between min and max, exclusive).
		$this->assertCount( 2, $data );
		$this->assertEquals( $n3['id'], $data[0]['id'] );
		$this->assertEquals( $n2['id'], $data[1]['id'] );
	}
}
