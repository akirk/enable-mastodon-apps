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
	public bool $locked = false;

	/**
	 * Additional metadata attached to a profile as name-value pairs.
	 *
	 * @var array
	 */
	public array $fields = array();

	/**
	 * Custom emoji entities to be used when rendering the profile.
	 *
	 * @var array
	 */
	public array $emojis = array();

	/**
	 * Indicates that the account may perform automated actions, may not be monitored, or identifies as a robot.
	 *
	 * @var bool
	 */
	public bool $bot = false;

	/**
	 * Indicates that the account represents a Group actor.
	 *
	 * @var bool
	 */
	public bool $group = false;

	/**
	 * Whether the account has opted into discovery features such as the profile directory.
	 *
	 * @var bool
	 */
	public bool $discoverable = true;

	/**
	 * Whether the local user has opted out of being indexed by search engines.
	 *
	 * @var bool
	 */
	public bool $noindex = false;

	/**
	 * Indicates that the profile is currently inactive and that its user has moved to a new account.
	 *
	 * @var Account|null
	 */
	public ?Account $moved;

	/**
	 * An extra attribute returned only when an account is suspended.
	 *
	 * @var bool
	 */
	public bool $suspended = false;

	/**
	 * An extra attribute returned only when an account is silenced.
	 * If true, indicates that the account should be hidden behind a warning screen.
	 *
	 * @var bool
	 */
	public bool $limited = false;

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
	public ?string $last_status_at;

	/**
	 * How many statuses are attached to this account.
	 *
	 * @var int
	 */
	public int $statuses_count = 0;

	/**
	 * The reported followers of this profile.
	 *
	 * @var int
	 */
	public int $followers_count = 0;

	/**
	 * The reported follows of this profile.
	 *
	 * @var int
	 */
	public int $following_count = 0;
}
