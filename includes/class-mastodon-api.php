<?php
/**
 * Mastodon API
 *
 * This contains the REST API handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function PHPUnit\Framework\returnCallback;

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
	const VERSION                     = ENABLE_MASTODON_APPS_VERSION;
	/**
	 * The OAuth handler.
	 *
	 * @var Mastodon_OAuth
	 */
	private $oauth;

	private static $last_error = false;

	const PREFIX         = 'enable-mastodon-apps';
	const APP_TAXONOMY   = 'mastodon-app';
	const REMAP_TAXONOMY = 'mastodon-api-remap';
	const CPT            = 'enable-mastodon-apps';
	const POST_CPT       = 'ema-post';

	/**
	 * Constructor
	 */
	public function __construct() {
		Mastodon_App::register_taxonomy();
		$this->oauth = new Mastodon_OAuth();
		$this->register_hooks();
		$this->register_taxonomy();
		$this->register_custom_post_types();
		new Mastodon_Admin( $this->oauth );

		// Register Handlers.
		new Handler\Account();
		new Handler\Instance();
		new Handler\Media_Attachment();
		new Handler\Notification();
		new Handler\Relationship();
		new Handler\Search();
		new Handler\Status();
		new Handler\Status_Source();
		new Handler\Timeline();
	}

	public function register_hooks() {
		add_action( 'wp_loaded', array( $this, 'rewrite_rules' ) );
		add_action( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'send_http_links' ), 10, 3 );
		add_filter( 'rest_pre_echo_response', array( $this, 'reformat_error_response' ), 10, 3 );
		add_filter( 'template_include', array( $this, 'log_404s' ) );
		add_filter( 'rest_json_encode_options', array( $this, 'rest_json_encode_options' ), 10, 2 );
		add_action( 'default_option_mastodon_api_default_post_formats', array( $this, 'default_option_mastodon_api_default_post_formats' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'rest_request_before_callbacks' ), 10, 3 );
		add_filter( 'rest_authentication_errors', array( $this, 'rest_authentication_errors' ) );
		add_filter( 'mastodon_api_mapback_user_id', array( $this, 'mapback_user_id' ) );
		add_filter( 'mastodon_api_in_reply_to_id', array( self::class, 'maybe_get_remapped_reblog_id' ), 15 );
		add_filter( 'activitypub_support_post_types', array( $this, 'activitypub_support_post_types' ) );
	}

	/**
	 * Allow the Mastodon API to be accessed via CORS.
	 *
	 * @param WP_REST_Request $request Request used to generate the response.
	 */
	public function allow_cors( $request ) {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: content-type, authorization' );
		header( 'Access-Control-Allow-Credentials: true' );
		if ( 'OPTIONS' === $request->get_method() ) {
			header( 'Access-Control-Allow-Origin: *', true, 204 );
		}
	}

	public function send_http_links( \WP_REST_Response $response, \WP_REST_Server $server, \WP_REST_Request $request ) {
		if ( 0 !== strpos( $request->get_route(), '/' . self::PREFIX ) ) {
			return $response;
		}

		foreach ( $response->get_links() as $rel => $link ) {
			if ( ! in_array( $rel, array( 'prev', 'next' ) ) ) {
				continue;
			}
			$response->link_header( $rel, $link[0]['href'] );
			$response->remove_link( $rel );
		}

		return $response;
	}

	/**
	 * Reformat error responses to match the Mastodon API.
	 *
	 * @see https://docs.joinmastodon.org/entities/Error/
	 *
	 * @param mixed            $result  The API result.
	 * @param \WP_REST_Server  $server  The REST server instance.
	 * @param \WP_REST_Request $request The REST request instance.
	 *
	 * @return array The reformatted result.
	 */
	public function reformat_error_response( $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
		if ( 0 !== strpos( $request->get_route(), '/' . self::PREFIX ) ) {
			return $result;
		}

		if ( http_response_code() < 400 ) {
			return $result;
		}

		if ( ! isset( $result['code'] ) && isset( $result['error'] ) && is_string( $result['error'] ) ) {
			$result['code']    = sanitize_title_with_dashes( $result['error'] );
			$result['message'] = $result['error'];
		}

		return array(
			'error'             => empty( $result['code'] ) ? __( 'unknown_error', 'enable-mastodon-apps' ) : $result['code'],
			'error_description' => empty( $result['message'] ) ? __( 'Unknown error', 'enable-mastodon-apps' ) : $result['message'],
		);
	}

	public function rest_json_encode_options( $options, $request ) {
		if ( 0 === strpos( $request->get_route(), '/' . self::PREFIX ) ) {
			$options |= JSON_UNESCAPED_SLASHES;
			$options |= JSON_UNESCAPED_UNICODE;
		}
		return $options;
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

		register_taxonomy( self::REMAP_TAXONOMY, null, $args );

		register_term_meta( self::REMAP_TAXONOMY, 'meta', array( 'type' => 'string' ) );
	}

	public function register_custom_post_types() {
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

		$args = array(
			'labels'       => array(
				'name'          => __( 'Mastodon Posts', 'enable-mastodon-apps' ),
				'singular_name' => __( 'Mastodon Post', 'enable-mastodon-apps' ),
				'menu_name'     => __( 'Mastodon Posts', 'enable-mastodon-apps' ),
			),
			'description'  => __( 'Posted through a Mastodon app.', 'enable-mastodon-apps' ),
			'public'       => ! get_option( 'mastodon_api_posting_cpt' ),
			'show_in_rest' => false,
			'rewrite'      => false,
			'menu_icon'    => 'dashicons-megaphone',
			'supports'     => array( 'post-formats', 'comments', 'revisions', 'author' ),
		);

		register_post_type( self::POST_CPT, $args );
	}

	public function activitypub_support_post_types( $post_types ) {
		$post_types[] = self::POST_CPT;
		return $post_types;
	}
	public function rewrite_rules() {
		$existing_rules = get_option( 'rewrite_rules' );
		$needs_flush    = false;

		$generic      = apply_filters(
			'mastodon_api_generic_routes',
			array(
				'api/v1/accounts/relationships',
				'api/v1/accounts/verify_credentials',
				'api/v1/accounts/update_credentials',
				'api/v1/accounts/familiar_followers',
				'api/v1/accounts/search',
				'api/v1/accounts/lookup',
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
				'api/v1/search',
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
				'api/v1/statuses/([0-9]+)/source'        => 'api/v1/statuses/$matches[1]/source',
				'api/v1/statuses/([0-9]+)/favourited_by' => 'api/v1/statuses/$matches[1]/favourited_by',
				'api/v1/statuses/([0-9]+)/favourite'     => 'api/v1/statuses/$matches[1]/favourite',
				'api/v1/statuses/([0-9]+)/unfavourite'   => 'api/v1/statuses/$matches[1]/unfavourite',
				'api/v1/statuses/([0-9]+)/reblog'        => 'api/v1/statuses/$matches[1]/reblog',
				'api/v1/statuses/([0-9]+)/unreblog'      => 'api/v1/statuses/$matches[1]/unreblog',
				'api/v1/notifications/([^/]+)/dismiss'   => 'api/v1/notifications/$matches[1]/dismiss',
				'api/v1/notifications/([^/|$]+)/?$'      => 'api/v1/notifications/$matches[1]',
				'api/v1/notifications'                   => 'api/v1/notifications',
				'api/nodeinfo/([0-9]+[.][0-9]+)'         => 'api/nodeinfo/$matches[1]',
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
			if ( empty( $existing_rules[ '^' . $rule ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				$needs_flush = true;
			}
			add_rewrite_rule( '^' . $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rule, 'top' );
		}

		foreach ( $parametrized as $rule => $rewrite ) {
			if ( empty( $existing_rules[ '^' . $rule ] ) ) {
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
			'api/nodeinfo/(?P<version>[\w\.]+)',
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
				'permission_callback' => $this->required_scope( 'read:accounts' ),
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
			'api/v1/accounts/lookup',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_accounts_lookup' ),
				'permission_callback' => array( $this, 'public_api_permission' ),
				'args'                => array(
					'acct' => array(
						'type'        => 'string',
						'description' => 'What to lookup.',
						'required'    => true,
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
			'api/v1/statuses/(?P<post_id>[0-9]+)/source',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_get_status_source' ),
				'permission_callback' => $this->required_scope( 'read:statuses', true ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)/context',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_get_status_context' ),
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
				'methods'             => array( 'PUT', 'OPTIONS' ),
				'callback'            => array( $this, 'api_edit_post' ),
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
						'enum'        => array( 'accounts', 'hashtags', 'statuses', '' ),
						'default'     => '',
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

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/update_credentials',
			array(
				'methods'             => array( 'PATCH', 'OPTIONS' ),
				'callback'            => array( $this, 'api_update_credentials' ),
				'permission_callback' => $this->required_scope( 'write:accounts' ),
				'args'                => array(
					'display_name'      => array(
						'type'              => 'string',
						'description'       => 'The name to display in the user’s profile.',
						'sanitize_callback' => 'sanitize_text_field',
						'required'          => false,
					),
					'note'              => array(
						'type'              => 'string',
						'description'       => 'A new biography for the user.',
						'sanitize_callback' => 'sanitize_text_field',
						'required'          => false,
					),
					'fields_attributes' => array(
						'type'        => 'object',
						'description' => 'A list of custom fields to update.',

						'required'    => false,
					),
				),
			)
		);

		do_action( 'mastodon_api_register_rest_routes', $this );
	}

	/**
	 * Get the body from php://input.
	 * We don't use $request->get_body() because its data is mangled.
	 */
	private static function get_body_from_php_input() {
		// A helpful shim in case this is PHP <=5.6 when php://input could only be accessed once.
		static $input;
		if ( ! isset( $input ) ) {
			$input = file_get_contents( 'php://input' ); // phpcs:ignore
		}

		return $input;
	}

	/**
	 * Get file upload from PATCH request.
	 *
	 * @param string          $key The form field name of the file input.
	 * @param WP_REST_Request $request The request object.
	 * @return array|false Array of file data similar to a key in $_FILES, or false if no file found.
	 */
	private function get_patch_upload( $key, $request ) {
		if ( 'PATCH' !== $request->get_method() ) {
			return false;
		}

		$raw_data = self::get_body_from_php_input();
		if ( empty( $raw_data ) ) {
			return false;
		}

		$content_type = $request->get_content_type();
		if ( ! $content_type || empty( $content_type['parameters'] ) ) {
			return false;
		}
		if ( ! preg_match( '/boundary="?([^";]+)"?/', $content_type['parameters'], $matches ) ) {
			return false;
		}
		$boundary = $matches[1];

		$parts = array_slice( explode( '--' . $boundary, $raw_data ), 1, -1 );
		foreach ( $parts as $part ) {
			if ( false === strpos( $part, 'name="' . $key . '"' ) ) {
				continue;
			}

			list( $header_raw, $file_content ) = explode( "\r\n\r\n", $part, 2 );
			$headers = array();
			foreach ( explode( "\r\n", $header_raw ) as $line ) {
				if ( false !== strpos( $line, ': ' ) ) {
					list( $name, $value ) = explode( ': ', $line );
					$headers[ $name ] = $value;
				}
			}

			if ( ! preg_match( '/filename="([^"]+)"/', $headers['Content-Disposition'], $matches ) ) {
				return false;
			}
			$file_name = $matches[1];

			// require the file needed fo wp_tempnam.
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$tmp_name = wp_tempnam( 'patch_upload_' . $key );
			// Use WP_Filesystem abstraction.
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				// init if not yet done.
				WP_Filesystem();
			}
			$wp_filesystem->put_contents( $tmp_name, $file_content );

			return array(
				'name'     => $file_name,
				'type'     => isset( $headers['Content-Type'] ) ? $headers['Content-Type'] : 'application/octet-stream',
				'size'     => strlen( $file_content ),
				'tmp_name' => $tmp_name,
				'error'    => UPLOAD_ERR_OK,
			);
		}

		return false;
	}

	/**
	 * Get data from a PATCH request.
	 *
	 * This function handles different content types:
	 * - application/x-www-form-urlencoded
	 * - application/json
	 * - multipart/form-data
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array The merged array of request data.
	 */
	private function get_patch_data( $request ) {
		$data = array();
		if ( 'PATCH' !== $request->get_method() ) {
			return $data;
		}

		$content_type = $request->get_content_type();
		if ( ! $content_type ) {
			return $data;
		}

		$input = self::get_body_from_php_input();

		// We only handle multipart/form-data.
		if ( 'multipart/form-data' !== $content_type['value'] ) {
			return $data;
		}

		$boundary = preg_match( '/boundary="?([^";]+)"?/', $content_type['parameters'], $matches ) ? $matches[1] : null;
		if ( empty( $boundary ) ) {
			return $data;
		}
		$parts = array_slice( explode( $boundary, $input ), 1, -1 );

		foreach ( $parts as $part ) {
			if ( strpos( $part, 'filename=' ) !== false ) {
				// This is a file upload, handle separately.
				continue;
			}

			if ( preg_match( '/name="([^"]+)"/', $part, $matches ) ) {
				$name          = $matches[1];
				$value         = substr( $part, strpos( $part, "\r\n\r\n" ) + 4, -2 );

				// Handle nested arrays, or just simple key-value pairs.
				if ( preg_match( '/^(\w+)\[(\d+)\]\[(\w+)\]$/', $name, $matches ) ) {
					// Nested array.
					$key = $matches[1];
					$index = $matches[2];
					$subkey = $matches[3];
					$data[ $key ][ $index ][ $subkey ] = $value;
				} else {
					// Simple key-value pair.
					$data[ $name ] = $value;
				}
			}
		}

		return $data;
	}

	public function api_update_credentials( $request ) {
		$token = $this->oauth->get_token();
		$user = get_user_by( 'id', $token['user_id'] );
		if ( ! $user ) {
			return new \WP_Error( 'user-not-found', 'User not found', array( 'status' => 404 ) );
		}

		// handle avatar.
		$avatar = $this->get_patch_upload( 'avatar', $request );
		if ( $avatar ) {
			$avatar = $this->handle_upload( $avatar );
		}
		if ( is_wp_error( $avatar ) ) {
			return $avatar;
		}

		// same for header.
		$header = $this->get_patch_upload( 'header', $request );
		if ( $header ) {
			$header = $this->handle_upload( $header );
		}
		if ( is_wp_error( $header ) ) {
			return $header;
		}

		// now populate the params - get_patch_data is unsanitized so we re-run request param sanitization.
		$request->set_body_params( $this->get_patch_data( $request ) );
		$request->sanitize_params();

		$data = array(
			'avatar'            => $avatar, // Attachment ID.
			'header'            => $header, // Attachment ID.
			'display_name'      => $request->get_param( 'display_name' ),
			'note'              => $request->get_param( 'note' ),
			'fields_attributes' => $request->get_param( 'fields_attributes' ),
		);
		$data = array_filter( $data );
		$user_id = (int) $user->ID;

		/**
		 * An action for clients to hook into for setting user profile data.
		 *
		 * @param array $data   User attributes requested to update. Only keys requested for update will be present.
		 *                      Keys: avatar(attachment_id)|header(attachment_id)|display_name(string)|note(string)|fields_attributes(hash)
		 *                      If your plugin acts on data and you don't want this plugin to runs it own update,
		 *                      remove the keys from the array.
		 * @param int $user_id  The user_id to act on.
		 */
		$data = apply_filters( 'mastodon_api_update_credentials', $data, $user_id );

		// Update the user with any available data for fields we support (just display_name and note currently).
		if ( isset( $data['display_name'] ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $data['display_name'],
				)
			);
		}
		if ( isset( $data['note'] ) ) {
			update_user_meta( $user_id, 'description', $data['note'] );
		}

		// if we set this earlier it gets cleared out by `$request->sanitize_params()`.
		$request->set_param( 'user_id', $user_id );

		// Return the account.
		return $this->api_account( $request );
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
		$token     = $this->oauth->get_token();
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
		$this->allow_cors( $request );
		// Optionally log in.
		$token = $this->oauth->get_token();
		if ( ! $token ) {
			if ( get_option( 'mastodon_api_debug_mode' ) > time() ) {
				$app = Mastodon_App::get_debug_app();
				$app->was_used(
					$request,
					array(
						'user_agent' => $_SERVER['HTTP_USER_AGENT'], // phpcs:ignore
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
		// phpcs:disable
		if ( 0 !== strpos( $_SERVER['REQUEST_URI'], '/api/v' ) ) {
			return $template;
		}
		$request = new WP_REST_Request( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );
		$request->set_query_params( $_GET );
		$request->set_body_params( $_POST );
		// phpcs:enable
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
				'user_agent' => $_SERVER['HTTP_USER_AGENT'], // phpcs:ignore
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
			$app                    = new \WP_Error( $code, $message, array( 'status' => 422 ) );
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
		$this->allow_cors( $request );
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
		$this->allow_cors( $request );
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
	public function default_option_mastodon_api_default_post_formats( $post_formats ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return array(
			'standard',
		);
	}

	/**
	 * Converts param validation errors to error codes expected by Mastodon apps.
	 *
	 * @param WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response Result to send to the client.
	 * @param WP_REST_Server                                     $handler The handler instance.
	 * @param WP_REST_Request                                    $request Thr request.
	 *
	 * @return WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed
	 */
	public function rest_request_before_callbacks( $response, $handler, $request ) {
		if ( 0 !== strpos( $request->get_route(), '/' . self::PREFIX ) ) {
			return $response;
		}

		if ( is_wp_error( $response ) && 'rest_missing_callback_param' === $response->get_error_code() ) {
			$response = new \WP_Error( $response->get_error_code(), $response->get_error_message(), array( 'status' => 422 ) );
		}

		return $response;
	}

	/**
	 * Log REST API errors for debugging.
	 *
	 * @param WP_Error $errors WP_Error object.
	 * @return WP_Error WP_Error object.
	 */
	public function rest_authentication_errors( $errors ) {
		if ( $errors && get_option( 'mastodon_api_debug_mode' ) > time() ) {
			$request = new WP_REST_Request( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ); // phpcs:ignore
			$request->set_query_params( $_GET ); // phpcs:ignore
			$request->set_body_params( $_POST ); // phpcs:ignore
			$request->set_headers( getallheaders() );

			$app = Mastodon_App::get_debug_app();
			$app->was_used(
				$request,
				array(
					'user_agent' => $_SERVER['HTTP_USER_AGENT'], // phpcs:ignore
					'errors'     => $errors,
				)
			);
		}

		return $errors;
	}

	public function api_verify_credentials( $request ) {
		$request->set_param( 'user_id', get_current_user_id() );
		return $this->api_account( $request );
	}

	public function mapback_user_id( $user_id ) {
		/**
		 * Map a user id to a canonical user id.
		 *
		 * This allows to ensure that the user id stays the same no matter of the relationship to this user.
		 * For example, if external user information is fetched about a user and we later follow that user, the user id should stay the same.
		 *
		 * @param int $user_id The user ID.
		 * @return int The potentially modified user ID.
		 */
		$user_id = apply_filters( 'mastodon_api_canonical_user_id', $user_id );

		if ( $user_id > 1e10 ) {
			$remote_user_id = get_term_by( 'id', intval( $user_id ) - 1e10, self::REMAP_TAXONOMY );
			if ( $remote_user_id ) {
				return apply_filters( 'mastodon_api_canonical_user_id', $remote_user_id->name );

			}
		}

		return $user_id;
	}

	private function get_user_id_from_request( $request ) {
		/**
		 * Map back a public id to the previous user id.
		 *
		 * @param int $user_id The public user ID.
		 * @return int The potentially modified user ID.
		 */
		return apply_filters( 'mastodon_api_mapback_user_id', $request->get_param( 'user_id' ) );
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

	private function filter_non_entities( $entities, $type ) {
		$wp_rest_response = false;
		if ( $entities instanceof \WP_REST_Response ) {
			$wp_rest_response = $entities;
			$entities         = $entities->data;
		}
		if ( ! is_array( $entities ) ) {
			return array();
		}
		$entities = array_values(
			array_filter(
				$entities,
				function ( $entity ) use ( $type ) {
					if ( ! $entity instanceof $type ) {
						return false;
					}

					if ( ! $entity->is_valid() ) {
						return false;
					}
					return true;
				}
			)
		);
		if ( $wp_rest_response ) {
			$wp_rest_response->data = $entities;
			return $wp_rest_response;
		}
		return $entities;
	}

	public function api_submit_post( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Insufficient permissions', array( 'status' => 401 ) );
		}

		$status_text = $request->get_param( 'status' );
		if ( empty( $status_text ) ) {
			return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Validation failed: Text can\'t be blank', array( 'status' => 422 ) );
		}

		/**
		 * Allow modifying the status text before it gets posted.
		 *
		 * @param string $status The user submitted status text.
		 * @param WP_REST_Request $request The REST request object.
		 * @return string The potentially modified status text.
		 */
		$status_text = apply_filters( 'mastodon_api_submit_status_text', $status_text, $request );

		$visibility = $request->get_param( 'visibility' );
		if ( empty( $visibility ) ) {
			$visibility = 'public';
		}

		/**
		 * Allow modifying the in_reply_to_id before it gets used
		 *
		 * For example, this could be a remapped blog id, or a remapped URL.
		 *
		 * @param string $in_reply_to_id The user submitted in_reply_to_id.
		 * @param WP_REST_Request $request The REST request object.
		 * @return string The potentially modified in_reply_to_id.
		 */
		$in_reply_to_id = apply_filters( 'mastodon_api_in_reply_to_id', $request->get_param( 'in_reply_to_id' ), $request );

		$media_ids    = $request->get_param( 'media_ids' );
		$scheduled_at = $request->get_param( 'scheduled_at' );

		$app              = Mastodon_App::get_current_app();
		$app_post_formats = array();
		if ( $app ) {
			$app_post_formats = $app->get_post_formats();
		}
		if ( empty( $app_post_formats ) ) {
			$app_post_formats = array( 'status' );
		}
		$post_format = apply_filters( 'mastodon_api_new_post_format', $app_post_formats[0] );

		$status = apply_filters( 'mastodon_api_submit_status', null, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at, $request );

		return $this->validate_entity( $status, Entity\Status::class );
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
		if ( empty( $media ) || empty( $media['file'] ) ) {
			return new \WP_Error( 'mastodon_api_post_media', 'Media is empty', array( 'status' => 422 ) );
		}

		$attachment_id = $this->handle_upload( $media['file'] );
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
	 * Handle the upload of a media file.
	 *
	 * @param array $media The media file data.
	 * @return int|\WP_Error The attachment ID or a WP_Error object on failure.
	 */
	private function handle_upload( $media ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! isset( $media['name'] ) || false === strpos( $media['name'], '.' ) ) {
			switch ( $media['type'] ) {
				case 'image/png':
					$media['name'] = 'image.png';
					break;
				case 'image/jpeg':
					$media['name'] = 'image.jpg';
					break;
				case 'image/gif':
					$media['name'] = 'image.gif';
					break;
			}
		}
		$attachment_id = \media_handle_sideload( $media );
		if ( is_wp_error( $attachment_id ) ) {
			return new \WP_Error( 'mastodon_api_post_media', $attachment_id->get_error_message(), array( 'status' => 422 ) );
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) ) );
		return $attachment_id;
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
		 * add_filter( 'mastodon_api_public_timeline', function( $statuses, $request ) {
		 *    array_unshift( $statuses, new Entity\Status( array( 'content' => 'Hello World' ) ) );
		 *    return $statuses;
		 * } );
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
		return apply_filters( 'mastodon_api_search', array(), $request );
	}

	public function api_accounts_lookup( object $request ) {
		$acct = $request->get_param( 'acct' );
		if ( ! $acct ) {
			return new \WP_Error( 'mastodon_api_accounts_lookup', 'Validation failed: acct can\'t be blank', array( 'status' => 422 ) );
		}

		if ( is_numeric( $acct ) ) {
			$acct = $this->mapback_user_id( $acct );
		}

		if ( is_string( $acct ) ) {
			$user = get_user_by( 'login', $acct );
			if ( $user && ! is_wp_error( $user ) ) {
				$acct = $user->ID;
			}
		}

		$account = \apply_filters( 'mastodon_api_account', null, $acct, $request, null );

		if ( ! $account ) {
			return new \WP_Error( 'mastodon_api_accounts_lookup', 'Record not found', array( 'status' => 404 ) );
		}

		return $this->validate_entity( $account, Entity\Account::class );
	}

	public function api_push_subscription( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return array();
	}

	public function api_get_status_source( $request ) {
		$source_id = $request->get_param( 'post_id' );
		if ( ! $source_id ) {
			return false;
		}

		$source_id = self::maybe_get_remapped_reblog_id( $source_id );
		$source_id = self::maybe_get_remapped_comment_id( $source_id );

		return apply_filters( 'mastodon_api_status_source', null, $source_id );
	}

	public function api_get_status_context( $request ) {
		$context_post_id = $request->get_param( 'post_id' );
		if ( ! $context_post_id ) {
			return false;
		}

		$context_post_id = self::maybe_get_remapped_reblog_id( $context_post_id );
		$url             = get_permalink( $context_post_id );

		$context = array(
			'ancestors'   => array(),
			'descendants' => array(),
		);

		return apply_filters( 'mastodon_api_status_context', $context, $context_post_id, $url );
	}

	public function api_favourite_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		$post_id = self::maybe_get_remapped_reblog_id( $post_id );

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

		$post_id = self::maybe_get_remapped_reblog_id( $post_id );

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

		$post_id = self::maybe_get_remapped_reblog_id( $post_id );

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

		$post_id = self::maybe_get_remapped_reblog_id( $post_id );

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

	public function api_edit_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		$status_text = $request->get_param( 'status' );
		if ( empty( $status_text ) ) {
			return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Validation failed: Text can\'t be blank', array( 'status' => 422 ) );
		}

		/**
		 * Allow modifying the status text before it gets posted.
		 *
		 * @param string $status The user submitted status text.
		 * @param WP_REST_Request $request The REST request object.
		 * @return string The potentially modified status text.
		 */
		$status_text = apply_filters( 'mastodon_api_submit_status_text', $status_text, $request );

		$visibility = $request->get_param( 'visibility' );
		if ( empty( $visibility ) ) {
			$visibility = 'public';
		}

		/**
		 * Allow modifying the in_reply_to_id before it gets used
		 *
		 * For example, this could be a remapped blog id, or a remapped URL.
		 *
		 * @param string $in_reply_to_id The user submitted in_reply_to_id.
		 * @param WP_REST_Request $request The REST request object.
		 * @return string The potentially modified in_reply_to_id.
		 */
		$in_reply_to_id = apply_filters( 'mastodon_api_in_reply_to_id', $request->get_param( 'in_reply_to_id' ), $request );

		$media_ids    = $request->get_param( 'media_ids' );
		$scheduled_at = $request->get_param( 'scheduled_at' );

		$app              = Mastodon_App::get_current_app();
		$app_post_formats = array();
		if ( $app ) {
			$app_post_formats = $app->get_post_formats();
		}
		if ( empty( $app_post_formats ) ) {
			$app_post_formats = array( 'status' );
		}
		$post_format = apply_filters( 'mastodon_api_new_post_format', $app_post_formats[0] );

		$status = apply_filters( 'mastodon_api_edit_status', null, $post_id, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility, $scheduled_at, $request );

		return $this->validate_entity( $status, Entity\Status::class );
	}

	public function api_delete_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
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
		if ( self::CPT === get_post_type( $post_id ) ) {
			$post_id = self::maybe_get_remapped_reblog_id( $post_id );
		}

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

		if ( $status->id !== $request->get_param( 'post_id' ) && isset( $status->reblog ) && $status->reblog->id === $request->get_param( 'post_id' ) ) {
			$status = $status->reblog;
		}

		return $this->validate_entity( $status, Entity\Status::class );
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
		$user_id = apply_filters( 'mastodon_api_account_follow', $user_id, $request );

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
		$user_id = $this->mapback_user_id( $user_id );

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
		 * }, 10, 3 );
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

	public function api_preferences( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$preferences = array(
			'posting:default:language'   => self::get_mastodon_language( get_user_locale() ),
			'posting:default:visibility' => apply_filters( 'mastodon_api_account_visibility', 'public', wp_get_current_user() ),
		);
		return $preferences;
	}

	public static function get_mastodon_language( $lang ) {
		if ( false === strpos( $lang, '_' ) ) {
			return $lang . '_' . strtoupper( $lang );
		}
		return $lang;
	}

	public static function set_last_error( $message ) {
		self::$last_error = $message;
	}

	public static function get_last_error() {
		return self::$last_error;
	}

	public function api_account_statuses( $request ) {
		$user_id = $this->get_user_id_from_request( $request );

		$args = array(
			'author'          => $user_id,
			'exclude_replies' => $request->get_param( 'exclude_replies' ),
		);
		$args = apply_filters( 'mastodon_api_account_statuses_args', $args, $request );

		/**
		 * Filter the account statuses.
		 *
		 * @param array|null $statuses Current statuses.
		 * @param array $args Current statuses arguments.
		 * @param int|null $min_id Optional minimum status ID.
		 * @param int|null $max_id Optional maximum status ID.
		 */
		$statuses = apply_filters( 'mastodon_api_statuses', null, $args, $request->get_param( 'min_id' ), $request->get_param( 'max_id' ) );

		return $this->filter_non_entities( $statuses, Entity\Status::class );
	}

	public function api_account( $request ) {
		$user_id = $this->get_user_id_from_request( $request );

		if ( ! is_user_member_of_blog( $user_id ) ) {
			return new \WP_Error( 'mastodon_api_account', 'Record not found', array( 'status' => 404 ) );
		}

		/**
		 * Modify the account data returned for `/api/account/{user_id}` requests.
		 *
		 * @param Entity\Account|null $account The account data.
		 * @param int $user_id The requested user ID.
		 * @param WP_REST_Request|null $request The request object.
		 * @param WP_Post|null $post The post object.
		 * @return Entity\Account|null The modified account data.
		 *
		 * ### Example
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
		 * }, 10, 2 );
		 * ```
		 */
		$account = \apply_filters( 'mastodon_api_account', null, $user_id, $request, null );
		if ( is_null( $account ) ) {
			return new \WP_Error( 'mastodon_api_account', 'Record not found', array( 'status' => 404 ) );

		}

		return $this->validate_entity( $account, Entity\Account::class );
	}

	public static function remap_reblog_id( $post_id ) {
		$remapped_post_id = get_post_meta( $post_id, 'mastodon_reblog_id', true );
		if ( ! $remapped_post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return $post_id;
			}
			$remapped_post_id = wp_insert_post(
				array(
					'post_type'     => self::CPT,
					'post_author'   => 0,
					'post_status'   => 'publish',
					'post_title'    => 'Reblog of ' . $post_id,
					'post_date'     => $post->post_date,
					'post_date_gmt' => $post->post_date_gmt,
					'meta_input'    => array(
						'mastodon_reblog_id' => $post_id,
					),
				)
			);

			update_post_meta( $post_id, 'mastodon_reblog_id', $remapped_post_id );
		}
		return $remapped_post_id;
	}

	public static function maybe_get_remapped_reblog_id( $remapped_post_id ) {
		$post_id = get_post_meta( $remapped_post_id, 'mastodon_reblog_id', true );
		if ( $post_id ) {
			return $post_id;
		}
		return $remapped_post_id;
	}

	public static function maybe_get_remapped_comment_id( $remapped_post_id ) {
		$comment_id = get_post_meta( $remapped_post_id, 'mastodon_comment_id', true );
		if ( $comment_id ) {
			return $comment_id;
		}
		return $remapped_post_id;
	}

	public static function remap_comment_id( $comment_id ) {
		$remapped_comment_id = get_comment_meta( $comment_id, 'mastodon_comment_id', true );
		if ( ! $remapped_comment_id ) {
			$comment = get_comment( $comment_id );
			if ( ! $comment ) {
				return $comment_id;
			}
			if ( $comment->comment_parent ) {
				$post_parent = self::remap_comment_id( $comment->comment_parent );
			} else {
				$post_parent = self::remap_reblog_id( $comment->comment_post_ID );
			}
			$remapped_comment_id = wp_insert_post(
				array(
					'post_type'     => self::CPT,
					'post_author'   => 0,
					'post_status'   => 'publish',
					'post_title'    => 'Comment ' . $comment_id,
					'post_date'     => $comment->comment_date,
					'post_date_gmt' => $comment->comment_date_gmt,
					'post_parent'   => $post_parent,
					'meta_input'    => array(
						'mastodon_comment_id' => $comment_id,
					),
				)
			);

			update_comment_meta( $comment_id, 'mastodon_comment_id', $remapped_comment_id );
		}
		return $remapped_comment_id;
	}


	public static function remap_user_id( $user_id ) {
		$user_id        = apply_filters( 'mastodon_api_canonical_user_id', $user_id );
		$term           = get_term_by( 'name', $user_id, self::REMAP_TAXONOMY );
		$remote_user_id = 0;
		if ( $term ) {
			$remote_user_id = $term->term_id;
		} else {
			$term = wp_insert_term( $user_id, self::REMAP_TAXONOMY );
			if ( is_wp_error( $term ) ) {
				return $remote_user_id;
			}
			$remote_user_id = $term['term_id'];
		}

		return 1e10 + $remote_user_id;
	}

	public static function remap_url( $url ) {
		$term        = get_term_by( 'name', $url, self::REMAP_TAXONOMY );
		$remapped_id = 0;
		if ( $term ) {
			$remapped_id = $term->term_id;
		} else {
			$term = wp_insert_term( $url, self::REMAP_TAXONOMY );
			if ( ! is_wp_error( $term ) ) {
				$remapped_id = $term['term_id'];
			}
		}

		return 2e10 + $remapped_id;
	}

	public static function maybe_get_remapped_url( $id ) {
		if ( $id > 2e10 ) {
			$term = get_term_by( 'id', intval( $id ) - 2e10, self::REMAP_TAXONOMY );
			if ( $term ) {
				return $term->name;
			}
		}
		return $id;
	}

	private function software_string() {
		global $wp_version;
		$software = 'WordPress/' . $wp_version;
		if ( defined( 'ACTIVITYPUB_VERSION' ) ) {
			$software .= ', ActivityPub/' . ACTIVITYPUB_VERSION;
		}
		$software .= ', EMA/' . self::VERSION;
		return $software;
	}

	public function api_nodeinfo( $request ) {
		$nodeinfo_version = $request->get_param( 'version' );

		if ( '2.0' === $nodeinfo_version ) {
			global $wp_version;
			$software = array(
				'name'    => $this->software_string(),
				'version' => self::VERSION,
			);
			$software = apply_filters( 'mastodon_api_nodeinfo_software', $software );
			$ret      = array(
				'metadata' => array(
					'nodeName'          => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'nodeDescription'   => html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
					'config'            => array(
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
					'version'           => '2.0',
					'protocols'         => array(
						'activitpypub',
					),
					'services'          => array(
						'outbound' => array(),
					),

					'software'          => $software,
					'openRegistrations' => false,
				),
			);
		} else {
			$ret = new WP_Error(
				'mastodon_api_nodeinfo',
				'Unsupported version',
				array( 'status' => 404 )
			);
		}

		return apply_filters( 'mastodon_api_nodeinfo', $ret, $nodeinfo_version );
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

		$post_formats = $app->get_post_formats();
		$content[]    = sprintf(
			// Translators: %s is the post formats.
			_n( 'Posts with the post format <strong>%s</strong> will appear in this app.', 'Posts with the post formats <strong>%s</strong> will appear in this app.', count( $app->get_post_formats() ), 'enable-mastodon-apps' ),
			implode( ', ', $post_formats )
		);

		$content[] = sprintf(
			// Translators: %s is the post format.
			__( 'If you create a new note in this app, it will be created with the <strong>%s</strong> post format.', 'enable-mastodon-apps' ),
			reset( $post_formats )
		);

		if ( 'standard' === reset( $post_formats ) ) {
			$content[] = __( 'If you want to create a post in WordPress with a title, add a new line after the title. The first line will then appear as the title of the post.', 'enable-mastodon-apps' );
		} else {
			$content[] = __( 'Because a new post is not created in the standard post format, it will be published without title. To change this, select the <strong>standard</strong> post format in the Enable Mastodon Apps settings.', 'enable-mastodon-apps' );
		}

		$ret[] = array(
			'id'           => 1,
			'content'      => '<h1><strong>' . __( 'Mastodon Apps', 'enable-mastodon-apps' ) . '</strong></h1><p>' . implode( '</p><p>' . PHP_EOL, $content ) . '</p>',
			'published_at' => gmdate( 'Y-m-d\TH:i:s.000P', $app->get_creation_date() ),
			'updated_at'   => gmdate( 'Y-m-d\TH:i:s.000P' ),
			'starts_at'    => null,
			'ends_at'      => null,
			'all_day'      => false,
			'read'         => false,
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
	public function api_instance_peers( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
	public function api_instance_rules( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
	public function api_instance_domain_blocks( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
	public function api_instance_extended_description( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
	public function api_instance_translation_languages( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
