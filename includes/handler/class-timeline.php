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
class Timeline {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_account', array( $this, 'api_account' ), 10, 2 );
	}

	/**
	 * Get the query args for the posts.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return Entity\Status[]|array An array of Status objects.
	 */
	public function api_timelines( $request ) {
		$args = $this->get_posts_query_args( $request );
		if ( empty( $args ) ) {
			return array();
		}
		$args = apply_filters( 'mastodon_api_timelines_args', $args, $request );

		return $this->get_posts( $args, $request->get_param( 'min_id' ), $request->get_param( 'max_id' ) );
	}

	/**
	 * Undocumented function
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return Entity\Status[]|array An array of Status objects.
	 */
	public function api_tag_timelines( $request ) {
		$args = $this->get_posts_query_args( $request );
		$args['tag'] = $request->get_param( 'hashtag' );
		$args = apply_filters( 'mastodon_api_timelines_args', $args, $request );

		return $this->get_posts( $args, $request->get_param( 'min_id' ), $request->get_param( 'max_id' ) );
	}

	/**
	 * Helper function to get the query args for the posts.
	 *
	 * @param array    $args   The query args.
	 * @param int|null $min_id The minimum id.
	 * @param int|null $max_id The maximum id.
	 *
	 * @return array An array of Status objects.
	 */
	private function get_posts( $args, $min_id = null, $max_id = null ) {
		if ( $min_id ) {
			$min_filter_handler = function ( $where ) use ( $min_id ) {
				global $wpdb;
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $min_id );
			};
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

		$statuses = array();
		foreach ( $posts as $post ) {
			$status = $this->get_status_array( $post );
			if ( $status ) {
				$statuses[ $post->post_date ] = $status;
			}
		}

		if ( ! isset( $args['pinned'] ) || ! $args['pinned'] ) {
			// Comments cannot be pinned for now.
			$comments = get_comments(
				array(
					'meta_key'   => 'protocol',
					'meta_value' => 'activitypub',
				)
			);

			foreach ( $comments as $comment ) {
				$status = $this->get_comment_status_array( $comment );
				if ( $status ) {
					$statuses[ $comment->comment_date ] = $status;
				}
			}
		}
		ksort( $statuses );

		if ( $min_id ) {
			$min_id = strval( $min_id );
			$min_id_exists_in_statuses = false;
			foreach ( $statuses as $status ) {
				if ( $status['id'] === $min_id ) {
					$min_id_exists_in_statuses = true;
					break;
				}
			}
			if ( ! $min_id_exists_in_statuses ) {
				// We don't need to watch the min_id.
				$min_id = null;
			}
		}

		$ret = array();
		$c = $args['posts_per_page'];
		$next_max_id = false;
		foreach ( $statuses as $status ) {
			if ( false === $next_max_id ) {
				$next_max_id = $status['id'];
			}
			if ( $min_id ) {
				if ( $status['id'] !== $min_id ) {
					continue;
				}
				// We can now include results but need to skip this one.
				$min_id = null;
				continue;
			}
			if ( $max_id && strval( $max_id ) === $status['id'] ) {
				break;
			}
			if ( $c-- <= 0 ) {
				break;
			}
			array_unshift( $ret, $status );
		}

		if ( ! empty( $ret ) ) {
			if ( $next_max_id ) {
				header( 'Link: <' . add_query_arg( 'max_id', $next_max_id, home_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ) ) . '>; rel="next"', false );
			}
			header( 'Link: <' . add_query_arg( 'min_id', $ret[0]['id'], home_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ) ) . '>; rel="prev"', false );
		}

		return $ret;
	}

	private function get_comment_status_array( \WP_Comment $comment ) {
		if ( ! $comment ) {
			return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Record not found', array( 'status' => 404 ) );
		}

		$post = (object) array(
			'ID'           => $this->remap_comment_id( $comment->comment_ID ),
			'guid'         => $comment->guid . '#comment-' . $comment->comment_ID,
			'post_author'  => $comment->user_id,
			'post_content' => $comment->comment_content,
			'post_date'    => $comment->comment_date,
			'post_status'  => $comment->post_status,
			'post_type'    => $comment->post_type,
			'post_title'   => '',
		);

		return $this->get_status_array(
			$post,
			array(
				'in_reply_to_id' => $comment->comment_post_ID,
			)
		);
	}
}
