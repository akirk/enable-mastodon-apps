<?php
/**
 * Tests for video thumbnail integration.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Test video thumbnail scheduling.
 */
class VideoThumbnailIntegration_Test extends Mastodon_API_TestCase {
	public function tearDown(): void {
		remove_all_filters( 'mastodon_api_video_thumbnail_scheduler' );

		parent::tearDown();
	}

	public function test_video_upload_schedules_thumbnail_generation() {
		$attachment_id = $this->create_video_attachment();
		$scheduled     = array();
		add_filter(
			'mastodon_api_video_thumbnail_scheduler',
			function () use ( &$scheduled ) {
				return function ( $scheduled_attachment_id ) use ( &$scheduled ) {
					$scheduled[] = $scheduled_attachment_id;
				};
			}
		);
		$handler = new Handler\Media_Attachment();

		$handler->schedule_video_thumbnail_generation( $attachment_id );

		$this->assertSame( array( $attachment_id ), $scheduled );
	}

	public function test_image_upload_does_not_schedule_thumbnail_generation() {
		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => 'Test image',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			),
			'test-image.jpg'
		);
		$scheduled     = false;
		add_filter(
			'mastodon_api_video_thumbnail_scheduler',
			function () use ( &$scheduled ) {
				$scheduled = true;
				return null;
			}
		);
		$handler = new Handler\Media_Attachment();

		$handler->schedule_video_thumbnail_generation( $attachment_id );

		$this->assertFalse( $scheduled );
	}

	/**
	 * Create a video attachment post.
	 *
	 * @return int
	 */
	private function create_video_attachment(): int {
		return wp_insert_attachment(
			array(
				'post_title'     => 'Test video',
				'post_mime_type' => 'video/mp4',
				'post_status'    => 'inherit',
			),
			'test-video.mp4'
		);
	}
}
