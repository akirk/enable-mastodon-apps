<?php
/**
 * Scheduled Status entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * Represents a status that will be published at a future date.
 */
class Scheduled_Status extends Entity {
	protected $types = array(
		'id'                => 'string',
		'scheduled_at'      => 'DateTime',
		'params'            => 'array',
		'media_attachments' => 'array[Media_Attachment?]',
	);

	/**
	 * The scheduled status ID.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * When the status will be published.
	 *
	 * @var \DateTime
	 */
	public $scheduled_at;

	/**
	 * The parameters submitted when scheduling the status.
	 *
	 * @var array
	 */
	public array $params = array();

	/**
	 * Media that will be attached to the status.
	 *
	 * @var array
	 */
	public array $media_attachments = array();
}
