<?php
/**
 * Status handler.
 *
 * This contains the default Status handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Handler\Handler;
use Enable_Mastodon_Apps\Entity\Status as Status_Entity;
use WP_REST_Response;

/**
 * This is the class that implements the default handler for all Status endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Status extends Handler {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_status', array( $this, 'api_status' ), 10, 3 );
		add_filter( 'mastodon_api_status', array( $this, 'api_status_account_ensure_numeric_id' ), 100 );
		add_filter( 'mastodon_api_account_statuses_args', array( $this, 'mastodon_api_account_statuses_args' ), 10, 2 );
		add_filter( 'mastodon_api_statuses', array( $this, 'api_statuses' ), 10, 4 );
	}

	/**
	 * Get media attachments from blocks. They will be formatted as ActivityPub attachments, not as WP attachments.
	 * Copied almost verbatim from the ActivityPub plugin.
	 *
	 * @param \WP_Post $post The post to get the attachments from.
	 * @param int      $max_media The maximum number of attachments to return.
	 *
	 * @return array The attachments.
	 */
	protected function get_block_attachments( \WP_Post $post, int $max_media = 10 ): array {
		$media_ids = array();

		if ( \function_exists( 'has_post_thumbnail' ) && \has_post_thumbnail( $post->ID ) ) {
			$media_ids[] = \get_post_thumbnail_id( $post->ID );
		}

		$blocks = \parse_blocks( $post->post_content );
		return self::get_media_ids_from_blocks( $blocks, $media_ids, $max_media );
	}
	/**
	 * Recursively get media IDs from blocks.
	 * Copied almost verbatim from the ActivityPub plugin.
	 *
	 * @param array $blocks The blocks to search for media IDs.
	 * @param array $media_ids The media IDs to append new IDs to.
	 * @param int   $max_media The maximum number of media to return.
	 *
	 * @return array The image IDs.
	 */
	protected function get_media_ids_from_blocks( array $blocks, array $media_ids, int $max_media ): array {

		foreach ( $blocks as $block ) {
			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$media_ids = self::get_media_ids_from_blocks( $block['innerBlocks'], $media_ids, $max_media );
			}

			switch ( $block['blockName'] ) {
				case 'core/image':
				case 'core/cover':
				case 'core/audio':
				case 'core/video':
				case 'videopress/video':
					if ( ! empty( $block['attrs']['id'] ) ) {
						$media_ids[] = $block['attrs']['id'];
					}
					break;
				case 'jetpack/slideshow':
				case 'jetpack/tiled-gallery':
					if ( ! empty( $block['attrs']['ids'] ) ) {
						$media_ids = array_merge( $media_ids, $block['attrs']['ids'] );
					}
					break;
				case 'jetpack/image-compare':
					if ( ! empty( $block['attrs']['beforeImageId'] ) ) {
						$media_ids[] = $block['attrs']['beforeImageId'];
					}
					if ( ! empty( $block['attrs']['afterImageId'] ) ) {
						$media_ids[] = $block['attrs']['afterImageId'];
					}
					break;
			}

			// Depupe.
			$media_ids = \array_unique( $media_ids );

			// Stop doing unneeded work.
			if ( count( $media_ids ) >= $max_media ) {
				break;
			}
		}

		// Still need to slice it because one gallery could knock us over the limit.
		return array_slice( $media_ids, 0, $max_media );
	}

	/**
	 * Get a status array.
	 *
	 * @param Status_Entity $status Current status array.
	 * @param int           $object_id The object ID to get the status from.
	 * @param array         $data Additional status data.
	 * @return Status_Entity The status entity
	 */
	public function api_status( ?Status_Entity $status, int $object_id, array $data = array() ): ?Status_Entity {
		if ( $status instanceof Status_Entity ) {
			return $status;
		}

		$post = get_post( $object_id );

		if ( $post instanceof \WP_Post ) {
			// Documented in class-mastodon-api.php.
			$account = apply_filters( 'mastodon_api_account', null, $post->post_author, null, $post );

			if ( ! ( $account instanceof \Enable_Mastodon_Apps\Entity\Account ) ) {
				return $status;
			}
			$status = new Status_Entity();
			$status->id = strval( $post->ID );
			$status->created_at = new \DateTime( $post->post_date );
			$status->visibility = 'public';
			$status->uri = get_permalink( $post->ID );
			$status->content = $post->post_content;
			$status->account = $account;
			$media_attachments = $this->get_block_attachments( $post );
			foreach ( $media_attachments as $media_id ) {
				$media_attachment = apply_filters( 'mastodon_api_media_attachment', null, $media_id );
				if ( $media_attachment ) {
					$status->media_attachments[] = $media_attachment;
				}
			}
		}

		return $status;
	}

	/**
	 * Get a list of statuses.
	 *
	 * @param array|null $statuses Current statuses.
	 * @param array      $args Current statuses arguments.
	 * @param int|null   $min_id Optional minimum status ID.
	 * @param int|null   $max_id Optional maximum status ID.
	 * @return array
	 */
	public function api_statuses( ?array $statuses, array $args, ?int $min_id = null, ?int $max_id = null ): \WP_REST_Response {
		if ( $statuses ) {
			if ( ! $statuses instanceof \WP_REST_Response ) {
				$statuses = new \WP_REST_Response( array_values( $statuses ) );
			}
			return $statuses;
		}
		return $this->get_posts( $args, $min_id, $max_id );
	}

	public function mastodon_api_account_statuses_args( $args, $request ) {
		return $this->get_posts_query_args( $args, $request );
	}


	public function api_status_account_ensure_numeric_id( $status ) {
		if ( isset( $status->account->id ) && ! is_numeric( $status->account->id ) ) {
			$status->account->id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $status->account->id );
		}
		if ( isset( $status->reblog->account->id ) && ! is_numeric( $status->reblog->account->id ) ) {
			$status->reblog->account->id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $status->reblog->account->id );
		}
		return $status;
	}
}
