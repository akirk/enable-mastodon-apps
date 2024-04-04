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
class Instance extends Entity {
	/**
	 * The types of each property.
	 *
	 * @var array
	 */
	protected $_types = array(
		'domain'        => 'string',
		'title'         => 'string',
		'version'       => 'string',
		'source_url'    => 'string',
		'description'   => 'string',
		'usage'         => 'array',
		'thumbnail'     => 'array',
		'languages'     => 'array',
		'configuration' => 'array',
		'registrations' => 'array',
		'contact'       => 'array',
		'rules'         => 'array',
	);

	/**
	 * The domain name of the instance.
	 *
	 * @var string
	 */
	protected string $domain = '';

	/**
	 * The title of the instance.
	 *
	 * @var string
	 */
	protected string $title = '';

	/**
	 * The version of Mastodon installed on the instance.
	 *
	 * @var string
	 */
	protected string $version = '';

	/**
	 * The URL for the source code of the software running on this instance, in keeping with AGPL license requirements.
	 *
	 * @var string
	 */
	protected string $source_url = '';

	/**
	 * Description of the instance.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Usage data for this instance.
	 *
	 * @var array[]
	 */
	protected array $usage = array();

	/**
	 * An image used to represent this instance.
	 *
	 * @var array[]
	 */
	protected array $thumbnail = array();

	/**
	 * Primary languages of the website and its staff.
	 *
	 * @var string[]
	 */
	protected array $languages = array();

	/**
	 * Configured values and limits for this website.
	 *
	 * @var array[]
	 */
	protected array $configuration = array();

	/**
	 * Information about registering for this website.
	 *
	 * @var array[]
	 */
	protected array $registrations = array();

	/**
	 * Hints related to contacting a representative of the website.
	 *
	 * @var array
	 */
	protected array $contact = array();

	/**
	 * An itemized list of rules for this website.
	 *
	 * @var Rule[]
	 */
	protected array $rules = array();
}
