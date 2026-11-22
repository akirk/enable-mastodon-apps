<?php
/**
 * Class Test_Apps_Endpoint
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * A testcase for the comments CPT.
 *
 * @package
 */
class CommentsCPT_Tests extends \WP_UnitTestCase {
	/**
	 * Comment accounts use their stored ActivityPub actor without URL resolution.
	 */
	public function test_remote_comment_uses_stored_actor_and_falls_back_to_url() {
		if ( ! class_exists( '\\Activitypub\\Collection\\Remote_Actors' ) ) {
			$this->markTestSkipped( 'ActivityPub is not available.' );
		}
		$parent_id = self::factory()->post->create();
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'    => $parent_id,
				'comment_author_url' => 'https://example.org/@reader',
				'user_id'            => 0,
			)
		);
		$actor_id = self::factory()->post->create( array( 'post_type' => 'ap_actor' ) );
		$account = new Entity\Account();
		$account->id = (string) $actor_id;
		$lookups = array();
		$resolve = function ( $value, $id ) use ( $actor_id, $comment, $account, &$lookups ) {
			if ( (int) $id === $actor_id || $comment->comment_author_url === $id ) {
				$lookups[] = $id;
				return $account;
			}
			return $value;
		};
		$restore_account = function ( $value, $id ) use ( $actor_id, $comment, $account ) {
			return (int) $id === $actor_id || $comment->comment_author_url === $id ? $account : $value;
		};
		add_filter( 'mastodon_api_account', $resolve, 1, 2 );
		add_filter( 'mastodon_api_account', $restore_account, PHP_INT_MAX, 2 );
		try {
			$handler = new Comment_CPT();
			$proxy_id = Comment_CPT::comment_id_to_post_id( $comment->comment_ID );
			update_comment_meta( $comment->comment_ID, '_activitypub_remote_actor_id', $actor_id );
			$status = $handler->api_status( null, $proxy_id, array( 'comment' => $comment ) );
			$this->assertSame( $account, $status->account );
			$this->assertSame( array( $actor_id ), $lookups );
			$lookups = array();
			$this->assertSame( $account, $handler->api_account( null, 0, null, get_post( $proxy_id ) ) );
			$this->assertSame( array( $actor_id ), $lookups );
			// A missing or stale actor must retain the URL fallback.
			foreach ( array( 0, $parent_id ) as $stale_actor ) {
				$lookups = array();
				update_comment_meta( $comment->comment_ID, '_activitypub_remote_actor_id', $stale_actor );
				$status = $handler->api_status( null, $proxy_id, array( 'comment' => $comment ) );
				$this->assertSame( $account, $status->account );
				$this->assertSame( array( $comment->comment_author_url ), $lookups );
			}
		} finally {
			remove_filter( 'mastodon_api_account', $resolve, 1 );
			remove_filter( 'mastodon_api_account', $restore_account, PHP_INT_MAX );
		}
	}

	public function test_new_comment() {
		$posts_count = wp_count_posts( Comment_CPT::CPT );
		$post = $this->factory->post->create_and_get();
		$comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post->ID,
			)
		);
		$this->assertNotNull( $comment );

		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( 1 + $posts_count->publish, $new_count->publish );

		$comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID'  => $post->ID,
				'comment_approved' => '0',
			)
		);
		$this->assertNotNull( $comment );

		$third_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( 1 + $posts_count->publish, $third_count->publish );
	}

	public function test_approving_comment() {
		$posts_count = wp_count_posts( Comment_CPT::CPT );
		$post = $this->factory->post->create_and_get();
		$comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID'  => $post->ID,
				'comment_approved' => '0',
			)
		);
		$this->assertNotNull( $comment );

		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( $posts_count->publish, $new_count->publish );

		wp_update_comment(
			array(
				'comment_ID'       => $comment->comment_ID,
				'comment_approved' => '1',
			)
		);

		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( 1 + $posts_count->publish, $new_count->publish );

		wp_update_comment(
			array(
				'comment_ID'       => $comment->comment_ID,
				'comment_approved' => '0',
			)
		);

		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( $posts_count->publish, $new_count->publish );

		wp_update_comment(
			array(
				'comment_ID'       => $comment->comment_ID,
				'comment_approved' => '1',
			)
		);

		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( 1 + $posts_count->publish, $new_count->publish );

		wp_trash_comment( $comment->comment_ID );

		wp_cache_delete( _count_posts_cache_key( Comment_CPT::CPT ), 'counts' );
		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( $posts_count->publish, $new_count->publish );
	}

	public function test_deleting() {
		$posts_count = wp_count_posts( Comment_CPT::CPT );
		$post = $this->factory->post->create_and_get();
		$comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID'  => $post->ID,
				'comment_approved' => '1',
			)
		);
		$this->assertNotNull( $comment );

		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( 1 + $posts_count->publish, $new_count->publish );

		wp_delete_post( $post->ID, true );

		wp_cache_delete( _count_posts_cache_key( Comment_CPT::CPT ), 'counts' );
		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( $posts_count->publish, $new_count->publish );
	}

	public function test_upgrade() {
		remove_all_actions( 'wp_insert_comment' );
		$original_post = $this->factory->post->create_and_get();
		$approved_comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID'  => $original_post->ID,
				'comment_approved' => '1',
			)
		);
		$synced_approved_post = $this->factory->post->create_and_get(
			array(
				'post_type'  => Comment_CPT::CPT,
				'meta_input' => array(
					Comment_CPT::META_KEY => $approved_comment->comment_ID,
				),
			)
		);

		$spam_comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID'  => $original_post->ID,
				'comment_approved' => '0',
			)
		);
		$synced_spam_post = $this->factory->post->create_and_get(
			array(
				'post_type'  => Comment_CPT::CPT,
				'meta_input' => array(
					Comment_CPT::META_KEY => $spam_comment->comment_ID,
				),
			)
		);

		$post_count = wp_count_posts();
		$this->assertEquals( 1, $post_count->publish );
		$old_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( 2, $old_count->publish );

		Mastodon_Admin::upgrade_plugin( '0.9.0' );

		wp_cache_delete( _count_posts_cache_key(), 'counts' );
		$post_count = wp_count_posts();
		$this->assertEquals( 1, $post_count->publish );

		wp_cache_delete( _count_posts_cache_key( Comment_CPT::CPT ), 'counts' );
		$new_count = wp_count_posts( Comment_CPT::CPT );
		$this->assertEquals( 1, $new_count->publish );

		$this->assertNotNull( get_post( $synced_approved_post->ID ) );
		$this->assertNull( get_post( $synced_spam_post->ID ) );
	}
}
