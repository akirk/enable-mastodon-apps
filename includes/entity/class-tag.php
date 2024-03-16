<?php
/**
 * Tag entity.
 *
 * This contains the Tag entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Tag entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Tag extends Entity {
	protected $_types = array(
		'name' => 'string',
		'url' => 'string',
		'history' => 'array',
		'following' => 'bool',
	);

	/**
	 * Name for tag.
	 *
	 * @var string
	 */
	public string $name;


	/**
	 * URL for tag.
	 *
	 * @var string
	 */
	public string $url;


	/**
	 * History of tag.
	 *
	 * @var array
	 */
	public array $history;


	/**
	 * Following the tag.
	 *
	 * @var bool
	 */
	public bool $following;
}
