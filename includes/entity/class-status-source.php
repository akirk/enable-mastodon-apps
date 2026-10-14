<?php
/**
 * Status entity.
 *
 * This contains the Status entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Status entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Status_Source extends Entity {
	protected $types = array(
		'id'           => 'string',
		'spoiler_text' => 'string',
		'text'         => 'string',
	);

	/**
	 * The status ID.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * The plain text used to compose the status’s subject or content warning.
	 *
	 * @var string
	 */
	public string $spoiler_text = '';

	/**
	 * The plain text used to compose the status
	 *
	 * @var string
	 */
	public string $text;
}
