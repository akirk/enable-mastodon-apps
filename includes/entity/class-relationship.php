<?php
/**
 * Relationship entity.
 *
 * This contains the Relationship entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * Relationship entity.
 *
 * @see https://docs.joinmastodon.org/entities/Relationship/
 */
class Relationship extends Entity {

	/**
	 * The types of the properties.
	 *
	 * @var array
	 */
	protected $_types = array(
		'id'                   => 'string',
		'following'            => 'bool',
		'showing_reblogs'      => 'bool',
		'notifying'            => 'bool',
		'languages'            => 'array',
		'followed_by'          => 'bool',
		'blocking'             => 'bool',
		'blocked_by'           => 'bool',
		'muting'               => 'bool',
		'muting_notifications' => 'bool',
		'requested'            => 'bool',
		'requested_by'         => 'bool',
		'domain_blocking'      => 'bool',
		'endorsed'             => 'bool',
		'note'                 => 'string',
	);

	/**
	 * The relationship id.
	 *
	 * @var string
	 */
	protected string $id = '';

	/**
	 * Whether the user is following the account.
	 *
	 * @var bool
	 */
	protected bool $following = false;

	/**
	 * Whether the user is followed by the account.
	 *
	 * @var bool
	 */
	protected bool $showing_reblogs = false;

	/**
	 * Whether the user has enabled notifications for the account.
	 *
	 * @var bool
	 */
	protected bool $notifying = false;

	/**
	 * The languages the user is following in.
	 *
	 * @var string[] Array of ISO 639-1 language two-letter codes.
	 */
	protected array $languages = array();

	/**
	 * Whether the user is followed by the account.
	 *
	 * @var bool
	 */
	protected bool $followed_by = false;

	/**
	 * Whether the user is blocking the account.
	 *
	 * @var bool
	 */
	protected bool $blocking = false;

	/**
	 * Whether the user is blocked by the account.
	 *
	 * @var bool
	 */
	protected bool $blocked_by = false;

	/**
	 * Whether the user is muting the account.
	 *
	 * @var bool
	 */
	protected bool $muting = false;

	/**
	 * Whether the user is muting the account's notifications.
	 *
	 * @var bool
	 */
	protected bool $muting_notifications = false;

	/**
	 * Whether the user has requested to follow the account.
	 *
	 * @var bool
	 */
	protected bool $requested = false;

	/**
	 * Whether the user has requested to follow the account.
	 *
	 * @var bool
	 */
	protected bool $requested_by = false;

	/**
	 * Whether the user is blocking the account's domain.
	 *
	 * @var bool
	 */
	protected bool $domain_blocking = false;

	/**
	 * Whether the user is endorsing the account.
	 *
	 * @var bool
	 */
	protected bool $endorsed = false;

	/**
	 * This user’s profile bio.
	 *
	 * @var string
	 */
	protected string $note = '';
}
