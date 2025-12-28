<?php
/**
 * Generic handler.
 *
 * This contains the default Account handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Mastodon_App;
use Enable_Mastodon_Apps\Entity\Status as Status_Entity;

/**
 * This is the generic handler to provide needed helper functions.
 */
class Handler {
	protected function get_posts_query_args( $args, $request ) {
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 20;
		}

		$app = Mastodon_App::get_current_app();

		if ( ! isset( $args['post_type'] ) ) {
			$post_types = array( 'post', 'comment' );
			if ( $app ) {
				$post_types = $app->get_view_post_types();
			}
			$args['post_type'] = $post_types;
		}
		$args['posts_per_page']   = $limit;
		$args['suppress_filters'] = false;
		$args['post_status']      = array( 'publish', 'private' );

		$pinned = $request->get_param( 'pinned' );
		if ( $pinned || 'true' === $pinned ) {
			$args['pinned']   = true;
			$args['post__in'] = get_option( 'sticky_posts' );
			if ( empty( $args['post__in'] ) ) {
				// No pinned posts, we need to find nothing.
				$args['post__in'] = array( -1 );
			}
		}

		if ( $app ) {
			$args = $app->modify_wp_query_args( $args );
		}

		$post_id = $request->get_param( 'post_id' );
		if ( $post_id ) {
			$args['p'] = $post_id;
		}

		$args = apply_filters( 'mastodon_api_get_posts_query_args', $args, $request );
		if ( isset( $args['author'] ) && $args['author'] <= 0 ) {
			// Author could not be found by a plugin.
			return array();
		}
		return $args;
	}

	protected function get_posts( $args, $min_id = null, $max_id = null ): \WP_REST_Response {
		$posts = array();

		// It's possible that the $args are set to something falsy by a filter.
		if ( $args ) {
			if ( $min_id ) {
				$min_filter_handler = function ( $where ) use ( $min_id ) {
					global $wpdb;
					return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $min_id );
				};
				$args['order']      = 'ASC';
				add_filter( 'posts_where', $min_filter_handler );
			}

			if ( $max_id ) {
				$max_filter_handler = function ( $where ) use ( $max_id ) {
					global $wpdb;
					return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", $max_id );
				};
				add_filter( 'posts_where', $max_filter_handler );
			}
			$posts = get_posts( $args );

			if ( $min_id ) {
				remove_filter( 'posts_where', $min_filter_handler );
			}
			if ( $max_id ) {
				remove_filter( 'posts_where', $max_filter_handler );
			}
		}

		$statuses = array();
		foreach ( $posts as $post ) {
			/**
			 * Modify the status data.
			 *
			 * @param array|null $account The status data.
			 * @param int $post_id The object ID to get the status from.
			 * @param array $data Additional status data.
			 * @return array|null The modified status data.
			 */
			$status = apply_filters( 'mastodon_api_status', null, $post->ID, array() );

			if ( ! $status ) {
				continue;
			}

			if ( is_wp_error( $status ) ) {
				error_log( print_r( $status, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
				continue;
			}

			if ( isset( $args['exclude_replies'] ) && $args['exclude_replies'] ) {
				if ( $status->in_reply_to_id ) {
					continue;
				}
			}

			$app = Mastodon_App::get_current_app();
			if ( $app && $app->get_media_only() && empty( $status->media_attachments ) ) {
				continue;
			}

			if ( ! $status->is_valid() ) {
				error_log( wp_json_encode( compact( 'status', 'post' ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				continue;
			}

			$statuses[ $status->id ] = $status;
		}

		usort(
			$statuses,
			function ( $a, $b ) {
				return $b->created_at->getTimestamp() - $a->created_at->getTimestamp();
			}
		);

		$response = new \WP_REST_Response( array_values( $statuses ) );
		if ( ! empty( $statuses ) ) {
			$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$response->add_link( 'next', remove_query_arg( 'min_id', add_query_arg( 'max_id', end( $statuses )->id, home_url( $req_uri ) ) ) );
			$response->add_link( 'prev', remove_query_arg( 'max_id', add_query_arg( 'min_id', reset( $statuses )->id, home_url( $req_uri ) ) ) );
		}
		return $response;
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
}
