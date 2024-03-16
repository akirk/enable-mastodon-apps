<?php
/**
 * Notification handler.
 *
 * This contains the default Notification handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Entity\Notification as Notification_Entity;

/**
 * This is the class that implements the default handler for all Notification endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Notification {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {

		add_action( 'mastodon_api_notification_clear', array( $this, 'notification_clear' ), 20, 1 );
		add_action( 'mastodon_api_notification_dismiss', array( $this, 'notification_dismiss' ), 20, 1 );

		add_filter( 'mastodon_api_notification_get', array( $this, 'notification_get' ), 20, 2 );
		add_filter( 'mastodon_api_notifications_get', array( $this, 'notifications_get' ), 20, 2 );
	}

	/**
	 * Handle action calls to get dismiss all notifications.
	 *
	 * @param object $request Request object from WP.
	 *
	 * @return void
	 */
	public function notification_clear( object $request ): void {

		$notification_dismissed_tag = apply_filters( 'mastodon_api_notification_dismissed_tag', 'notification-dismissed' );
		$notifications              = $this->fetch_notifications( $request );
		foreach ( $notifications as $notification ) {
			if ( $notification['status'] ) {
				wp_set_object_terms( $notification['status']['id'], $notification_dismissed_tag, 'post_tag', true );
			}
		}
	}

	/**
	 * Handle action calls to get dismiss one notification.
	 *
	 * @param object $request Request object from WP.
	 *
	 * @return void
	 */
	public function notification_dismiss( object $request ): void {
		$notification_dismissed_tag = apply_filters( 'mastodon_api_notification_dismissed_tag', 'notification-dismissed' );
		$notifications              = $this->fetch_notifications( $request );
		foreach ( $notifications as $notification ) {
			if ( $request->get_param( 'id' ) !== $notification['id'] ) {
				continue;
			}
			if ( $notification['status'] ) {
				wp_set_object_terms( $notification['status']['id'], $notification_dismissed_tag, 'post_tag', true );
			}
		}
	}

	/**
	 * Handle filter calls to get one notification.
	 *
	 * @param mixed  $notification Notification to return after working with it.
	 * @param object $request Request object from WP.
	 *
	 * @return mixed
	 */
	public function notification_get( $notification, object $request ): object {
		$notifications = $this->fetch_notifications( $request );
		foreach ( $notifications as $notification ) {
			if ( $request->get_param( 'id' ) !== $notification['id'] ) {
				continue;
			}

			return $notification;
		}

		return new WP_Error( 'notification_not_found', __( 'Notification not found.', 'enable-mastodon-apps' ) );
	}

	/**
	 * Handle filter calls to get all notifications.
	 *
	 * @param array  $notifications Notification to return after working with it.
	 * @param object $request Request object from WP.
	 *
	 * @return array
	 */
	public function notifications_get( array $notifications, object $request ): array {
		return $this->fetch_notifications( $request );
	}

	/**
	 * Helper to get all notifications.
	 *
	 * @param object $request Request object from WP.
	 *
	 * @return array
	 */
	private function fetch_notifications( object $request ): array {
		$limit         = $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 15;
		$notifications = array();
		$types         = $request->get_param( 'types' );
		$args          = array(
			'posts_per_page' => $limit + 2,
		);
		$exclude_types = $request->get_param( 'exclude_types' );
		if ( ( ! is_array( $types ) || in_array( 'mention', $types, true ) ) && ( ! is_array( $exclude_types ) || ! in_array( 'mention', $exclude_types, true ) ) ) {
			$external_user = apply_filters( 'mastodon_api_external_mentions_user', null );
			if ( ! $external_user || ! ( $external_user instanceof \WP_User ) ) {
				return array();
			}
			$args                   = $this->get_posts_query_args( $request );
			$args['posts_per_page'] = $limit + 2;
			$args['author']         = $external_user->ID;
			if ( class_exists( '\Friends\User' ) ) {
				if (
					$external_user instanceof \Friends\User
					&& method_exists( $external_user, 'modify_get_posts_args_by_author' )
				) {
					$args = $external_user->modify_get_posts_args_by_author( $args );
				}
			}

			$notification_dismissed_tag = get_term_by( 'slug', apply_filters( 'mastodon_api_notification_dismissed_tag', 'notification-dismissed' ), 'post_tag' );
			if ( $notification_dismissed_tag ) {
				$args['tag__not_in'] = array( $notification_dismissed_tag->term_id );
			}
			foreach ( get_posts( $args ) as $post ) {
				$meta = get_post_meta( $post->ID, 'activitypub', true );
				if ( ! $meta ) {
					continue;
				}
				$user_id = $post->post_author;
				if ( class_exists( '\Friends\User' ) && $post instanceof \WP_Post ) {
					$user    = \Friends\User::get_post_author( $post );
					$user_id = $user->ID;
				}
				// @TODO: fix use of get_friend_account_data and get_status_array
				$notifications[] = $this->get_notification_array( 'mention', mysql2date( 'Y-m-d\TH:i:s.000P', $post->post_date, false ), $this->get_friend_account_data( $user_id, $meta ), $this->get_status_array( $post ) );
			}
		}

		$min_id      = $request->get_param( 'min_id' );
		$max_id      = $request->get_param( 'max_id' );
		$since_id    = $request->get_param( 'since_id' );
		$next_min_id = false;

		$last_modified = $request->get_header( 'if-modified-since' );
		if ( $last_modified ) {
			$last_modified = gmdate( 'Y-m-d\TH:i:s.000P', strtotime( $last_modified ) );
			if ( $last_modified > $max_id ) {
				$max_id = $last_modified;
			}
		}

		$ret = array();
		$c   = $limit;
		foreach ( $notifications as $notification ) {
			if ( $max_id ) {
				if ( strval( $notification['id'] ) >= strval( $max_id ) ) {
					continue;
				}
				$max_id = null;
			}
			if ( false === $next_min_id ) {
				$next_min_id = $notification['id'];
			}
			if ( $min_id && strval( $min_id ) >= strval( $notification['id'] ) ) {
				break;
			}
			if ( $since_id && strval( $since_id ) > strval( $notification['id'] ) ) {
				break;
			}
			if ( $c-- <= 0 ) {
				break;
			}
			$ret[] = $notification;
		}

		if ( ! empty( $ret ) ) {
			if ( $next_min_id ) {
				header( 'Link: <' . add_query_arg( 'min_id', $next_min_id, home_url( '/api/v1/notifications' ) ) . '>; rel="prev"', false );
			}
			header( 'Link: <' . add_query_arg( 'max_id', $ret[ count( $ret ) - 1 ]['id'], home_url( '/api/v1/notifications' ) ) . '>; rel="next"', false );
		}

		return $ret;
	}

	/**
	 * Helper to get notifications as array based on parameter.
	 *
	 * @param string $type                                  Type of notification.
	 * @param mixed $date                                   Date for limit.
	 * @param \Enable_Mastodon_Apps\Entity\Account $account Attached account for notifications.
	 * @param array $status                                 Status of notifications.
	 *
	 * @return array
	 */
	private function get_notification_array( string $type, $date, \Enable_Mastodon_Apps\Entity\Account $account, array $status = array() ): array {
		$notification = array(
			'id'         => preg_replace( '/[^0-9]/', '', $date ),
			'created_at' => $date,
		);
		switch ( $type ) {
			// As per https://docs.joinmastodon.org/entities/Notification/.
			case 'mention': // Someone mentioned you in their status.
			case 'status': // Someone you enabled notifications for has posted a status.
			case 'reblog': // Someone boosted one of your statuses.
			case 'follow': // Someone followed you.
			case 'follow_request': // Someone requested to follow you.
			case 'favourite': // Someone favourited one of your statuses.
			case 'poll': // A poll you have voted in or created has ended.
			case 'update': // A status you interacted with has been edited.
				$notification['type'] = $type;
				break;
			default:
				return array();
		}

		if ( $account ) {
			$notification['account'] = $account;
		}

		if ( $status ) {
			$notification['status'] = $status;
			$notification['id'] .= $status['id'];
		}

		return $notification;
	}
}
