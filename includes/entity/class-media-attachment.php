<?php
/**
 * Media Attachment entity.
 *
 * This contains the Media Attachment entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Media_Attachment entity.
 */
class Media_Attachment extends Entity {
	protected $types = array(
		'id'          => 'string',
		'type'        => 'string',
		'url'         => 'string',
		'preview_url' => 'string',
		'remote_url'  => 'string??',
		'meta'        => 'array',
		'description' => 'string??',
		'blurhash'    => 'string??',
	);

	/**
	 * The media attachment id.
	 *
	 * @var string
	 */
	public string $id = '';

	/**
	 * The media attachment type.
	 *
	 * One of: 'unknown', 'image', 'gifv', 'video', 'audio'.
	 *
	 * @var string
	 */
	public string $type = 'unknown';

	/**
	 * The media attachment url.
	 *
	 * @var string
	 */
	public string $url = '';

	/**
	 * The media attachment preview url.
	 *
	 * @var string
	 */
	public string $preview_url = '';

	/**
	 * The media attachment remote url.
	 *
	 * @var string|null
	 */
	public ?string $remote_url = null;

	/**
	 * The media attachment meta.
	 *
	 * May contain subtrees small and original, as well as various other top-level properties.
	 *
	 * @var array
	 */
	public array $meta = array();

	/**
	 * The media attachment description.
	 *
	 * @var string|null
	 */
	public ?string $description = null;

	/**
	 * The media attachment blurhash.
	 *
	 * @var string
	 */
	public ?string $blurhash = null;

	/**
	 * Normalize a set of dimensions.
	 *
	 * @param mixed $meta The array that should carry width and height.
	 *
	 * @return array|null The array with consistent width, height, size and aspect, or null if there are no usable dimensions.
	 */
	private static function normalize_dimensions( $meta ) {
		if ( ! is_array( $meta ) ) {
			return null;
		}

		$width  = isset( $meta['width'] ) ? intval( $meta['width'] ) : 0;
		$height = isset( $meta['height'] ) ? intval( $meta['height'] ) : 0;
		if ( $width <= 0 || $height <= 0 ) {
			return null;
		}

		$meta['width']  = $width;
		$meta['height'] = $height;
		$meta['size']   = $width . 'x' . $height;
		$meta['aspect'] = $width / $height;

		return $meta;
	}

	/**
	 * Normalize the meta data, dropping dimensions we cannot use.
	 *
	 * Width and height are optional in ActivityStreams, so an attachment handed
	 * to us by another plugin often has none, and other sources supply zeroes.
	 * Those dimensions are dropped rather than treated as an error: an image
	 * without a size hint still displays, an image dropped from the status does
	 * not.
	 *
	 * @param mixed $meta The meta data.
	 *
	 * @return array The meta data without unusable dimensions.
	 */
	private static function normalize_meta( $meta ) {
		if ( ! is_array( $meta ) ) {
			return array();
		}

		foreach ( array( 'small', 'original' ) as $size ) {
			if ( ! isset( $meta[ $size ] ) ) {
				continue;
			}
			$dimensions = self::normalize_dimensions( $meta[ $size ] );
			if ( null === $dimensions ) {
				unset( $meta[ $size ] );
			} else {
				$meta[ $size ] = $dimensions;
			}
		}

		if ( isset( $meta['width'] ) || isset( $meta['height'] ) ) {
			$dimensions = self::normalize_dimensions( $meta );
			if ( null === $dimensions ) {
				unset( $meta['width'], $meta['height'], $meta['size'], $meta['aspect'] );
			} else {
				$meta = $dimensions;
			}
		}

		return $meta;
	}

	public function validate( $key ) {
		if ( 'preview_url' === $key ) {
			if ( 'video' === $this->type ) {
				if ( ! $this->preview_url || ! is_string( $this->preview_url ) ) {
					return new \WP_Error( 'invalid-preview-url', 'Preview URL must be a string.' );
				}
			}
		}
		return parent::validate( $key );
	}

	public function __get( $key ) {
		if ( 'url' === $key || 'preview_url' === $key || 'remote_url' === $key ) {
			return str_replace( ' ', '%20', $this->$key );
		}
		if ( 'meta' === $key ) {
			$meta = self::normalize_meta( $this->meta );

			// Mastodon documents meta as a hash, and an empty PHP array would be serialized as [].
			return empty( $meta ) ? new \stdClass() : $meta;
		}
		return parent::__get( $key );
	}
}
