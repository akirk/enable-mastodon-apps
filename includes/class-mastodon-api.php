<?php
/**
 * Friends Mastodon API
 *
 * This contains the REST API handlers.
 *
 * @package Mastodon_API
 */

namespace Mastodon_API;

use Friends\Friends;
/**
 * This is the class that implements the Mastodon API endpoints.
 *
 * @since 0.1
 *
 * @package Mastodon_API
 * @author Alex Kirk
 */
class Mastodon_API {
	const ACTIVITYPUB_USERNAME_REGEXP = '(?:([A-Za-z0-9_-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))';
	const VERSION = MASTODON_API_VERSION;
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

	const PREFIX = 'mastodon-api';
	const APP_TAXONOMY = 'mastodon-app';

	/**
	 * Constructor
	 */
	public function __construct() {
		Mastodon_App::register_taxonomy();
		$this->oauth = new Mastodon_OAuth();
		$this->register_hooks();
		new Mastodon_Admin( $this->oauth );
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
			'api/nodeinfo/2.0.json',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_nodeinfo' ),
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
			'api/v2/media',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_post_media' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/media/(?P<post_id>[0-9]+)',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_get_media' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( $this, 'api_submit_post' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)/context',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_get_post_context' ),
				'permission_callback' => array( $this, 'logged_in_for_private_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/statuses/(?P<post_id>[0-9]+)',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_get_post' ),
				'permission_callback' => array( $this, 'logged_in_for_private_permission' ),
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
			'api/v1/accounts/(?P<user_id>[^/]+)/statuses',
			array(
				'methods'             => array( 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'api_account_statuses' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
			)
		);

		register_rest_route(
			self::PREFIX,
			'api/v1/accounts/(?P<user_id>[^/]+)$',
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
			'api/v2/media',
		);
		$parametrized = array(
			'api/v1/accounts/([^/+])/statuses' => 'api/v1/accounts/$matches[1]/statuses',
			'api/v1/statuses/([0-9]+)/context' => 'api/v1/statuses/$matches[1]/context',
			'api/nodeinfo/([0-9]+[.][0-9]+).json' => 'api/nodeinfo/$matches[1].json',
			'api/v1/media/([0-9]+)' => 'api/v1/media/$matches[1]',
			'api/v1/statuses/([0-9]+)' => 'api/v1/statuses/$matches[1]',
			'api/v1/statuses' => 'api/v1/statuses',
			'api/v1/accounts/(.+)' => 'api/v1/accounts/$matches[1]',
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
		if ( get_option( 'mastodon_api_disable_logins' ) ) {
			return new \WP_Error( 'registation-disabled', __( 'App registration has been disabled.', 'mastodon_api' ), array( 'status' => 403 ) );
		}

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
			'name'          => $app->get_client_name(),
			'website'       => $app->get_website(),

		);
	}

	public function logged_in_permission() {
		$this->allow_cors();
		$token = $this->oauth->authenticate();
		if ( is_null( $token ) ) {
			return false;
		}
		$this->app = Mastodon_App::get_by_client_id( $token['client_id'] );
		$this->app->was_used();
		return is_user_logged_in();
	}

	public function logged_in_for_private_permission( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		if ( get_post_status( $post_id ) !== 'publish' ) {
			return $this->logged_in_permission();
		}

		return true;
	}

	public function api_verify_credentials( $request ) {
		return $this->get_friend_account_data( get_current_user_id() );
	}

	private function get_posts_query_args( $request ) {
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 10;
		}

		$args = array(
			'posts_per_page' => $limit,
			'post_type' => apply_filters( 'friends_frontend_post_types', array( 'post' ) ),
			'tax_query' => array(
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => 'post-format-status',
				)
			),
			'suppress_filters' => false,
			'post_status' => array( 'publish', 'private' ),
		);

		$post_id = $request->get_param( 'post_id' );
		if ( $post_id ) {
			$args['p'] = $post_id;
		}

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
			$status = $this->get_status_array( $post );
			if ( $status ) {
				$ret[] = $status;
			}
		}
		return $ret;
	}

	private function get_status_array( $post ) {
		$meta = get_post_meta( $post->ID, 'activitypub', true );
		$account_data = $this->get_friend_account_data( $post->post_author, $meta );
		if ( is_wp_error( $account_data ) ) {
			return null;
		}

		$data = array(
			'id'                => strval( $post->ID ),
			'uri'               => $post->guid,
			'url'               => $post->guid,
			'account'           => $account_data,
			'in_reply_to_id'    => null,
			'in_reply_to_account_id' => null,
			'reblog'            => null,
			'content'           => $post->post_content,
			'created_at'        => mysql2date( \DateTimeInterface::RFC3339_EXTENDED, $post->post_date, false ),
			'edited_at'         => null,
			'emojis'            => array(),
			'replies_count'     => 0,
			'reblogs_count'     => 0,
			'favourites_count'  => 0,
			'reblogged'         => false,
			'reblogged_by'      => array(),
			'muted'             => false,
			'sensitive'         => false,
			'spoiler_text'      => '',
			'visibility'        => 'publish' === $post->post_status ? 'public' : 'unlisted',
			'media_attachments' => array(),
			'mentions'          => array(),
			'tags'              => array(),
			'language'          => 'en',
			'pinned'            => false,
			'card'              => null,
			'poll'              => null,
		);

		// get the attachments for the post.
		$attachments = get_attached_media( '', $post->ID );
		$p = strpos( $data['content'], '<!-- wp:image' );
		while ( ( $p = strpos( $data['content'], '<!-- wp:image' ) ) !== false ) {
			$e = strpos( $data['content'], '<!-- /wp:image', $p );
			if ( ! $e ) {
				break;
			}
			$img = substr( $data['content'], $p, $e - $p + 19 );
			if ( preg_match( '#<img(?:\s+src="(?P<url>[^"]+)"|\s+width="(?P<width>\d+)"|\s+height="(?P<height>\d+)"|\s+class="(?P<class>[^"]+))+"#', $img, $img_tag )) {
				$media_id = crc32( $img_tag['url'] );
				foreach ( $attachments as $attachment_id => $attachment ) {
					if ( $attachment->guid === $img_tag['url'] ) {
						$media_id = $attachment_id;
						break;
					}
				}
				$data['media_attachments'][] =array(
					'id' => $media_id,
					'type' => 'image',
					'url' => $img_tag['url'],
					'preview_url' => $img_tag['url'],
					'text_url' => $img_tag['url'],
					'width' => $img_tag['width'],
					'height' => $img_tag['height'],
				);
			}
			$data['content'] = substr( $data['content'], 0, $p ) . substr( $data['content'], $e + 19 );
		}

		$author_name = $data['account']['display_name'];
		$override_author_name = get_post_meta( $post->ID, 'author', true );

		if ( isset( $meta['reblog'] ) && $meta['reblog'] ) {
			$data['reblog'] = $data;
			if ( ! empty( $meta['attributedTo']['icon'] ) ) {
				$data['reblog']['account']['avatar'] = $meta['attributedTo']['icon'];
			}
			if ( ! empty( $meta['attributedTo']['preferredUsername'] ) ) {
				$data['reblog']['account']['display_name'] = $meta['attributedTo']['preferredUsername'];
			}
			$data['reblog']['account']['acct'] = $this->get_acct( $meta['attributedTo']['id'] );
			$data['reblog']['account']['username'] = $this->get_acct( $meta['attributedTo']['id'] );
			$data['reblog']['account']['id'] = $this->get_acct( $meta['attributedTo']['id'] );
			if ( ! empty( $meta['attributedTo']['summary'] ) ) {
				$data['reblog']['account']['note'] = $meta['attributedTo']['summary'];
			}
		} elseif ( $author_name !== $override_author_name ) {
			$data['account_data']['display_name'] = $override_author_name;
		}

		return $data;
	}

	public function api_submit_post( $request ) {
		$status = $request->get_param( 'status' );
		if ( empty( $status ) ) {
			return new \WP_Error( 'mastodon_api_submit_post', 'Status is empty', array( 'status' => 400 ) );
		}

		$visibility = $request->get_param( 'visibility' );
		if ( empty( $visibility ) ) {
			$visibility = 'public';
		}
		$post_data = array(
			'post_content' => $status,
			'post_status' => 'public' === $visibility ? 'publish' : 'private',
			'post_type' => 'post',
			'post_title' => '',
		);

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
					return new \WP_Error( 'mastodon_api_submit_post', 'Media not found', array( 'status' => 400 ) );
				}
				if ( 'attachment' !== $media->post_type ) {
					return new \WP_Error( 'mastodon_api_submit_post', 'Media not found', array( 'status' => 400 ) );
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

		$post_format = apply_filters( 'mastodon_api_new_post_format', 'status' );
		set_post_format( $post_id, $post_format );
		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				wp_update_post( array(
					'ID' => $media_id,
					'post_parent' => $post_id,
				) );
			}
		}

		if ( $scheduled_at ) {
			return array(
				'id' => $post_id,
				'scheduled_at' => $scheduled_at,
				'params' => array(
					'text' => $status,
					'visibility' => $visibility,
					'scheduled_at' => $scheduled_at,
				),
			);
		}
		return $this->get_status_array( get_post( $post_id ) );
	}

	public function api_get_media( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! is_numeric( $post_id ) || $post_id < 0 ) {
			return new \WP_Error( 'mastodon_api_get_media', 'Invalid post ID', array( 'status' => 400 ) );
		}
		$attachment = wp_get_attachment_metadata( $post_id );
		return array(
			'id' => $post_id,
			'type' => 'image',
			'url' => wp_get_attachment_url( $post_id ),
			'preview_url' => wp_get_attachment_url( $post_id ),
			'height' => $attachment['height'],
			'width' => $attachment['width'],
		);
	}

	public function api_post_media( $request ) {
		$media = $request->get_file_params();
		if ( empty( $media ) ) {
			return new \WP_Error( 'mastodon_api_post_media', 'Media is empty', array( 'status' => 400 ) );
		}

		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$attachment_id = \media_handle_sideload( $media['file'] );

		return array(
			'id' => $attachment_id,
			'type' => 'image',
			'url' => \wp_get_attachment_url( $attachment_id ),
			'preview_url' => \wp_get_attachment_url( $attachment_id ),
			'text_url' => \wp_get_attachment_url( $attachment_id ),
		);
	}

	public function api_timelines( $request ) {
		$args = $this->get_posts_query_args( $request );
		if ( empty( $args ) ) {
			return array();
		}
		$args = apply_filters( 'mastodon_api_timelines_args', $args, $request );

		return $this->get_posts( $args, $request->get_param( 'max_id' ) );
	}

	public function api_get_post_context( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}
		$context = array(
			'ancestors' => array(),
			'descendants' => array(),
		);

		if ( ! class_exists( '\Friends\Friends' ) || \Friends\Friends::CPT !== get_post_type( $post_id ) ) {
			return $context;
		}


		$meta = get_post_meta( $post_id, 'activitypub', true );
		if ( $meta ) {
			$transient_key = 'mastodon_api_get_post_context_' . $post_id;
			$saved_context = get_transient( $transient_key );
			if ( $saved_context ) {
				return $saved_context;
			}

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
				}
				if ( isset( $context['error'] ) ) {
					$context = array_merge(
						$context,
						array(
							'ancestors' => array(),
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
		return $context;
	}

	public function api_get_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! $post_id ) {
			return false;
		}

		return $this->get_status_array( get_post( $post_id ) );
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
		if ( 'Note' !== $activity['object']['type'] ) {
			return null;
		}
		return array(
			'id'                => $activity['id'],
			'uri'               => $activity['object']['id'],
			'url'               => $activity['object']['id'],
			'account'           => $this->get_friend_account_data( $user_id ),
			'in_reply_to_id'    => $activity['object']['inReplyTo'],
			'in_reply_to_account_id' => null,
			'reblog'            => null,
			'content'           => $activity['object']['content'],
			'created_at'        => $activity['object']['published'],
			'emojis'            => array(),
			'favourites_count'  => 0,
			'reblogged'         => false,
			'reblogged_by'      => array(),
			'muted'             => false,
			'sensitive'         => $activity['object']['sensitive'],
			'spoiler_text'      => '',
			'visibility'        => 'public',
			'media_attachments' => array_map( function( $attachment ) {
				return array(
					'id'                => crc32( $attachment['url'] ),
					'type'              => strtok( $attachment['mediaType'], '/' ),
					'url'               => $attachment['url'],
					'preview_url'       => $attachment['url'],
					'text_url'          => $attachment['url'],
					'width' 		   => $attachment['width'],
					'height' 		   => $attachment['height'],
				);
			}, $activity['object']['attachment'] ),
			'mentions'          =>  array_map( function( $mention ) {
				return array(
					'id'                => $mention['href'],
					'username'          => $mention['name'],
					'acct'              => $mention['name'],
					'url'               => $mention['href'],
				);
			}, array_filter( $activity['object']['tag'], function( $tag ) {
				return 'Mention' === $tag['type'];
			} ) ),
			'tags'              => array_map( function( $tag ) {
				return array(
					'name'              => $tag['name'],
					'url'               => $tag['href'],
				);
			}, array_filter( $activity['object']['tag'], function( $tag ) {
				return 'Hashtag' === $tag['type'];
			} ) ),
			'language'          => 'en',
			'pinned'            => false,
			'card'              => null,
			'poll'              => null,
		);
	}

	public function api_account_statuses( $request ) {
		$user_id = rawurldecode( $request->get_param( 'user_id' ) );
		if ( preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $user_id ) ) {
			$url = $this->get_activitypub_url( $user_id );
			if ( $url ) {
				$account = $this->get_acct( $user_id );
				$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $url );
				if ( $meta && ! is_wp_error( $meta ) ) {
					$outbox = $this->get_json( $meta['outbox'], 'outbox-' . $account, array( 'first' => null ) );
					$outbox_page = $this->get_json( $outbox['first'], 'outbox-' . $account, array( 'orderedItems' => array() ) );

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

		$args = apply_filters( 'mastodon_api_account_statuses_args', $args, $request );

		return $this->get_posts( $args );
	}

	public function api_account_relationships( $request ) {
		$user_id = $request->get_param( 'id' );

		return array();
	}

	public function api_account( $request ) {
		return $this->get_friend_account_data( rawurldecode( $request->get_param( 'user_id' ) ) );
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

	private function get_json( $url, $transient_key, $fallback = null ) {
		$response = \get_transient( $transient_key );
		if ( $response ) {
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
				'headers' => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 2,
				'timeout' => 5,
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

		return $body;
	}


	private function get_friend_account_data( $user_id, $meta = array() ) {
		$cache_key = 'account-' . $user_id;
		$ret = wp_cache_get( $cache_key, 'mastodon-api' );
		if ( false !== $ret ) {
			return $ret;
		}

		if ( preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $user_id ) ) {
			$url = $this->get_activitypub_url( $user_id );
			if ( ! $url ) {
				return array();
			}
			$account = $this->get_acct( $user_id );
			$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $url );
			$ret = array(
				'id' => $account,
				'acct'              => $account,
				'username'          => '',
				'display_name'      => '',
				'note'             => '',
				'created_at'        => date( \DateTimeInterface::RFC3339_EXTENDED ),
				'followers_count'   => 0,
				'following_count'   => 0,
				'statuses_count'    => 0,
				'url'               => '',
				'avatar'            => '',
				'avatar_static'     => '',
				'header'            => '',
				'header_static'     => '',
				'locked'            => false,
				'emojis'            => array(),
				'fields'            => array(),
				'bot'               => false,
				'group'             => false,
				'last_status_at'    => null,
				'source'            => array(
					'privacy'       => 'public',
					'sensitive'     => false,
					'language'      => 'en',
				),
			);

			if ( $meta && ! is_wp_error( $meta ) ) {
				$followers = $this->get_json( $meta['followers'], 'followers-' . $account, array( 'totalItems' => 0 ) );
				$following = $this->get_json( $meta['following'], 'following-' . $account, array( 'totalItems' => 0 ) );
				$outbox = $this->get_json( $meta['outbox'], 'outbox-' . $account, array( 'totalItems' => 0 ) );

				$ret['username'] = $meta['preferredUsername'];
				$ret['display_name'] = $meta['name'];
				$ret['note'] = $meta['summary'];
				$ret['created_at'] = $meta['published'];
				$ret['followers_count'] = intval( $followers['totalItems'] );
				$ret['following_count'] = intval( $following['totalItems'] );
				$ret['statuses_count'] = intval( $outbox['totalItems'] );
				$ret['url'] = $meta['url'];
				if ( isset( $meta['icon'] ) ) {
					$ret['avatar'] = $meta['icon']['url'];
					$ret['avatar_static'] = $meta['icon']['url'];
				}
				if ( isset( $meta['image'] ) ) {
					$ret['header'] = $meta['image']['url'];
					$ret['header_static'] = $meta['image']['url'];
				}
			}

			wp_cache_set( $cache_key, $ret, 'mastodon-api' );

			return $ret;
		}

		$user = false;
		if ( class_exists( '\Friends\User' ) ) {
			$user = \Friends\User::get_user_by_id( $user_id );
		}
		if ( ! $user || is_wp_error( $user ) ) {
			$user = new \WP_User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				$cache[$user_id] = new \WP_Error( 'user-not-found', __( 'User not found.', 'mastodon_api' ), array( 'status' => 403 ) );
				return $cache[$user_id];
			}
		}

		$avatar = get_avatar_url( $user->ID );
		if ( $user instanceof \Friends\User ) {
			$posts = $user->get_post_count_by_post_format();
		} else {
			// Get post count for the post format status for the user.
			$posts = array(
				'status' => count_user_posts( $user->ID, 'post', true ),
			);
		}


		$data = array(
			'id'                => strval( $user->ID ),
			'username'          => $user->user_login,
			'acct'              => isset( $meta['attributedTo']['id'] ) ? $this->get_acct( $meta['attributedTo']['id'] ) : $this->get_user_acct( $user ),
			'display_name'      => $user->display_name,
			'locked'            => false,
			'created_at'        => mysql2date( \DateTimeInterface::RFC3339_EXTENDED, $user->user_registered, false ),
			'followers_count'   => 0,
			'following_count'   => 0,
			'statuses_count'    => isset( $posts['status'] ) ? intval( $posts['status'] ) : 0,
			'note'              => '',
			'url'               => strval( $user->get_url() ),
			'avatar'            => $avatar,
			'avatar_static'     => $avatar,
			'header'            => '',
			'header_static'     => '',
			'emojis'            => array(),
			'fields'            => array(),
			'bot'               => false,
			'group'             => false,
			'last_status_at'    => null,
			'source'            => array(
				'privacy'       => 'public',
				'sensitive'     => false,
				'language'      => 'en',
			),
		);

		foreach ( apply_filters( 'friends_get_user_feeds', array(), $user ) as $feed ) {
			$meta = apply_filters( 'friends_get_feed_metadata', array(), $feed );
			if ( $meta && ! is_wp_error( $meta ) ) {
				if ( ! empty( $meta['image']['url'] ) ) {
					$data['header'] = $meta['image']['url'];
					$data['header_static'] = $meta['image']['url'];
				}
				$data['url'] = $meta['url'];
				$data['note'] = $meta['summary'];
				$data['acct'] = $this->get_acct( $meta['id'] );
			}
		}

		$cache[$user_id] = $data;
		return $cache[$user_id];
	}

	public function get_user_acct( $user ) {
		return strtok( $this->get_acct( get_author_posts_url( $user->ID ) ), '@' );
	}

	public function get_acct( $id_or_url ) {
		$webfinger = $this->webfinger( $id_or_url );
		if ( !isset( $webfinger['subject'] ) ) {
			return false;
		}
		return $webfinger['subject'];
	}

	public function get_activitypub_url( $id_or_url ) {
		$webfinger = $this->webfinger( $id_or_url );
		if ( empty( $webfinger['aliases'] ) ) {
			return false;
		}
		$first_alias = false;
		foreach ( $webfinger['aliases'] as $alias ) {
			if ( ! $first_alias ) {
				$first_alias = $alias;
			}
			if ( strpos( $alias, '@' ) === false ) {
				return $alias;
			}
			return $alias;
		}

		return $first_alias;
	}

	private function webfinger( $id_or_url ) {
		if ( strpos( $id_or_url, 'acct:' ) === 0 ) {
			$id_or_url = substr( $id_or_url, 5 );
		}

		$id = $id_or_url;
		if ( preg_match( '#^https://([^/]+)/(?:@|users/|author/)([^/]+)/?$#', $id_or_url, $m ) ) {
			$id = $m[2] . '@' . $m[1];
			$host = $m[1];
		} else {
			$parts = explode( '@', ltrim( $id_or_url, '@' ) );
			$host = $parts[1];
		}

		$transient_key = 'mastodon_api_webfinger_' . md5( $id_or_url );

		$body = \get_transient( $transient_key );
		if ( $body ) {
			if ( is_wp_error( $body ) ) {
				return $id;
			}
			return $body;
		}

		$url = \add_query_arg( 'resource', $id, 'https://' . $host . '/.well-known/webfinger' );
		if ( ! self::check_url( $url ) ) {
			$response = new \WP_Error( 'invalid_webfinger_url', null, $url );
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $response;
		}

		// try to access author URL
		$body = $this->get_json( $url, $transient_key );

		if ( \is_wp_error( $body ) ) {
			$body = new \WP_Error( 'webfinger_url_not_accessible', null, $url );
			\set_transient( $transient_key, $body, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $response;
		}

		\set_transient( $transient_key, $body, WEEK_IN_SECONDS );
		return $body;
	}

	public function api_nodeinfo() {
		global $wp_version;

		$software = array(
			'name' => 'WordPress/' . $wp_version . ' with Mastodon-API Plugin',
			'version' => self::VERSION,
		);
		if ( 'okhttp/4.9.2' === $_SERVER['HTTP_USER_AGENT'] ) {
			$software['name'] = 'pixelfed';
			$software['version'] = '0.11.4';
		}

		$ret = array(
			'metadata' => array(
				'nodeName' => get_bloginfo( 'name' ),
				'nodeDescription' => get_bloginfo( 'description' ),
				'software' => $software,
				'config' => array(
					'features' => array(
						"timelines" => array(
							'local' => true,
							'network' => true,
						),
						"mobile_apis" => true,
						"stories" => false,
						"video" => false,
						"import" => array(
							"instagram" => false,
							"mastodon" => false,
							"pixelfed" => false
						),
					),
				),
			),
			'version' => '2.0',
			'protocols' => array(
				'activitpypub',
			),
			"services" => array(
				"inbound" => array(),
				"outbound" => array(),
			),

			'software' => $software,
			'openRegistrations' => false,
		);

		return $ret;
	}


	public function api_instance() {
		$ret = array(
			'uri'               => home_url(),
			'account_domain'    => \wp_parse_url( \home_url(), \PHP_URL_HOST ),
			'title'             => get_bloginfo( 'name' ),
			'description'       => get_bloginfo( 'description' ),
			'email'             => 'not@public.example',
			'version'           => self::VERSION,
			'registrations'     => false,
			'approval_required' => false,
		);

		return $ret;
	}
}
