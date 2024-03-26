<?php
/**
 * Rule entity.
 *
 * This contains the Rule entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * Represents a rule that server users should follow.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Rule extends Entity {
	/**
	 * The types of the properties.
	 *
	 * @var array
	 */
	protected $_types = array(
		'id'   => 'string',
		'text' => 'string',
	);

	/**
	 * An identifier for the rule.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * The rule to be followed.
	 *
	 * @var string
	 */
	public string $text;
}
