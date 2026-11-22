<?php
/**
 * Tests for merging independently cached timeline objects.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Exercise source preference, event identity, visibility, and cursor pagination.
 */
class Timeline_Deduplication_Test extends Mastodon_API_TestCase {
	private $timeline;
	private $cached_ids = array();
	private $boost_ids = array();
	private $template;
	private $hidden_id = 0;

	public function set_up() {
		parent::set_up();
		wp_set_current_user( $this->administrator );
		register_post_type( 'test_cache_primary', array( 'public' => true ) );
		register_post_type( 'test_cache_secondary', array( 'public' => true ) );
		$this->template = apply_filters( 'mastodon_api_status', null, $this->post, array() );
		$this->timeline = new Handler\Timeline();
		add_filter( 'mastodon_api_timelines_args', array( $this, 'timeline_args' ), PHP_INT_MAX, 2 );
		add_filter( 'mastodon_api_timeline_post_identity', array( $this, 'object_identity' ), 10, 2 );
		add_filter( 'mastodon_api_timeline_post_priority', array( $this, 'representation_priority' ), 10, 2 );
		add_filter( 'mastodon_api_status', array( $this, 'cached_status' ), PHP_INT_MAX, 2 );
		add_filter( 'posts_where', array( $this, 'restrict_visibility' ), PHP_INT_MAX, 2 );
	}

	public function tear_down() {
		remove_filter( 'mastodon_api_timelines_args', array( $this, 'timeline_args' ), PHP_INT_MAX );
		remove_filter( 'mastodon_api_timeline_post_identity', array( $this, 'object_identity' ), 10 );
		remove_filter( 'mastodon_api_timeline_post_priority', array( $this, 'representation_priority' ), 10 );
		remove_filter( 'mastodon_api_status', array( $this, 'cached_status' ), PHP_INT_MAX );
		remove_filter( 'posts_where', array( $this, 'restrict_visibility' ), PHP_INT_MAX );
		remove_filter( 'mastodon_api_timelines', array( $this->timeline, 'api_timelines' ), 10 );
		remove_filter( 'mastodon_api_tag_timeline', array( $this->timeline, 'api_tag_timeline' ), 10 );
		remove_filter( 'mastodon_api_public_timeline', array( $this->timeline, 'api_public_timeline' ), 10 );
		unregister_post_type( 'test_cache_primary' );
		unregister_post_type( 'test_cache_secondary' );
		parent::tear_down();
	}

	private function cached_post( $type, $guid, $boost = false ) {
		$id = self::factory()->post->create(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_author' => $this->administrator,
				'post_date'   => '2026-01-01 00:00:' . sprintf( '%02d', count( $this->cached_ids ) ),
				'guid'        => $guid,
			)
		);
		$this->cached_ids[] = $id;
		if ( $boost ) {
			$this->boost_ids[] = $id;
		}
		return (string) $id;
	}

	public function timeline_args( $args, $request ) {
		return array(
			'post_type'                => array( 'test_cache_primary', 'test_cache_secondary' ),
			'post_status'              => 'publish',
			'post__in'                 => $this->cached_ids,
			'posts_per_page'           => $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 20,
			'orderby'                  => 'ID',
			'order'                    => 'DESC',
			'suppress_filters'         => false,
			'mastodon_api_deduplicate' => array( 'test_cache_secondary' ),
			'test_timeline_visibility' => true,
		);
	}

	public function restrict_visibility( $where, $query ) {
		if ( $query->get( 'test_timeline_visibility' ) && $this->hidden_id ) {
			global $wpdb;
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID <> %d", $this->hidden_id );
		}
		return $where;
	}

	public function object_identity( $identity, $post ) {
		if ( in_array( $post->ID, $this->cached_ids, true ) ) {
			return in_array( $post->ID, $this->boost_ids, true ) ? false : $post->guid;
		}
		return $identity;
	}

	public function representation_priority( $priority, $post ) {
		return 'test_cache_primary' === $post->post_type ? 10 : $priority;
	}

	public function cached_status( $status, $id ) {
		if ( in_array( (int) $id, $this->cached_ids, true ) ) {
			$status             = clone $this->template;
			$status->id         = (string) $id;
			$status->created_at = new \DateTime( get_post( $id )->post_date );
			if ( in_array( (int) $id, $this->boost_ids, true ) ) {
				$status->reblog = clone $this->template;
			}
		}
		return $status;
	}

	private function page( $params = array() ) {
		$request = new \WP_REST_Request( 'GET', '/enable-mastodon-apps/api/v1/timelines/home' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return wp_list_pluck( $this->timeline->api_timelines( null, $request )->get_data(), 'id' );
	}

	public function test_deduplication_precedes_both_pagination_directions_and_keeps_boosts() {
		$preferred = $this->cached_post( 'test_cache_primary', 'https://example.org/shared' );
		$older     = $this->cached_post( 'test_cache_primary', 'https://example.org/older' );
		$duplicate = $this->cached_post( 'test_cache_secondary', 'https://example.org/shared' );
		$boost_one = $this->cached_post( 'test_cache_primary', 'https://example.org/shared', true );
		$boost_two = $this->cached_post( 'test_cache_secondary', 'https://example.org/shared', true );
		$newest    = $this->cached_post( 'test_cache_secondary', 'https://example.org/newest' );
		// Publication dates from independent caches do not necessarily follow IDs.
		wp_update_post(
			array(
				'ID'        => (int) $older,
				'post_date' => '2026-02-01 00:00:00',
			)
		);

		$this->assertSame( array( $newest, $boost_two ), $this->page( array( 'limit' => 2 ) ) );
		$this->assertSame(
			array( $boost_one, $older ),
			$this->page(
				array(
					'limit'  => 2,
					'max_id' => $boost_two,
				)
			)
		);
		$this->assertSame(
			array( $preferred ),
			$this->page(
				array(
					'limit'  => 2,
					'max_id' => $older,
				)
			)
		);
		$this->assertSame(
			array( $boost_two, $boost_one ),
			$this->page(
				array(
					'limit'  => 2,
					'min_id' => $older,
				)
			)
		);
		$this->assertSame( $duplicate, apply_filters( 'mastodon_api_status', null, $duplicate, array() )->id );
	}

	public function test_hidden_preferred_copy_cannot_suppress_a_visible_copy() {
		$this->hidden_id = (int) $this->cached_post( 'test_cache_primary', 'https://example.org/shared' );
		$visible         = $this->cached_post( 'test_cache_secondary', 'https://example.org/shared' );
		$this->assertSame( array( $visible ), $this->page() );
	}

	public function test_older_integrations_can_expose_boost_identity_through_the_status() {
		$original = $this->cached_post( 'test_cache_primary', 'https://example.org/shared' );
		$boost    = $this->cached_post( 'test_cache_secondary', 'https://example.org/shared', true );
		remove_filter( 'mastodon_api_timeline_post_identity', array( $this, 'object_identity' ), 10 );
		$this->assertSame( array( $boost, $original ), $this->page() );
	}

	public function test_exclusions_preserve_existing_query_exclusions() {
		$preferred = $this->cached_post( 'test_cache_primary', 'https://example.org/shared' );
		$duplicate = $this->cached_post( 'test_cache_secondary', 'https://example.org/shared' );
		$request   = new \WP_REST_Request( 'GET', '/enable-mastodon-apps/api/v1/timelines/home' );
		$args      = $this->timeline_args( array(), $request );
		unset( $args['post__in'] );
		$args['post__not_in'] = array( $this->post );
		$args = $this->timeline->deduplicate_query_args( $args, $request );
		$this->assertContains( $this->post, $args['post__not_in'] );
		$this->assertContains( (int) $duplicate, $args['post__not_in'] );
		$this->assertNotContains( (int) $preferred, $args['post__not_in'] );
	}

	public function test_guid_comparison_is_case_sensitive() {
		$first  = $this->cached_post( 'test_cache_primary', 'https://example.org/Case' );
		$second = $this->cached_post( 'test_cache_secondary', 'https://example.org/case' );
		$this->assertSame( array( $second, $first ), $this->page() );
	}
}
