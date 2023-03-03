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
	private $oauth;

	const PREFIX = 'friends-mastodon-api';
	const APP_TAXONOMY = 'mastodon-app';

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->oauth = new Mastodon_Oauth( $friends );
		$this->register_hooks();
	}

	function register_hooks() {
		add_action( 'wp_loaded', array( $this, 'rewrite_rules' ) );
		add_action( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'allow_cors' ), 10, 4 );
		$this->allow_cors(); // TODO: Remove
	}

	function allow_cors() {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: content-type, authorization' );
		header( 'Access-Control-Allow-Credentials: true' );
		if ( $_SERVER['REQUEST_METHOD']  === 'OPTIONS' ) {
			header( 'Access-Control-Allow-Origin: *', true, 204 );
			exit;
		}
	}

	public function add_rest_routes() {
		register_rest_route(
			self::PREFIX,
			'api/v1/apps',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_apps' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/instance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_instance' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/verify_credentials',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_accounts_verify_credentials' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/timelines/(home)',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_timelines' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);

	}

	public function query_vars( $query_vars ) {
		$query_vars[] = 'mastodon-api';
		return $query_vars;
	}

	public function rewrite_rules() {
		$existing_rules = get_option( 'rewrite_rules' );
		$needs_flush = false;

		$generic = array(
			'api/v1/apps',
			'api/v1/instance',
			'api/v1/accounts/verify_credentials',
			'api/v1/timelines/home',
		);
		$parametrized = array(
			'api/v1/timelines/(home)' => '?mastodon-api=$matches[1]',
		);

		foreach ( $generic as $rule ) {
			if ( empty( $existing_rules[ $rule ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				$needs_flush = true;
			}
			add_rewrite_rule( $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rule, 'top' );
		}

		foreach ( $parametrized as $rule => $append ) {
			if ( empty( $existing_rules[ $rule ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				$needs_flush = true;
			}
			add_rewrite_rule( $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rule . $append, 'top' );
		}
		if ( $needs_flush ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}

	public function public_api_permission() {
		$this->allow_cors();
		return true;
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

	public function logged_in_permission() {
		$this->oauth->authenticate();
		$this->allow_cors();
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

	public function api_timelines( $request ) {
		$tax_query = $this->friends->wp_query_get_post_format_tax_query( array(), 'status' );
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 1;
		}
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 1;
		}

		// get posts with a post->ID larger than the given $max_id.
		$max_id = $request->get_param( 'max_id' );
		if ( $max_id ) {
			$filter_handler = function( $where ) use ( $max_id ) {
			    global $wpdb;
			    return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", $max_id );
			};
			add_filter( 'posts_where', $filter_handler );
		}

		$posts = get_posts(
			array(
				'posts_per_page' => $limit,
				'post_type' => Friends::get_frontend_post_types(),
				'tax_query' => $tax_query,
				'suppress_filters' => false
			)
		);

		if ( $max_id ) {
			remove_filter( 'posts_where', $filter_handler );
		}

		$ret = array();
		foreach ( $posts as $post ) {
			$ret[] = $this->get_status( $post );
		}
		return $ret;
	}

	public function get_status( $post ) {
		$user = get_user_by( 'id', $post->post_author );
		$avatar = get_avatar_url( $user->ID );
		$avatar = str_replace( 'http://', 'https://', $avatar );
		return array(
			'id'                => $post->ID,
			'uri'               => home_url( '/?p=' . $post->ID ),
			'url'               => home_url( '/?p=' . $post->ID ),
			'account'           => array(
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
			),
			'in_reply_to_id'    => null,
			'in_reply_to_account_id' => null,
			'reblog'            => null,
			'content'           => $post->post_content,
			'created_at'        => mysql2date( 'c', $post->post_date, false ),
			'emojis'            => array(),
			'favourites_count'  => 0,
			'reblogged'         => false,
			'reblogged_by'      => array(),
			'muted'             => false,
			'sensitive'         => false,
			'spoiler_text'      => '',
			'visibility'        => 'public',
			'media_attachments' => array(),
			'mentions'          => array(),
			'tags'              => array(),
			'application'       => array(
				'name' => 'WordPress',
				'website' => home_url(),
			),
			'language'          => 'en',
			'pinned'            => false,
			'card'              => null,
			'poll'              => null,
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
