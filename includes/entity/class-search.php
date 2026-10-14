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
	protected $types = array(
		'accounts' => 'array',
		'statuses' => 'array',
		'hashtags' => 'array',
	);

	/**
	 * Accounts as search result.
	 *
	 * @var Account[]
	 */
	public array $accounts;

	/**
	 * Statuses as search result.
	 *
	 * @var Status[]
	 */
	public array $statuses;

	/**
	 * Tags as search results.
	 *
	 * @var Tag[]
	 */
	public array $hashtags;
}
