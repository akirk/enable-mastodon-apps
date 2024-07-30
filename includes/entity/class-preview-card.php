<?php
/**
 * Preview Card entity.
 *
 * This contains the Preview Card entity.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * This is the class that implements the Preview Card entity.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Preview_Card extends Entity {
	protected $types = array(
		'url'           => 'string',
		'title'         => 'string',
		'description'   => 'string',
		'type'          => 'string',
		'author_name'   => 'string',
		'author_url'    => 'string',
		'provider_name' => 'string',
		'provider_url'  => 'string',
		'html'          => 'string',
		'embed_url'     => 'string',

		'blurhash'      => 'string?',
		'image'         => 'string?',

		'width'         => 'int',
		'height'        => 'int',
	);

	/**
	 * Location of linked resource.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * Title of linked resource.
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * Description of preview.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * The type of the preview card.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * The author of the original resource.
	 *
	 * @var string
	 */
	public string $author_name;

	/**
	 * A link to the author of the original resource.
	 *
	 * @var string
	 */
	public string $author_url;

	/**
	 * The provider of the original resource.
	 *
	 * @var string
	 */
	public string $provider_name;

	/**
	 * A link to the provider of the original resource.
	 *
	 * @var string
	 */
	public string $provider_url;

	/**
	 * HTML to be used for generating the preview card.
	 *
	 * @var string
	 */
	public string $html;

	/**
	 * Used for photo embeds, instead of custom html.
	 *
	 * @var string
	 */
	public string $embed_url;

	/**
	 * A hash for generating colorful preview thumbnails when media has not been
	 * downloaded yet.
	 *
	 * @var null|string
	 */
	public ?string $blurhash = null;

	/**
	 * Preview thumbnail.
	 *
	 * @var null|string
	 */
	public ?string $image = null;

	/**
	 * Width of preview, in pixels.
	 *
	 * @var int
	 */
	public int $width;

	/**
	 * Height of preview, in pixels.
	 *
	 * @var int
	 */
	public int $height;
}
