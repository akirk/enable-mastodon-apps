<?php
/**
 * Friends Mastodon API
 *
 * This contains the REST API handlers.
 *
 * @package Friends_Mastodon_API
 */

namespace Friends;

/**
 * This is the class that implements the Mastodon API endpoints.
 *
 * @since 0.1
 *
 * @package Friends_Mastodon_API
 * @author Alex Kirk
 */
class Mastodon_API {
	const VERSION = '0.0.1';
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	const PREFIX = 'friends-mastodon-api';
	const APP_TAXONOMY = 'mastodon-app';

	private $rewrite_rules = array(
		'api/v1/apps',
		'api/v1/instance',
		'api/v1/instance',
	);

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		new Mastodon_Oauth( $friends );
		$this->register_hooks();
	}

	function register_hooks() {
		add_action( 'wp_loaded', array( $this, 'rewrite_rules' ) );
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'log_rest_api_response' ), 9999, 4 );
	}


	function log_rest_api_response( $served, $result, $request, $rest_server ) {
		$log_entry = add_query_arg( $request->get_query_params(), $request->get_route() ) . ' params: ' . print_r( $request->get_json_params(), true );
		error_log($log_entry);
		error_log(print_r($result->get_data(),true));
		return $served;
	}

	public function add_rest_routes() {
		register_rest_route(
			self::PREFIX,
			'api/v1/apps',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_apps' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/instance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_instance' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/verify_credentials',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_accounts_verify_credentials' ),
				'permission_callback' => array( $this, 'api_accounts_verify_credentials_permission' ),
			)
		);

	}

	public function rewrite_rules() {
		$rules = get_option( 'rewrite_rules' );
		$needs_flush = false;

		foreach ( $this->rewrite_rules as $rule ) {
			if ( empty( $rules[ $rule ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				add_rewrite_rule( $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rule, 'top' );
				$needs_flush = true;
			}
		}
		if ( $needs_flush ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}

	public function api_apps( $request ) {
		try {
			$app = Mastodon_App::save(
				$request->get_param( 'client_name' ),
				explode( ',', $request->get_param( 'redirect_uris' ) ),
				$request->get_param( 'scopes' ),
				$request->get_param( 'website' )
			);
		} catch ( \Exception $e ) {
			$message = explode( ',', $e->getMessage(), 2 );
			$app = new \WP_Error(
				$message[0],
				$message[1],
				array( 'status' => 422 )
			);
		}

		if ( is_wp_error( $app ) ) {
			return $app;
		}

		return array(
			'client_id'     => $app->get_client_id(),
			'client_secret' => $app->get_client_secret(),
			'redirect_uris' => $app->get_redirect_uris(),

		);
	}

	public function api_accounts_verify_credentials_permission( $request ) {
		return is_user_logged_in();
	}

	public function api_accounts_verify_credentials( $request ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return new \WP_Error( 'not_logged_in', 'Not logged in', array( 'status' => 401 ) );
		}
		$avatar = get_avatar_url( $user->ID );
		$avatar = str_replace( 'http://', 'https://', $avatar );
		return array(
			'id'                => $user->ID,
			'username'          => $user->user_login,
			'acct'              => $user->user_login,
			'display_name'      => $user->display_name,
			'locked'            => false,
			'created_at'        => mysql2date( 'c', $user->user_registered, false ),
			'followers_count'   => 0,
			'following_count'   => 0,
			'statuses_count'    => 0,
			'note'              => '',
			'url'               => home_url( '/author/' . $user->user_login ),
			'avatar'            => $avatar,
			'avatar_static'     => $avatar,
			'header'            => '',
			'header_static'     => '',
			'emojis'            => array(),
			'fields'            => array(),
			'bot'               => false,
			'last_status_at'    => null,
			'source'            => array(
				'privacy'       => 'public',
				'sensitive'     => false,
				'language'      => 'en',
			),
			'pleroma'           => array(
				'background_image' => null,
				'confirmation_pending' => false,
				'fields' => array(),
				'follow_requests_count' => 0,
				'hide_favorites' => false,
				'hide_followers' => false,
				'hide_follows' => false,
				'hide_followers_count' => false,
				'hide_follows_count' => false,
				'invited_by' => null,
				'is_admin' => false,
				'is_confirmed' => true,
				'is_moderator' => false,
				'is_moved' => false,
			),
		);

	}

	public function api_instance() {
		$ret = array(
			'uri'               => home_url(),
			'account_domain'    => \wp_parse_url( \home_url(), \PHP_URL_HOST ),
			'title'             => get_bloginfo( 'name' ),
			'description'       => get_bloginfo( 'description' ),
			'version'           => self::VERSION,
			'registrations'     => false,
			'approval_required' => false,
		);

		return $ret;
	}
}
