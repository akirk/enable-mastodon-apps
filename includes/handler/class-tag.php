<?php
/**
 * Tag handler.
 *
 * This contains the default Tag handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Handler\Handler;
use Enable_Mastodon_Apps\Entity\Tag as Tag_Entity;

/**
 * This is the class that implements the default handler for Tag endpoints.
 *
 * @since 0.9.0
 *
 * @package Enable_Mastodon_Apps
 */
class Tag extends Handler {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_tag', array( $this, 'api_tag' ), 10, 2 );
	}

	/**
	 * Handle GET /api/v1/tags/:name requests.
	 *
	 * @param Tag_Entity|null  $tag     The tag data.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return Tag_Entity The tag entity.
	 */
	public function api_tag( $tag, $request ) {
		if ( $tag instanceof Tag_Entity ) {
			return $tag;
		}

		$tag_name = strtolower( $request->get_param( 'tag_name' ) );

		$tag           = new Tag_Entity();
		$tag->name     = $tag_name;
		$tag->url      = home_url( '/tag/' . $tag_name );
		$tag->history  = array();
		$tag->following = false;

		// Check if a matching WordPress tag exists and use its URL.
		$wp_tag = get_term_by( 'slug', $tag_name, 'post_tag' );
		if ( $wp_tag ) {
			$tag->url  = get_tag_link( $wp_tag );
			$tag->name = $wp_tag->name;
		}

		return $tag;
	}
}
