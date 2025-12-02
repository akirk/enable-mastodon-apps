<?php
/**
 * Notification entity.
 *
 * This contains the Notification entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Notification entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Notification extends Entity {
	protected $types = array(
		'id'         => 'string',
		'type'       => 'string',
		'created_at' => 'string',
		'account'    => 'Account',
		'status'     => 'Status?',
	);

	/**
	 * The notification id.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * The notification type.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * The notification timestamp.
	 *
	 * @var string
	 */
	public string $created_at;

	/**
	 * The account connected to notification.
	 *
	 * @var Account
	 */
	public Account $account;

	/**
	 * The status connected to notification (not present for follow notifications).
	 *
	 * @var Status|null
	 */
	public ?Status $status = null;
}
