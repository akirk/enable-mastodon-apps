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
		add_filter( 'mastodon_api_notification', array( $this, 'api_notification' ), 10, 2 );
	}

	public function api_notification( $user_data, $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return $user_data;
		}

		$notification = new Notification_Entity();
		$notification->id             = strval( $user->ID );
		$notification->username       = $user->user_login;
		$notification->display_name   = $user->display_name;
		$notification->avatar         = get_avatar_url( $user->ID );
		$notification->avatar_static  = get_avatar_url( $user->ID );
		$notification->acct           = $user->user_login;
		$notification->note           = get_user_meta( $user->ID, 'description', true );
		$notification->created_at     = new \DateTime( $user->user_registered );
		$notification->statuses_count = count_user_posts( $user->ID, 'post', true );
		$notification->last_status_at = new \DateTime( $user->user_registered );
		$notification->url            = get_author_posts_url( $user->ID );

		$notification->source = array(
			'privacy'   => 'public',
			'sensitive' => false,
			'language'  => get_user_locale( $user->ID ),
			'note'      => '',
			'fields'    => array(),
		);

		return $notification;
	}
}
