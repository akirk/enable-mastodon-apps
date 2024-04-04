<?php
/**
 * Instance entity.
 *
 * This contains the Instance entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Instance entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Instance_V1 extends Entity {
	/**
	 * The types of each property.
	 *
	 * @var array
	 */
	protected $_types = array(
		'uri'               => 'string',
		'title'             => 'string',
		'short_description' => 'string',
		'description'       => 'string',
		'email'             => 'string',
		'version'           => 'string',
		'stats'             => 'array',
		'thumbnail'         => 'string',
		'languages'         => 'array',
		'registrations'     => 'bool',
		'approval_required' => 'bool',
		'invites_enabled'   => 'bool',
		'configuration'     => 'array',
		'contact_account'   => 'Account?',
		'rules'             => 'array',
	);

	/**
	 * The domain name of the instance.
	 *
	 * @var string
	 */
	protected string $uri = '';

	/**
	 * The title of the instance.
	 *
	 * @var string
	 */
	protected string $title = '';

	/**
	 * A short description of the instance.
	 *
	 * @var string
	 */
	protected string $short_description = '';

	/**
	 * Description of the instance.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * An email that may be contacted for any inquiries.
	 *
	 * @var string
	 */
	protected string $email = '';

	/**
	 * The version of Mastodon installed on the instance.
	 *
	 * @var string
	 */
	protected string $version = '';

	/**
	 * URLs of interest for clients apps.
	 *
	 * @var array[]
	 */
	protected array $urls = array();

	/**
	 * Statistics about how much information the instance contains.
	 *
	 * @var array[]
	 */
	protected array $stats = array();

	/**
	 * An image used to represent this instance.
	 *
	 * @var string
	 */
	protected string $thumbnail = '';

	/**
	 * Primary languages of the website and its staff.
	 *
	 * @var string[]
	 */
	protected array $languages = array();

	/**
	 * Whether registrations are enabled.
	 *
	 * @var bool
	 */
	protected bool $registrations = false;

	/**
	 * Whether registrations require moderator approval.
	 *
	 * @var bool
	 */
	protected bool $approval_required = false;

	/**
	 * Whether invites are enabled.
	 *
	 * @var bool
	 */
	protected bool $invites_enabled = false;

	/**
	 * Configured values and limits for this website.
	 *
	 * @var array[]
	 */
	protected array $configuration = array();

	/**
	 * A user that can be contacted, as an alternative to `email`.
	 *
	 * @var Account|null
	 */
	protected $contact_account = null;

	/**
	 * An itemized list of rules for this website.
	 *
	 * @var Rule[]
	 */
	protected array $rules = array();
}
