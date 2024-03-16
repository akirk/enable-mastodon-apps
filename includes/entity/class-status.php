<?php
/**
 * Status entity.
 *
 * This contains the Status entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Status entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Status extends Entity {
	protected $_types = [
		'id'                     => 'string',
		'created_at'             => 'string',
		'spoiler_text'           => 'string',
		'visibility'             => 'string',
		'language'               => 'string',
		'uri'                    => 'string',
		'url'                    => 'string',
		'content'                => 'string',
		'text'                   => 'string',

		'in_reply_to_id'         => 'string?',
		'in_reply_to_account_id' => 'string?',

		'sensitive'              => 'bool',
		'favourited'             => 'bool',
		'reblogged'              => 'bool',
		'muted'                  => 'bool',
		'bookmarked'             => 'bool',
		'pinned'                 => 'bool',
		'filtered'               => 'bool',

		'media_attachments'      => 'array',
		'mentions'               => 'array',
		'tags'                   => 'array',
		'emojis'                 => 'array',

		'replies_count'          => 'int',
		'reblogs_count'          => 'int',
		'favourites_count'       => 'int',

		'card'                   => 'object?',
		'poll'                   => 'object?',

		'account'                => 'Account',

		'reblog'                 => 'Status?',

		'application'            => 'Application?',
	];

	/**
	 * The status ID.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * When the status was created.
	 *
	 * @var string
	 */
	public string $created_at;

	/**
	 * Text to be shown as a warning or subject before the actual content.
	 *
	 * @var string
	 */
	public string $spoiler_text;

	/**
	 * The visibility of the status.
	 *
	 * @var string
	 */
	public string $visibility;

	/**
	 * ISO 639 language code for this status.
	 *
	 * @var string
	 */
	public string $language;

	/**
	 * The URI to this status.
	 *
	 * @var string
	 */
	public string $uri;

	/**
	 * The URL to this status.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * The HTML-encoded content of this status.
	 *
	 * @var string
	 */
	public string $content;

	/**
	 * The plain-text content of this status.
	 *
	 * @var string
	 */
	public string $text;

	/**
	 * The ID of a status this status is a reply to.
	 *
	 * @var null|string
	 */
	public ?string $in_reply_to_id = null;

	/**
	 * The account ID of a status this status is a reply to.
	 *
	 * @var null|string
	 */
	public ?string $in_reply_to_account_id = null;

	/**
	 * Whether this status contains sensitive content.
	 *
	 * @var bool
	 */
	public bool $sensitive = false;

	/**
	 * Whether this status is a favorite.
	 *
	 * @var bool
	 */
	public bool $favourited = false;

	/**
	 * Whether this status has been reblogged.
	 *
	 * @var bool
	 */
	public bool $reblogged = false;

	/**
	 * Whether this status is muted.
	 *
	 * @var bool
	 */
	public bool $muted = false;

	/**
	 * Whether this status has been bookmarked.
	 *
	 * @var bool
	 */
	public bool $bookmarked = false;

	/**
	 * Whether this status has been pinned.
	 *
	 * @var bool
	 */
	public bool $pinned = false;

	/**
	 * Whether this status has been filtered.
	 *
	 * @var bool
	 */
	public bool $filtered = false;

	/**
	 * List of media attachments of this status.
	 *
	 * @var array
	 */
	public array $media_attachments = [];

	/**
	 * List of mentions.
	 * TODO: implement as list of Status_Mention objects
	 *
	 * @var array
	 */
	public array $mentions = [];

	/**
	 * List of tags of this status.
	 * TODO: implement as list of Status_Tag objects
	 *
	 * @var array
	 */
	public array $tags = [];

	/**
	 * List of emojis.
	 * TODO: implement as list of CustomEmoji objects
	 *
	 * @var array
	 */
	public array $emojis = [];

	/**
	 * The amount of replies to this status.
	 *
	 * @var int
	 */
	public int $replies_count;

	/**
	 * The amount of reblogs of this status.
	 *
	 * @var int
	 */
	public int $reblogs_count;

	/**
	 * The amount of favorites for this status.
	 *
	 * @var int
	 */
	public int $favourites_count;

	/**
	 * The account this status belongs to.
	 *
	 * @var Account
	 */
	public Account $account;

	/**
	 * The status object that gets reblogged.
	 *
	 * @var null|Status
	 */
	public ?Status $reblog = null;

	/**
	 * The application of this status.
	 *
	 * @var null|Application
	 */
	public ?Application $application = null;

	/**
	 * Preview card for links included within status content.
	 *
	 * @var null|Preview_Card
	 */
	public ?Preview_Card $card = null;

	/**
	 * The poll attached to the status.
	 *
	 * @var null|Poll
	 */
	public ?Poll $poll = null;
}
