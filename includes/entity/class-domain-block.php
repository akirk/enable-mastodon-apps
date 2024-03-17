<?php
/**
 * Domain Block entity.
 *
 * This contains the Domain Block entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * Represents a domain that is blocked by the instance.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Domain_Block extends Entity {
	/**
	 * The types of the properties.
	 *
	 * @var array
	 */
	protected $_types = array(
		'domain'   => 'string',
		'digest'   => 'string',
		'severity' => 'string',
		'comment'  => 'string',
	);

	/**
	 * The domain that is blocked.
	 *
	 * @var string
	 */
	public string $domain = '';

	/**
	 * The SHA256 hash of the domain that is blocked.
	 *
	 * @var string
	 */
	public string $digest = '';

	/**
	 * The severity of the domain block.
	 *
	 * @var string
	 */
	public string $severity = '';

	/**
	 * An optional reason for the domain block.
	 *
	 * @var string
	 */
	public string $comment = '';
}
