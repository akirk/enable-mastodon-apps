<?php
/**
 * Extended Description entity.
 *
 * This contains the Extended Description entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * Represents an extended description for the instance, to be shown on its about page.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Extended_Description extends Entity {
	/**
	 * The types of the properties.
	 *
	 * @var array
	 */
	protected $types = array(
		'updated_at' => 'string',
		'content'    => 'string',
	);

	/**
	 * A timestamp of when the extended description was last updated.
	 *
	 * @var string
	 */
	public string $updated_at = '';

	/**
	 * The extended description for the instance.
	 *
	 * @var string
	 */
	public string $content = '';
}
