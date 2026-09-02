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
		add_filter( 'mastodon_api_account_id', array( $this, 'api_account_id' ), 10, 2 );
		add_filter( 'mastodon_api_account', array( $this, 'api_account_ema' ), 10, 4 );
		add_filter( 'mastodon_api_account', array( $this, 'api_account_media_overrides' ), 20, 2 );
		add_filter( 'mastodon_api_account', array( get_called_class(), 'api_account_ensure_numeric_id' ), 100, 2 );
		add_filter( 'mastodon_api_account', array( get_called_class(), 'api_account_mastodon_defaults' ), 110, 2 );
	}

	public function api_account_ema( $account, $user_id, $request = null, $post = null ) {
		if ( is_null( $post ) || ! is_object( $post ) || \Enable_Mastodon_Apps\Mastodon_API::ANNOUNCE_CPT !== $post->post_type ) {
			return $account;
		}
		$user_id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( -30 );
		$ema_php = ENABLE_MASTODON_APPS_PLUGIN_DIR . '/enable-mastodon-apps.php';
		$account                 = new Account_Entity();
		$account->id             = strval( $user_id );
		$account->username       = 'Enable Mastodon Apps';
		$account->display_name   = 'Enable Mastodon Apps';
		$account->avatar         = plugin_dir_url( $ema_php ) . 'logo-256x256.png';
		$account->avatar_static  = plugin_dir_url( $ema_php ) . 'logo-256x256.png';
		$account->acct           = 'ema@kirk.at';
		$account->note           = 'Enable Mastodon Apps plugin';
		$account->created_at     = new \DateTime( '@' . filemtime( $ema_php ) );
		$account->statuses_count = 0;
		$account->last_status_at = new \DateTime( $post->post_date_gmt );
		$account->url            = 'https://wordpress.org/plugins/enable-mastodon-apps/';

		$account->source = array(
			'privacy'   => 'public',
			'sensitive' => false,
			'language'  => 'en',
			'note'      => 'Enable Mastodon Apps plugin',
			'fields'    => array(),
		);

		return $account;
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
		// The author archive is also what the ActivityPub plugin serves the actor from.
		$account->uri = $account->url;

		$account->source = array(
			'privacy'               => 'public',
			'sensitive'             => false,
			'language'              => get_user_locale( $user->ID ),
			'note'                  => $note,
			'fields'                => array(),
			'follow_requests_count' => 0,
		);

		return $account;
	}

	public function api_account_media_overrides( $account, $user_id ) {
		if ( ! is_object( $account ) || ! is_numeric( $user_id ) || $user_id <= 0 ) {
			return $account;
		}

		$avatar = get_user_meta( $user_id, 'mastodon_api_avatar', true );
		if ( is_array( $avatar ) && ! empty( $avatar['full'] ) ) {
			$account->avatar        = $avatar['full'];
			$account->avatar_static = $avatar['full'];
		}

		$header_id = absint( get_user_meta( $user_id, 'mastodon_api_header_id', true ) );
		if ( $header_id ) {
			$header_url = wp_get_attachment_url( $header_id );
			if ( $header_url ) {
				$account->header        = $header_url;
				$account->header_static = $header_url;
			}
		}

		return $account;
	}

	public static function api_account_ensure_numeric_id( $user_data, $user_id ) {
		if ( ! is_object( $user_data ) ) {
			return $user_data;
		}
		if ( ! isset( $user_data->header ) ) {
			$user_data->header = '';
		}
		if ( ! isset( $user_data->header_static ) ) {
			$user_data->header_static = $user_data->header;
		}
		if ( ! is_numeric( $user_data->id ) ) {
			$user_data->id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $user_data->id );
		}

		return $user_data;
	}

	/**
	 * Fill in the parts of the account entity that Mastodon always sends.
	 *
	 * Accounts can come from integrations through the `mastodon_api_account` filter, so
	 * this runs last and only supplies what is missing. Clients deserialize these into
	 * typed models, and a missing key is not the same as an empty one for them.
	 *
	 * @param mixed $user_data The account.
	 * @param int   $user_id   The user id.
	 * @return mixed The account.
	 */
	public static function api_account_mastodon_defaults( $user_data, $user_id ) {
		if ( ! is_object( $user_data ) ) {
			return $user_data;
		}

		if ( empty( $user_data->uri ) && ! empty( $user_data->url ) ) {
			$user_data->uri = $user_data->url;
		}

		if ( is_array( $user_data->source ) && ! isset( $user_data->source['follow_requests_count'] ) ) {
			$user_data->source['follow_requests_count'] = 0;
		}

		// Mastodon always states whether a profile field has been verified.
		$user_data->fields = self::add_field_verification( $user_data->fields );
		if ( is_array( $user_data->source ) && isset( $user_data->source['fields'] ) ) {
			$user_data->source['fields'] = self::add_field_verification( $user_data->source['fields'] );
		}

		return $user_data;
	}

	/**
	 * Add the `verified_at` key to profile fields that don't have it.
	 *
	 * @param mixed $fields The profile fields.
	 * @return mixed The profile fields.
	 */
	private static function add_field_verification( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( $fields as $i => $field ) {
			if ( is_array( $field ) && ! array_key_exists( 'verified_at', $field ) ) {
				$fields[ $i ]['verified_at'] = null;
			}
		}

		return $fields;
	}

	public static function api_account_id( $user_id, $post_id ) {
		if ( ! $user_id ) {
			$user_id = get_post_field( 'post_author', $post_id );
		}
		if ( ! is_numeric( $user_id ) ) {
			$user_id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $user_id );
		}

		return $user_id;
	}
}
