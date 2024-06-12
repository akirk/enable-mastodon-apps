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
