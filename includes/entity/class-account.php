<?php
/**
 * Account entity.
 *
 * This contains the Account entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Account entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Account extends Entity {
	protected $_types = array(
		'id'              => 'string',
		'username'        => 'string',
		'acct'            => 'string',
		'url'             => 'string',
		'display_name'    => 'string',
		'note'            => 'string',
		'avatar'          => 'string',
		'avatar_static'   => 'string',
		'header'          => 'string',
		'header_static'   => 'string',

		'locked'          => 'bool',
		'bot'             => 'bool',
		'group'           => 'bool',
		'discoverable'    => 'bool',
		'noindex'         => 'bool',
		'suspended'       => 'bool',
		'limited'         => 'bool',

		'statuses_count'  => 'int',
		'followers_count' => 'int',
		'following_count' => 'int',

		'fields'          => 'array',
		'emojis'          => 'array',

		'moved'           => 'Account',

		'created_at'      => 'DateTime',
		'last_status_at'  => 'DateTime',
	);
	/**
	 * The account id.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * The username of the account, not including domain.
	 *
	 * @var string
	 */
	public $username;

	/**
	 * The Webfinger account URI. Equal to username for local users, or username@domain for remote users.
	 *
	 * @var string
	 */
	public $acct;

	/**
	 * The location of the user’s profile page.
	 *
	 * @var string
	 */
	public $url;

	/**
	 * The profile’s display name.
	 *
	 * @var string
	 */
	public $display_name;

	/**
	 * The profile’s bio or description.
	 *
	 * @var string
	 */
	public $note = '';

	/**
	 * An image icon that is shown next to statuses and in the profile.
	 *
	 * @var string
	 */
	public $avatar = '';

	/**
	 * A static version of the avatar. Equal to avatar if its value is a static image; different if avatar is an animated GIF.
	 *
	 * @var string
	 */
	public $avatar_static = '';

	/**
	 * An image banner that is shown above the profile and in profile cards.
	 *
	 * @var string
	 */
	public $header = '';

	/**
	 * A static version of the header. Equal to header if its value is a static image; different if header is an animated GIF.
	 *
	 * @var string
	 */
	public $header_static = '';

	/**
	 * Whether the account manually approves follow requests.
	 *
	 * @var bool
	 */
	public $locked = false;

	/**
	 * Additional metadata attached to a profile as name-value pairs.
	 *
	 * @var array
	 */
	public $fields = array();

	/**
	 * Custom emoji entities to be used when rendering the profile.
	 *
	 * @var array
	 */
	public $emojis = array();

	/**
	 * Indicates that the account may perform automated actions, may not be monitored, or identifies as a robot.
	 *
	 * @var bool
	 */
	public $bot = false;

	/**
	 * Indicates that the account represents a Group actor.
	 *
	 * @var bool
	 */
	public $group = false;

	/**
	 * Whether the account has opted into discovery features such as the profile directory.
	 *
	 * @var bool
	 */
	public $discoverable = true;

	/**
	 * Whether the local user has opted out of being indexed by search engines.
	 *
	 * @var bool
	 */
	public $noindex = false;

	/**
	 * Indicates that the profile is currently inactive and that its user has moved to a new account.
	 *
	 * @var Account|null
	 */
	public $moved;

	/**
	 * An extra attribute returned only when an account is suspended.
	 *
	 * @var bool
	 */
	public $suspended = false;

	/**
	 * An extra attribute returned only when an account is silenced.
	 * If true, indicates that the account should be hidden behind a warning screen.
	 *
	 * @var bool
	 */
	public $limited = false;

	/**
	 * When the account was created.
	 *
	 * @var string
	 */
	public $created_at;

	/**
	 * When the most recent status was posted.
	 *
	 * @var string|null
	 */
	public $last_status_at;

	/**
	 * How many statuses are attached to this account.
	 *
	 * @var int
	 */
	public $statuses_count = 0;

	/**
	 * The reported followers of this profile.
	 *
	 * @var int
	 */
	public $followers_count = 0;

	/**
	 * The reported follows of this profile.
	 *
	 * @var int
	 */
	public $following_count = 0;
}
