<?php
/**
 * Conversation entity.
 *
 * This contains the Conversation entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Conversation entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Conversation extends Entity {
	protected $types = array(
		'id'          => 'string',
		'unread'      => 'bool',
		'accounts'    => 'array[Account]',
		'last_status' => 'Status??',
	);

	/**
	 * The status ID.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * The unread status.
	 *
	 * @var bool
	 */
	public bool $unread;

	/**
	 * The accounts.
	 *
	 * @var array
	 */
	public array $accounts;

	/**
	 * The last status.
	 *
	 * @var Status
	 */
	public ?Status $last_status;
}
