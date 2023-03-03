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
			'api/v1/announcements',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/filters',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/lists',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/custom_emojis',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/verify_credentials',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_verify_credentials' ),
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

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[0-9]+)/statuses',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account_statuses' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[0-9]+)$',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);


		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/relationships',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account_relationships' ),
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
			'api/v1/accounts/relationships',
			'api/v1/accounts/verify_credentials',
			'api/v1/announcements',
			'api/v1/apps',
			'api/v1/custom_emojis',
			'api/v1/filters',
			'api/v1/instance',
			'api/v1/lists',
		);
		$parametrized = array(
			'api/v1/accounts/([0-9]+)/statuses' => 'api/v1/accounts/$matches[1]/statuses',
			'api/v1/accounts/([0-9]+)' => 'api/v1/accounts/$matches[1]',
			'api/v1/timelines/(home)' => 'api/v1/timelines/$matches[1]',
		);

		foreach ( $generic as $rule ) {
			if ( empty( $existing_rules[ $rule ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				$needs_flush = true;
			}
			add_rewrite_rule( $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rule, 'top' );
		}

		foreach ( $parametrized as $rule => $rewrite ) {
			if ( empty( $existing_rules[ $rule ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				$needs_flush = true;
			}
			add_rewrite_rule( $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rewrite, 'top' );
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
		$this->allow_cors();
		if ( apply_filters( 'friends_mastodon_api_skip_login_check', false ) ) {
			return true;
		}
		$this->oauth->authenticate();
		return is_user_logged_in();
	}

	public function api_verify_credentials( $request ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return new \WP_Error( 'not_logged_in', 'Not logged in', array( 'status' => 401 ) );
		}
		$avatar = get_avatar_url( $user->ID );
		$data = array(
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
		);

		if ( class_exists( '\ActivityPub\ActivityPub' ) ) {
			$data['followers_count'] = \ActivityPub\count_followers( $user->ID );
		}

		return $data;
	}

	private function get_posts_query_args( $request ) {
		$tax_query = $this->friends->wp_query_get_post_format_tax_query( array(), apply_filters( 'friends_mastodon_api_post_format', 'status' ) );
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 1;
		}
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 1;
		}

		$args = array(
			'posts_per_page' => $limit,
			'post_type' => Friends::get_frontend_post_types(),
			'tax_query' => $tax_query,
			'suppress_filters' => false
		);
		return $args;
	}

	private function get_posts( $args, $max_id = null ) {
		if ( $max_id ) {
			$filter_handler = function( $where ) use ( $max_id ) {
			    global $wpdb;
			    return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", $max_id );
			};
			add_filter( 'posts_where', $filter_handler );
		}

		$posts = get_posts( $args );

		if ( $max_id ) {
			remove_filter( 'posts_where', $filter_handler );
		}
		$ret = array();
		foreach ( $posts as $post ) {
			$ret[] = $this->get_status_array( $post );
		}
		return $ret;
	}

	private function get_status_array( $post ) {
		$account_data = $this->get_friend_account_data( $post->post_author );

		$data = array(
			'id'                => $post->ID,
			'uri'               => $post->guid,
			'url'               => $post->guid,
			'account'           => $account_data,
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
				'name' => 'WordPress/' . $GLOBALS['wp_version'] . ' Friends/' . FRIENDS_VERSION,
				'website' => home_url(),
			),
			'language'          => 'en',
			'pinned'            => false,
			'card'              => null,
			'poll'              => null,
		);

		$p = strpos( $data['content'], '<!-- wp:image' );
		while ( ( $p = strpos( $data['content'], '<!-- wp:image' ) ) !== false ) {
			$e = strpos( $data['content'], '<!-- /wp:image -->', $p );
			if ( ! $e ) {
				break;
			}
			$img = substr( $data['content'], $p, $e - $p + 19 );
			if ( preg_match( '#<img src="(?P<url>[^"]+)" width="(\d+)" height="(\d+)" class="size-full"#', $img, $attachment )) {
				$data['media_attachments'][] =array(
					'type' => 'image',
					'url' => $attachment['url'],
					'preview_url' => $attachment['url'],
					'text_url' => $attachment['url'],
				);
			}
			$data['content'] = substr( $data['content'], 0, $p ) . substr( $data['content'], $e + 19 );
		}

		$author_name = $data['account']['display_name'];
		$override_author_name = get_post_meta( $post->ID, 'author', true );
		if ( $override_author_name !== $author_name ) {
			// This is a reblog.
			$data['reblog'] = $data;
			$data['reblog']['account']['display_name'] = $override_author_name;

		}

		return $data;
	}
	public function api_timelines( $request ) {
		$args = $this->get_posts_query_args( $request );
		$args = apply_filters( 'friends_mastodon_api_timelines_args', $args, $request );

		return $this->get_posts( $args, $request->get_param( 'max_id' ) );
	}

	public function api_account_statuses( $request ) {
		$args = $this->get_posts_query_args( $request );
		$args['author'] = $request->get_param( 'user_id' );

		$args = apply_filters( 'friends_mastodon_api_account_statuses_args', $args, $request );

		return $this->get_posts( $args );
	}

	public function api_account_relationships( $request ) {
		$user_id = $request->get_param( 'id' );
		$friend_user = User::get_user_by_id( $id );

		return array();
	}

	public function api_account( $request ) {
		return $this->get_friend_account_data( $request->get_param( 'user_id' ) );
	}

	private function get_friend_account_data( $user_id ) {
		$friend_user = User::get_user_by_id( $user_id );
		$avatar = get_avatar_url( $friend_user->ID );
		$posts = $friend_user->get_post_count_by_post_format();

		$data = array(
			'id'                => $friend_user->ID,
			'username'          => $friend_user->user_login,
			'acct'              => $friend_user->user_login,
			'display_name'      => $friend_user->display_name,
			'locked'            => false,
			'created_at'        => mysql2date( 'c', $friend_user->user_registered, false ),
			'followers_count'   => 0,
			'following_count'   => 0,
			'statuses_count'    => isset( $posts['status'] ) ? $posts['status'] : 0,
			'note'              => '',
			'url'               => home_url( '/author/' . $friend_user->user_login ),
			'avatar'            => $avatar,
			'avatar_static'     => $avatar,
			'header'            => null,
			'header_static'     => null,
			'emojis'            => array(),
			'fields'            => array(),
			'bot'               => false,
			'last_status_at'    => null,
			'source'            => array(
				'privacy'       => 'public',
				'sensitive'     => false,
				'language'      => 'en',
			),
		);

		if ( class_exists( __NAMESPACE__ . '\Feed_Parser_ActivityPub' ) ) {
			foreach ( $friend_user->get_feeds() as $feed ) {
				if ( Feed_Parser_ActivityPub::SLUG === $feed->get_parser() ) {
					$meta = Feed_Parser_ActivityPub::get_metadata( $feed->get_url() );
					if ( $meta && ! is_wp_error( $meta ) ) {
						if ( ! empty( $meta['image']['url'] ) ) {
							$data['header'] = $meta['image']['url'];
							$data['header_static'] = $meta['image']['url'];
						}
						$data['note'] = $meta['summary'];
						$data['acct'] = $meta['id'];
						if ( preg_match( '#^https://([^/]+)/@([^/]+)$#', $meta['url'], $m ) ) {
							$data['acct'] = $m[2] . '@' . $m[1];
						}
					}
				}
			}
		}
		return $data;
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
