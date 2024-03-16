<?php
/**
 * Poll entity.
 *
 * This contains the Poll entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Poll entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Poll extends Entity {
	protected $_types = array(
		'id'           => 'string',

		'expires_at'   => 'string?',

		'expired'      => 'bool',
		'multiple'     => 'bool',
		'voted'        => 'bool',

		'votes_count'  => 'int',

		'voters_count' => 'int?',

		'options'      => 'array',
		'emojis'       => 'array',
		'own_votes'    => 'array',
	);

	/**
	 * The ID of the poll in the database
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * When the poll ends.
	 *
	 * @var null|string
	 */
	public ?string $expires_at;

	/**
	 * Is the poll currently expired?
	 *
	 * @var bool
	 */
	public bool $expired;

	/**
	 * Does the poll allow multiple-choice answers?
	 *
	 * @var bool
	 */
	public bool $multiple;

	/**
	 * How many votes have been received.
	 *
	 * @var int
	 */
	public int $votes_count;

	/**
	 * How many unique accounts have voted on a multiple-choice poll.
	 * TODO: validate whether null is only returned if $multiple is false
	 *
	 * @var null|int
	 */
	public ?int $voters_count = 0;

	/**
	 * Possible answers for the poll.
	 * TODO: implement as list of Poll_Option objects
	 *
	 * @var array
	 */
	public array $options;

	/**
	 * Custom emoji to be used for rendering poll options.
	 * TODO: implement as list of CustomEmoji objects
	 *
	 * @var array
	 */
	public array $emojis;

	/**
	 * When called with a user token, has the authorized user voted?
	 *
	 * @var bool
	 */
	public bool $voted = false;

	/**
	 * When called with a user token, which options has the authorized user chosen?
	 * TODO: validate if all items are integers
	 *
	 * @var int[]
	 */
	public array $own_votes = array();
}
