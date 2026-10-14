<?php
/**
 * Nested reblog serialization tests.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use Enable_Mastodon_Apps\Entity\Status;

/**
 * Verify original-post resolution and cycle protection for integration boosts.
 */
class Nested_Reblogs_Test extends \WP_UnitTestCase {
	private function status( $id, $author ) {
		$status = new Status();
		$status->id = strval( $id );
		$status->created_at = new \DateTime( '2025-01-01', new \DateTimeZone( 'UTC' ) );
		$status->uri = 'https://example.org/status/' . $id;
		$status->content = 'Post ' . $id;
		$status->account = apply_filters( 'mastodon_api_account', null, $author );
		return $status;
	}

	public function test_boost_of_boost_preserves_outer_boost_and_original_post() {
		$author = self::factory()->user->create();
		$booster = self::factory()->user->create();
		$original = $this->status( 1, $author );
		$middle = $this->status( 2, $booster );
		$outer = $this->status( 3, $booster );
		$middle->reblog = $original;
		$outer->reblog = $middle;

		$data = $outer->jsonSerialize();
		$this->assertSame( '3', $data['id'] );
		$this->assertSame( strval( $booster ), $data['account']['id'] );
		$this->assertSame( '1', $data['reblog']['id'] );
		$this->assertSame( strval( $author ), $data['reblog']['account']['id'] );
		$this->assertSame( 'Post 1', $data['reblog']['content'] );
		$this->assertNull( $data['reblog']['reblog'] );
		$this->assertSame( $middle, $outer->reblog );
		$this->assertSame( $original, $middle->reblog );
	}

	public function test_circular_reblogs_fail_without_recursing() {
		$author = self::factory()->user->create();
		$first = $this->status( 1, $author );
		$second = $this->status( 2, $author );
		$first->reblog = $second;
		$second->reblog = $first;
		$this->assertSame( array( 'error' => 'Circular reblog reference.' ), $first->jsonSerialize() );
	}
}
