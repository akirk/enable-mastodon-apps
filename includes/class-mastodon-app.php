<?php
/**
 * Friends Mastodon Apps
 *
 * This contains the REST Apps handlers.
 *
 * @package Friends_Mastodon_Apps
 */

namespace Friends;

/**
 * This is the class that implements the Mastodon Apps endpoints.
 *
 * @since 0.1
 *
 * @package Friends_Mastodon_Apps
 * @author Alex Kirk
 */
class Mastodon_App {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var \WP_Term
	 */
	private $term;

	const TAXONOMY = 'mastodon-app';
	const VALID_SCOPES = array(
		'read',
		'write',
		'follow',
		'push',
	);


	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( \WP_Term $term ) {
		$this->term = $term;
	}

	public function get_client_id() {
		return $this->term->slug;
	}

	public function get_client_secret() {
		return get_term_meta( $this->term->term_id, 'client_secret', true );
	}

	public function get_redirect_uris() {
		return get_term_meta( $this->term->term_id, 'redirect_uris', true );
	}

	public function get_client_name() {
		return get_term_meta( $this->term->term_id, 'client_name', true );
	}

	public function get_scopes() {
		return get_term_meta( $this->term->term_id, 'scopes', true );
	}

	public function get_website() {
		return get_term_meta( $this->term->term_id, 'website', true );
	}

	public function check_redirect_uri( $redirect_uri ) {
		$redirect_uris = $this->get_redirect_uris();
		if ( ! is_array( $redirect_uris ) ) {
			$redirect_uris = array( $redirect_uris );
		}

		foreach ( $redirect_uris as $uri ) {
			if ( $uri === $redirect_uri ) {
				return true;
			}
		}

		return false;
	}

	public function check_scopes( $requested_scopes ) {
		$allowed_scopes = explode( ' ', $this->get_scopes() );

		foreach (  explode( ' ', $requested_scopes ) as $s ) {
			if ( ! in_array( $s, $allowed_scopes, true ) ) {
				return false;
			}
		}

		return false;
	}



	public static function register_taxonomy() {
		$args = array(
			'labels'       => array(
				'name'          => __( 'Mastodon Apps', 'friends' ),
				'singular_name' => __( 'Mastodon App', 'friends' ),
				'menu_name'     => __( 'Mastodon Apps', 'friends' ),
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'rewrite'      => false,
		);

		register_taxonomy( self::TAXONOMY, null, $args );

		register_term_meta( self::TAXONOMY, 'client_secret', array(
			'show_in_rest' => false,
			'single'       => true,
			'type'         => 'string',
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_term_meta( self::TAXONOMY, 'redirect_uris', array(
			'show_in_rest' => false,
			'single'       => true,
			'type'         => 'array',
			'sanitize_callback' => function( $value ) {
				if ( ! is_array( $value ) ) {
					return array();
				}
				$urls = array();
				foreach ( $value as $url ) {
					if ( Mastodon_Oauth::OOB_REDIRECT_URI !== $url ) {
						$urls[] = $url;
					} elseif ( Friends::check_url( $url ) ) {
						$urls[] = $url;
					}
				}

				if ( empty( $urls ) ) {
					throw new \Exception( 'invalid_redirect_uris,No valid redirect URIs given' );
				}

				return $urls;
			},
		) );

		register_term_meta( self::TAXONOMY, 'client_name', array(
			'show_in_rest' => false,
			'single'       => true,
			'type'         => 'string',
			'sanitize_callback' => function( $value ) {
				if ( ! is_string( $value ) || strlen( $value ) > 200 ) {
					throw new \Exception( 'invalid_client_name,Client secret must be a string of 200 chars or less.' );
				}
				return $value;
			},
		) );

		register_term_meta( self::TAXONOMY, 'scopes', array(
			'show_in_rest' => false,
			'single'       => true,
			'type'         => 'string',
			'sanitize_callback' => function( $value ) {
				foreach ( explode( ' ', $value ) as $scope ) {
					if ( ! in_array( $scope, self::VALID_SCOPES, true ) ) {
						throw new \Exception( 'invalid_scope,Invalid scope given: ' . $scope );
					}
				}

				return $value;
			}
		) );

		register_term_meta( self::TAXONOMY, 'website', array(
			'show_in_rest' => false,
			'single'       => true,
			'type'         => 'string',
			'sanitize_callback' => function( $value ) {
				if ( ! Friends::check_url( $value ) ) {
					$value = '';
				}
				return $value;
			},
		) );
	}

	/**
	 * Get an app via client_id.
	 *
	 * @param      string $client_id     The client id.
	 *
	 * @return     object|\WP_Error   A Mastodon_App object.
	 */
	public static function get_by_client_id( $client_id ) {
		$term_query = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'slug'       => $client_id,
				'hide_empty' => false,
			)
		);
		foreach ( $term_query->get_terms() as $term ) {
			return new self( $term );
		}

		return new \WP_Error( 'term_not_found', $client_id );
	}


	public static function save( $client_name, array $redirect_uris, $scopes, $website ) {
		$client_id = strtolower( wp_generate_password( 32, false ) );
		$client_secret = wp_generate_password( 128, false );

		$term = wp_insert_term( $client_id, self::TAXONOMY );

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = $term['term_id'];
		foreach ( compact(
			'client_name',
			'client_secret',
			'redirect_uris',
			'scopes',
			'website',
		) as $key => $value ) {
			if ( metadata_exists( 'term', $term_id, $key ) ) {
				update_metadata( 'term', $term_id, $key, $value );
			} else {
				add_metadata( 'term', $term_id, $key, $value, true );
			}
		}

		$term = get_term( $term['term_id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term );
	}
}
