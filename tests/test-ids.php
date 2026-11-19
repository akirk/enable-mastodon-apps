<?php
/**
 * Class Ids_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use WP_REST_Request;
/**
 * A testcase for the ids.
 *
 * @package
 */
class Ids_Test extends Mastodon_API_TestCase {
	private $mapped_id;

	public function test_remote_reply_and_reblog_ids_are_numeric() {
		$url = 'https://metalhead.club/users/mariusor/statuses/117642274990754211';
		$status = new Entity\Status();
		$status->id = '123';
		$status->in_reply_to_id = $url;
		$status->reblog = new Entity\Status();
		$status->reblog->id = $url;
		$status->reblog->in_reply_to_id = $url . '-parent';

		$handler = new Handler\Handler();
		$status = $handler->api_status_ensure_numeric_id( $status );

		$this->assertSame( '123', $status->id );
		$this->assertTrue( ctype_digit( $status->in_reply_to_id ) );
		$this->assertTrue( is_numeric( $status->reblog->id ) );
		$this->assertTrue( ctype_digit( $status->reblog->in_reply_to_id ) );
		$this->assertSame( $url, Mastodon_API::maybe_get_remapped_url( $status->in_reply_to_id ) );
		$this->assertSame( $url, Mastodon_API::maybe_get_remapped_url( $status->reblog->id ) );
	}

	public function account_id_mapper( $account, $user_id ) {
		if ( strval( $user_id ) === strval( $this->administrator ) ) {
			$account->id = 'activitypub@example.org';
		}
		return $account;
	}

	public function mastodon_api_canonical_user_id( $id ) {
		if ( $id === $this->mapped_id ) {
			return $this->administrator;
		}
		return $id;
	}

	public function test_canonical_id() {
		$request = $this->api_request( 'GET', '/api/v1/accounts/' . $this->administrator );
		$response = $this->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertEquals( $this->administrator, $data['id'] );

		add_filter( 'mastodon_api_account', array( $this, 'account_id_mapper' ), 20, 2 );
		$response = $this->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertTrue( $data['id'] > 1e10 );
		$this->mapped_id = $data['id'];

		add_filter( 'mastodon_api_canonical_user_id', array( $this, 'mastodon_api_canonical_user_id' ), 20 );
		$request = $this->api_request( 'GET', '/api/v1/accounts/' . $this->mapped_id );
		$response = $this->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		$this->assertEquals( $this->mapped_id, $data['id'] );
	}
}
