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
		add_filter( 'mastodon_api_media_attachment', array( $this, 'image_attachment' ), 10, 2 );
		add_filter( 'mastodon_api_media_attachment', array( $this, 'video_attachment' ), 10, 2 );
		add_filter( 'mastodon_api_status', array( $this, 'add_generic_image_attachments' ), 20 );
		add_filter( 'mastodon_api_status', array( $this, 'add_generic_video_attachments' ), 20 );
		add_filter( 'mastodon_api_status', array( $this, 'add_missing_image_dimensions' ), 30 );
		add_action( 'mastodon_api_media_uploaded', array( $this, 'schedule_video_thumbnail_generation' ) );
	}

	/**
	 * Schedule a Videopack thumbnail for video uploads when that plugin is available.
	 *
	 * @param int $attachment_id The uploaded attachment ID.
	 */
	public function schedule_video_thumbnail_generation( int $attachment_id ): void {
		if ( ! \wp_attachment_is( 'video', $attachment_id ) || \has_post_thumbnail( $attachment_id ) ) {
			return;
		}

		/**
		 * Filters the callable used to schedule thumbnail generation for video uploads.
		 *
		 * Return null to use the built-in Videopack integration.
		 *
		 * @param callable|null $scheduler     Thumbnail scheduler callback.
		 * @param int           $attachment_id The uploaded attachment ID.
		 */
		$scheduler = \apply_filters( 'mastodon_api_video_thumbnail_scheduler', null, $attachment_id );
		if ( \is_callable( $scheduler ) ) {
			$scheduler( $attachment_id );
			return;
		}

		if ( ! \function_exists( 'kgvid_schedule_attachment_processing' ) ) {
			return;
		}

		if ( \function_exists( 'kgvid_get_options' ) ) {
			$options = \kgvid_get_options();
			if ( isset( $options['auto_thumb'] ) && 'on' === $options['auto_thumb'] ) {
				return;
			}
		}

		\kgvid_schedule_attachment_processing( $attachment_id, 'thumbs' );
	}

	/**
	 * Image attachment handler.
	 *
	 * @param null $data          The media attachment data.
	 * @param int  $attachment_id The media attachment id.
	 *
	 * @return ?Media_Attachment_Entity The media attachment object.
	 */
	public function image_attachment( $data, int $attachment_id ): ?Media_Attachment_Entity {
		if ( ! $attachment_id || ! \wp_attachment_is_image( $attachment_id ) ) {
			return $data;
		}
		$thumb = \wp_get_attachment_image_src( $attachment_id );
		if ( ! $thumb ) {
			return $data;
		}
		$url  = \wp_get_attachment_url( $attachment_id );
		$meta = \wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			// The metadata was never generated, e.g. for an import; fall back to the file itself.
			$meta = array(
				'width'  => 0,
				'height' => 0,
			);
			$file = \get_attached_file( $attachment_id );
			if ( $file && file_exists( $file ) ) {
				$size = \wp_getimagesize( $file );
				if ( $size ) {
					$meta['width']  = $size[0];
					$meta['height'] = $size[1];
				}
			}
		}
		$thumb = false;
		if ( isset( $meta['sizes']['medium_large'] ) ) {
			$thumb = \wp_get_attachment_image_src( $attachment_id, 'medium_large' );
		} elseif ( isset( $meta['sizes']['medium'] ) ) {
			$thumb = \wp_get_attachment_image_src( $attachment_id, 'medium' );
		}
		if ( ! $thumb ) {
			$thumb = array( $url, $meta['width'], $meta['height'] );
		}

		$media_attachment              = new Media_Attachment_Entity();
		$media_attachment->id          = strval( $attachment_id );
		$media_attachment->type        = 'image';
		$media_attachment->url         = $url;
		$media_attachment->preview_url = $thumb[0];
		$media_attachment->description = get_the_excerpt( $attachment_id );
		$media_attachment->meta        = array(
			'original' => array(
				'width'  => $meta['width'],
				'height' => $meta['height'],
				'size'   => $meta['width'] . 'x' . $meta['height'],
				'aspect' => $meta['height'] ? $meta['width'] / $meta['height'] : 0,
			),
			'small'    => array(
				'width'  => $thumb[1],
				'height' => $thumb[2],
				'size'   => $thumb[1] . 'x' . $thumb[2],
				'aspect' => $thumb[2] ? $thumb[1] / $thumb[2] : 0,
			),
		);

		return $media_attachment;
	}

	/**
	 * Video attachment handler.
	 *
	 * @param null $data          The media attachment data.
	 * @param int  $attachment_id The media attachment id.
	 *
	 * @return ?Media_Attachment_Entity The media attachment object.
	 */
	public function video_attachment( $data, int $attachment_id ): ?Media_Attachment_Entity {
		if ( ! $attachment_id || ! \wp_attachment_is( 'video', $attachment_id ) ) {
			return $data;
		}
		$thumb = array(
			home_url( '/wp-includes/images/media/video.png' ),
			48,
			64,
		);
		if ( \has_post_thumbnail( $attachment_id ) ) {
			$thumbnail_id = get_post_thumbnail_id( $attachment_id );
			$thumb        = \wp_get_attachment_image_src( $thumbnail_id );
			$thumb_meta   = \wp_get_attachment_metadata( $thumbnail_id );
			if ( isset( $thumb_meta['sizes']['medium-large'] ) ) {
				$thumb = \wp_get_attachment_image_src( $thumbnail_id, 'medium-large' );
			} elseif ( isset( $thumb_meta['sizes']['medium'] ) ) {
				$thumb = \wp_get_attachment_image_src( $thumbnail_id, 'medium' );
			}
		}
		$meta = \wp_get_attachment_metadata( $attachment_id );
		if ( isset( $meta['sizes']['medium-large'] ) ) {
			$thumb = \wp_get_attachment_image_src( $attachment_id, 'medium-large' );
		} elseif ( isset( $meta['sizes']['medium'] ) ) {
			$thumb = \wp_get_attachment_image_src( $attachment_id, 'medium' );
		}
		$url = \wp_get_attachment_url( $attachment_id );

		$media_attachment              = new Media_Attachment_Entity();
		$media_attachment->id          = strval( $attachment_id );
		$media_attachment->type        = 'video';
		$media_attachment->url         = $url;
		$media_attachment->preview_url = $thumb[0];
		$media_attachment->description = get_the_excerpt( $attachment_id );
		$media_attachment->meta        = array(
			'original' => array(
				'width'  => $meta['width'],
				'height' => $meta['height'],
				'size'   => $meta['width'] . 'x' . $meta['height'],
				'aspect' => $meta['height'] ? $meta['width'] / $meta['height'] : 0,
			),
			'small'    => array(
				'width'  => $thumb[1],
				'height' => $thumb[2],
				'size'   => $thumb[1] . 'x' . $thumb[2],
				'aspect' => $thumb[2] ? $thumb[1] / $thumb[2] : 0,
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
		if ( false === strpos( $status->content, '<img' ) ) {
			return $status;
		}

		preg_match_all( '/<img\b([^>]+)>/', $status->content, $matches, PREG_SET_ORDER );
		if ( empty( $matches ) ) {
			return $status;
		}

		foreach ( $matches as $match ) {
			$status->content = str_replace( $match[0], '', $status->content );
			if ( ! preg_match( '/<img\b([^>]+)>/', $match[0], $img ) ) {
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
			$parts = wp_parse_url( html_entity_decode( $block['src'], ENT_QUOTES ) );
			if ( empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
				continue;
			}

			$attachment              = new \Enable_Mastodon_Apps\Entity\Media_Attachment();
			$attachment->id          = strval( 2e10 + crc32( $block['src'] ) );
			$attachment->type        = 'image';
			$attachment->url         = $block['src'];
			$attachment->preview_url = $block['src'];
			$attachment->remote_url  = $block['src'];
			if ( isset( $block['width'] ) && $block['width'] > 0 && isset( $block['height'] ) && $block['height'] > 0 ) {
				$attachment->meta             = array(
					'width'  => intval( $block['width'] ),
					'height' => intval( $block['height'] ),
					'size'   => $block['width'] . 'x' . $block['height'],
					'aspect' => (int) $block['width'] / (int) $block['height'],
				);
				$attachment->meta['original'] = $attachment->meta;
			}
			$attachment->description     = '';
			$status->media_attachments[] = $attachment;
		}
		return $status;
	}

	/**
	 * Get the dimensions of a locally hosted image, remembering them for the request.
	 *
	 * @param string $url The image URL.
	 *
	 * @return array|false An array with the width and the height, or false if they cannot be determined.
	 */
	private static function get_local_image_dimensions( string $url ) {
		static $cache = array();
		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		$cache[ $url ] = self::look_up_local_image_dimensions( $url );

		return $cache[ $url ];
	}

	/**
	 * Look up the dimensions of a locally hosted image.
	 *
	 * @param string $url The image URL.
	 *
	 * @return array|false An array with the width and the height, or false if they cannot be determined.
	 */
	private static function look_up_local_image_dimensions( string $url ) {
		$uploads = \wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) || 0 !== strpos( $url, $uploads['baseurl'] ) ) {
			return false;
		}

		// A resized file states its dimensions in its filename.
		if ( preg_match( '/-(\d+)x(\d+)\.\w+$/', $url, $matches ) ) {
			return array( intval( $matches[1] ), intval( $matches[2] ) );
		}

		$attachment_id = \attachment_url_to_postid( $url );
		if ( ! $attachment_id ) {
			return false;
		}

		$meta = \wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			return array( intval( $meta['width'] ), intval( $meta['height'] ) );
		}

		// The metadata was never generated, e.g. for an import; fall back to the file itself.
		$file = \get_attached_file( $attachment_id );
		if ( $file && file_exists( $file ) ) {
			$size = \wp_getimagesize( $file );
			if ( $size ) {
				return array( $size[0], $size[1] );
			}
		}

		return false;
	}

	/**
	 * Add the dimensions of image attachments that arrived without them.
	 *
	 * Width and height are optional in ActivityStreams, so a status assembled
	 * from an AS2 object -- as the ActivityPub plugin does for its own posts --
	 * carries no dimensions at all. Clients use them to lay the image out
	 * before it has loaded, so look them up when the image is one of our own.
	 *
	 * @param \Enable_Mastodon_Apps\Entity\Status $status The status object.
	 *
	 * @return \Enable_Mastodon_Apps\Entity\Status The status object with image dimensions added.
	 */
	public function add_missing_image_dimensions( $status ) {
		if ( ! $status instanceof Status_Entity || empty( $status->media_attachments ) ) {
			return $status;
		}

		foreach ( $status->media_attachments as $media_attachment ) {
			if ( ! $media_attachment instanceof Media_Attachment_Entity || 'image' !== $media_attachment->type ) {
				continue;
			}
			if ( ! empty( $media_attachment->meta['width'] ) || ! empty( $media_attachment->meta['original']['width'] ) ) {
				continue;
			}

			$dimensions = self::get_local_image_dimensions( $media_attachment->url );
			if ( ! $dimensions ) {
				continue;
			}

			list( $width, $height ) = $dimensions;

			$media_attachment->meta['original'] = array(
				'width'  => $width,
				'height' => $height,
				'size'   => $width . 'x' . $height,
				'aspect' => $height ? $width / $height : 0,
			);
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
			$block           = array();
			foreach ( array( 'src', 'width', 'height', 'poster' ) as $attr ) {
				if ( preg_match( '/\s' . $attr . '="(?P<' . $attr . '>[^"]+)"/', $match[1], $m ) ) {
					$block[ $attr ] = $m[ $attr ];
				}
			}

			if ( ! isset( $block['src'] ) ) {
				continue;
			}

			$attachment       = new \Enable_Mastodon_Apps\Entity\Media_Attachment();
			$attachment->id   = strval( 2e10 + crc32( $block['src'] ) );
			$attachment->type = 'video';
			$attachment->url  = $block['src'];
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
					'aspect' => (int) $block['width'] / (int) $block['height'],
				);
			}
			$attachment->description     = '';
			$status->media_attachments[] = $attachment;
		}
		return $status;
	}
}
