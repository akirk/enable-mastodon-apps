<?php
/**
 * Application entity.
 *
 * This contains the Application entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Application entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Application extends Entity {
	protected $types = array(
		'name'          => 'string',
		'client_id'     => 'string',
		'client_secret' => 'string',

		'website'       => 'string?',
	);

	/**
	 * The name of the application.
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * Client ID key, to be used for obtaining OAuth tokens.
	 *
	 * @var string
	 */
	public string $client_id = '';

	/**
	 * Client secret key, to be used for obtaining OAuth tokens.
	 *
	 * @var string
	 */
	public string $client_secret = '';

	/**
	 * The website associated with your application.
	 *
	 * @var null|string
	 */
	public ?string $website = null;
}
