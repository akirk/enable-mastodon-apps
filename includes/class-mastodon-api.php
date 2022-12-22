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
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	const PREFIX = 'friends-mastodon-api';

	private $rewrite_rules = array(
		'api/v1/apps',
	);

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	function register_hooks() {
		add_action( 'wp_loaded', array( $this, 'rewrite_rules' ) );
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );

	}

	public function add_rest_routes() {
		register_rest_route(
			self::PREFIX,
			'api/v1/apps',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_apps' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rewrite_rules() {
		$rules = get_option( 'rewrite_rules' );
		$needs_flush = false;

		foreach ( $this->rewrite_rules as $rule ) {
			if ( ! isset( $rules[ $rule ] ) ) {
				add_rewrite_rule( $rule, 'index.php?rest_route=/' . self::PREFIX . '/' . $rule, 'top' );
			}
		}

		if ( $needs_flush ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}

	function api_apps() {
		return array( 'ok' => true );
	}
}
