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
		'remote_url'  => 'string',
		'text_url'    => 'string',
		'meta'        => 'array',
		'description' => 'string',
		'blurhash'    => 'string',
	);

	/**
	 * The media attachment id.
	 *
	 * @var string
	 */
	protected string $id = '';

	/**
	 * The media attachment type.
	 *
	 * One of: 'unknown', 'image', 'gifv', 'video', 'audio'.
	 *
	 * @var string
	 */
	protected string $type = 'unknown';

	/**
	 * The media attachment url.
	 *
	 * @var string
	 */
	protected string $url = '';

	/**
	 * The media attachment preview url.
	 *
	 * @var string
	 */
	protected string $preview_url = '';

	/**
	 * The media attachment remote url.
	 *
	 * @var string|null
	 */
	protected $remote_url = null;

	/**
	 * The media attachment text url.
	 *
	 * @var string
	 */
	protected string $text_url = '';

	/**
	 * The media attachment meta.
	 *
	 * May contain subtrees small and original, as well as various other top-level properties.
	 *
	 * @var array
	 */
	protected array $meta = array();

	/**
	 * The media attachment description.
	 *
	 * @var string|null
	 */
	protected $description = null;

	/**
	 * The media attachment blurhash.
	 *
	 * @var string
	 */
	protected string $blurhash = '';
}
