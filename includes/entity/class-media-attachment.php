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
 * This is the class that implements the Media Attachment entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Media_Attachment extends Entity {
	protected $_types = array(
		'id'          => 'string',
		'type'        => 'string',
		'url'         => 'string',
		'preview_url' => 'string',

		'blurhash'    => 'string?',
		'remote_url'  => 'string?',
		'description' => 'string?',

		'meta'        => 'object',
	);

	/**
	 * The ID of the attachment.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * The type of the attachment.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * The location of the original full-size attachment.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * The location of a scaled-down preview of the attachment.
	 *
	 * @var string
	 */
	public string $preview_url;

	/**
	 * The location of the full-size original attachment on the remote website.
	 *
	 * @var null|string
	 */
	public ?string $remote_url;

	/**
	 * Media metadata.
	 *
	 * @var object
	 */
	public object $meta;

	/**
	 * Alternate text that describes what is in the media attachment.
	 *
	 * @var null|string
	 */
	public ?string $description;

	/**
	 * A hash for generating colorful preview thumbnails when media has not been
	 * downloaded yet.
	 *
	 * @var null|string
	 */
	public ?string $blurhash = null;
}
