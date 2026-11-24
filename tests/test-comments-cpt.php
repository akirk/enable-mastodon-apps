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
	 * Render a comment with a deterministic account, without serializing partial fixtures.
	 *
	 * @param int              $object_id The mapped comment or reply target ID.
	 * @param \WP_Comment|null $comment   Optional explicit comment from create/edit handlers.
	 * @return Entity\Status The comment status.
	 */
	private function render_comment_status( $object_id, $comment = null ) {
		$account = function () {
			$value = new Entity\Account();
			$value->id = '123456';
			return $value;
		};
		add_filter( 'mastodon_api_account', $account, 1 );
		try {
			$data = $comment ? array( 'comment' => $comment ) : array();
			return ( new Comment_CPT() )->api_status( null, (int) $object_id, $data );
		} finally {
			remove_filter( 'mastodon_api_account', $account, 1 );
		}
	}

	/**
	 * Core and protocol comment filters render links while storage and edit source stay raw.
	 */
	public function test_status_renders_comment_text_and_preserves_source() {
		$post_id = self::factory()->post->create();
		$content = '@friend@example.org See https://example.org/plain and <a href="https://example.org/existing">an existing link</a>.';
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => $content,
			)
		);
		$mapped_id = Comment_CPT::comment_id_to_post_id( $comment->comment_ID );
		$received = null;
		$format = function ( $text, $source, $args ) use ( &$received ) {
			$received = array( $source->comment_ID, $args );
			return str_replace( '@friend@example.org', '<a href="https://example.org/@friend" rel="mention">@friend</a>', $text ) . '<script>alert(1)</script>';
		};
		add_filter( 'comment_text', $format, 50, 3 );
		try {
			$status = $this->render_comment_status( $mapped_id );
		} finally {
			remove_filter( 'comment_text', $format, 50 );
		}
		$this->assertSame( array( $comment->comment_ID, array() ), $received );
		$this->assertSame( (string) $post_id, $status->in_reply_to_id );
		$this->assertStringContainsString( '<p>', $status->content );
		$this->assertStringNotContainsString( '<script', $status->content );
		$links = array();
		$processor = new \WP_HTML_Tag_Processor( $status->content );
		while ( $processor->next_tag( 'a' ) ) {
			$links[] = $processor->get_attribute( 'href' );
		}
		$this->assertContains( 'https://example.org/plain', $links );
		$this->assertContains( 'https://example.org/@friend', $links );
		$this->assertSame( 1, count( array_keys( $links, 'https://example.org/existing', true ) ) );
		$this->assertSame( $content, get_comment( $comment->comment_ID )->comment_content );
		$source = ( new Comment_CPT() )->api_status_source( null, (int) $mapped_id );
		$this->assertSame( trim( wp_strip_all_tags( $content ) ), $source->text );
	}

	/**
	 * Create/edit responses format the explicitly supplied reply, not the reply target.
	 */
	public function test_status_renders_explicit_comment_instead_of_mapped_target() {
		$post_id = self::factory()->post->create();
		$parent = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'https://example.org/parent',
			)
		);
		$reply = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_parent'  => $parent->comment_ID,
				'comment_content' => 'https://example.org/reply',
			)
		);
		$target = Comment_CPT::comment_id_to_post_id( $parent->comment_ID );
		$status = $this->render_comment_status( $target, $reply );
		$this->assertStringContainsString( 'href="https://example.org/reply"', $status->content );
		$this->assertStringNotContainsString( 'https://example.org/parent', $status->content );
		$this->assertSame( (string) $target, $status->in_reply_to_id );
	}

	/**
	 * Independent comments render without audience lookups or leaking mention callbacks.
	 */
	public function test_rendering_comments_does_not_build_activitypub_audience() {
		global $wp_filter;
		$before = isset( $wp_filter['activitypub_extract_mentions'] ) ? $wp_filter['activitypub_extract_mentions']->callbacks : array();
		$user_id = get_current_user_id();
		$calls = 0;
		$block_http = function () use ( &$calls ) {
			++$calls;
			return new \WP_Error( 'unexpected_http', 'Rendering comment text should not fetch an audience.' );
		};
		add_filter( 'pre_http_request', $block_http, 1 );
		try {
			foreach ( array( 'first', 'second' ) as $name ) {
				$post_id = self::factory()->post->create();
				$comment = self::factory()->comment->create_and_get(
					array(
						'comment_post_ID' => $post_id,
						'comment_content' => 'https://example.org/' . $name,
					)
				);
				$status = $this->render_comment_status( Comment_CPT::comment_id_to_post_id( $comment->comment_ID ) );
				$this->assertStringContainsString( 'href="https://example.org/' . $name . '"', $status->content );
			}
		} finally {
			remove_filter( 'pre_http_request', $block_http, 1 );
		}
		$this->assertStringNotContainsString( 'https://example.org/first', $status->content );
		$this->assertSame( 0, $calls );
		$this->assertSame( $user_id, get_current_user_id() );
		$after = isset( $wp_filter['activitypub_extract_mentions'] ) ? $wp_filter['activitypub_extract_mentions']->callbacks : array();
		$this->assertSame( $before, $after );
	}

	/**
	 * Protocol integrations receive the original comment when resolving its account.
	 */
	public function test_comment_account_filter_receives_original_comment() {
		$post_id = self::factory()->post->create();
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'    => $post_id,
				'comment_author_url' => 'https://example.org/@reader',
				'user_id'            => 0,
			)
		);
		$proxy_id = Comment_CPT::comment_id_to_post_id( $comment->comment_ID );
		$account = new Entity\Account();
		$account->id = '123456';
		$context = null;
		$resolve = function ( $value, $id, $request = null, $source = null ) use ( $comment, $account, &$context ) {
			if ( $comment->comment_author_url === $id ) {
				$context = $source;
				return $account;
			}
			return $value;
		};
		add_filter( 'mastodon_api_account', $resolve, 1, 4 );
		try {
			$result = ( new Comment_CPT() )->api_account( null, 0, null, get_post( $proxy_id ) );
			$this->assertSame( $account->id, (string) $result->id );
			$this->assertInstanceOf( '\\WP_Comment', $context );
			$this->assertSame( (int) $comment->comment_ID, (int) $context->comment_ID );
		} finally {
			remove_filter( 'mastodon_api_account', $resolve, 1 );
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
