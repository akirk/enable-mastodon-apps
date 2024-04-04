<?php
/**
 * Search entity.
 *
 * This contains the Search entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Search entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Search extends Entity {
	protected $_types = array(
		'accounts' => 'array',
		'statuses' => 'array',
		'hashtags' => 'array',
	);

	/**
	 * Accounts as search result.
	 *
	 * @var Account[]
	 */
	protected array $accounts;

	/**
	 * Statuses as search result.
	 *
	 * @var Status[]
	 */
	protected array $statuses;

	/**
	 * Tags as search results.
	 *
	 * @var Tag[]
	 */
	protected array $hashtags;
}
