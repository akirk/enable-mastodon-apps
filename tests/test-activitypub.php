<?php
/**
 * Class ActivityPub_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use Enable_Mastodon_Apps\Comment_CPT;

/**
 * Testcase for the ActivityPub interactions.
 *
 * @package
 */
class ActivityPub_Test extends Mastodon_API_TestCase {
	public function set_up() {
		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			return $this->markTestSkipped( 'The ActivityPub plugin is not loaded.' );
		}
		parent::set_up();

		add_filter( 'pre_http_request', array( $this, 'mock_http_requests' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http_requests' ) );
		parent::tear_down();
	}

	public function mock_http_requests( $preempt, $request, $url ) {
		if ( get_the_permalink( $this->post ) === $url ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'type'    => 'Note',
						'content' => 'Test post',
					)
				),
				'response' => array(
					'code' => 200,
				),
			);
		}
		if ( 'https://example.org/.well-known/webfinger?resource=https%3A%2F%2Fexample.org%2F%40user' === $url ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'subject' => 'acct:https://example.org/@user',
						'aliases' => array(
							'https://example.org/@user',
						),
						'links'   => array(
							array(
								'rel'  => 'self',
								'type' => 'application/activity+json',
								'href' => 'https://example.org/@user',
							),
						),
					)
				),
				'response' => array(
					'code' => 200,
				),
			);
		}
		if ( 'https://example.org/@user' === $url ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'id'                => 'https://example.org/@user',
						'preferredUsername' => 'user',
						'name'              => 'user',
						'summary'           => 'A user',
						'followers'         => 0,
						'following'         => 0,
						'inbox'             => 'https://example.org/@user/inbox',
						'outbox'            => 'https://example.org/@user/outbox',
					)
				),
				'response' => array(
					'code' => 200,
				),
			);

		}
		return $preempt;
	}

	public function test_activitypub_submit_status_reply() {
		$comment_content = 'Test comment';
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $this->post,
				'comment_type'         => 'comment',
				'comment_content'      => $comment_content,
				'comment_author_url'   => 'https://example.org/@user',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);
		$reply_text = 'reply!';

		$this->assertCount( 1, get_comments( array( 'post_id' => $this->post ) ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$comment_post_id = Comment_CPT::comment_id_to_post_id( $comment_id );
		foreach ( $data as $item ) {
			if ( $item['id'] === $comment_post_id ) {
				$this->assertEquals( $comment_content, $data[0]['content'] );
				break;
			}
		}
		$this->assertCount( 3, $data );

		$comments = get_comments( array( 'post_id' => $this->post ) );
		$this->assertCount( 1, $comments );

		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', $reply_text );
		$request->set_param( 'in_reply_to_id', $data[0]['id'] );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data->id );
		$this->assertIsNumeric( $data->id );
		$comments = get_comments( array( 'post_id' => $this->post ) );
		$this->assertCount( 2, $comments );
		foreach ( $comments as $comment ) {
			if ( $comment->comment_content === $reply_text ) {
				break;
			}
		}
		$this->assertNotEmpty( $comment );
		$this->assertTrue( \ActivityPub\should_comment_be_federated( $comment ) );

		$type = 'Create';
		$outbox = \get_posts(
			array(
				'post_type'      => \Activitypub\Collection\Outbox::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'pending',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$this->assertNotEmpty( $outbox );
		$json = json_decode( $outbox[0]->post_content );
		$this->assertEquals( $json->object->url, home_url( '?c=' . $comment->comment_ID ) );
		do_action( 'activitypub_process_outbox' );

		$this->assertEquals( 'federate', substr( get_comment_meta( $comment->comment_ID, 'activitypub_status', true ), 0, 8 ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertCount( 4, $data );
	}
}
