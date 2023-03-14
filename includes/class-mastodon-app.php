<?php
/**
 * Mastodon Apps
 *
 * This contains the Mastodon Apps handlers.
 *
 * @package Mastodon_Apps
 */

namespace Mastodon_API;

/**
 * This is the class that implements the Mastodon Apps storage.
 *
 * @since 0.1
 *
 * @package Mastodon_Apps
 * @author Alex Kirk
 */
class Mastodon_App {
	/**
	 * Contains a reference to the term that represents the app.
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
	 * @param WP_Term $term The term that represents the app.
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

	public function get_creation_date() {
		$date = get_term_meta( $this->term->term_id, 'creation_date', true );
		if ( ! $date ) {
			return null;
		}
		return date( 'r', $date );
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

	public function is_outdated() {
		foreach( OAuth2\AccessTokenStorage::getAll() as $token ) {
			if ( $token['client_id'] === $this->get_client_id() && !$token['expired'] ) {
				return false;
			}
		}
		foreach( OAuth2\AuthorizationCodeStorage::getAll() as $code ) {
			if ( $code['client_id'] === $this->get_client_id() && ! $code['expired'] ) {
				return false;
			}
		}
		return true;
	}

	public function delete() {
		return wp_delete_term( $this->term->term_id, self::TAXONOMY );
	}

	public static function get_all() {
		$apps = array();
		foreach ( get_terms( array(
			'taxonomy' => self::TAXONOMY,
			'hide_empty' => false,
		) ) as $term ) {
			if ( $term instanceof \WP_Term ) {
				$app = new Mastodon_App( $term );
				$apps[ $app->get_client_id() ] = $app;
			}
		}

		return $apps;
	}

	public static function register_taxonomy() {
		$args = array(
			'labels'       => array(
				'name'          => __( 'Mastodon Apps', 'mastodon-api' ),
				'singular_name' => __( 'Mastodon App', 'mastodon-api' ),
				'menu_name'     => __( 'Mastodon Apps', 'mastodon-api' ),
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
					if ( Mastodon_OAuth::OOB_REDIRECT_URI === $url ) {
						$urls[] = $url;
					} elseif ( preg_match( '#[a-z0-9.-]+://[a-z0-9.-]+#i', $url ) ) {
						// custom protocols are ok.
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
			'sanitize_callback' => function( $url ) {
				$host = parse_url( $url, PHP_URL_HOST );

				$check_url = apply_filters( 'friends_host_is_valid', null, $host );
				if ( ! is_null( $check_url ) ) {
					return $check_url;
				}

				if ( ! wp_http_validate_url( $url ) ) {
					$url = '';
				}

				return $url;
			},
		) );

		register_term_meta( self::TAXONOMY, 'creation_date', array(
			'show_in_rest' => false,
			'single'       => true,
			'type'         => 'int',
			'sanitize_callback' => function( $value ) {
				if ( ! is_int( $value ) ) {
					$value = time();
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

	public static function delete_outdated() {
		$count = 0;
		foreach ( self::get_all() as $app ) {
			if ( $app->is_outdated() ) {
				if ( $app->delete() ) {
					$count += 1;
				}
			}
		}
		return $count;
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
		add_metadata( 'term', $term_id, 'creation_date', time(), true );

		$term = get_term( $term['term_id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term );
	}
}
