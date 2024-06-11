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
}
