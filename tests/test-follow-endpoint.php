<?php
/**
 * Class Follow_Endpoint_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Testcase for following remote accounts through the ActivityPub plugin.
 *
 * @package
 */
class Follow_Endpoint_Test extends Mastodon_API_TestCase {
	/**
	 * The remote actor post id.
	 *
	 * @var int
	 */
	private $actor_post_id;

	public function set_up() {
		if ( ! Integration\Activitypub::is_available() ) {
			return $this->markTestSkipped( 'The ActivityPub plugin is not loaded.' );
		}
		parent::set_up();

		$this->actor_post_id = \Activitypub\Collection\Remote_Actors::upsert(
			array(
				'id'                => 'https://mastodon.local/users/remote',
				'type'              => 'Person',
				'name'              => 'Remote Person',
				'preferredUsername' => 'remote',
				'summary'           => 'A remote person',
				'url'               => 'https://mastodon.local/@remote',
				'inbox'             => 'https://mastodon.local/users/remote/inbox',
				'outbox'            => 'https://mastodon.local/users/remote/outbox',
			)
		);
		$this->assertIsInt( $this->actor_post_id );
		update_post_meta( $this->actor_post_id, '_activitypub_acct', 'remote@mastodon.local' );
	}

	/**
	 * Get the number of Follow activities in the outbox.
	 *
	 * @return int The number of Follow activities.
	 */
	private function count_follow_activities() {
		return count(
			get_posts(
				array(
					'post_type'      => \Activitypub\Collection\Outbox::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => array(
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Follow',
						),
					),
				)
			)
		);
	}

	/**
	 * A follow of a remote actor reaches the ActivityPub plugin.
	 */
	public function test_follow_remote_actor() {
		$this->assertFalse( \Activitypub\Collection\Following::check_status( $this->administrator, $this->actor_post_id ) );

		$request  = $this->api_request( 'POST', '/api/v1/accounts/' . $this->actor_post_id . '/follow' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 1, $this->count_follow_activities() );
		$this->assertEquals(
			\Activitypub\Collection\Following::PENDING,
			\Activitypub\Collection\Following::check_status( $this->administrator, $this->actor_post_id )
		);

		$data = $response->get_data();
		$this->assertInstanceOf( Entity\Relationship::class, $data );
		$this->assertEquals( strval( $this->actor_post_id ), $data->id );
		$this->assertTrue( $data->requested );
	}

	/**
	 * An accepted follow is reported as following in the relationship.
	 */
	public function test_relationship_reports_accepted_follow() {
		\Activitypub\Collection\Following::follow( $this->actor_post_id, $this->administrator );
		\Activitypub\Collection\Following::accept( $this->actor_post_id, $this->administrator );

		$request = $this->api_request( 'GET', '/api/v1/accounts/relationships' );
		$request->set_param( 'id', array( strval( $this->actor_post_id ) ) );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertTrue( $data[0]->following );
		$this->assertFalse( $data[0]->requested );
	}

	/**
	 * Unfollowing removes the follow from the ActivityPub plugin.
	 */
	public function test_unfollow_remote_actor() {
		\Activitypub\Collection\Following::follow( $this->actor_post_id, $this->administrator );
		\Activitypub\Collection\Following::accept( $this->actor_post_id, $this->administrator );

		$request  = $this->api_request( 'POST', '/api/v1/accounts/' . $this->actor_post_id . '/unfollow' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( \Activitypub\Collection\Following::check_status( $this->administrator, $this->actor_post_id ) );
		$this->assertFalse( $response->get_data()->following );
	}

	/**
	 * Following a locally known handle resolves it without a remote lookup.
	 */
	public function test_follow_by_webfinger_handle() {
		$user_id = Mastodon_API::remap_user_id( 'remote@mastodon.local' );

		$request  = $this->api_request( 'POST', '/api/v1/accounts/' . $user_id . '/follow' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			\Activitypub\Collection\Following::PENDING,
			\Activitypub\Collection\Following::check_status( $this->administrator, $this->actor_post_id )
		);
	}

	/**
	 * A follow that cannot be performed must not be reported as a success.
	 */
	public function test_follow_unknown_account_errors() {
		$user_id = Mastodon_API::remap_user_id( 'nobody@mastodon.local' );

		$request  = $this->api_request( 'POST', '/api/v1/accounts/' . $user_id . '/follow' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertEquals( 0, $this->count_follow_activities() );
	}

	/**
	 * Another plugin that handles follows keeps the last word.
	 */
	public function test_follow_defers_to_another_integration() {
		$handled = false;
		$handler = function ( $user_id ) use ( &$handled ) {
			$handled = true;
			return $user_id;
		};
		add_filter( 'mastodon_api_account_follow', $handler );

		$user_id  = Mastodon_API::remap_user_id( 'nobody@mastodon.local' );
		$request  = $this->api_request( 'POST', '/api/v1/accounts/' . $user_id . '/follow' );
		$response = $this->dispatch_authenticated( $request );

		remove_filter( 'mastodon_api_account_follow', $handler );

		$this->assertTrue( $handled );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * The account of a local user reports the ActivityPub follow counts.
	 */
	public function test_account_reports_following_count() {
		\Activitypub\Collection\Following::follow( $this->actor_post_id, $this->administrator );
		\Activitypub\Collection\Following::accept( $this->actor_post_id, $this->administrator );

		$request  = $this->api_request( 'GET', '/api/v1/accounts/verify_credentials' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 1, $response->get_data()->following_count );
	}

	/**
	 * The following list contains the remote accounts followed via ActivityPub.
	 */
	public function test_account_following_list() {
		\Activitypub\Collection\Following::follow( $this->actor_post_id, $this->administrator );
		\Activitypub\Collection\Following::accept( $this->actor_post_id, $this->administrator );

		$request  = $this->api_request( 'GET', '/api/v1/accounts/' . $this->administrator . '/following' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( strval( $this->actor_post_id ), $data[0]->id );
		$this->assertEquals( 'remote@mastodon.local', $data[0]->acct );
	}
}
