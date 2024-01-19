<?php
/**
 * Account handler.
 *
 * This contains the default Account handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * This is the class that implements the default handler for all Account endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Account {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_action( 'mastodon_api_account', array( $this, 'api_account' ), 10, 2 );
	}

	public function api_account( $user_data, $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return $user_data;
		}

		$data = array(
			'id'              => strval( $user->ID ),
			'username'        => $user->user_login,
			'display_name'    => $user->display_name,
			'avatar'          => $avatar,
			'avatar_static'   => $avatar,
			'header'          => $placeholder_image,
			'header_static'   => $placeholder_image,
			'acct'            => $user->user_login,
			'note'            => '',
			'created_at'      => mysql2date( 'Y-m-d\TH:i:s.000P', $user->user_registered, false ),
			'followers_count' => 0,
			'following_count' => 0,
			'statuses_count'  => isset( $posts['status'] ) ? intval( $posts['status'] ) : 0,
			'last_status_at'  => mysql2date( 'Y-m-d\TH:i:s.000P', $user->user_registered, false ),
			'fields'          => array(),
			'locked'          => false,
			'emojis'          => array(),
			'url'             => get_author_posts_url( $user->ID ),
			'source'          => array(
				'privacy'   => 'public',
				'sensitive' => false,
				'language'  => self::get_mastodon_language( get_user_locale( $user->ID ) ),
				'note'      => '',
				'fields'    => array(),
			),
			'bot'             => false,
			'discoverable'    => true,
		);

		return $data;
	}
}
