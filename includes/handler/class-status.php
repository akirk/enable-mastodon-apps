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
use Enable_Mastodon_Apps\Comment_CPT;
use Enable_Mastodon_Apps\Mastodon_API;
use Enable_Mastodon_Apps\Mastodon_App;
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
		add_filter( 'mastodon_api_status', array( $this, 'api_status' ), 10, 2 );
		add_filter( 'mastodon_api_status', array( $this, 'api_status_ensure_numeric_id' ), 100 );
		add_filter( 'mastodon_api_account_statuses_args', array( $this, 'mastodon_api_account_statuses_args' ), 10, 2 );
		add_filter( 'mastodon_api_statuses', array( $this, 'api_statuses' ), 10, 4 );
		add_filter( 'mastodon_api_statuses', array( $this, 'api_statuses_ensure_numeric_id' ), 100 );
		add_filter( 'mastodon_api_submit_status', array( $this, 'api_submit_comment' ), 10, 7 );
		add_filter( 'mastodon_api_submit_status', array( $this, 'api_submit_post' ), 15, 7 );
		add_filter( 'mastodon_api_edit_status', array( $this, 'api_edit_comment' ), 10, 8 );
		add_filter( 'mastodon_api_edit_status', array( $this, 'api_edit_post' ), 15, 8 );
		add_filter( 'mastodon_api_status_context', array( $this, 'api_status_context' ), 10, 2 );
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
						$media_ids[ $block['attrs']['id'] ] = $block['innerHTML'];
					}
					break;
				case 'jetpack/slideshow':
				case 'jetpack/tiled-gallery':
					if ( ! empty( $block['attrs']['ids'] ) ) {
						foreach ( $block['attrs']['ids'] as $id ) {
							$media_ids[ $id ] = $block['innerHTML'];
						}
					}
					break;
				case 'jetpack/image-compare':
					if ( ! empty( $block['attrs']['beforeImageId'] ) ) {
						$media_ids[ $block['attrs']['beforeImageId'] ] = $block['innerHTML'];
					}
					if ( ! empty( $block['attrs']['afterImageId'] ) ) {
						$media_ids[ $block['attrs']['afterImageId'] ] = $block['innerHTML'];
					}
					break;
			}

			if ( count( $media_ids ) >= $max_media ) {
				break;
			}
		}

		// Still need to slice it because one gallery could knock us over the limit.
		return array_slice( $media_ids, 0, $max_media, true );
	}

	/**
	 * Get a status array.
	 *
	 * @param Status_Entity $status Current status array.
	 * @param int           $object_id The object ID to get the status from.
	 * @return Status_Entity The status entity
	 */
	public function api_status( ?Status_Entity $status, int $object_id ): ?Status_Entity {
		if ( $status instanceof Status_Entity ) {
			return $status;
		}

		$post = get_post( $object_id );

		if ( $post && Mastodon_API::ANNOUNCE_CPT === $post->post_type ) {
			$meta = get_post_meta( $post->ID, 'ema_app_id', true );
			$app = Mastodon_App::get_current_app();
			if ( $meta && ( ! $app || $app->get_client_id() !== $meta ) ) {
				return null;
			}

			// translators: %s: settings page URL.
			$post->post_content = trim( $post->post_content ) .
				'<br>' . PHP_EOL . '<br>' . PHP_EOL .
				// translators: %s: settings page URL.
				sprintf( __( 'This message has been added by the EMA plugin. You can disable these messages <a href=%s>in the settings</a>.', 'enable-mastodon-apps' ), '"' . esc_url( admin_url( 'options-general.php?page=enable-mastodon-apps&tab=settings' ) ) . '"' );
			if ( $app ) {
				// translators: %s: settings page URL.
				$post->post_content .= ' ' . sprintf( __( 'Change the <a href=%s>settings for this app here</a>.', 'enable-mastodon-apps' ), '"' . esc_url( admin_url( 'options-general.php?page=enable-mastodon-apps&app=' . $app->get_client_id() ) ) . '"' );
			}
		}

		if ( $post instanceof \WP_Post ) {
			// Documented in class-mastodon-api.php.
			$account = apply_filters( 'mastodon_api_account', null, $post->post_author, null, $post );

			if ( ! ( $account instanceof \Enable_Mastodon_Apps\Entity\Account ) ) {
				return $status;
			}
			$status             = new Status_Entity();
			$status->id         = strval( $post->ID );
			$status->created_at = new \DateTime( $post->post_date_gmt, new \DateTimeZone( 'UTC' ) );
			$status->visibility = 'public';
			if ( strpos( $post->post_type, 'ema-dm-' ) === 0 ) {
				$status->visibility = 'direct';
			}
			$status->uri        = get_the_guid( $post->ID );
			$status->content    = $post->post_content;
			if ( ! empty( $post->post_title ) && trim( $post->post_title ) ) {
				$status->content = '<strong>' . esc_html( $post->post_title ) . '</strong>' . PHP_EOL . $status->content;
			}
			$status->account    = $account;
			$media_attachments  = $this->get_block_attachments( $post );
			foreach ( $media_attachments as $media_id => $html ) {
				$media_attachment = apply_filters( 'mastodon_api_media_attachment', null, $media_id );
				if ( $media_attachment ) {
					// We don't want to show the media in the content as they are attachments.
					$status->content             = str_replace( $html, '', $status->content );
					$status->media_attachments[] = $media_attachment;
				}
			}
			$status->content = trim( wp_kses_post( $status->content ) );
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
	 * @return WP_REST_Response The statuses as a REST response.
	 */
	public function api_statuses( ?array $statuses, array $args, ?int $min_id = null, ?int $max_id = null ): WP_REST_Response {
		if ( $statuses ) {
			if ( ! $statuses instanceof WP_REST_Response ) {
				$statuses = new WP_REST_Response( array_values( $statuses ) );
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
		if ( $statuses instanceof WP_REST_Response ) {
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

	public static function convert_to_blocks( $post_content ) {
		$post_content = preg_split( '/(<br>|<br \/>|<\/p>|' . PHP_EOL . ')/i', $post_content );
		$post_content = array_map( 'trim', $post_content );
		$post_content = array_map(
			function ( $el ) {
				$el = preg_replace( '/^<p>/i', '', $el );
				$el = preg_replace( '/<\/p>$/i', '', $el );
				return $el;
			},
			$post_content
		);
		$post_content = array_filter( $post_content );
		if ( empty( $post_content ) ) {
			return '';
		}
		$post_content = '<!-- wp:paragraph -->' . PHP_EOL . '<p>' . implode( '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL . PHP_EOL . '<!-- wp:paragraph -->' . PHP_EOL . '<p>', $post_content ) . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->';
		return $post_content;
	}

	public function prepare_post_data( $post_id, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at ) {
		$post_data = array();

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
		}
		$app = Mastodon_App::get_current_app();

		/**
		 * Allow modifying the status text before it gets posted.
		 *
		 * @param string $status The user submitted status text.
		 * @param int|null $in_reply_to_id The ID of the post to reply to.
		 * @param string $visibility The visibility of the post.
		 * @return string The potentially modified status text.
		 */
		$status_text = apply_filters( 'mastodon_api_submit_status_text', $status_text, $in_reply_to_id, $visibility );

		$post_data['post_content'] = make_clickable( $status_text );
		$post_data['post_type']    = $app->get_create_post_type();

		switch ( $visibility ) {
			case 'public':
				$post_data['post_status'] = 'publish';
				break;

			case 'direct':
				$post_data['post_status'] = 'ema_unread';
				$post_data['post_type'] = Mastodon_API::get_dm_cpt();
				break;

			default:
				$post_data['post_status'] = 'private';
				break;
		}

		$post_data['post_title'] = '';

		if ( ! $post_format || 'standard' === $post_format ) {
			$post_content_parts = preg_split( '/(<br>|<br \/>|<\/p>|' . PHP_EOL . ')/i', $status_text, 2 );
			if ( count( $post_content_parts ) === 2 ) {
				$post_data['post_title']   = wp_strip_all_tags( $post_content_parts[0] );
				$post_data['post_content'] = trim( $post_content_parts[1] );
			}
		}

		if ( ! $app->get_disable_blocks() ) {
			$post_data['post_content'] = self::convert_to_blocks( $post_data['post_content'] );
		}

		if ( $in_reply_to_id ) {
			$post_data['post_parent'] = $in_reply_to_id;
		}

		if ( $scheduled_at ) {
			$post_data['post_status'] = 'future';
			$post_data['post_date']   = $scheduled_at;
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
				if ( \wp_attachment_is( 'image', $media_id ) ) {
					$post_data['post_content'] .= PHP_EOL;
					$meta_json                  = array(
						'id'       => intval( $media_id ),
						'sizeSlug' => 'large',
					);
					$post_data['post_content'] .= '<!-- wp:image ' . wp_json_encode( $meta_json ) . ' -->' . PHP_EOL;
					$post_data['post_content'] .= '<figure class="wp-block-image"><img src="' . esc_url( wp_get_attachment_url( $media_id ) ) . '" alt="" class="wp-image-' . esc_attr( $media_id ) . '"/></figure>' . PHP_EOL;
					$post_data['post_content'] .= '<!-- /wp:image -->' . PHP_EOL;
				} elseif ( \wp_attachment_is( 'video', $media_id ) ) {
					$post_data['post_content'] .= PHP_EOL;
					$post_data['post_content'] .= '<!-- wp:video ' . wp_json_encode( array( 'id' => $media_id ) ) . '  -->' . PHP_EOL;
					$post_data['post_content'] .= '<figure class="wp-block-video"><video controls src="' . esc_url( wp_get_attachment_url( $media_id ) ) . '" width="' . esc_attr( $attachment['width'] ) . '" height="' . esc_attr( $attachment['height'] ) . '" /></figure>' . PHP_EOL;
					$post_data['post_content'] .= '<!-- /wp:video -->' . PHP_EOL;
				}
			}
		}

		return $post_data;
	}

	public function api_submit_post( $status, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at ) {
		if (
			$status instanceof \WP_Error // An error was thrown in an earlier hook.
			|| $status instanceof Status_Entity // A status was already saved in an earlier hook.
		) {
			return $status;
		}

		$mentions = array();
		if ( 'direct' === $visibility ) {
			preg_match_all( '/@(?:[a-zA-Z0-9_@.-]+)/', $status_text, $matches );
			foreach ( $matches[0] as $match ) {
				$user = get_user_by( 'login', ltrim( $match, '@' ) );
				if ( $user ) {
					$mentions[] = $user->ID;
				}
			}

			if ( empty( $mentions ) ) {
				return new \WP_Error( 'mastodon_' . __FUNCTION__, 'No mentions found', array( 'status' => 400 ) );
			}
		}

		$post_data = $this->prepare_post_data( null, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at );

		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( 'direct' === $visibility ) {
			$dm_post_ids = array( $post_data['post_type'] => $post_id );

			if ( $post_data['post_parent'] ) {
				$dm_ids = get_post_meta( $post_data['post_parent'], 'ema_dm_ids', true );
			}

			foreach ( $mentions as $mention_user_id ) {
				$post_data['post_type'] = Mastodon_API::get_dm_cpt( $mention_user_id );
				if ( isset( $dm_ids[ $post_data['post_type'] ] ) ) {
					$post_data['post_parent'] = $dm_ids[ $post_data['post_type'] ];
				} else {
					unset( $post_data['post_parent'] );
				}
				$dm_id = wp_insert_post( $post_data );
				if ( is_wp_error( $dm_id ) ) {
					return $dm_id;
				}

				$dm_post_ids[ $post_data['post_type'] ] = $dm_id;
			}

			foreach ( $dm_post_ids as $dm_post_id ) {
				if ( $post_format && 'standard' !== $post_format ) {
					set_post_format( $dm_post_id, $post_format );
				}
				update_post_meta( $dm_post_id, 'ema_dm_ids', $dm_post_ids );
			}
		} elseif ( $post_format && 'standard' !== $post_format ) {
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

	public function api_edit_post( $status, $post_id, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at ) {
		if ( $status instanceof \WP_Error || $status instanceof Status_Entity ) {
			return $status;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $status;
		}

		$post_data = $this->prepare_post_data( $post_id, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at );

		$post_id = wp_update_post( $post_data );
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
		if (
			$status instanceof \WP_Error // An error was thrown in an earlier hook.
			|| $status instanceof Status_Entity // A status was already saved in an earlier hook.
			|| ! $in_reply_to_id // A non-reply should be a post.
			|| 'direct' === $visibility // But a reply that's a DM should also be a post.
		) {
			return $status;
		}
		$comment_data = array();
		$comment_data['comment_content']  = $status_text;
		$comment_data['comment_parent']   = 0;

		$parent_comment = get_comment( $in_reply_to_id );
		if ( $parent_comment ) {
			$comment_data['comment_post_ID'] = $parent_comment->comment_post_ID;
			$comment_data['comment_parent'] = $in_reply_to_id;
		} else {
			$comment_data['comment_post_ID'] = $in_reply_to_id;
		}

		$comment_data['comment_author']   = '';
		$comment_data['user_id']          = get_current_user_id();
		$comment_data['comment_approved'] = 1;

		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				$media = get_post( $media_id );
				if ( ! $media ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				if ( 'attachment' !== $media->post_type ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				$attachment                       = \wp_get_attachment_metadata( $media_id );
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

	public function api_edit_comment( $status, $comment_id, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at ) {
		if ( $status instanceof \WP_Error || $status instanceof Status_Entity || ! $in_reply_to_id ) {
			return $status;
		}
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return $status;
		}

		$comment_data = array();
		$comment_data['comment_content']  = $status_text;

		$parent_comment = get_comment( $in_reply_to_id );
		if ( $parent_comment ) {
			$comment_data['comment_post_ID'] = $parent_comment->comment_post_ID;
			$comment_data['comment_parent'] = $in_reply_to_id;
		} else {
			$comment_data['comment_post_ID'] = $in_reply_to_id;
		}

		$comment_data['comment_author']   = get_current_user_id();
		$comment_data['user_id']          = get_current_user_id();
		$comment_data['comment_approved'] = 1;

		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				$media = get_post( $media_id );
				if ( ! $media ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				if ( 'attachment' !== $media->post_type ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				$attachment                       = \wp_get_attachment_metadata( $media_id );
				$comment_data['comment_content'] .= PHP_EOL;
				$comment_data['comment_content'] .= '<!-- wp:image -->';
				$comment_data['comment_content'] .= '<p><img src="' . esc_url( wp_get_attachment_url( $media_id ) ) . '" width="' . esc_attr( $attachment['width'] ) . '"  height="' . esc_attr( $attachment['height'] ) . '" class="size-full" /></p>';
				$comment_data['comment_content'] .= '<!-- /wp:image -->';
			}
		}

		$comment_id = wp_update_comment( $comment_data );

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
		$post = get_post( $context_post_id );
		if ( ! $post ) {
			return $context;
		}

		$found = array();
		$post_date = new \DateTime( $post->post_date_gmt, new \DateTimeZone( 'UTC' ) );

		$app = Mastodon_App::get_current_app();
		if ( $app ) {
			$post_types = $app->get_view_post_types();
		} else {
			$post_types = array();
		}

		$post_types[] = \Enable_Mastodon_Apps\Comment_CPT::CPT;
		$post_types = apply_filters( 'mastodon_api_status_context_post_types', $post_types, $context_post_id );
		$post_statuses = apply_filters( 'mastodon_api_status_context_post_statuses', 'any', $context_post_id );

		$checked = array();
		$to_check = array( $context_post_id );
		while ( ! empty( $to_check ) ) {
			$check = array_shift( $to_check );
			if ( isset( $checked[ $check ] ) ) {
				continue;
			}
			$checked[ $check ] = true;
			$ancestors = get_post_ancestors( $check );
			foreach ( $ancestors as $ancestor ) {
				if ( isset( $checked[ $ancestor ] ) ) {
					continue;
				}
				$found[ $ancestor ] = get_post( $ancestor );
				$to_check[] = $ancestor;
			}
			$children = get_posts(
				array(
					'post_parent' => $check,
					'post_type'   => $post_types,
					'post_status' => $post_statuses,
				)
			);
			foreach ( $children as $child ) {
				$to_check[] = $child->ID;
				$found[ $child->ID ] = $child;
			}
		}

		foreach ( array_keys( $found ) as $post_id ) {
			if ( intval( $post_id ) === intval( $context_post_id ) ) {
				continue;
			}
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
				$type = 'descendants';
				if ( $status->created_at < $post_date ) {
					$type = 'ancestors';
				}
				if ( ! isset( $context[ $type ] ) ) {
					$context[ $type ] = array();
				}
				$context[ $type ][ $status->id ] = $status;
			}
		}

		ksort( $context['ancestors'] );

		ksort( $context['descendants'] );

		$context['ancestors']   = array_values( $context['ancestors'] );
		$context['descendants'] = array_values( $context['descendants'] );
		return $context;
	}
}
