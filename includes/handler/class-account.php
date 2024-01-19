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
		add_action( 'mastodon_api_account', array( $this, 'api_account' ) );
	}

	public function api_account( $user_id ) {
		$data = array(
			'id'              => strval( 1e10 + $remote_user_id ),
			'username'        => '',
			'acct'            => $account,
			'display_name'    => '',
			'locked'          => false,
			'bot'             => false,
			'discoverable'    => true,
			'group'           => false,
			'created_at'      => gmdate( 'Y-m-d\TH:i:s.000P' ),
			'note'            => '',
			'url'             => '',
			'avatar'          => $placeholder_image,
			'avatar_static'   => $placeholder_image,
			'header'          => $placeholder_image,
			'header_static'   => $placeholder_image,
			'followers_count' => 0,
			'following_count' => 0,
			'statuses_count'  => 0,
			'last_status_at'  => gmdate( 'Y-m-d' ),
			'emojis'          => array(),
			'fields'          => array(),
		);
	}
}
