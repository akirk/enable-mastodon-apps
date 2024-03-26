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
	public string $domain = '';

	/**
	 * The title of the instance.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * The version of Mastodon installed on the instance.
	 *
	 * @var string
	 */
	public string $version = '';

	/**
	 * The URL for the source code of the software running on this instance, in keeping with AGPL license requirements.
	 *
	 * @var string
	 */
	public string $source_url = '';

	/**
	 * Description of the instance.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * Usage data for this instance.
	 *
	 * @var array[]
	 */
	public array $usage = array();

	/**
	 * An image used to represent this instance.
	 *
	 * @var array[]
	 */
	public array $thumbnail = array();

	/**
	 * Primary languages of the website and its staff.
	 *
	 * @var string[]
	 */
	public array $languages = array();

	/**
	 * Configured values and limits for this website.
	 *
	 * @var array[]
	 */
	public array $configuration = array();

	/**
	 * Information about registering for this website.
	 *
	 * @var array[]
	 */
	public array $registrations = array();

	/**
	 * Hints related to contacting a representative of the website.
	 *
	 * @var array
	 */
	public array $contact = array();

	/**
	 * An itemized list of rules for this website.
	 *
	 * @var Rule[]
	 */
	public array $rules = array();
}
