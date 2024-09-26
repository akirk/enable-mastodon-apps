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

		$this->post = wp_insert_post(
			array(
				'post_author'  => $this->administrator,
				'post_content' => 'Test post',
				'post_title'   => '',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => '2023-01-03 00:00:00',
			)
		);
		set_post_format( $this->post, 'status' );

		add_filter( 'pre_http_request', array( $this, 'mock_http_requests' ), 10, 3 );
	}

	public function mock_http_requests( $preempt, $request, $url ) {
		if ( get_the_permalink( $this->post ) === $url ) {
			return array(
				'headers'  => array(),
				'body'     => json_encode(
					array(
						'type' => 'Note',
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
				'body'     => json_encode(
					array(
						'subject' => 'acct:https://example.org/@user',
						'aliases' => array(
							'https://example.org/@user',
						),
						'links' => array(
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
				'body'     => json_encode(
					array(
						'id' => 'https://example.org/@user',
						'preferredUsername' => 'user',
						'name' => 'user',
						'summary' => 'A user',
						'followers' => 0,
						'following' => 0,
						'inbox' => 'https://example.org/@user/inbox',
						'outbox' => 'https://example.org/@user/outbox',
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
				'comment_post_ID' => $this->post,
				'comment_type' => 'comment',
				'comment_content' => $comment_content,
				'comment_author_url' => 'https://example.org/@user',
				'comment_author_email' => '',
				'comment_meta' => array(
					'protocol' => 'activitypub',
				),
			)
		);
		$reply_text = 'reply';

		$this->assertCount( 1, get_comments( array( 'post_id' => $this->post ) ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertEquals( $comment_content, $data[0]['content'] );
		$this->assertCount( 4, $data );

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
		// $this->assertEquals( $data->id, Comment_CPT::comment_id_to_post_id( $comment->comment_ID ) );

		$type = 'Create';
		$schedule = \wp_next_scheduled( 'activitypub_send_comment', array( $comment->comment_ID, $type ) );
		$this->assertNotFalse( $schedule );
		do_action( 'activitypub_send_comment', $comment->comment_ID, $type );

		$this->assertEquals( 'federated', get_comment_meta( $comment->comment_ID, 'activitypub_status', true ) );

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertCount( 5, $data );


	}
}
