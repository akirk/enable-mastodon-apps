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
	protected $_types = array(
		'id'                     => 'string',
		'created_at'             => 'DateTime',
		'spoiler_text'           => 'string',
		'visibility'             => 'string',
		'uri'                    => 'string',
		'content'                => 'string',

		'url'                    => 'string??',
		'language'               => 'string??',
		'in_reply_to_id'         => 'string??',
		'in_reply_to_account_id' => 'string??',
		'text'                   => 'string??',
		'edited_at'              => 'DateTime??',

		'sensitive'              => 'bool',
		'favourited'             => 'bool?',
		'reblogged'              => 'bool?',
		'muted'                  => 'bool?',
		'bookmarked'             => 'bool?',
		'pinned'                 => 'bool?',

		'filtered'               => 'array?',
		'media_attachments'      => 'array',
		'mentions'               => 'array',
		'tags'                   => 'array',
		'emojis'                 => 'array',

		'replies_count'          => 'int',
		'reblogs_count'          => 'int',
		'favourites_count'       => 'int',

		'card'                   => 'Preview_Card??',

		'poll'                   => 'Poll??',

		'account'                => 'Account',

		'reblog'                 => 'Status??',

		'application'            => 'Application?',
	);

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
	public $created_at;

	/**
	 * When the status was edited.
	 *
	 * @var null|string
	 */
	public $edited_at = null;

	/**
	 * Text to be shown as a warning or subject before the actual content.
	 *
	 * @var string
	 */
	public string $spoiler_text = '';

	/**
	 * The visibility of the status.
	 *
	 * @var string
	 */
	public string $visibility = 'public';

	/**
	 * ISO 639 language code for this status.
	 *
	 * @var null|string
	 */
	public ?string $language = null;

	/**
	 * The URI to this status.
	 *
	 * @var string
	 */
	public string $uri;

	/**
	 * The URL to this status.
	 *
	 * @var null|string
	 */
	public ?string $url = null;

	/**
	 * The HTML-encoded content of this status.
	 *
	 * @var string
	 */
	public string $content;

	/**
	 * The plain-text content of this status.
	 *
	 * @var null|string
	 */
	public ?string $text = null;

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
	 * The filter and keywords that matched this status.
	 * TODO: implement as list of FilterResult objects
	 *
	 * @var array
	 */
	public array $filtered = array();

	/**
	 * List of media attachments of this status.
	 *
	 * @var array
	 */
	public array $media_attachments = array();

	/**
	 * List of mentions.
	 * TODO: implement as list of Status_Mention objects
	 *
	 * @var array
	 */
	public array $mentions = array();

	/**
	 * List of tags of this status.
	 * TODO: implement as list of Status_Tag objects
	 *
	 * @var array
	 */
	public array $tags = array();

	/**
	 * List of emojis.
	 * TODO: implement as list of CustomEmoji objects
	 *
	 * @var array
	 */
	public array $emojis = array();

	/**
	 * The amount of replies to this status.
	 *
	 * @var int
	 */
	public int $replies_count = 0;

	/**
	 * The amount of reblogs of this status.
	 *
	 * @var int
	 */
	public int $reblogs_count = 0;

	/**
	 * The amount of favorites for this status.
	 *
	 * @var int
	 */
	public int $favourites_count = 0;

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

	public function __get( $k ) {
		if ( 'content' === $k ) {
			return $this->normalize_whitespace( $this->content );
		}

		return parent::__get( $k );
	}

	/**
	 * Strip empty whitespace
	 *
	 * @param      string $post_content  The post content.
	 *
	 * @return     string  The normalized content.
	 */
	protected function normalize_whitespace( $post_content ) {
		$post_content = \strip_shortcodes( $post_content );
		$post_content = \do_blocks( $post_content );
		$post_content = \wptexturize( $post_content );
		$post_content = \convert_smilies( $post_content );
		$post_content = \wp_filter_content_tags( $post_content, 'template' );
		$post_content = \str_replace( ']]>', ']]&gt;', $post_content );
		$post_content = \preg_replace( '/[\n\r\t]/', '', $post_content );

		return trim( $post_content );
	}
}
