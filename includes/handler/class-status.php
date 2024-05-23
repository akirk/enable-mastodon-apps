<?php
/**
 * Status handler.
 *
 * This contains the default Status handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Entity\Entity;
use Enable_Mastodon_Apps\Handler\Handler;
use Enable_Mastodon_Apps\Mastodon_API;
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
		add_filter( 'mastodon_api_status', array( $this, 'api_status_ensure_numeric_id' ), 100 );
		add_filter( 'mastodon_api_comment_parent_post_id', array( Mastodon_API::class, 'maybe_get_remapped_comment_id' ), 30 );
		add_filter( 'mastodon_api_account_statuses_args', array( $this, 'mastodon_api_account_statuses_args' ), 10, 2 );
		add_filter( 'mastodon_api_statuses', array( $this, 'api_statuses' ), 10, 4 );
		add_filter( 'mastodon_api_statuses', array( $this, 'api_statuses_ensure_numeric_id' ), 100 );
		add_filter( 'mastodon_api_submit_status', array( $this, 'api_submit_comment' ), 10, 7 );
		add_filter( 'mastodon_api_submit_status', array( $this, 'api_submit_post' ), 15, 7 );
		add_filter( 'mastodon_api_status_context', array( $this, 'api_status_context' ), 10, 3 );
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

		if ( isset( $data['comment'] ) && $data['comment'] instanceof \WP_Comment ) {
			$comment = $data['comment'];
			$account = apply_filters( 'mastodon_api_account', null, $comment->user_id, null, $comment );
			if ( ! ( $account instanceof \Enable_Mastodon_Apps\Entity\Account ) ) {
				return $status;
			}
			$status = new Status_Entity();
			$status->id = strval( Mastodon_API::remap_comment_id( $comment->comment_ID ) );
			$status->created_at = new \DateTime( $comment->comment_date );
			$status->visibility = 'public';
			$status->uri = get_comment_link( $comment );
			$status->content = $comment->comment_content;
			$status->account = $account;
			if ( $comment->comment_parent ) {
				$status->in_reply_to_id = strval( Mastodon_API::remap_comment_id( $comment->comment_parent ) );
			} else {
				$status->in_reply_to_id = strval( $comment->comment_post_ID );
			}
		} elseif ( $post instanceof \WP_Post ) {
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

	public function api_statuses_ensure_numeric_id( $statuses ) {
		$response = null;
		if ( $statuses instanceof \WP_REST_Response ) {
			$response = $statuses;
			$statuses = $response->data;
		}
		if ( ! is_array( $statuses ) ) {
			return $statuses;
		}

		foreach ( $statuses as $k => $status ) {
			$statuses[ $k ] = $this->api_status_ensure_numeric_id( $status );
		}

		if ( $response ) {
			$response->data = $statuses;
			return $response;
		}

		return $statuses;
	}

	public function api_status_ensure_numeric_id( $status ) {
		if ( ! $status instanceof Status_Entity ) {
			return $status;
		}

		if ( isset( $status->id ) && ! is_numeric( $status->id ) ) {
			$status->id = \Enable_Mastodon_Apps\Mastodon_API::remap_url( $status->id );
		}

		if ( isset( $status->account->id ) && ! is_numeric( $status->account->id ) ) {
			$status->account->id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $status->account->id );
		}

		if ( isset( $status->reblog->account->id ) && ! is_numeric( $status->reblog->account->id ) ) {
			$status->reblog->account->id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $status->reblog->account->id );
		}

		foreach ( $status->media_attachments as $media_attachment ) {
			if ( isset( $media_attachment->id ) && ! is_numeric( $media_attachment->id ) ) {
				$media_attachment->id = \Enable_Mastodon_Apps\Mastodon_API::remap_url( $media_attachment->id );
			}
		}

		return $status;
	}

	public function api_submit_post( $status, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at ) {
		if ( $status instanceof \WP_Error || $status instanceof Status_Entity ) {
			return $status;
		}
		$post_data = array();

		$post_data['post_content'] = $status_text;
		$post_data['post_status']  = 'public' === $visibility ? 'publish' : 'private';
		$post_data['post_type']    = 'post';
		$post_data['post_title']   = '';

		if ( 'standard' === $post_format ) {
			// Use the first line of a post as the post title if we're using a standard post format.
			list( $post_title, $post_content ) = explode( PHP_EOL, $post_data['post_content'], 2 );
			$post_data['post_title']   = $post_title;
			$post_data['post_content'] = trim( $post_content );
		}

		if ( $in_reply_to_id ) {
			$post_data['post_parent'] = $in_reply_to_id;
		}

		if ( $scheduled_at ) {
			$post_data['post_status'] = 'future';
			$post_data['post_date'] = $scheduled_at;
		}

		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				$media = get_post( $media_id );
				if ( ! $media ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				if ( 'attachment' !== $media->post_type ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				$attachment = \wp_get_attachment_metadata( $media_id );
				$post_data['post_content'] .= PHP_EOL;
				$post_data['post_content'] .= '<!-- wp:image -->';
				$post_data['post_content'] .= '<p><img src="' . esc_url( wp_get_attachment_url( $media_id ) ) . '" width="' . esc_attr( $attachment['width'] ) . '"  height="' . esc_attr( $attachment['height'] ) . '" class="size-full" /></p>';
				$post_data['post_content'] .= '<!-- /wp:image -->';
			}
		}

		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( 'standard' !== $post_format ) {
			set_post_format( $post_id, $post_format );
		}

		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				wp_update_post(
					array(
						'ID'          => $media_id,
						'post_parent' => $post_id,
					)
				);
			}
		}

		if ( $scheduled_at ) {
			return array(
				'id'           => $post_id,
				'scheduled_at' => $scheduled_at,
				'params'       => array(
					'text'         => $status,
					'visibility'   => $visibility,
					'scheduled_at' => $scheduled_at,
				),
			);
		}

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

		return $status;
	}

	public function api_submit_comment( $status, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at ) {
		if ( $status instanceof \WP_Error || $status instanceof Status_Entity || ! $in_reply_to_id ) {
			return $status;
		}
		$comment_data = array();

		/**
		 * Get the post id that is being responded to.
		 *
		 * @param int $in_reply_to_id The post ID that is being responded to.
		 * @param string $status_text The status text.
		 * @return int The post ID that is being responded to.
		 */
		$parent_post_id = apply_filters( 'mastodon_api_comment_parent_post_id', $in_reply_to_id, $status_text );

		$comment_data['comment_content'] = $status_text;
		$comment_data['comment_post_ID'] = $parent_post_id;
		$comment_data['comment_parent']  = 0;
		$comment_data['comment_author'] = get_current_user_id();
		$comment_data['user_id'] = get_current_user_id();
		$comment_data['comment_approved'] = 1;

		$parent_comment_id = Mastodon_API::maybe_get_remapped_comment_id( $in_reply_to_id );
		if ( intval( $parent_comment_id ) !== intval( $in_reply_to_id ) ) {
			$parent_comment = get_comment( $parent_comment_id );
			if ( $parent_comment ) {
				$comment_data['comment_parent'] = $parent_comment_id;
			}
		}

		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				$media = get_post( $media_id );
				if ( ! $media ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				if ( 'attachment' !== $media->post_type ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				$attachment = \wp_get_attachment_metadata( $media_id );
				$comment_data['comment_content'] .= PHP_EOL;
				$comment_data['comment_content'] .= '<!-- wp:image -->';
				$comment_data['comment_content'] .= '<p><img src="' . esc_url( wp_get_attachment_url( $media_id ) ) . '" width="' . esc_attr( $attachment['width'] ) . '"  height="' . esc_attr( $attachment['height'] ) . '" class="size-full" /></p>';
				$comment_data['comment_content'] .= '<!-- /wp:image -->';
			}
		}

		$comment_id = wp_insert_comment( $comment_data );

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters(
			'mastodon_api_status',
			null,
			$in_reply_to_id,
			array(
				'comment' => get_comment( $comment_id ),
			)
		);

		return $status;
	}

	public function api_status_context( $context, $context_post_id ) {
		foreach ( get_post_ancestors( $context_post_id ) as $post_id ) {
			$args = array();

			/**
			 * Modify the status data.
			 *
			 * @param array|null $account The status data.
			 * @param int $post_id The object ID to get the status from.
			 * @param array $data Additional status data.
			 * @return array|null The modified status data.
			 */
			$status = apply_filters(
				'mastodon_api_status',
				null,
				$post_id,
				$args
			);
			if ( $status ) {
				$context['ancestors'][ $status->id ] = $status;
			}
		}

		$children = get_children(
			array(
				'post_parent' => $context_post_id,
				'post_type'   => 'any',
			)
		);
		foreach ( $children as $post ) {
			$post = get_post( $post->post_parent );
			$post_id = $post->ID;
			$args = array();
			/**
			 * Modify the status data.
			 *
			 * @param array|null $account The status data.
			 * @param int $post_id The object ID to get the status from.
			 * @param array $data Additional status data.
			 * @return array|null The modified status data.
			 */
			$status = apply_filters(
				'mastodon_api_status',
				null,
				$post_id,
				$args
			);
			if ( $status ) {
				$context['descendants'][ $status->id ] = $status;
			}
		}

		ksort( $context['ancestors'] );

		ksort( $context['descendants'] );

		$context['ancestors'] = array_values( $context['ancestors'] );
		$context['descendants'] = array_values( $context['descendants'] );
		return $context;
	}
}
