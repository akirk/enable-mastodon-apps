<?php
/**
 * Timeline handler.
 *
 * This contains the default Account handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use WP_REST_Request;
use Enable_Mastodon_Apps\Entity\Timeline as Timeline_Entity;

/**
 * This is the class that implements the default handler for all Timeline endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Timeline extends Handler {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_timelines', array( $this, 'api_timelines' ), 10, 2 );
		add_filter( 'mastodon_api_tag_timeline', array( $this, 'api_tag_timeline' ), 10, 2 );
		add_filter( 'mastodon_api_public_timeline', array( $this, 'api_public_timeline' ), 10, 2 );
	}

	/**
	 * Handle timeline requests.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return Entity\Status[]|array An array of Status objects.
	 */
	public function api_timelines( $statuses, $request ) {
		$args = $this->get_posts_query_args( $request );
		if ( empty( $args ) ) {
			return array();
		}
		$args = apply_filters( 'mastodon_api_timelines_args', $args, $request );

		return $this->get_posts( $args, $request->get_param( 'min_id' ), $request->get_param( 'max_id' ) );
	}

	/**
	 * Handle tag timeline requests.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return Entity\Status[]|array An array of Status objects.
	 */
	public function api_tag_timelines( $statuses, $request ) {
		$args = $this->get_posts_query_args( $request );
		$args['tag'] = $request->get_param( 'hashtag' );
		$args = apply_filters( 'mastodon_api_timelines_args', $args, $request );

		return $this->get_posts( $args, $request->get_param( 'min_id' ), $request->get_param( 'max_id' ) );
	}

	/**
	 * Handle public simeline requests
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return Entity\Status[]|array An array of Status objects.
	 */
	public function api_public_timeline( $statuses, $request ) {
		$args = $this->get_posts_query_args( $request );
		if ( empty( $args ) ) {
			return array();
		}

		// Only get the published posts for the public timeline.
		$args['post_status'] = array( 'publish' );

		$args = apply_filters( 'mastodon_api_timelines_args', $args, $request );

		return $this->get_posts( $args, $request->get_param( 'min_id' ), $request->get_param( 'max_id' ) );
	}
}
