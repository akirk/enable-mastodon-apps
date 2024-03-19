<?php
/**
 * Mastodon API
 *
 * This contains the REST API handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use WP_REST_Request;
use WP_REST_Response;

/**
 * This is the class that implements the Mastodon API endpoints.
 *
 * @since 0.1
 *
 * @package Enable_Mastodon_Apps
 * @author Alex Kirk
 */
class Mastodon_API {
	const ACTIVITYPUB_USERNAME_REGEXP = '(?:([A-Za-z0-9_-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))';
	const VERSION = ENABLE_MASTODON_APPS_VERSION;
	/**
	 * The OAuth handler.
	 *
	 * @var Mastodon_OAuth
	 */
	private $oauth;

	/**
	 * The Mastodon App.
	 *
	 * @var Mastodon_App
	 */
	private $app;

	const PREFIX = 'enable-mastodon-apps';
	const APP_TAXONOMY = 'mastodon-app';
	const REMOTE_USER_TAXONOMY = 'mastodon-api-remote-user';
	const CPT = 'enable-mastodon-apps';

	/**
	 * Constructor
	 */
	public function __construct() {
		Mastodon_App::register_taxonomy();
		$this->oauth = new Mastodon_OAuth();
		$this->register_hooks();
		$this->register_taxonomy();
		$this->register_custom_post_type();
		new Mastodon_Admin( $this->oauth );

		// Register Handlers.
		new Handler\Account();
		new Handler\Instance();
		new Handler\Media_Attachment();
		new Handler\Notification();
		new Handler\Relationship();
		new Handler\Search();
		new Handler\Status();
		new Handler\Timeline();
	}

	public function register_hooks() {
		add_action( 'wp_loaded', array( $this, 'rewrite_rules' ) );
		add_action( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'allow_cors' ), 10, 4 );
		add_filter( 'rest_pre_echo_response', array( $this, 'reformat_error_response' ), 10, 3 );
		add_filter( 'template_include', array( $this, 'log_404s' ) );
		add_filter( 'enable_mastodon_apps_get_json', array( $this, 'get_json' ), 10, 4 );
		add_action( 'default_option_mastodon_api_default_post_formats', array( $this, 'default_option_mastodon_api_default_post_formats' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'rest_request_before_callbacks' ) );
	}

	public function allow_cors() {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: content-type, authorization' );
		header( 'Access-Control-Allow-Credentials: true' );
		if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			header( 'Access-Control-Allow-Origin: *', true, 204 );
			exit;
		}
	}

	/**
	 * Reformat error responses to match the Mastodon API.
	 *
	 * @see https://docs.joinmastodon.org/entities/Error/
	 *
	 * @param array           $result  The API result.
	 * @param WP_REST_Server  $server  The REST server instance.
	 * @param WP_REST_Request $request The REST request instance.
	 *
	 * @return array The reformatted result.
	 */
	public function reformat_error_response( $result, $server, $request ) {
		if ( 0 !== strpos( $request->get_route(), '/' . self::PREFIX ) ) {
			return $result;
		}

		if ( http_response_code() < 400 ) {
			return $result;
		}

		return array(
			'error'             => empty( $result['code'] ) ? __( 'unknown_error', 'enable-mastodon-apps' ) : $result['code'],
			'error_description' => empty( $result['message'] ) ? __( 'Unknown error', 'enable-mastodon-apps' ) : $result['message'],
		);
	}

	public function register_taxonomy() {
		$args = array(
			'labels'       => array(
				'name'          => 'Mastodon Remote Users',
				'singular_name' => 'Mastodon Remote User',
				'menu_name'     => 'Mastodon Remote Users',
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'rewrite'      => false,
		);

		register_taxonomy( self::REMOTE_USER_TAXONOMY, null, $args );
	}

	public function register_custom_post_type() {
		$args = array(
			'labels'       => array(
				'name'          => 'Mapping',
				'singular_name' => 'Mapping',
				'menu_name'     => 'Mappings',
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'rewrite'      => false,
		);

		register_post_type( self::CPT, $args );
	}


	public function rewrite_rules() {
		$existing_rules = get_option( 'rewrite_rules' );
		$needs_flush = false;

		$generic = apply_filters(
			'mastodon_api_generic_routes',
			array(
				'api/v1/accounts/relationships',
				'api/v1/accounts/verify_credentials',
				'api/v1/accounts/familiar_followers',
				'api/v1/accounts/search',
				'api/v1/announcements',
				'api/v1/apps',
				'api/v1/bookmarks',
				'api/v1/conversations',
				'api/v1/custom_emojis',
				'api/v1/favourites',
				'api/v1/filters',
				'api/v1/follow_requests',
				'api/v1/followed_tags',
				'api/v1/instance/peers',
				'api/v1/instance/rules',
				'api/v1/instance/domain_blocks',
				'api/v1/instance/extended_description',
				'api/v1/instance/translation_languages',
				'api/v1/instance',
				'api/v2/instance',
				'api/v1/lists',
				'api/v1/markers',
				'api/v1/mutes',
				'api/v1/notifications/clear',
				'api/v1/preferences',
				'api/v1/trends/statuses',
				'api/v1/push/subscription',
				'api/v1/streaming',
				'api/v2/media',
			)
		);
		$parametrized = apply_filters(
			'mastodon_api_parametrized_routes',
			array(
				'api/v1/accounts/([^/]+)/featured_tags'  => 'api/v1/accounts/$matches[1]/featured_tags',
				'api/v1/accounts/([^/]+)/followers'      => 'api/v1/accounts/$matches[1]/followers',
				'api/v1/accounts/([^/]+)/follow'         => 'api/v1/accounts/$matches[1]/follow',
				'api/v1/accounts/([^/]+)/unfollow'       => 'api/v1/accounts/$matches[1]/unfollow',
				'api/v1/accounts/([^/]+)/statuses'       => 'api/v1/accounts/$matches[1]/statuses',
				'api/v1/statuses/([0-9]+)/context'       => 'api/v1/statuses/$matches[1]/context',
				'api/v1/statuses/([0-9]+)/favourited_by' => 'api/v1/statuses/$matches[1]/favourited_by',
				'api/v1/statuses/([0-9]+)/favourite'     => 'api/v1/statuses/$matches[1]/favourite',
				'api/v1/statuses/([0-9]+)/unfavourite'   => 'api/v1/statuses/$matches[1]/unfavourite',
				'api/v1/statuses/([0-9]+)/reblog'        => 'api/v1/statuses/$matches[1]/reblog',
				'api/v1/statuses/([0-9]+)/unreblog'      => 'api/v1/statuses/$matches[1]/unreblog',
				'api/v1/notifications/([^/]+)/dismiss'   => 'api/v1/notifications/$matches[1]/dismiss',
				'api/v1/notifications/([^/|$]+)/?$'      => 'api/v1/notifications/$matches[1]',
				'api/v1/notifications'                   => 'api/v1/notifications',
				'api/nodeinfo/([0-9]+[.][0-9]+).json'    => 'api/nodeinfo/$matches[1].json',
				'api/v1/media/([0-9]+)'                  => 'api/v1/media/$matches[1]',
				'api/v1/statuses/([0-9]+)'               => 'api/v1/statuses/$matches[1]',
				'api/v1/statuses'                        => 'api/v1/statuses',
				'api/v1/accounts/(.+)'                   => 'api/v1/accounts/$matches[1]',
				'api/v1/timelines/(home|public)'         => 'api/v1/timelines/$matches[1]',
				'api/v1/timelines/tag/([^/|$]+)'         => 'api/v1/timelines/tag/$matches[1]',
				'api/v2/search'                          => 'api/v1/search',
			)
		);

		foreach ( $generic as $rule ) {
			if ( empty( $existing_rules[ $rule ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				$needs_flush = true;
			}
			add_rewrite_rule( '^' . $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rule, 'top' );
		}

		foreach ( $parametrized as $rule => $rewrite ) {
			if ( empty( $existing_rules[ $rule ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				$needs_flush = true;
			}
			add_rewrite_rule( '^' . $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rewrite, 'top' );
		}

		if ( $needs_flush ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
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
				'args'                => array(
					'client_name'   => array(
						'type'        => 'string',
						'description' => 'The name of your application.',
						'required'    => true,
					),
					'redirect_uris' => array(
						'type'        => 'string',
						'description' => 'Where the user should be redirected after authorization.',
						'required'    => true,
					),
					'scopes'        => array(
						'type'        => 'string',
						'description' => 'Space separated list of scopes.',
						'default'     => 'read',
					),
					'website'       => array(
						'type'        => 'string',
						'description' => 'The URL to your application’s website.',
						'format'      => 'uri',
					),
				),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/announcements',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_announcements' ),
				'permission_callback' => $this->required_scope( 'read:announcements' ),
				'args'                => array(
					'with_dismissed' => array(
						'type'        => 'boolean',
						'description' => 'Whether to include dismissed announcements.',
						'default'     => false,
					),
				),
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
			'api/v2/instance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_instance_v2' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/instance/peers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_instance_peers' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/instance/rules',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_instance_rules' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/instance/domain_blocks',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_instance_domain_blocks' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/instance/extended_description',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_instance_extended_description' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/instance/translation_languages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_instance_translation_languages' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/nodeinfo/2.0.json',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_nodeinfo' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/follow_requests',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:follows,follow' ),
				'args'                => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 40,
						'maximum'     => 80,
					),
				),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/followed_tags',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:follows' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/bookmarks',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:bookmarks' ),
				'args'                => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 100,
						'maximum'     => 200,
					),
				),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:statuses' ),
				'args'                => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 40,
						'maximum'     => 80,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/favourites',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:favourites' ),
				'args'                => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 40,
						'maximum'     => 80,
					),
				),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/filters',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:filters' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/lists',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:lists' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/markers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => '__return_empty_array',
					'permission_callback' => $this->required_scope( 'read:statuses' ),
					'args'                => array(
						'timeline' => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => array(
									'home',
									'notifications',
								),
							),
							'description' => 'The timeline(s) to fetch markers for.',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => '__return_empty_array',
					'permission_callback' => $this->required_scope( 'write:statuses' ),
					'args'                => array(
						'home'          => array(
							'type'        => 'string',
							'description' => 'ID of the last status read in the home timeline.',
						),
						'notifications' => array(
							'type'        => 'string',
							'description' => 'ID of the last notification read.',
						),
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/mutes',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:mutes' ),
				'args'                => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 40,
						'maximum'     => 80,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/notifications/clear',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_notification_clear' ),
				'permission_callback' => $this->required_scope( 'write:notifications' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/notifications/(?P<id>[^/]+)/dismiss',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_notification_dismiss' ),
				'permission_callback' => $this->required_scope( 'write:notifications' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/notifications/(?P<id>.+)$',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_notification_get' ),
				'permission_callback' => $this->required_scope( 'read:notifications' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_notifications_get' ),
				'permission_callback' => $this->required_scope( 'read:notifications' ),
				'args'                => array(
					'max_id'        => array(
						'type'              => 'string',
						'description'       => 'All results returned will be lesser than this ID. In effect, sets an upper bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'since_id'      => array(
						'type'              => 'string',
						'description'       => 'All results returned will be greater than this ID. In effect, sets a lower bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'min_id'        => array(
						'type'              => 'string',
						'description'       => 'Returns results immediately newer than this ID. In effect, sets a cursor at this ID and paginates forward.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'limit'         => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 40,
						'maximum'     => 80,
					),
					'types'         => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => array(
								'mention',
								'status',
								'reblog',
								'follow',
								'follow_request',
								'favourite',
								'poll',
								'update',
								'admin.sign_up',
								'admin.report',
								'severed_relationship',
							),
						),
						'description' => 'The types of notifications to fetch.',
					),
					'exclude_types' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => array(
								'mention',
								'status',
								'reblog',
								'follow',
								'follow_request',
								'favourite',
								'poll',
								'update',
								'admin.sign_up',
								'admin.report',
								'severed_relationship',
							),
						),
						'description' => 'The types of notifications to exclude.',
					),
					'account_id'    => array(
						'type'        => 'string',
						'description' => 'The ID of the account to fetch notifications for.',
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/preferences',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_preferences' ),
				'permission_callback' => $this->required_scope( 'read:accounts' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/trends/statuses',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => array( $this, 'public_api_permission' ),
				'args'                => array(
					'limit'  => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 20,
						'maximum'     => 40,
					),
					'offset' => array(
						'type'        => 'integer',
						'description' => 'Skip the first n results.',
						'default'     => 0,
					),
				),
			)
		);
		register_rest_route(
			self::PREFIX,
			'api/v1/custom_emojis',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/familiar_followers',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:follows' ),
				'args'                => array(
					'id' => array(
						'type'        => 'array',
						'description' => 'Find familiar followers for the provided account IDs.',
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/verify_credentials',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_verify_credentials' ),
				'permission_callback' => $this->required_scope( 'follow:accounts' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/search',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_accounts_search' ),
				'permission_callback' => $this->required_scope( 'read:accounts' ),
				'args'                => array(
					'q'         => array(
						'type'        => 'string',
						'description' => 'What to search for.',
						'required'    => true,
					),
					'limit'     => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 40,
						'maximum'     => 80,
					),
					'offset'    => array(
						'type'        => 'integer',
						'description' => 'Skip the first n results.',
						'default'     => 0,
					),
					'resolve'   => array(
						'type'        => 'boolean',
						'description' => 'Attempt WebFinger lookup. Use this when `q` is an exact address.',
						'default'     => false,
					),
					'following' => array(
						'type'        => 'boolean',
						'description' => 'Only return accounts the current user is following.',
						'default'     => false,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v2/media',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_post_media' ),
				'permission_callback' => $this->required_scope( 'write:media' ),
				'args'                => array(
					'description' => array(
						'type'        => 'string',
						'description' => 'A plain-text description of the media.',
					),
					'focus'       => array(
						'type'        => 'string',
						'description' => 'Two floating points (x,y), comma-delimited, ranging from -1.0 to 1.0.',
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/media/(?P<post_id>[0-9]+)',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_get_media' ),
				'permission_callback' => $this->required_scope( 'write:media' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/media/(?P<post_id>[0-9]+)',
			array(
				'methods'             => array( 'PUT', 'OPTIONS' ),
				'callback'            => array( $this, 'api_update_media' ),
				'permission_callback' => $this->required_scope( 'write:media' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_submit_post' ),
				'permission_callback' => $this->required_scope( 'write:statuses' ),
				'args'                => array(
					'status'         => array(
						'type'        => 'string',
						'description' => ' The text content of the status. If `media_ids` is provided, this becomes optional. Attaching a `poll` is optional while `status` is provided.',
					),
					'media_ids'      => array(
						'type'        => 'array',
						'description' => 'Include Attachment IDs to be attached as media. If provided, `status` becomes optional, and `poll` cannot be used.',
						'items'       => array(
							'type' => 'string',
						),
					),
					'poll'           => array(
						'type'        => 'object',
						'description' => 'Poll options.',
						'properties'  => array(
							'options'     => array(
								'type'        => 'array',
								'description' => ' Possible answers to the poll. If provided, `media_ids` cannot be used, and `poll[expires_in]` must be provided.',
								'items'       => array(
									'type' => 'string',
								),
							),
							'expires_in'  => array(
								'type'        => 'integer',
								'description' => 'Duration in seconds before the poll ends. If provided, `media_ids` cannot be used, and `poll[options]` must be provided.',
							),
							'multiple'    => array(
								'type'        => 'boolean',
								'description' => 'Allow multiple choices.',
								'default'     => false,
							),
							'hide_totals' => array(
								'type'        => 'boolean',
								'description' => 'Hide poll results until the poll ends.',
								'default'     => false,
							),
						),
					),
					'in_reply_to_id' => array(
						'type'        => 'integer',
						'description' => 'The ID of the status being replied to.',
					),
					'sensitive'      => array(
						'type'        => 'boolean',
						'description' => 'Mark the status as NSFW.',
					),
					'spoiler_text'   => array(
						'type'        => 'string',
						'description' => 'Text to be shown as a warning before the actual content.',
					),
					'visibility'     => array(
						'type'        => 'string',
						'description' => 'The status visibility.',
						'enum'        => array( 'public', 'unlisted', 'private', 'direct' ),
						'default'     => 'public',
					),
					'language'       => array(
						'type'        => 'string',
						'description' => 'ISO 639 language code for this status.',
					),
					'scheduled_at'   => array(
						'type'        => 'string',
						'description' => 'ISO 8601 Datetime at which to schedule a status. Providing this parameter will cause `ScheduledStatus` to be returned instead of `Status`. Must be at least 5 minutes in the future.',
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)/context',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_get_post_context' ),
				'permission_callback' => $this->required_scope( 'read:statuses', true ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)/favourited_by',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:statuses', true ),
				'args'                => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 40,
						'maximum'     => 80,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)/favourite',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_favourite_post' ),
				'permission_callback' => $this->required_scope( 'write:favourites' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)/unfavourite',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_unfavourite_post' ),
				'permission_callback' => $this->required_scope( 'write:favourites' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)/reblog',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_reblog_post' ),
				'permission_callback' => $this->required_scope( 'write:statuses' ),
				'args'                => array(
					'visibility' => array(
						'type'        => 'string',
						'description' => 'The visibility of the reblog. Currently unused in UI.',
						'enum'        => array( 'public', 'unlisted', 'private' ),
						'default'     => 'public',
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)/unreblog',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_unreblog_post' ),
				'permission_callback' => $this->required_scope( 'write:statuses' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)',
			array(
				'methods'             => array( 'DELETE', 'OPTIONS' ),
				'callback'            => array( $this, 'api_delete_post' ),
				'permission_callback' => $this->required_scope( 'write:statuses' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_get_post' ),
				'permission_callback' => $this->required_scope( 'read:statuses', true ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/timelines/(home)',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_timelines' ),
				'permission_callback' => $this->required_scope( 'read:statuses' ),
				'args'                => array(
					'max_id'   => array(
						'type'              => 'string',
						'description'       => 'All results returned will be lesser than this ID. In effect, sets an upper bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'since_id' => array(
						'type'              => 'string',
						'description'       => 'All results returned will be greater than this ID. In effect, sets a lower bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'min_id'   => array(
						'type'              => 'string',
						'description'       => 'Returns results immediately newer than this ID. In effect, sets a cursor at this ID and paginates forward.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'limit'    => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 20,
						'maximum'     => 40,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/timelines/(tag)/(?P<hashtag>[^/]+)',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_tag_timeline' ),
				'permission_callback' => $this->required_scope( 'read:statuses' ),
				'args'                => array(
					'any'        => array(
						'type'        => 'array',
						'description' => 'Return statuses that contain any of these additional tags.',
						'items'       => array(
							'type' => 'string',
						),
					),
					'all'        => array(
						'type'        => 'array',
						'description' => 'Return statuses that contain all of these additional tags.',
						'items'       => array(
							'type' => 'string',
						),
					),
					'none'       => array(
						'type'        => 'array',
						'description' => 'Return statuses that contain none of these additional tags.',
						'items'       => array(
							'type' => 'string',
						),
					),
					'local'      => array(
						'type'        => 'boolean',
						'description' => 'Only return statuses originating from this instance.',
						'default'     => false,
					),
					'remote'     => array(
						'type'        => 'boolean',
						'description' => 'Only return statuses originating from other instances.',
						'default'     => false,
					),
					'only_media' => array(
						'type'        => 'boolean',
						'description' => 'Only return statuses that have media attachments.',
						'default'     => false,
					),
					'max_id'     => array(
						'type'              => 'string',
						'description'       => 'All results returned will be lesser than this ID. In effect, sets an upper bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'since_id'   => array(
						'type'              => 'string',
						'description'       => 'All results returned will be greater than this ID. In effect, sets a lower bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'min_id'     => array(
						'type'              => 'string',
						'description'       => 'Returns results immediately newer than this ID. In effect, sets a cursor at this ID and paginates forward.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'limit'      => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 20,
						'maximum'     => 40,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/timelines/(public)',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_public_timeline' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
				'args'                => array(
					'max_id'   => array(
						'type'              => 'string',
						'description'       => 'All results returned will be lesser than this ID. In effect, sets an upper bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'since_id' => array(
						'type'              => 'string',
						'description'       => 'All results returned will be greater than this ID. In effect, sets a lower bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'min_id'   => array(
						'type'              => 'string',
						'description'       => 'Returns results immediately newer than this ID. In effect, sets a cursor at this ID and paginates forward.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'limit'    => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 20,
						'maximum'     => 40,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/streaming(/public)?(/local)?',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => '__return_empty_array',
				'permission_callback' => $this->required_scope( 'read:statuses' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/push/subscription',
			array(
				'methods'             => array( 'GET', 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_push_subscription' ),
				'permission_callback' => $this->required_scope( 'push' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/search',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_search' ),
				'permission_callback' => array( $this, 'have_token_permission' ),
				'args'                => array(
					'q'          => array(
						'type'        => 'string',
						'description' => 'What to search for.',
						'required'    => true,
					),
					'type'       => array(
						'type'        => 'string',
						'description' => 'The type of search to perform.',
						'enum'        => array( 'accounts', 'hashtags', 'statuses' ),
						'default'     => 'statuses',
					),
					'resolve'    => array(
						'type'        => 'boolean',
						'description' => 'Attempt WebFinger lookup. Use this when `q` is an exact address.',
						'default'     => false,
					),
					'account_id' => array(
						'type'        => 'string',
						'description' => 'The ID of the account to search for.',
					),
					'max_id'     => array(
						'type'              => 'string',
						'description'       => 'All results returned will be lesser than this ID. In effect, sets an upper bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'min_id'     => array(
						'type'              => 'string',
						'description'       => 'Returns results immediately newer than this ID. In effect, sets a cursor at this ID and paginates forward.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'limit'      => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 40,
						'maximum'     => 80,
					),
					'offset'     => array(
						'type'        => 'integer',
						'description' => 'Skip the first n results.',
						'default'     => 0,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/relationships',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account_relationships' ),
				'permission_callback' => $this->required_scope( 'read:follows' ),
				'args'                => array(
					'id'             => array(
						'type'        => 'array',
						'description' => 'The account IDs to fetch relationships for',
						'items'       => array(
							'type' => 'string',
						),
						'required'    => true,
					),
					'with_suspended' => array(
						'type'        => 'boolean',
						'description' => 'Whether to include relationships with suspended accounts',
						'default'     => false,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[^/]+)/featured_tags',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => '__return_empty_array',
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[^/]+)/followers',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account_followers' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
				'args'                => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return',
						'default'     => 40,
						'maximum'     => 80,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[^/]+)/follow',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account_follow' ),
				'permission_callback' => $this->required_scope( 'write:follows' ),
				'args'                => array(
					'reblogs'   => array(
						'type'        => 'boolean',
						'description' => 'Whether to also follow the account&#8217;s reblogs',
						'default'     => true,
					),
					'notify'    => array(
						'type'        => 'boolean',
						'description' => 'Whether to also send a notification to the account',
						'default'     => false,
					),
					'languages' => array(
						'type'        => 'array',
						'description' => ' Filter received statuses for these languages. If not provided, you will receive this account’s posts in all languages.',
						'items'       => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[^/]+)/unfollow',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account_unfollow' ),
				'permission_callback' => $this->required_scope( 'write:follows' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[^/]+)/statuses',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account_statuses' ),
				'permission_callback' => $this->required_scope( 'read:statuses', true ),
				'args'                => array(
					'max_id'          => array(
						'type'              => 'string',
						'description'       => 'All results returned will be lesser than this ID. In effect, sets an upper bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'since_id'        => array(
						'type'              => 'string',
						'description'       => 'All results returned will be greater than this ID. In effect, sets a lower bound on results.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'min_id'          => array(
						'type'              => 'string',
						'description'       => 'Returns results immediately newer than this ID. In effect, sets a cursor at this ID and paginates forward.',
						'sanitize_callback' => array( $this, 'id_as_strval' ),
					),
					'limit'           => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results to return.',
						'default'     => 20,
						'maximum'     => 40,
					),
					'only_media'      => array(
						'type'        => 'boolean',
						'description' => 'Only return statuses that have media attachments.',
						'default'     => false,
					),
					'exclude_replies' => array(
						'type'        => 'boolean',
						'description' => 'Skip statuses that are replies.',
						'default'     => false,
					),
					'exclude_reblogs' => array(
						'type'        => 'boolean',
						'description' => 'Skip statuses that are reblogs.',
						'default'     => false,
					),
					'pinned'          => array(
						'type'        => 'boolean',
						'description' => 'Only return statuses that are pinned.',
						'default'     => false,
					),
					'tagged'          => array(
						'type'        => 'string',
						'description' => 'Only return statuses that have this tag.',
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[^/]+)$',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
			)
		);

		do_action( 'mastodon_api_register_rest_routes', $this );
	}

	public function query_vars( $query_vars ) {
		$query_vars[] = 'enable-mastodon-apps';
		return $query_vars;
	}

	public function id_as_strval( $id ) {
		return strval( $id );
	}

	public function ensure_required_scope( $request, $scopes, $also_public ) {
		if ( $also_public ) {
			if ( ! $this->logged_in_for_private_permission( $request ) ) {
				return new \WP_Error( 'token-required', 'Token required', array( 'status' => 401 ) );
			}
		} elseif ( ! $this->logged_in_permission( $request ) ) {
			return new \WP_Error( 'token-required', 'Token required', array( 'status' => 401 ) );
		}

		$has_scope = false;
		$token = $this->oauth->get_token();
		if ( $token && isset( $token['scope'] ) ) {
			foreach ( explode( ',', $scopes ) as $scope ) {
				if ( OAuth2\Scope_Util::checkSingleScope( $scope, $token['scope'] ) ) {
					$has_scope = true;
				}
			}
		}

		if ( ! $has_scope && ! $also_public ) {
			return new \WP_Error( 'insufficient-permissions', 'Insufficient permissions', array( 'status' => 401 ) );
		}

		return true;
	}

	public function required_scope( $scopes, $also_public = false ) {
		return function ( $request ) use ( $scopes, $also_public ) {
			return $this->ensure_required_scope( $request, $scopes, $also_public );
		};
	}

	public function public_api_permission( $request ) {
		$this->allow_cors();
		// Optionally log in.
		$token = $this->oauth->get_token();
		if ( ! $token ) {
			if ( get_option( 'mastodon_api_debug_mode' ) > time() ) {
				$app = Mastodon_App::get_debug_app();
				$app->was_used(
					$request,
					array(
						'user_agent' => $_SERVER['HTTP_USER_AGENT'],
					)
				);
			}
			return true;
		}
		wp_set_current_user( $token['user_id'] );
		Mastodon_App::set_current_app( $token['client_id'], $request );
		return is_user_logged_in();
	}

	public function log_404s( $template ) {
		if ( ! is_404() ) {
			return $template;
		}
		if ( get_option( 'mastodon_api_debug_mode' ) <= time() ) {
			return $template;
		}
		if ( 0 !== strpos( $_SERVER['REQUEST_URI'], '/api/v' ) ) {
			return $template;
		}
		$request = new WP_REST_Request( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );
		$request->set_query_params( $_GET );
		$request->set_body_params( $_POST );
		$request->set_headers( getallheaders() );
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$this->oauth->get_token();
		}
		$app = Mastodon_App::get_current_app();
		if ( ! $app ) {
			$app = Mastodon_App::get_debug_app();
		}
		$app->was_used(
			$request,
			array(
				'user_agent' => $_SERVER['HTTP_USER_AGENT'],
				'status'     => 404,
			)
		);

		return $template;
	}

	public function api_apps( $request ) {
		if ( get_option( 'mastodon_api_disable_logins' ) ) {
			return new \WP_Error( 'registation-disabled', __( 'App registration has been disabled.', 'enable-mastodon-apps' ), array( 'status' => 403 ) );
		}

		try {
			$redirect_uris = $request->get_param( 'redirect_uris' );
			if ( ! $redirect_uris ) {
				$redirect_uris = '';
			}
			if ( ! is_array( $redirect_uris ) ) {
				$redirect_uris = explode( ',', $redirect_uris );
			}
			$app = Mastodon_App::save(
				$request->get_param( 'client_name' ),
				$redirect_uris,
				$request->get_param( 'scopes' ),
				$request->get_param( 'website' )
			);
		} catch ( \Exception $e ) {
			list( $code, $message ) = explode( ',', $e->getMessage(), 2 );
			$app = new \WP_Error( $code, $message, array( 'status' => 422 ) );
		}

		if ( is_wp_error( $app ) ) {
			return $app;
		}

		return array(
			'id'            => $app->get_client_id(),
			'name'          => $app->get_client_name(),
			'website'       => $app->get_website(),
			'redirect_uri'  => implode( ',', is_array( $redirect_uris ) ? $redirect_uris : array( $redirect_uris ) ),
			'client_id'     => $app->get_client_id(),
			'client_secret' => $app->get_client_secret(),

		);
	}

	public function logged_in_permission( $request ) {
		$this->allow_cors();
		$token = $this->oauth->get_token();
		if ( ! $token ) {
			return is_user_logged_in();
		}

		OAuth2\Access_Token_Storage::was_used( $token['access_token'] );
		wp_set_current_user( $token['user_id'] );
		Mastodon_App::set_current_app( $token['client_id'], $request );
		return is_user_logged_in();
	}

	public function have_token_permission( $request ) {
		$this->allow_cors();
		$token = $this->oauth->get_token();
		if ( ! $token ) {
			return is_user_logged_in();
		}
		OAuth2\Access_Token_Storage::was_used( $token['access_token'] );
		Mastodon_App::set_current_app( $token['client_id'], $request );
		return true;
	}

	public function logged_in_for_private_permission( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return true;
		}

		if ( get_post_status( $post_id ) !== 'publish' ) {
			return $this->logged_in_permission( $request );
		}

		return true;
	}

	/**
	 * Set the default post format.
	 *
	 * @param mixed $post_formats The default value to return if the option does not exist
	 *                        in the database.
	 *
	 * @return     array   The potentially modified default value.
	 */
	public function default_option_mastodon_api_default_post_formats( $post_formats ) {
		if ( ! defined( 'FRIENDS_VERSION' ) ) {
			$post_formats = array(
				'standard',
			);
		}

		return $post_formats;
	}

	/**
	 * Converts param validation errors to error codes expected by Mastodon apps.
	 *
	 * @param WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response Result to send to the client.
	 * @return WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed
	 */
	public function rest_request_before_callbacks( $response ) {
		if ( is_wp_error( $response ) && 'rest_missing_callback_param' === $response->get_error_code() ) {
			$response = new \WP_Error( $response->get_error_code(), $response->get_error_message(), array( 'status' => 422 ) );
		}

		return $response;
	}


	public function api_verify_credentials( $request ) {
		$request->set_param( 'user_id', get_current_user_id() );
		return $this->api_account( $request );
	}

	private function get_posts_query_args( $request ) {
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 10;
		}

		$args = array(
			'posts_per_page'   => $limit,
			'post_type'        => array( 'post' ),
			'suppress_filters' => false,
			'post_status'      => 'any',
		);

		$pinned = $request->get_param( 'pinned' );
		if ( $pinned || 'true' === $pinned ) {
			$args['pinned'] = true;
			$args['post__in'] = get_option( 'sticky_posts' );
			if ( empty( $args['post__in'] ) ) {
				// No pinned posts, we need to find nothing.
				$args['post__in'] = array( -1 );
			}
		}

		$app = Mastodon_App::get_current_app();
		if ( $app ) {
			$args = $app->modify_wp_query_args( $args );
		} else {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( 'post-format-status' ),
				),
			);
		}

		$post_id = $request->get_param( 'post_id' );
		if ( $post_id ) {
			$args['p'] = $post_id;
		}

		return apply_filters( 'mastodon_api_get_posts_query_args', $args, $request );
	}

	private function get_posts( $args, $min_id = null, $max_id = null ) {
		if ( $min_id ) {
			$min_filter_handler = function ( $where ) use ( $min_id ) {
				global $wpdb;
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $min_id );
			};
			add_filter( 'posts_where', $min_filter_handler );
		}

		if ( $max_id ) {
			$max_filter_handler = function ( $where ) use ( $max_id ) {
				global $wpdb;
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", $max_id );
			};
			add_filter( 'posts_where', $max_filter_handler );
		}

		$posts = get_posts( $args );
		if ( $min_id ) {
			remove_filter( 'posts_where', $min_filter_handler );
		}
		if ( $max_id ) {
			remove_filter( 'posts_where', $max_filter_handler );
		}

		$statuses = array();
		foreach ( $posts as $post ) {
			/**
			 * Modify the status data.
			 *
			 * @param array|null $account The status data.
			 * @param int $post_id The object ID to get the status from.
			 * @param array $data Additional status data.
			 * @return array|null The modified status data.
			 */
			$status = apply_filters( 'mastodon_api_status', null, $post->ID, array() );

			if ( $status ) {
				$statuses[ $post->post_date ] = $status;
			}
		}

		if ( ! isset( $args['pinned'] ) || ! $args['pinned'] ) {
			// Comments cannot be pinned for now.
			$comments = get_comments(
				array(
					'meta_key'   => 'protocol',
					'meta_value' => 'activitypub',
				)
			);

			foreach ( $comments as $comment ) {
				$status = $this->get_comment_status_array( $comment );
				if ( $status ) {
					$statuses[ $comment->comment_date ] = $status;
				}
			}
		}
		ksort( $statuses );

		if ( $min_id ) {
			$min_id = strval( $min_id );
			$min_id_exists_in_statuses = false;
			foreach ( $statuses as $status ) {
				if ( $status['id'] === $min_id ) {
					$min_id_exists_in_statuses = true;
					break;
				}
			}
			if ( ! $min_id_exists_in_statuses ) {
				// We don't need to watch the min_id.
				$min_id = null;
			}
		}

		$ret = array();
		$c = $args['posts_per_page'];
		$next_max_id = false;
		foreach ( $statuses as $status ) {
			if ( false === $next_max_id ) {
				$next_max_id = $status['id'];
			}
			if ( $min_id ) {
				if ( $status['id'] !== $min_id ) {
					continue;
				}
				// We can now include results but need to skip this one.
				$min_id = null;
				continue;
			}
			if ( $max_id && strval( $max_id ) === $status['id'] ) {
				break;
			}
			if ( $c-- <= 0 ) {
				break;
			}
			array_unshift( $ret, $status );
		}

		if ( ! empty( $ret ) ) {
			if ( $next_max_id ) {
				header( 'Link: <' . add_query_arg( 'max_id', $next_max_id, home_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ) ) . '>; rel="next"', false );
			}
			header( 'Link: <' . add_query_arg( 'min_id', $ret[0]['id'], home_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ) ) . '>; rel="prev"', false );
		}

		return $ret;
	}

	private function get_comment_status_array( \WP_Comment $comment ) {
		if ( ! $comment ) {
			return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Record not found', array( 'status' => 404 ) );
		}

		$post = (object) array(
			'ID'           => $this->remap_comment_id( $comment->comment_ID ),
			'guid'         => $comment->guid . '#comment-' . $comment->comment_ID,
			'post_author'  => $comment->user_id,
			'post_content' => $comment->comment_content,
			'post_date'    => $comment->comment_date,
			'post_status'  => $comment->post_status,
			'post_type'    => $comment->post_type,
			'post_title'   => '',
		);

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters(
			'mastodon_api_status',
			null,
			$post->ID,
			array(
				'in_reply_to_id' => $comment->comment_post_ID,
			)
		);

		return $status;
	}

	private function get_user_id_from_request( $request ) {
		$user_id = $request->get_param( 'user_id' );

		if ( $user_id > 1e10 ) {
			$remote_user_id = get_term_by( 'id', intval( $user_id ) - 1e10, self::REMOTE_USER_TAXONOMY );
			if ( $remote_user_id ) {
				return $remote_user_id->name;
			}
		}

		return $user_id;
	}

	/**
	 * Validate the type of an entity.
	 *
	 * @param object $entity The entity object.
	 * @param string $type The entity types.
	 * @return object|WP_Error Entity or WP_Error object
	 */
	private function validate_entity( $entity, $type ) {
		if ( ! $entity instanceof $type ) {
			return new \WP_Error( 'invalid-entity', 'Invalid entity, not one of ' . $type, array( 'status' => 404 ) );
		}

		if ( ! $entity->is_valid() ) {
			return new \WP_Error( 'integrity-error', 'Integrity Error', array( 'status' => 500 ) );
		}

		return $entity;
	}

	public function api_submit_post( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Insufficient permissions', array( 'status' => 401 ) );
		}

		$status = $request->get_param( 'status' );
		if ( empty( $status ) ) {
			return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Validation failed: Text can\'t be blank', array( 'status' => 422 ) );
		}

		$status = make_clickable( $status );
		if ( class_exists( '\Activitypub\Mention' ) ) {
			$status = \Activitypub\Mention::the_content( $status );
		}
		$status = trim( $status );

		$visibility = $request->get_param( 'visibility' );
		if ( empty( $visibility ) ) {
			$visibility = 'public';
		}
		$post_data = array();

		$parent_post    = false;
		$parent_post_id = $request->get_param( 'in_reply_to_id' );
		if ( ! empty( $parent_post_id ) ) {
			$parent_post = get_post( $parent_post_id );
			if ( $parent_post && get_option( 'mastodon_api_reply_as_comment' ) ) {
				$user = wp_get_current_user();

				$commentdata = array(
					'comment_post_ID'      => $parent_post_id,
					'comment_author'       => $user->user_nicename,
					'user_id'              => get_current_user_id(),
					'comment_author_email' => $user->user_email,
					'comment_author_url'   => $user->user_url,
					'comment_content'      => $status,
					'comment_type'         => 'comment',
					'comment_parent'       => 0,
					'comment_meta'         => array(
						'protocol' => 'activitypub',
					),
				);

				\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

				$id = \wp_new_comment( $commentdata, true );
				if ( is_wp_error( $id ) ) {
					return $id;
				}

				\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

				return $this->get_comment_status_array( get_comment( $id ) );
			}

			if ( ! $parent_post ) {
				$post_data['post_meta_input'] = array(
					'activitypub_in_reply_to' => $parent_post_id,
				);
			}
		}

		$post_data['post_content'] = $status;
		$post_data['post_status']  = 'public' === $visibility ? 'publish' : 'private';
		$post_data['post_type']    = 'post';
		$post_data['post_title']   = '';

		$app = Mastodon_App::get_current_app();
		$app_post_formats = array();
		if ( $app ) {
			$app_post_formats = $app->get_post_formats();
		}
		if ( empty( $app_post_formats ) ) {
			$app_post_formats = array( 'status' );
		}
		$post_format = apply_filters( 'mastodon_api_new_post_format', $app_post_formats[0] );
		if ( 'standard' === $post_format ) {
			// Use the first line of a post as the post title if we're using a standard post format.
			list( $post_title, $post_content ) = explode( PHP_EOL, $post_data['post_content'], 2 );
			$post_data['post_title']   = $post_title;
			$post_data['post_content'] = trim( $post_content );
		}

		if ( $parent_post ) {
			$post_data['post_parent'] = $parent_post->ID;
		}

		$scheduled_at = $request->get_param( 'scheduled_at' );
		if ( $scheduled_at ) {
			$post_data['post_status'] = 'future';
			$post_data['post_date'] = $scheduled_at;
		}

		$media_ids = $request->get_param( 'media_ids' );
		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				$media = get_post( $media_id );
				if ( ! $media ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				if ( 'attachment' !== $media->post_type ) {
					return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Media not found', array( 'status' => 400 ) );
				}
				$attachment = \wp_get_attachment_metadata( $media_id );
				$post_data['post_content'] .= PHP_EOL;
				$post_data['post_content'] .= '<!-- wp:image -->';
				$post_data['post_content'] .= '<p><img src="' . esc_url( wp_get_attachment_url( $media_id ) ) . '" width="' . esc_attr( $attachment['width'] ) . '"  height="' . esc_attr( $attachment['height'] ) . '" class="size-full" /></p>';
				$post_data['post_content'] .= '<!-- /wp:image -->';
			}
		}

		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( 'standard' !== $post_format ) {
			set_post_format( $post_id, $post_format );
		}

		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				wp_update_post(
					array(
						'ID'          => $media_id,
						'post_parent' => $post_id,
					)
				);
			}
		}

		if ( $scheduled_at ) {
			return array(
				'id'           => $post_id,
				'scheduled_at' => $scheduled_at,
				'params'       => array(
					'text'         => $status,
					'visibility'   => $visibility,
					'scheduled_at' => $scheduled_at,
				),
			);
		}

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

		return $status;
	}

	/**
	 * Get a Media_Attachment entity by ID.
	 *
	 * @param WP_REST_Request $request The full data about the request.
	 * @return Entity\Media_Attachment|\WP_Error
	 */
	public function api_get_media( WP_REST_Request $request ) {
		$attachment_id = $request->get_param( 'post_id' );
		if ( ! is_numeric( $attachment_id ) || $attachment_id < 0 ) {
			return new \WP_Error( 'mastodon_api_get_media', 'Invalid post ID', array( 'status' => 400 ) );
		}

		/**
		 * Filters the media attachment returned by the API.
		 *
		 * @param array $media_attachment The media attachment.
		 * @param int   $attachment_id    The attachment ID.
		 * @return Entity\Media_Attachment The media attachment.
		 */
		return apply_filters( 'mastodon_api_media_attachment', null, $attachment_id );
	}

	/**
	 * Create a new media attachment.
	 *
	 * @param WP_REST_Request $request The full data about the request.
	 * @return \WP_Error|WP_REST_Response
	 */
	public function api_post_media( WP_REST_Request $request ) {
		$media = $request->get_file_params();
		if ( empty( $media ) ) {
			return new \WP_Error( 'mastodon_api_post_media', 'Media is empty', array( 'status' => 422 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! isset( $media['file']['name'] ) || false === strpos( $media['file']['name'], '.' ) ) {
			switch ( $media['file']['type'] ) {
				case 'image/png':
					$media['file']['name'] = 'image.png';
					break;
				case 'image/jpeg':
					$media['file']['name'] = 'image.jpg';
					break;
				case 'image/gif':
					$media['file']['name'] = 'image.gif';
					break;
			}
		}
		$attachment_id = \media_handle_sideload( $media['file'] );
		if ( is_wp_error( $attachment_id ) ) {
			return new \WP_Error( 'mastodon_api_post_media', $attachment_id->get_error_message(), array( 'status' => 422 ) );
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) ) );

		$request->set_param( 'post_id', $attachment_id );

		$description = $request->get_param( 'description' );
		if ( $description ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => $description,
				)
			);
		}

		return rest_ensure_response( $this->api_get_media( $request ) );
	}

	/**
	 * Update a media attachment.
	 *
	 * @param WP_REST_Request $request The full data about the request.
	 * @return \WP_Error|WP_REST_Response
	 */
	public function api_update_media( WP_REST_Request $request ) {
		$attachment_id = $request->get_param( 'post_id' );
		if ( ! is_numeric( $attachment_id ) || $attachment_id < 0 ) {
			return new \WP_Error( 'mastodon_api_get_media', 'Invalid post ID', array( 'status' => 400 ) );
		}

		$description = $request->get_param( 'description' );
		if ( $description ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => $description,
				)
			);
		}
		return rest_ensure_response( $this->api_get_media( $request ) );
	}

	public function api_timelines( $request ) {
		/**
		 * Modify the timelines data returned for `/api/timelines/(home)` requests.
		 *
		 * @param Entity\Status[] $statuses The statuses data.
		 * @param WP_REST_Request $request  The request object.
		 * @return Entity\Status[] The modified statuses data.
		 *
		 * Example:
		 * ```php
		 * ```
		 */
		return \apply_filters( 'mastodon_api_timelines', null, $request );
	}

	public function api_tag_timeline( $request ) {
		/**
		 * Modify the timelines data returned for `/api/timelines/(tag)/` requests.
		 *
		 * @param Entity\Status[] $statuses The statuses data.
		 * @param WP_REST_Request $request  The request object.
		 * @return Entity\Status[] The modified statuses data.
		 *
		 * Example:
		 * ```php
		 * ```
		 */
		return \apply_filters( 'mastodon_api_tag_timeline', null, $request );
	}

	public function api_public_timeline( $request ) {
		/**
		 * Modify the public timelines data returned for `/api/timelines/(public)` requests.
		 *
		 * @param Entity\Status[] $statuses The statuses data.
		 * @param WP_REST_Request $request  The request object.
		 * @return Entity\Status[] The modified statuses data.
		 *
		 * Example:
		 * ```php
		 * ```
		 */
		return \apply_filters( 'mastodon_api_public_timeline', null, $request );
	}

	public function api_accounts_search( $request ) {
		$request->set_param( 'type', 'accounts' );
		$ret = $this->api_search( $request );
		return $ret['accounts'];
	}

	/**
	 * Call out API request to search as WP filter.
	 *
	 * @param object $request Request object from WP.
	 *
	 * @return object
	 */
	public function api_search( object $request ) {
		return apply_filters( 'mastodon_api_search', null, $request );
	}

	public function api_push_subscription( $request ) {
		return array();
	}

	public function api_get_post_context( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		$post_id = $this->maybe_get_remapped_reblog_id( $post_id );

		$context = array(
			'ancestors'   => array(),
			'descendants' => array(),
		);

		$meta = get_post_meta( $post_id, 'activitypub', true );
		if ( $meta ) {
			$transient_key = 'mastodon_api_get_post_context_' . $post_id;
			$saved_context = get_transient( $transient_key );
			if ( $saved_context ) {
				// $context = $saved_context;
			} else {
				$post = get_post( $post_id );
				if ( preg_match( '#^https://([^/]+)/(?:users/)?[^/]+(?:/statuses)?/(.+)$#', $post->guid, $matches ) ) {
					$domain = $matches[1];
					$id = $matches[2];
					$context_api_url = 'https://' . $domain . '/api/v1/statuses/' . $id . '/context';
					$response = wp_safe_remote_get(
						$context_api_url,
						array(
							'timeout'     => apply_filters( 'friends_http_timeout', 20 ),
							'redirection' => 1,
						)
					);

					if ( ! is_wp_error( $response ) ) {
						$context = json_decode( wp_remote_retrieve_body( $response ), true );
						if ( isset( $context['error'] ) ) {
							$context = array_merge(
								$context,
								array(
									'ancestors'   => array(),
									'descendants' => array(),
								)
							);
						} else {
							foreach ( $context as $key => $posts ) {
								foreach ( $posts as $index => $post ) {
									if ( false === strpos( $post['account']['acct'], '@' ) ) {
										$post['account']['acct'] .= '@' . $domain;
									}
									$context[ $key ][ $index ]['account']['id'] = $post['account']['acct'];
								}
							}
						}

						set_transient( $transient_key, $context, HOUR_IN_SECONDS );
					}
				}
			}
		}

		$comment_id = $this->get_remapped_comment_id( $post_id );
		if ( $comment_id ) {
			$comment = get_comment( $comment_id );
			$post_id = $comment->comment_post_ID;

			/**
			 * Modify the status data.
			 *
			 * @param array|null $account The status data.
			 * @param int $post_id The object ID to get the status from.
			 * @param array $data Additional status data.
			 * @return array|null The modified status data.
			 */
			$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

			if ( $status ) {
				$context['ancestors'][] = $status;
			}
		}

		foreach ( get_comments( array( 'post_id' => $post_id ) ) as $comment ) {
			if ( intval( $comment->comment_ID ) === $comment_id ) {
				continue;
			}
			$status = $this->get_comment_status_array( $comment );
			if ( $status ) {
				$context['descendants'][] = $status;
			}
		}

		return $context;
	}

	public function api_favourite_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		$post_id = $this->maybe_get_remapped_reblog_id( $post_id );

		// 2b50 = star
		// 2764 = heart
		do_action( 'mastodon_api_react', $post_id, '2b50' );

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

		return $status;
	}

	public function api_unfavourite_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		$post_id = $this->maybe_get_remapped_reblog_id( $post_id );

		// 2b50 = star
		// 2764 = heart
		do_action( 'mastodon_api_unreact', $post_id, '2b50' );

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

		return $status;
	}

	public function api_reblog_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		$post_id = $this->maybe_get_remapped_reblog_id( $post_id );

		$post = get_post( $post_id );

		if ( $post ) {
			do_action( 'mastodon_api_reblog', $post );
		}

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

		return $status;
	}

	public function api_unreblog_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		$post_id = $this->maybe_get_remapped_reblog_id( $post_id );

		$post = get_post( $post_id );
		if ( $post ) {
			do_action( 'mastodon_api_unreblog', $post );
		}

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

		return $status;
	}

	public function api_delete_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		$comment_id = $this->get_remapped_comment_id( $post_id );
		if ( $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( intval( $comment->user_id ) === get_current_user_id() ) {
				wp_trash_comment( $comment_id );
			}
			return $this->get_comment_status_array( get_comment( $comment_id ) );
		}

		$post = get_post( $post_id );
		if ( intval( $post->post_author ) === get_current_user_id() ) {
			wp_trash_post( $post_id );
		}

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

		return $status;
	}

	public function api_get_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return new WP_REST_Response( array( 'error' => 'Record not found' ), 404 );
		}

		if ( get_post_status( $post_id ) !== 'publish' && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'error' => 'Record not found' ), 404 );
		}

		$comment_id = $this->get_remapped_comment_id( $post_id );
		if ( $comment_id ) {
			return $this->get_comment_status_array( get_comment( $comment_id ) );
		}

		$post_id = $this->maybe_get_remapped_reblog_id( $post_id );
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_REST_Response( array( 'error' => 'Record not found' ), 404 );
		}

		/**
		 * Modify the status data.
		 *
		 * @param array|null $account The status data.
		 * @param int $post_id The object ID to get the status from.
		 * @param array $data Additional status data.
		 * @return array|null The modified status data.
		 */
		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );

		return $this->validate_entity( $status, Entity\Status::class );
	}

	private function convert_outbox_to_status( $outbox, $user_id ) {
		$items = array();
		foreach ( $outbox['orderedItems'] as $item ) {
			$status = $this->convert_activity_to_status( $item, $user_id );
			if ( $status ) {
				$items[] = $status;
			}
		}
		return $items;
	}

	public function convert_activity_to_status( $activity, $user_id ) {
		if ( ! isset( $activity['object']['type'] ) || 'Note' !== $activity['object']['type'] ) {
			return null;
		}

		$id_parts = explode( '/', $activity['object']['id'] );
		return array(
			'id'                     => array_pop( $id_parts ),
			'created_at'             => $activity['object']['published'],
			'in_reply_to_id'         => $activity['object']['inReplyTo'],
			'in_reply_to_account_id' => null,
			'sensitive'              => $activity['object']['sensitive'],
			'spoiler_text'           => '',
			'language'               => null,
			'uri'                    => $activity['object']['id'],
			'url'                    => $activity['object']['url'],
			'muted'                  => false,
			'replies_count'          => 0, // could be fetched.
			'reblogs_count'          => 0,
			'favourites_count'       => 0,
			'edited_at'              => null,
			'favourited'             => false,
			'reblogged'              => false,
			'muted'                  => false,
			'bookmarked'             => false,
			'content'                => $activity['object']['content'],
			'filtered'               => array(),
			'reblog'                 => null,
			'account'                => $this->get_friend_account_data( $user_id ),
			'media_attachments'      => array_map(
				function ( $attachment ) {
					$request = new WP_REST_Request();
					$request->set_param( 'post_id', $attachment->id );

					return $this->api_get_media( $request );
				},
				$activity['object']['attachment']
			),
			'mentions'               => array_values(
				array_map(
					function ( $mention ) {
						return array(
							'id'       => $mention['href'],
							'username' => $mention['name'],
							'acct'     => $mention['name'],
							'url'      => $mention['href'],
						);
					},
					array_filter(
						$activity['object']['tag'],
						function ( $tag ) {
							if ( isset( $tag['type'] ) ) {
								return 'Mention' === $tag['type'];
							}
							return false;
						}
					)
				)
			),

			'tags'                   => array_values(
				array_map(
					function ( $tag ) {
						return array(
							'name' => $tag['name'],
							'url'  => $tag['href'],
						);
					},
					array_filter(
						$activity['object']['tag'],
						function ( $tag ) {
							if ( isset( $tag['type'] ) ) {
								return 'Hashtag' === $tag['type'];
							}
							return false;
						}
					)
				)
			),

			'emojis'                 => array(),
			'card'                   => null,
			'poll'                   => null,
		);
	}

	public function api_account_statuses( $request ) {
		$user_id = $this->get_user_id_from_request( $request );

		if ( preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $user_id ) ) {
			$url = $this->get_activitypub_url( $user_id );
			if ( $url ) {
				$account = $this->get_acct( $user_id );
				$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $url );
				if ( $meta && ! is_wp_error( $meta ) ) {
					$outbox = $this->get_json( $meta['outbox'], 'outbox-' . $account, array( 'first' => null ) );
					$outbox_page = $this->get_json( $outbox['first'], 'outboxpage-' . $account, array( 'orderedItems' => array() ) );

					$items = $this->convert_outbox_to_status( $outbox_page, $user_id );
					return $items;
				}
			}
		}

		$args = $this->get_posts_query_args( $request );
		if ( empty( $args ) ) {
			return array();
		}
		$args['author'] = $user_id;
		if ( class_exists( '\Friends\User' ) ) {
			$user = \Friends\User::get_user_by_id( $user_id );

			if (
				$user instanceof \Friends\User
				&& method_exists( $user, 'modify_get_posts_args_by_author' )
			) {
				$args = $user->modify_get_posts_args_by_author( $args );
			}
		}

		$args = apply_filters( 'mastodon_api_account_statuses_args', $args, $request );

		/**
		 * Filter the account statuses.
		 *
		 * @param array|null $statuses Current statuses.
		 * @param array $args Current statuses arguments.
		 * @param int|null $min_id Optional minimum status ID.
		 * @param int|null $max_id Optional maximum status ID.
		 */
		$statuses = apply_filters( 'mastodon_api_statuses', null, $args, null, null );

		if ( is_wp_error( $statuses ) || empty( $statuses ) ) {
			return new \WP_Error( 'invalid-statuses', 'Invalid statuses', array( 'status' => 404 ) );
		}

		return $statuses;
	}

	/**
	 * Get the account followers.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function api_account_followers( WP_REST_Request $request ) {
		$user_id = $this->get_user_id_from_request( $request );

		/**
		 * Modify the account followers.
		 *
		 * @param array           $followers The account followers.
		 * @param string          $user_id   The user ID.
		 * @param WP_REST_Request $request   The request object.
		 * @return array The modified account followers.
		 *
		 * Example:
		 * ```php
		 * apply_filters( 'mastodon_api_account_followers', function ( $followers, $user_id, $request ) {
		 *    $account     = new Entity\Account();
		 *    $account->id = $user_id
		 *
		 *    $followers[] = $account;
		 *
		 *    return $followers;
		 * } );
		 */
		$followers = \apply_filters( 'mastodon_api_account_followers', array(), $user_id, $request );

		if ( is_wp_error( $followers ) ) {
			return $followers;
		}

		$followers = array_filter(
			$followers,
			function ( $follower ) {
				return $follower instanceof Entity\Account;
			}
		);

		return new WP_REST_Response( $followers );
	}

	/**
	 * Follow the given account.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function api_account_follow( WP_REST_Request $request ) {
		$user_id = $this->get_user_id_from_request( $request );

		/**
		 * Follow the given account.
		 *
		 * Can also be used to update whether to show reblogs or enable notifications.
		 *
		 * @param string          $user_id The user ID.
		 * @param WP_REST_Request $request The request object.
		 */
		do_action( 'mastodon_api_account_follow', $user_id, $request );

		return rest_ensure_response( $this->get_relationship( $user_id, $request ) );
	}

	/**
	 * Unfollow the given account.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function api_account_unfollow( WP_REST_Request $request ) {
		$user_id = $this->get_user_id_from_request( $request );

		/**
		 * Unfollow the given account.
		 *
		 * @param string          $user_id The user ID.
		 * @param WP_REST_Request $request The request object.
		 */
		do_action( 'mastodon_api_account_unfollow', $user_id, $request );

		return rest_ensure_response( $this->get_relationship( $user_id, $request ) );
	}

	/**
	 * Get the account relationships.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function api_account_relationships( WP_REST_Request $request ) {
		$relationships = array();
		$user_ids      = $request->get_param( 'id' );
		if ( ! is_array( $user_ids ) ) {
			$user_ids = array( $user_ids );
		}

		foreach ( $user_ids as $user_id ) {
			$relationship = $this->get_relationship( $user_id, $request );
			if ( is_wp_error( $relationship ) ) {
				return $relationship;
			}

			$relationships[] = $relationship;
		}

		/**
		 * Modify the account relationships.
		 *
		 * @param array           $relationships The account relationships.
		 * @param array           $user_ids      The user IDs.
		 * @param WP_REST_Request $request       The request object.
		 * @return array The modified account relationships.
		 *
		 * Example:
		 * ```php
		 * apply_filters( 'mastodon_api_account_relationships', function ( $relationships, $user_ids, $request ) {
		 *    $relationships[0]->following = true;
		 *
		 *    return $relationships;
		 * } );
		 */
		$relationships = apply_filters( 'mastodon_api_account_relationships', $relationships, $user_ids, $request );

		$relationships = array_filter(
			$relationships,
			function ( $relationship ) {
				return $relationship instanceof Entity\Relationship;
			}
		);

		return rest_ensure_response( $relationships );
	}

	/**
	 * Relationship entity for the given user.
	 *
	 * @param string          $user_id The user ID.
	 * @param WP_REST_Request $request The request object.
	 * @return Entity\Relationship|\WP_Error The modified account relationships or WP_Error if the user is invalid.
	 */
	private function get_relationship( string $user_id, WP_REST_Request $request ) {
		/**
		 * Modify the account relationship.
		 *
		 * @param array           $relationship The account relationship.
		 * @param string          $user_id      The user ID.
		 * @param WP_REST_Request $request      The request object.
		 * @return Entity\Relationship The relationship entity.
		 *
		 * Example:
		 * ```php
		 * apply_filters( 'mastodon_api_relationship', function ( $relationship, $user_id, $request ) {
		 *      $user = get_user_by( 'ID', $user_id );
		 *
		 *      $relationship     = new Entity\Relationship();
		 *      $relationship->id = strval( $user->ID );
		 *
		 *      return $relationship;
		 * } );
		 * ```
		 */
		$relationship = apply_filters( 'mastodon_api_relationship', null, $user_id, $request );

		return $this->validate_entity( $relationship, Entity\Relationship::class );
	}

	/**
	 * Call out API request to clear all notifications as WP action.
	 *
	 * @param object $request Request object from WP.
	 *
	 * @return object
	 */
	public function api_notification_clear( object $request ): object {

		do_action( 'mastodon_api_notification_clear', $request );

		return (object) array();
	}

	/**
	 * Call out API request to clear one notification as WP action.
	 *
	 * @param object $request Request object from WP.
	 *
	 * @return object
	 */
	public function api_notification_dismiss( object $request ): object {

		do_action( 'mastodon_api_notification_dismiss', $request );

		return (object) array();
	}

	/**
	 * Call out API request to get one notification as WP filter.
	 *
	 * @param object $request Request object from WP.
	 *
	 * @return object
	 */
	public function api_notification_get( object $request ): object {
		return apply_filters( 'mastodon_api_notification_get', null, $request );
	}

	/**
	 * Call out API request to get notifications as WP filter.
	 *
	 * @param object $request Request object from WP.
	 *
	 * @return array
	 */
	public function api_notifications_get( object $request ): array {
		return apply_filters( 'mastodon_api_notifications_get', array(), $request );
	}

	public function api_preferences( $request ) {
		$preferences = array(
			'posting:default:language'   => self::get_mastodon_language( get_user_locale() ),
			'posting:default:visibility' => apply_filters( 'mastodon_api_account_visibility', 'public', wp_get_current_user() ),
		);
		return $preferences;
	}

	public function api_account( $request ) {
		$user_id = $this->get_user_id_from_request( $request );

		/**
		 * Modify the account data returned for `/api/account/{user_id}` requests.
		 *
		 * @param Entity\Account|null $account The account data.
		 * @param int $user_id The requested user ID.
		 * @param WP_REST_Request $request The request object.
		 * @return Entity\Account|null The modified account data.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_account', function( $user_data, $user_id ) {
		 *     $user = get_user_by( 'ID', $user_id );
		 *
		 *     $account                 = new Account_Entity();
		 *     $account->id             = strval( $user->ID );
		 *     $account->username       = $user->user_login;
		 *     $account->display_name   = $user->display_name;
		 *
		 *     return $account;
		 * } );
		 * ```
		 */
		$account = \apply_filters( 'mastodon_api_account', null, $user_id, $request );

		return $this->validate_entity( $account, Entity\Account::class );
	}

	/**
	 * Check whether this is a valid URL
	 *
	 * @param string $url The URL to check.
	 * @return false|string URL or false on failure.
	 */
	public static function check_url( $url ) {
		$host = parse_url( $url, PHP_URL_HOST );

		$check_url = apply_filters( 'friends_host_is_valid', null, $host );
		if ( ! is_null( $check_url ) ) {
			return $check_url;
		}

		return wp_http_validate_url( $url );
	}

	public function get_json( $url, $transient_key, $fallback = null, $force_retrieval = false ) {
		$expiry_key = $transient_key . '_expiry';
		$transient_expiry = \get_transient( $expiry_key );
		$response = \get_transient( $transient_key );
		if ( $transient_expiry < time() ) {
			// Re-retrieve it later.
			wp_schedule_single_event( time(), 'enable_mastodon_apps_get_json', array( $url, $transient_key, $fallback, true ) );
		}
		if ( $response && ! $force_retrieval ) {
			if ( is_wp_error( $response ) ) {
				if ( $fallback && 'http_request_failed' !== $response->get_error_code() ) {
					return $fallback;
				}
			} else {
				return $response;
			}
		}

		$response = \wp_remote_get(
			$url,
			array(
				'headers'     => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 2,
				'timeout'     => 5,
			)
		);

		if ( \is_wp_error( $response ) ) {
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			if ( $fallback ) {
				return $fallback;
			}
			return $response;
		}

		if ( \is_wp_error( $response ) ) {
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			if ( $fallback ) {
				return $fallback;
			}
			return $response;
		}

		$body = \wp_remote_retrieve_body( $response );
		$body = \json_decode( $body, true );

		\set_transient( $transient_key, $body, YEAR_IN_SECONDS );
		\set_transient( $expiry_key, time() + HOUR_IN_SECONDS );

		return $body;
	}

	private function update_account_data_with_meta( $data, $meta, $full_metadata = false ) {
		if ( ! $meta || is_wp_error( $meta ) || isset( $meta['error'] ) ) {
			if ( empty( $data['username'] ) || ! $data['username'] ) {
				$data['username'] = strtok( $data['id'], '@' );
			}
			if ( empty( $data['display_name'] ) || ! $data['display_name'] ) {
				$data['display_name'] = $data['username'];
			}

			if ( empty( $data['created_at'] ) || ! $data['created_at'] ) {
				$data['created_at'] = '1970-01-01T00:00:00Z';
			}
			if ( ! $data['last_status_at'] ) {
				$data['last_status_at'] = $data['created_at'];
			}

			return $data;
		}
		if ( $full_metadata ) {
			$followers = $this->get_json( $meta['followers'], 'followers-' . $data['acct'], array( 'totalItems' => 0 ) );
			$following = $this->get_json( $meta['following'], 'following-' . $data['acct'], array( 'totalItems' => 0 ) );
			$outbox = $this->get_json( $meta['outbox'], 'outbox-' . $data['acct'], array( 'totalItems' => 0 ) );
			$data['followers_count'] = intval( $followers['totalItems'] );
			$data['following_count'] = intval( $following['totalItems'] );
			$data['statuses_count'] = intval( $outbox['totalItems'] );
		}

		if ( empty( $data['username'] ) || ! $data['username'] ) {
			if ( isset( $meta['preferredUsername'] ) && $meta['preferredUsername'] ) {
				$data['username'] = $meta['preferredUsername'];
			} else {
				$data['username'] = strtok( $data['id'], '@' );
			}
		}
		if ( empty( $data['display_name'] ) || ! $data['display_name'] ) {
			if ( isset( $meta['name'] ) && $meta['name'] ) {
				$data['display_name'] = $meta['name'];
			} else {
				$data['display_name'] = $data['username'];
			}
		}
		if ( ! empty( $meta['summary'] ) ) {
			$data['note'] = (string) $meta['summary'];
		}
		if ( empty( $data['created_at'] ) || ! $data['created_at'] ) {
			if ( isset( $meta['published'] ) && $meta['published'] ) {
				$data['created_at'] = $meta['published'];
			} else {
				$data['created_at'] = '1970-01-01T00:00:00Z';
			}
		}
		if ( ! $data['last_status_at'] ) {
			$data['last_status_at'] = $data['created_at'];
		}
		$data['url'] = $meta['url'];
		if ( isset( $meta['icon'] ) ) {
			$data['avatar'] = $meta['icon']['url'];
			$data['avatar_static'] = $meta['icon']['url'];
		}
		if ( isset( $meta['image'] ) ) {
			$data['header'] = $meta['image']['url'];
			$data['header_static'] = $meta['image']['url'];
		}
		if ( isset( $meta['discoverable'] ) ) {
			$data['discoverable'] = $meta['discoverable'];
		}
		return $data;
	}

	private function get_friend_account_data( $user_id, $meta = array(), $full_metadata = false ) {
		$external_user = apply_filters( 'mastodon_api_external_mentions_user', null );
		$is_external_mention = $external_user && strval( $external_user->ID ) === strval( $user_id );
		if ( $is_external_mention && isset( $meta['attributedTo']['id'] ) ) {
			$user_id = $meta['attributedTo']['id'];
		}

		$cache_key = 'account-' . $user_id;

		$url = false;
		if (
			preg_match( '#^https?://[^/]+/@[a-z0-9-]+$#i', $user_id )
			|| preg_match( '#^https?://[^/]+/(users|author)/[a-z0-9-]+$#i', $user_id )
		) {
			$url = $user_id;
		}

		if (
			preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $user_id )
			|| $url
		) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'not-logged-in', 'Not logged in', array( 'status' => 401 ) );
			}
			$account = $this->get_acct( $user_id );

			if ( $account ) {
				$remote_user_id = get_term_by( 'name', $account, self::REMOTE_USER_TAXONOMY );
				if ( $remote_user_id ) {
					$remote_user_id = $remote_user_id->term_id;
				} else {
					$remote_user_id = wp_insert_term( $account, self::REMOTE_USER_TAXONOMY );
					if ( ! is_wp_error( $remote_user_id ) ) {
						$remote_user_id = $remote_user_id['term_id'];
					}
				}
			} elseif ( $user_id ) {
				$remote_user_id = get_term_by( 'name', $user_id, self::REMOTE_USER_TAXONOMY );
				if ( $remote_user_id ) {
					$remote_user_id = $remote_user_id->term_id;
				} else {
					$remote_user_id = wp_insert_term( $user_id, self::REMOTE_USER_TAXONOMY );
					if ( ! is_wp_error( $remote_user_id ) ) {
						$remote_user_id = $remote_user_id['term_id'];
					}
				}
			}

			if ( $remote_user_id ) {
				$cache_key = 'account-' . ( 1e10 + $remote_user_id );
			}
		}

		$ret = wp_cache_get( $cache_key, 'enable-mastodon-apps' );
		if ( false !== $ret ) {
			return $ret;
		}

		// Data URL of an 1x1px transparent png.
		$placeholder_image = 'https://files.mastodon.social/media_attachments/files/003/134/405/original/04060b07ddf7bb0b.png';
		// $placeholder_image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

		if (
			preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $user_id )
			|| $url
		) {
			if ( ! $remote_user_id ) {
				return null;
			}

			if ( ! $url ) {
				if ( isset( $meta['attributedTo']['id'] ) ) {
					$url = $meta['attributedTo']['id'];
					$user_id = $meta['attributedTo']['id'];
				} else {
					$url = $this->get_activitypub_url( $user_id );
				}
			}
			if ( ! $url ) {
				$data = new \WP_Error( 'user-not-found', 'User not found.', array( 'status' => 404 ) );
				wp_cache_set( $cache_key, $data, 'enable-mastodon-apps' );
				return $data;
			}
			$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $url );

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

			$data = $this->update_account_data_with_meta( $data, $meta, $full_metadata );

			wp_cache_set( $cache_key, $data, 'enable-mastodon-apps' );

			return $data;
		}

		$user = false;
		if ( class_exists( '\Friends\User' ) ) {
			$user = \Friends\User::get_user_by_id( $user_id );

			if ( $user instanceof \Friends\Subscription ) {
				$remote_user_id = get_term_by( 'name', $user->ID, self::REMOTE_USER_TAXONOMY );
				if ( $remote_user_id ) {
					$remote_user_id = $remote_user_id->term_id;
				} else {
					$remote_user_id = wp_insert_term( $user->ID, self::REMOTE_USER_TAXONOMY );
					if ( ! is_wp_error( $remote_user_id ) ) {
						$remote_user_id = $remote_user_id['term_id'];
					}
				}
				$user->ID = 1e10 + $remote_user_id;
			}
		}

		if ( ! $user || is_wp_error( $user ) ) {
			$user = new \WP_User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				$data = new \WP_Error( 'user-not-found', 'User not found.', array( 'status' => 404 ) );
				wp_cache_set( $cache_key, $data, 'enable-mastodon-apps' );
				return $data;
			}
		}
		$followers_count = 0;
		$following_count = 0;
		$avatar = get_avatar_url( $user->ID );
		if ( $user instanceof \Friends\User ) {
			$posts = $user->get_post_count_by_post_format();
		} else {
			// Get post count for the post format status for the user.
			$posts = array(
				'status' => count_user_posts( $user->ID, 'post', true ),
			);
		}
		if ( get_current_user_id() === $user->ID ) {
			if ( class_exists( '\Activitypub\Peer\Followers', false ) ) {
				$followers_count = count( \Activitypub\Peer\Followers::get_followers( $user->ID ) );
			}
			if ( class_exists( '\Activitypub\Collection\Followers', false ) ) {
				$followers_count = count( \Activitypub\Collection\Followers::get_followers( $user->ID ) );
			}
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
			'followers_count' => $followers_count,
			'following_count' => $following_count,
			'statuses_count'  => isset( $posts['status'] ) ? intval( $posts['status'] ) : 0,
			'last_status_at'  => mysql2date( 'Y-m-d\TH:i:s.000P', $user->user_registered, false ),
			'fields'          => array(),
			'locked'          => false,
			'emojis'          => array(),
			'url'             => get_author_posts_url( $user->ID ),
			'source'          => array(
				'privacy'   => apply_filters( 'mastodon_api_account_visibility', 'public', $user ),
				'sensitive' => false,
				'language'  => self::get_mastodon_language( get_user_locale( $user->ID ) ),
				'note'      => '',
				'fields'    => array(),
			),
			'bot'             => false,
			'discoverable'    => true,
		);

		if ( isset( $meta['attributedTo']['id'] ) && $is_external_mention ) {
			$data['acct'] = $this->get_acct( $meta['attributedTo']['id'] );
			$data['id'] = $data['acct'];
			$data['username'] = strtok( $data['acct'], '@' );

			$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $meta['attributedTo']['id'] );
			$data = $this->update_account_data_with_meta( $data, $meta, $full_metadata );
		} else {
			$acct = $this->get_user_acct( $user );
			if ( $acct ) {
				$data['acct'] = $acct;
			}

			foreach ( apply_filters( 'friends_get_user_feeds', array(), $user ) as $feed ) {
				$meta = apply_filters( 'friends_get_feed_metadata', array(), $feed );
				if ( $meta && ! is_wp_error( $meta ) && ! isset( $meta['error'] ) ) {
					$data['acct'] = $this->get_acct( $meta['id'] );
					$data = $this->update_account_data_with_meta( $data, $meta, $full_metadata );
					break;
				}
			}
		}

		wp_cache_set( $cache_key, $data, 'enable-mastodon-apps' );
		return $data;
	}

	public static function get_mastodon_language( $lang ) {
		if ( false === strpos( $lang, '_' ) ) {
			return $lang . '_' . strtoupper( $lang );
		}
		return $lang;
	}

	public function get_user_acct( $user ) {
		return strtok( $this->get_acct( get_author_posts_url( $user->ID ) ), '@' );
	}

	public function get_acct( $id_or_url ) {
		if ( is_wp_error( $id_or_url ) ) {
			return '';
		}
		$webfinger = $this->webfinger( $id_or_url );
		if ( is_wp_error( $webfinger ) || ! isset( $webfinger['subject'] ) ) {
			return '';
		}
		if ( substr( $webfinger['subject'], 0, 5 ) === 'acct:' ) {
			return substr( $webfinger['subject'], 5 );
		}
		return $webfinger['subject'];
	}

	public function get_activitypub_url( $id_or_url ) {
		$webfinger = $this->webfinger( $id_or_url );
		if ( is_wp_error( $webfinger ) || empty( $webfinger['links'] ) ) {
			return false;
		}
		foreach ( $webfinger['links'] as $link ) {
			if ( ! isset( $link['rel'] ) ) {
				continue;
			}
			if ( 'self' === $link['rel'] && 'application/activity+json' === $link['type'] && in_array( $link['href'], $webfinger['aliases'] ) ) {
				return $link['href'];
			}
		}

		if ( ! empty( $webfinger['aliases'] ) ) {
			return $webfinger['aliases'][0];
		}

		return false;
	}

	private function webfinger( $id_or_url ) {
		if ( strpos( $id_or_url, 'acct:' ) === 0 ) {
			$id_or_url = substr( $id_or_url, 5 );
		}

		$body = apply_filters( 'mastodon_api_webfinger', null, $id_or_url );
		if ( $body ) {
			return $body;
		}

		$id = $id_or_url;
		if ( preg_match( '#^https://([^/]+)/(?:@|users/|author/)([^/]+)/?$#', $id_or_url, $m ) ) {
			$id = $m[2] . '@' . $m[1];
			$host = $m[1];
		} elseif ( false !== strpos( $id_or_url, '@' ) ) {
			$parts = explode( '@', ltrim( $id_or_url, '@' ) );
			$host = $parts[1];
		} else {
			return null;
		}

		$transient_key = 'mastodon_api_webfinger_' . md5( $id_or_url );

		$body = \get_transient( $transient_key );
		if ( $body ) {
			if ( is_wp_error( $body ) ) {
				return $id;
			}
			return $body;
		}

		$url = \add_query_arg( 'resource', 'acct:' . ltrim( $id, '@' ), 'https://' . $host . '/.well-known/webfinger' );
		if ( ! self::check_url( $url ) ) {
			$response = new \WP_Error( 'invalid_webfinger_url', null, $url );
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $response;
		}

		$body = $this->get_json( $url, $transient_key );

		if ( \is_wp_error( $body ) ) {
			$body = new \WP_Error( 'webfinger_url_not_accessible', null, $url );
			\set_transient( $transient_key, $body, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $body;
		}

		\set_transient( $transient_key, $body, WEEK_IN_SECONDS );
		return $body;
	}

	private function remap_reblog_id( $post_id ) {
		$remapped_post_id = get_post_meta( $post_id, 'mastodon_reblog_id', true );
		if ( ! $remapped_post_id ) {
			$remapped_post_id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_author' => 0,
					'post_status' => 'publish',
					'post_title'  => 'Reblog of ' . $post_id,
					'meta_input'  => array(
						'mastodon_reblog_id' => $post_id,
					),
				)
			);

			update_post_meta( $post_id, 'mastodon_reblog_id', $remapped_post_id );
		}
		return $remapped_post_id;
	}

	private function maybe_get_remapped_reblog_id( $remapped_post_id ) {
		$post_id = get_post_meta( $remapped_post_id, 'mastodon_reblog_id', true );
		if ( $post_id ) {
			return $post_id;
		}
		return $remapped_post_id;
	}

	private function remap_comment_id( $comment_id ) {
		$remapped_comment_id = get_comment_meta( $comment_id, 'mastodon_comment_id', true );
		if ( ! $remapped_comment_id ) {
			$remapped_comment_id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_author' => 0,
					'post_status' => 'publish',
					'post_title'  => 'Comment ' . $comment_id,
					'meta_input'  => array(
						'mastodon_comment_id' => $comment_id,
					),
				)
			);

			update_comment_meta( $comment_id, 'mastodon_comment_id', $remapped_comment_id );
		}
		return $remapped_comment_id;
	}

	private function get_remapped_comment_id( $remapped_comment_id ) {
		$comment_id = get_post_meta( $remapped_comment_id, 'mastodon_comment_id', true );
		if ( $comment_id ) {
			return $comment_id;
		}
		return false;
	}

	private function software_string() {
		global $wp_version;
		$software = 'WordPress/' . $wp_version;
		if ( defined( 'FRIENDS_VERSION' ) ) {
			$software .= ', Friends/' . FRIENDS_VERSION;
		}
		if ( defined( 'ACTIVITYPUB_VERSION' ) ) {
			$software .= ', ActivityPub/' . ACTIVITYPUB_VERSION;
		}
		$software .= ', EMA/' . self::VERSION;
		return $software;
	}

	public function api_nodeinfo() {
		global $wp_version;
		$software = array(
			'name'    => $this->software_string(),
			'version' => Mastodon_API::VERSION,
		);
		$software = apply_filters( 'mastodon_api_nodeinfo_software', $software );
		$ret = array(
			'metadata'          => array(
				'nodeName'        => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'nodeDescription' => html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
				'software'        => $software,
				'config'          => array(
					'features' => array(
						'timelines'   => array(
							'local'   => true,
							'network' => true,
						),
						'mobile_apis' => true,
						'stories'     => false,
						'video'       => false,
						'import'      => array(
							'instagram' => false,
							'mastodon'  => false,
							'pixelfed'  => false,
						),
					),
				),
			),
			'version'           => '2.0',
			'protocols'         => array(
				'activitpypub',
			),
			'services'          => array(
				'inbound'  => array(),
				'outbound' => array(),
			),

			'software'          => $software,
			'openRegistrations' => false,
		);

		return apply_filters( 'mastodon_api_nodeinfo', $ret );
	}

	public function api_announcements() {
		$ret = array();

		$app = Mastodon_App::get_current_app();
		if ( ! $app ) {
			return $ret;
		}

		$content   = array();
		$content[] = sprintf(
			// Translators: %1$s is a URL, %2$s is the domain of your blog.
			__( 'Using this Mastodon app is made possible by the <a href=%1$s>Enable Mastodon Apps WordPress plugin</a> on %2$s.', 'enable-mastodon-apps' ),
			'"https://wordpress.org/plugins/enable-mastodon-apps"',
			'<a href="' . home_url() . '">' . get_bloginfo( 'name' ) . '</a>'
		);

		$content[] = sprintf(
			// Translators: %s is the post formats.
			_n( 'Posts with the post format <strong>%s</strong> will appear in this app.', 'Posts with the post formats <strong>%s</strong> will appear in this app.', count( $app->get_post_formats() ), 'enable-mastodon-apps' ),
			implode( ', ', $app->get_post_formats() )
		);

		$content[] = sprintf(
			// Translators: %s is the post format.
			__( 'If you create a new note in this app, it will be created with the <strong>%s</strong> post format.', 'enable-mastodon-apps' ),
			reset( $app->get_post_formats() )
		);

		if ( 'standard' === reset( $app->get_post_formats() ) ) {
			$content[] = __( 'If you want to create a post in WordPress with a title, add a new line after the title. The first line will then appear as the title of the post.', 'enable-mastodon-apps' );
		} else {
			$content[] = __( 'Because a new post is not created in the standard post format, it will be published without title. To change this, select the <strong>standard</strong> post format in the Enable Mastodon Apps settings.', 'enable-mastodon-apps' );
		}

		$ret[] = array(
			'id'           => 1,
			'content'      => '<h1><strong>' . __( 'Mastodon Apps', 'enable-mastodon-apps' ) . '</strong></h1><p>' . implode( '</p><p>' . PHP_EOL, $content ) . '</p>',
			'published_at' => $app->get_creation_date(),
			'updated_at'   => time(),
			'starts_at'    => null,
			'ends_at'      => null,
			'all_day'      => false,
			'read'         => true,
			'mentions'     => array(),
			'statuses'     => array(),
			'tags'         => array(),
			'emojis'       => array(),
			'reactions'    => array(),

		);

		return $ret;
	}

	/**
	 * Returns the software instance of Mastodon running on this domain.
	 *
	 * @return WP_REST_Response The instance data.
	 */
	public function api_instance(): WP_REST_Response {
		/**
		 * Modify the instance data returned for `/api/instance` requests.
		 *
		 * @param array $ret The instance data.
		 *
		 * @return array The modified instance data.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_instance_v2', function( $instance_data ) {
		 *      return new Entity\Instance();
		 *  } );
		 * ```
		 */
		$instance = apply_filters( 'mastodon_api_instance_v1', null );

		return rest_ensure_response( $instance );
	}

	/**
	 * Returns the software instance of Mastodon running on this domain.
	 *
	 * @return WP_REST_Response The instance data.
	 */
	public function api_instance_v2(): WP_REST_Response {
		/**
		 * Modify the instance data returned for `/api/instance` requests.
		 *
		 * @param null $instance_data The instance data.
		 * @return Entity\Instance The instance object.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_instance_v2', function( $instance_data ) {
		 *     return new Entity\Instance();
		 * } );
		 * ```
		 */
		$instance = apply_filters( 'mastodon_api_instance_v2', null );

		return rest_ensure_response( $instance );
	}

	/**
	 * Returns the list of connected domains.
	 *
	 * @param WP_REST_Request $request The full request object.
	 * @return WP_REST_Response
	 */
	public function api_instance_peers( WP_REST_Request $request ): WP_REST_Response {
		$peers = get_bookmarks();
		$peers = wp_list_pluck( $peers, 'link_url' );

		/**
		 * Modify the instance peers returned for `/api/instance/peers` requests.
		 *
		 * @param array $peers The instance peers.
		 * @return array The modified instance peers.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_instance_peers', function( $peers ) {
		 *     $peers[] = 'https://example.com';
		 *
		 *     return $peers;
		 * } );
		 * ```
		 */
		$peers = apply_filters( 'mastodon_api_instance_peers', $peers );

		return rest_ensure_response( $peers );
	}

	/**
	 * Rules that the users of this service should follow.
	 *
	 * @param WP_REST_Request $request The full request object.
	 * @return WP_REST_Response
	 */
	public function api_instance_rules( WP_REST_Request $request ): WP_REST_Response {
		/**
		 * Modify the instance rules returned for `/api/instance/rules` requests.
		 *
		 * @param array $rules The instance rules.
		 * @return array The modified instance rules.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_instance_rules', function( $rules ) {
		 *     $rules[] = 'https://example.com';
		 *
		 *     return $rules;
		 * } );
		 * ```
		 */
		$rules = apply_filters( 'mastodon_api_instance_rules', array() );

		return rest_ensure_response( $rules );
	}

	/**
	 * Obtain a list of domains that have been blocked.
	 *
	 * @param WP_REST_Request $request The full request object.
	 * @return WP_REST_Response
	 */
	public function api_instance_domain_blocks( WP_REST_Request $request ): WP_REST_Response {
		/**
		 * Modify the instance domain_blocks returned for `/api/instance/domain_blocks` requests.
		 *
		 * @param array $domain_blocks The instance domain_blocks.
		 * @return Entity\Domain_Block[] The list of blocked domains.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_instance_domain_blocks', function( $domain_blocks ) {
		 *     $domain_blocks[] = new Entity\Domain_Block();
		 *
		 *     return $domain_blocks;
		 * } );
		 * ```
		 */
		$domain_blocks = apply_filters( 'mastodon_api_instance_domain_blocks', array() );

		return rest_ensure_response( $domain_blocks );
	}

	/**
	 * Obtain an extended description of this server.
	 *
	 * @param WP_REST_Request $request The full request object.
	 * @return WP_REST_Response
	 */
	public function api_instance_extended_description( WP_REST_Request $request ): WP_REST_Response {
		/**
		 * Modify the instance extended_description returned for `/api/instance/extended_description` requests.
		 *
		 * @param null $extended_description The instance extended_description.
		 * @return Entity\Extended_Description The extended description of this server.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_instance_extended_description', function( $description ) {
		 *     return new Entity\Extended_Description();
		 * } );
		 * ```
		 */
		$extended_description = apply_filters( 'mastodon_api_instance_extended_description', null );

		return rest_ensure_response( $extended_description );
	}

	/**
	 * Translation language pairs supported by the translation engine used by the server.
	 *
	 * @param WP_REST_Request $request The full request object.
	 * @return WP_REST_Response
	 */
	public function api_instance_translation_languages( WP_REST_Request $request ): WP_REST_Response {
		/**
		 * Modify the translation languages returned for `/api/instance/translation_languages` requests.
		 *
		 * @param array $translation_languages The instance translation_languages.
		 * @return array The modified instance translation_languages.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_instance_translation_languages', function( $translation_languages ) {
		 *     $translation_languages['en'] = array( 'de', 'es', 'fr', 'it', 'ja', 'nl', 'pl', 'pt', 'ru', 'zh' );
		 *
		 *     return $translation_languages;
		 * } );
		 * ```
		 */
		$translation_languages = apply_filters( 'mastodon_api_instance_translation_languages', array() );

		return rest_ensure_response( $translation_languages );
	}
}
