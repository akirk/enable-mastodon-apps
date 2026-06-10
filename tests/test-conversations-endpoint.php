<?php
/**
 * Class ConversationsEndpoint_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Testcases for the conversations endpoint.
 *
 * @package
 */
class ConversationsEndpoint_Test extends Mastodon_API_TestCase {
	public function test_conversation_uses_remote_participant_and_latest_status() {
		register_post_status( 'friends_read' );

		$remote_account = new Entity\Account();
		$remote_account->id = '10000000059';
		$remote_account->username = 'diego';
		$remote_account->acct = 'diego@example.social';
		$remote_account->url = 'https://example.social/@diego';
		$remote_account->display_name = 'Diego';
		$remote_account->note = '';
		$remote_account->avatar = 'https://example.social/avatar.jpg';
		$remote_account->avatar_static = 'https://example.social/avatar.jpg';
		$remote_account->created_at = new \DateTime( '2026-06-10 10:55:05' );
		$remote_account->statuses_count = 0;
		$remote_account->followers_count = 0;
		$remote_account->following_count = 0;
		$remote_account->last_status_at = new \DateTime( '2026-06-10 10:55:05' );
		$remote_account->source = array(
			'privacy'   => 'public',
			'sensitive' => false,
			'language'  => 'en',
			'note'      => '',
			'fields'    => array(),
		);

		add_filter(
			'mastodon_api_account',
			function ( $account, $user_id, $request = null, $post = null ) use ( $remote_account ) {
				if ( 0 === intval( $user_id ) && $post instanceof \WP_Post && 0 === strpos( $post->post_type, 'ema-dm-' ) ) {
					return $remote_account;
				}
				return $account;
			},
			10,
			4
		);

		$root = wp_insert_post(
			array(
				'post_author'  => 0,
				'post_content' => 'Hey Alex',
				'post_status'  => 'friends_read',
				'post_type'    => Mastodon_API::get_dm_cpt( $this->administrator ),
				'post_date'    => '2026-06-10 10:55:05',
			)
		);

		$reply = wp_insert_post(
			array(
				'post_author'  => $this->administrator,
				'post_content' => 'Hi Diego!',
				'post_parent'  => $root,
				'post_status'  => 'friends_read',
				'post_type'    => Mastodon_API::get_dm_cpt( $this->administrator ),
				'post_date'    => '2026-06-10 11:24:50',
			)
		);

		$request = $this->api_request( 'GET', '/api/v1/conversations' );
		$request->set_param( 'limit', 1 );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( strval( $root ), $data[0]->id );
		$this->assertCount( 1, $data[0]->accounts );
		$this->assertEquals( '10000000059', $data[0]->accounts[0]->id );
		$this->assertEquals( strval( $reply ), $data[0]->last_status->id );
		$this->assertEquals( strval( $this->administrator ), $data[0]->last_status->account->id );
	}
}
