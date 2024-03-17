<?php
/**
 * Media Attachment handler.
 *
 * This contains the default Media Attachment handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Entity\Media_Attachment as Media_Attachment_Entity;

/**
 * This is the class that implements the default handler for all Media endpoints.
 */
class Media_Attachment {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_media_attachment', array( $this, 'api_media_attachment' ), 10, 2 );
	}

	/**
	 * Media attachment handler.
	 *
	 * @param null $data          The media attachment data.
	 * @param int  $attachment_id The media attachment id.
	 *
	 * @return Media_Attachment_Entity The media attachment object.
	 */
	public function api_media_attachment( $data, int $attachment_id ): Media_Attachment_Entity {
		$thumb = \wp_get_attachment_image_src( $attachment_id );
		$meta  = \wp_get_attachment_metadata( $attachment_id );
		$url   = \wp_get_attachment_url( $attachment_id );

		$media_attachment              = new Media_Attachment_Entity();
		$media_attachment->id          = strval( $attachment_id );
		$media_attachment->type        = wp_check_filetype( $meta['file'] )['type'];
		$media_attachment->url         = $url;
		$media_attachment->preview_url = $thumb[0];
		$media_attachment->text_url    = $url;
		$media_attachment->description = get_the_excerpt( $attachment_id );
		$media_attachment->meta        = array(
			'original' => array(
				'width'  => $meta['width'],
				'height' => $meta['height'],
				'size'   => $meta['width'] . 'x' . $meta['height'],
				'aspect' => $meta['width'] / $meta['height'],
			),
			'small'    => array(
				'width'  => $thumb[1],
				'height' => $thumb[2],
				'size'   => $thumb[1] . 'x' . $thumb[2],
				'aspect' => $thumb[1] / $thumb[2],
			),
		);

		return $media_attachment;
	}
}
