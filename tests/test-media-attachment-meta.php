<?php
/**
 * Class MediaAttachmentMeta_Test.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use Enable_Mastodon_Apps\Entity\Media_Attachment;
use Enable_Mastodon_Apps\Entity\Status;

/**
 * Testcases for the media attachment meta data.
 */
class MediaAttachmentMeta_Test extends Mastodon_API_TestCase {
	/**
	 * Create a status that carries a single image attachment.
	 *
	 * @param array  $meta The meta data of the attachment.
	 * @param string $url  The URL of the attachment.
	 *
	 * @return Status The status entity.
	 */
	private function status_with_image( array $meta, string $url = 'https://example.org/image.png' ): Status {
		$attachment              = new Media_Attachment();
		$attachment->id          = '1';
		$attachment->type        = 'image';
		$attachment->url         = $url;
		$attachment->preview_url = $url;
		$attachment->meta        = $meta;

		$status                    = new Status();
		$status->id                = strval( $this->friend_post );
		$status->created_at        = new \DateTime( '2023-01-04 00:00:00', new \DateTimeZone( 'UTC' ) );
		$status->visibility        = 'public';
		$status->uri               = 'https://example.org/?p=1';
		$status->content           = 'A photo.';
		$status->account           = apply_filters( 'mastodon_api_account', null, $this->friend );
		$status->media_attachments = array( $attachment );

		return $status;
	}

	public function test_attachment_without_meta_is_not_dropped() {
		$data = $this->status_with_image( array() )->jsonSerialize();

		$this->assertCount( 1, $data['media_attachments'] );
		// Mastodon documents meta as a hash, so it must not serialize as [].
		$this->assertEquals( '{}', wp_json_encode( $data['media_attachments'][0]['meta'] ) );
	}

	public function test_attachment_with_zero_dimensions_is_not_dropped() {
		$data = $this->status_with_image(
			array(
				'original' => array(
					'width'  => 0,
					'height' => 0,
					'size'   => '0x0',
					'aspect' => 1,
				),
			)
		)->jsonSerialize();

		$this->assertCount( 1, $data['media_attachments'] );
		$this->assertEquals( '{}', wp_json_encode( $data['media_attachments'][0]['meta'] ) );
	}

	public function test_incomplete_dimensions_are_completed() {
		$data = $this->status_with_image(
			array(
				'focus'    => array(
					'x' => 0.5,
					'y' => -0.5,
				),
				'original' => array(
					'width'  => '800',
					'height' => '400',
				),
			)
		)->jsonSerialize();

		$this->assertCount( 1, $data['media_attachments'] );
		$meta = $data['media_attachments'][0]['meta'];
		$this->assertSame( 800, $meta['original']['width'] );
		$this->assertSame( 400, $meta['original']['height'] );
		$this->assertSame( '800x400', $meta['original']['size'] );
		// PHP returns an int from an evenly dividing division, so don't be strict about the type here.
		$this->assertEquals( 2, $meta['original']['aspect'] );
		$this->assertSame(
			array(
				'x' => 0.5,
				'y' => -0.5,
			),
			$meta['focus']
		);
	}

	public function test_unusable_dimensions_keep_the_rest_of_the_meta() {
		$data = $this->status_with_image(
			array(
				'width'  => 0,
				'height' => 0,
				'focus'  => array(
					'x' => 0.0,
					'y' => 0.0,
				),
			)
		)->jsonSerialize();

		$meta = $data['media_attachments'][0]['meta'];
		$this->assertArrayNotHasKey( 'width', $meta );
		$this->assertArrayNotHasKey( 'height', $meta );
		$this->assertArrayHasKey( 'focus', $meta );
	}

	/**
	 * Run the handler that adds missing dimensions over a status.
	 *
	 * The handler is called directly rather than through the mastodon_api_status
	 * filter: the ActivityPub plugin hooks that filter at priority 9 and returns
	 * a status of its own making, discarding the one passed in, so a status built
	 * here would never reach the handler when that plugin is active.
	 *
	 * @param Status $status The status to run the handler over.
	 *
	 * @return array The serialized meta data of the first media attachment.
	 */
	private function add_missing_dimensions( Status $status ): array {
		// The constructor registers hooks that are registered already.
		$handler = ( new \ReflectionClass( \Enable_Mastodon_Apps\Handler\Media_Attachment::class ) )->newInstanceWithoutConstructor();
		$data    = $handler->add_missing_image_dimensions( $status )->jsonSerialize();

		$this->assertCount( 1, $data['media_attachments'] );

		return (array) $data['media_attachments'][0]['meta'];
	}

	public function test_missing_dimensions_are_looked_up_for_local_images() {
		$meta = $this->add_missing_dimensions( $this->status_with_image( array(), wp_get_attachment_url( $this->friend_attachment_id ) ) );

		$this->assertSame( 1000, $meta['original']['width'] );
		$this->assertSame( 1000, $meta['original']['height'] );
		$this->assertSame( '1000x1000', $meta['original']['size'] );
	}

	public function test_missing_dimensions_are_taken_from_a_resized_filename() {
		$url  = str_replace( 'ima ge.png', 'ima ge-300x200.png', wp_get_attachment_url( $this->friend_attachment_id ) );
		$meta = $this->add_missing_dimensions( $this->status_with_image( array(), $url ) );

		$this->assertSame( 300, $meta['original']['width'] );
		$this->assertSame( 200, $meta['original']['height'] );
	}

	public function test_missing_dimensions_of_remote_images_are_left_alone() {
		$meta = $this->add_missing_dimensions( $this->status_with_image( array(), 'https://remote.example/image.png' ) );

		$this->assertSame( array(), $meta );
	}

	public function test_existing_dimensions_are_not_overwritten() {
		$status = $this->status_with_image(
			array(
				'original' => array(
					'width'  => 400,
					'height' => 300,
				),
			),
			wp_get_attachment_url( $this->friend_attachment_id )
		);
		$meta   = $this->add_missing_dimensions( $status );

		$this->assertSame( 400, $meta['original']['width'] );
		$this->assertSame( 300, $meta['original']['height'] );
	}
}
