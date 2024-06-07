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
	protected $_types = array(
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

	private function check_meta( $meta, $prepend = '' ) {
		if ( ! isset( $meta['width'] ) || $meta['width'] <= 0 ) {
			return new \WP_Error( 'invalid-meta-width', $prepend . 'Meta width must be a positive integer.' );
		}
		if ( ! isset( $meta['height'] ) || $meta['height'] <= 0 ) {
			return new \WP_Error( 'invalid-meta-height', $prepend . 'Meta height must be a positive integer.' );
		}
		if ( ! isset( $meta['size'] ) || ! is_string( $meta['size'] ) ) {
			return new \WP_Error( 'invalid-meta-size', $prepend . 'Meta size must be a string.' );
		}
		if ( ! isset( $meta['aspect'] ) || ! is_numeric( $meta['aspect'] ) ) {
			return new \WP_Error( 'invalid-meta-aspect', $prepend . 'Meta aspect must be a number.' );
		}

		return $meta;
	}

	public function validate( $key ) {
		if ( 'meta' === $key ) {
			if ( empty( $this->meta ) ) {
				return new \WP_Error( 'invalid-meta', 'Meta must not be empty.' );
			}
			if ( ! is_array( $this->meta ) ) {
				return new \WP_Error( 'invalid-meta', 'Meta must be an array.' );
			}
			if ( 'image' === $this->type ) {
				if ( isset( $this->meta['small'] ) ) {
					$meta = $this->check_meta( $this->meta['small'], 'small: ' );
					if ( is_wp_error( $meta ) ) {
						return $meta;
					}
				}
				if ( isset( $this->meta['original'] ) ) {
					$meta = $this->check_meta( $this->meta['original'], 'original: ' );
					if ( is_wp_error( $meta ) ) {
						return $meta;
					}
				}
				if ( isset( $this->meta['width'] ) ) {
					$meta = $this->check_meta( $this->meta );
					if ( is_wp_error( $meta ) ) {
						return $meta;
					}
				}
			}
		}

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
		return parent::__get( $key );
	}
}
