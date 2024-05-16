<?php
/**
 * Generic handler.
 *
 * This contains the default Account handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Mastodon_API;
use Enable_Mastodon_Apps\Mastodon_App;

/**
 * This is the generic handler to provide needed helper functions.
 */
class Handler {
	protected function get_posts_query_args( $args, $request ) {
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 20;
		}

		$args['posts_per_page']   = $limit;
		$args['post_type']        = array( 'post', Mastodon_API::CPT );
		$args['suppress_filters'] = false;
		$args['post_status']      = array( 'publish', 'private' );

		$pinned = $request->get_param( 'pinned' );
		if ( $pinned || 'true' === $pinned ) {
			$args['pinned'] = true;
			$args['post__in'] = get_option( 'sticky_posts' );
			if ( empty( $args['post__in'] ) ) {
				// No pinned posts, we need to find nothing.
				$args['post__in'] = array( -1 );
			}
		}

		$app = Mastodon_App::get_current_app();
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
				$args['order'] = 'ASC';
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
				error_log( print_r( $status, true ) );
				continue;
			}

			if ( isset( $args['exclude_replies'] ) && $args['exclude_replies'] ) {
				if ( $status->in_reply_to_id ) {
					continue;
				}
			}

			if ( ! $status->is_valid() ) {
				error_log( wp_json_encode( compact( 'status', 'post' ) ) );
				continue;
			}

			$statuses[ $status->id ] = $status;
		}

		krsort( $statuses );

		$response = new \WP_REST_Response( array_values( $statuses ) );
		if ( ! empty( $statuses ) ) {
			$response->add_link( 'next', remove_query_arg( 'min_id', add_query_arg( 'max_id', end( $statuses )->id, home_url( $_SERVER['REQUEST_URI'] ) ) ) );
			$response->add_link( 'prev', remove_query_arg( 'max_id', add_query_arg( 'min_id', reset( $statuses )->id, home_url( $_SERVER['REQUEST_URI'] ) ) ) );
		}
		return $response;
	}
}
