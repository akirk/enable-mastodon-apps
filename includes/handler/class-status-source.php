<?php
/**
 * Status Source handler.
 *
 * This contains the default Status Source handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Entity\Status_Source as Status_Source_Entity;
use Enable_Mastodon_Apps\Mastodon_API;

/**
 * This is the class that implements the default handler for all Status Source endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Status_Source extends Status {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_status_source', array( $this, 'api_status_source' ), 10, 3 );
	}

	/**
	 * Get a status source array.
	 *
	 * @param Status_Source_Entity $status_source Current status source array.
	 * @param int                  $object_id The object ID to get the status from.
	 * @return Status_Source_Entity The status source entity
	 */
	public function api_status_source( ?Status_Source_Entity $status_source, int $object_id ): ?Status_Source_Entity {
		if ( $status_source instanceof Status_Source_Entity ) {
			return $status_source;
		}
		$post = get_post( $object_id );
		if ( ! $post ) {
			$comment = get_comment( $object_id );
			if ( isset( $comment ) && $comment instanceof \WP_Comment ) {
				$status_source = new Status_Source_Entity();
				$status_source->id = strval( Mastodon_API::remap_comment_id( $comment->comment_ID ) );
				$status_source->text = trim( wp_strip_all_tags( $comment->comment_content ) );
			}

			return $status_source;
		}

		if ( $post instanceof \WP_Post ) {
			$status_source = new Status_Source_Entity();
			$status_source->id = strval( $post->ID );
			$status_source->text = trim( wp_strip_all_tags( $post->post_title . PHP_EOL . $post->post_content ) );
		}

		return $status_source;
	}
}
