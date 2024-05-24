<?php
/**
 * Media Attachment handler.
 *
 * This contains the default Media Attachment handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Handler\Handler;
use Enable_Mastodon_Apps\Entity\Media_Attachment as Media_Attachment_Entity;
use Enable_Mastodon_Apps\Entity\Status as Status_Entity;

/**
 * This is the class that implements the default handler for all Media endpoints.
 */
class Media_Attachment extends Handler {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_media_attachment', array( $this, 'api_media_attachment' ), 10, 2 );
		add_filter( 'mastodon_api_status', array( $this, 'add_generic_image_attachments' ), 20 );
		add_filter( 'mastodon_api_status', array( $this, 'add_generic_video_attachments' ), 20 );
	}

	/**
	 * Media attachment handler.
	 *
	 * @param null $data          The media attachment data.
	 * @param int  $attachment_id The media attachment id.
	 *
	 * @return ?Media_Attachment_Entity The media attachment object.
	 */
	public function api_media_attachment( $data, int $attachment_id ): ?Media_Attachment_Entity {
		if ( ! $attachment_id ) {
			return $data;
		}
		$thumb = \wp_get_attachment_image_src( $attachment_id );
		if ( ! $thumb ) {
			return $data;
		}
		$meta  = \wp_get_attachment_metadata( $attachment_id );
		$url   = \wp_get_attachment_url( $attachment_id );

		$media_attachment              = new Media_Attachment_Entity();
		$media_attachment->id          = strval( $attachment_id );
		$media_attachment->type        = strtok( wp_check_filetype( $meta['file'] )['type'], '/' );
		$media_attachment->url         = $url;
		$media_attachment->preview_url = $thumb[0];
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

	/**
	 * Add generic image attachments.
	 *
	 * @param Enable_Mastodon_Apps\Entity\Status $status The status object.
	 * @return Enable_Mastodon_Apps\Entity\Status The status object with image attachments added.
	 */
	public function add_generic_image_attachments( $status ) {
		if ( ! $status instanceof Status_Entity ) {
			return $status;
		}
		if ( false === strpos( $status->content, '<!-- wp:image' ) ) {
			return $status;
		}
		preg_match_all( '/<!-- wp:image(\s\{[^}]+\})? -->(.*?)<!-- \/wp:image -->/s', $status->content, $matches, PREG_SET_ORDER );
		if ( empty( $matches ) ) {
			return $status;
		}

		foreach ( $matches as $match ) {
			$status->content = str_replace( $match[0], '', $status->content );
			if ( ! preg_match( '/<img\b([^>]+)>/', $match[2], $img ) ) {
				continue;
			}
			$block = array();
			foreach ( array( 'src', 'width', 'height' ) as $attr ) {
				if ( preg_match( '/\s' . $attr . '="(?P<' . $attr . '>[^"]+)"/', $img[1], $m ) ) {
					$block[ $attr ] = $m[ $attr ];
				}
			}
			if ( ! isset( $block['src'] ) ) {
				continue;
			}

			$attachment = new \Enable_Mastodon_Apps\Entity\Media_Attachment();
			$attachment->id = strval( 2e10 + crc32( $block['src'] ) );
			$attachment->type = 'image';
			$attachment->url = $block['src'];
			$attachment->preview_url = $block['src'];
			$attachment->remote_url = $block['src'];
			if ( isset( $block['width'] ) && $block['width'] > 0 && isset( $block['height'] ) && $block['height'] > 0 ) {
				$attachment->meta = array(
					'width'  => intval( $block['width'] ),
					'height' => intval( $block['height'] ),
					'size'   => $block['width'] . 'x' . $block['height'],
					'aspect' => $block['width'] / $block['height'],
				);
			} else {
				$attachment->meta = array(
					'width'  => 0,
					'height' => 0,
					'size'   => '0x0',
					'aspect' => 1,
				);
			}
			$original = $attachment->meta;
			$attachment->meta['original'] = $original;
			$attachment->description = '';
			$status->media_attachments[] = $attachment;
		}
		return $status;
	}

	/**
	 * Add generic video attachments.
	 *
	 * @param Enable_Mastodon_Apps\Entity\Status $status The status object.
	 * @return Enable_Mastodon_Apps\Entity\Status The status object with video attachments added.
	 */
	public function add_generic_video_attachments( $status ) {
		if ( ! $status instanceof Status_Entity ) {
			return $status;
		}
		if ( false === strpos( $status->content, '<video' ) ) {
			return $status;
		}
		preg_match_all( '/<video\b([^>]+)>/', $status->content, $matches, PREG_SET_ORDER );
		if ( empty( $matches ) ) {
			return $status;
		}

		foreach ( $matches as $match ) {
			$status->content = str_replace( $match[0], '', $status->content );
			$block = array();
			foreach ( array( 'src', 'width', 'height', 'poster' ) as $attr ) {
				if ( preg_match( '/\s' . $attr . '="(?P<' . $attr . '>[^"]+)"/', $match[1], $m ) ) {
					$block[ $attr ] = $m[ $attr ];
				}
			}

			if ( ! isset( $block['src'] ) ) {
				continue;
			}

			$attachment = new \Enable_Mastodon_Apps\Entity\Media_Attachment();
			$attachment->id = strval( 2e10 + crc32( $block['src'] ) );
			$attachment->type = 'video';
			$attachment->url = $block['src'];
			if ( isset( $block['poster'] ) ) {
				$attachment->preview_url = $block['poster'];
			} else {
				// Placeholder image.
				$attachment->preview_url = home_url( '/wp-includes/images/media/video.png' );
			}
			$attachment->remote_url = $block['src'];
			if ( isset( $block['width'] ) && $block['width'] > 0 && isset( $block['height'] ) && $block['height'] > 0 ) {
				$attachment->meta = array(
					'width'  => intval( $block['width'] ),
					'height' => intval( $block['height'] ),
					'size'   => $block['width'] . 'x' . $block['height'],
					'aspect' => $block['width'] / $block['height'],
				);
			} else {
				$attachment->meta = array(
					'width'  => 0,
					'height' => 0,
					'size'   => '0x0',
					'aspect' => 1,
				);
			}
			$attachment->description = '';
			$status->media_attachments[] = $attachment;
		}
		return $status;
	}
}
