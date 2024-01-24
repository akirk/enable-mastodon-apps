<?php
/**
 * Account handler.
 *
 * This contains the default Account handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Entity\Account as Account_Entity;

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
		add_filter( 'mastodon_api_account', array( $this, 'api_account' ), 10, 2 );
	}

	public function api_account( $user_data, $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return $user_data;
		}

		$account = new Account_Entity();

		$account->id             = strval( $user->ID );
		$account->username       = $user->user_login;
		$account->display_name   = $user->display_name;
		$account->avatar         = $avatar;
		$account->avatar_static  = $avatar;
		$account->header         = $placeholder_image;
		$account->header_static  = $placeholder_image;
		$account->acct           = $user->user_login;
		$account->note           = '';
		$account->created_at     = mysql2date( 'Y-m-d\TH:i:s.000P', $user->user_registered, false );
		$account->statuses_count = isset( $posts['status'] ) ? intval( $posts['status'] ) : 0;
		$account->last_status_at = mysql2date( 'Y-m-d\TH:i:s.000P', $user->user_registered, false );
		$account->url            = get_author_posts_url( $user->ID );
		$account->source         = array(
			'privacy'   => 'public',
			'sensitive' => false,
			'language'  => self::get_mastodon_language( get_user_locale( $user->ID ) ),
			'note'      => '',
			'fields'    => array(),
		);

		return $account;
	}
}
