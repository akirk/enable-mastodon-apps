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
	protected $_types = array(
		'id'         => 'string',
		'type'       => 'string',
		'created_at' => 'string',
		'account'    => 'Account',
		'status'     => 'Status',
	);

	/**
	 * The notification id.
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * The notification type.
	 *
	 * @var string
	 */
	protected string $type;

	/**
	 * The notification timestamp.
	 *
	 * @var string
	 */
	protected string $created_at;

	/**
	 * The account connected to notification.
	 *
	 * @var Account
	 */
	protected Account $account;

	/**
	 * The notification id.
	 *
	 * @var Status
	 */
	protected Status $status;
}
