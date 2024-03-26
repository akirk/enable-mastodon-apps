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
	public string $uri = '';

	/**
	 * The title of the instance.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * A short description of the instance.
	 *
	 * @var string
	 */
	public string $short_description = '';

	/**
	 * Description of the instance.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * An email that may be contacted for any inquiries.
	 *
	 * @var string
	 */
	public string $email = '';

	/**
	 * The version of Mastodon installed on the instance.
	 *
	 * @var string
	 */
	public string $version = '';

	/**
	 * URLs of interest for clients apps.
	 *
	 * @var array[]
	 */
	public array $urls = array();

	/**
	 * Statistics about how much information the instance contains.
	 *
	 * @var array[]
	 */
	public array $stats = array();

	/**
	 * An image used to represent this instance.
	 *
	 * @var string
	 */
	public string $thumbnail = '';

	/**
	 * Primary languages of the website and its staff.
	 *
	 * @var string[]
	 */
	public array $languages = array();

	/**
	 * Whether registrations are enabled.
	 *
	 * @var bool
	 */
	public bool $registrations = false;

	/**
	 * Whether registrations require moderator approval.
	 *
	 * @var bool
	 */
	public bool $approval_required = false;

	/**
	 * Whether invites are enabled.
	 *
	 * @var bool
	 */
	public bool $invites_enabled = false;

	/**
	 * Configured values and limits for this website.
	 *
	 * @var array[]
	 */
	public array $configuration = array();

	/**
	 * A user that can be contacted, as an alternative to `email`.
	 *
	 * @var Account|null
	 */
	public $contact_account = null;

	/**
	 * An itemized list of rules for this website.
	 *
	 * @var Rule[]
	 */
	public array $rules = array();
}
