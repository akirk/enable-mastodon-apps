<?php
/**
 * Account handler.
 *
 * This contains the default Account handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Handler\Handler;
use Enable_Mastodon_Apps\Entity\Account as Account_Entity;

/**
 * This is the class that implements the default handler for all Account endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Account extends Handler {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_account', array( $this, 'api_account' ), 10, 2 );
		add_filter( 'mastodon_api_account', array( $this, 'api_account_ensure_numeric_id' ), 100, 2 );
	}

	public function api_account( $user_data, $user_id ) {
		if ( $user_data instanceof Account_Entity ) {
			return $user_data;
		}
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return $user_data;
		}

		$note                    = get_user_meta( $user->ID, 'description', true );
		$account                 = new Account_Entity();
		$account->id             = strval( $user->ID );
		$account->username       = $user->user_login;
		$account->display_name   = $user->display_name;
		$account->avatar         = get_avatar_url( $user->ID );
		$account->avatar_static  = get_avatar_url( $user->ID );
		$account->acct           = $user->user_login;
		$account->note           = wpautop( $note );
		$account->created_at     = new \DateTime( $user->user_registered );
		$account->statuses_count = count_user_posts( $user->ID, 'post', true );
		$account->last_status_at = new \DateTime( $user->user_registered );
		$account->url            = get_author_posts_url( $user->ID );

		$account->source = array(
			'privacy'   => 'public',
			'sensitive' => false,
			'language'  => get_user_locale( $user->ID ),
			'note'      => $note,
			'fields'    => array(),
		);

		return $account;
	}

	public function api_account_ensure_numeric_id( $user_data, $user_id ) {
		if ( ! is_object( $user_data ) ) {
			return $user_data;
		}
		if ( ! is_numeric( $user_data->id ) ) {
			$user_data->id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $user_data->id );
		}

		return $user_data;
	}
}
