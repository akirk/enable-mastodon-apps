<?php
/**
 * Mastodon Apps
 *
 * This contains the Mastodon Apps handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * This is the class that implements the Mastodon Apps storage.
 *
 * @since 0.1
 *
 * @package Enable_Mastodon_Apps
 * @author Alex Kirk
 */
class Mastodon_App {
	/**
	 * Contains a reference to the term that represents the app.
	 *
	 * @var \WP_Term $term
	 */
	private $term;

	const DEBUG_CLIENT_ID = 'enable-mastodon-apps';
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
	 * @param \WP_Term $term The term that represents the app.
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

	public function set_client_secret( $client_secret ) {
		return update_term_meta( $this->term->term_id, 'client_secret', $client_secret );
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

	public function get_last_used() {
		return get_term_meta( $this->term->term_id, 'last_used', true );
	}

	public function get_creation_date() {
		return get_term_meta( $this->term->term_id, 'creation_date', true );
	}

	public function get_query_args() {
		return get_term_meta( $this->term->term_id, 'query_args', true );
	}

	public function get_post_formats() {
		$query_args = $this->get_query_args();
		if ( ! isset( $query_args['post_formats'] ) || ! is_array( $query_args['post_formats'] ) ) {
			return get_option( 'mastodon_api_default_post_formats', array( 'status' ) );
		}

		return $query_args['post_formats'];
	}

	public function set_post_formats( $post_formats ) {
		$query_args = $this->get_query_args();
		if ( ! is_array( $query_args ) ) {
			$query_args = array();
		}
		$query_args['post_formats'] = $post_formats;
		update_term_meta( $this->term->term_id, 'query_args', $query_args );
		return $this->get_post_formats();
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

	public function delete_last_requests() {
		return delete_metadata( 'term', $this->term->term_id, 'request' );
	}

	public function get_last_requests() {
		$requests = array();
		foreach ( get_term_meta( $this->term->term_id, 'request' ) as $request ) {
			if ( empty( $request ) || empty( $request['path'] ) ) {
				delete_metadata( 'term', $this->term->term_id, 'request', $request );
				continue;
			}
			$requests[ $request['timestamp'] * 10000 ] = $request;
		}

		ksort( $requests );
		return $requests;
	}

	public function was_used( $request, $additional_debug_data = array() ) {
		static $logged;
		if ( isset( $logged ) && $logged ) {
			// Prevent multiple calls.
			return true;
		}
		$logged = true;
		if ( get_option( 'mastodon_api_debug_mode' ) > time() ) {
			add_metadata(
				'term',
				$this->term->term_id,
				'request',
				array_merge(
					array(
						'timestamp'    => microtime( true ),
						'path'         => $_SERVER['REQUEST_URI'],
						'method'       => $_SERVER['REQUEST_METHOD'],
						'params'       => $request->get_params(),
						'json'         => $request->get_json_params(),
						'files'        => $request->get_file_params(),
						'current_user' => get_current_user_id(),
					),
					$additional_debug_data
				)
			);
		}

		if ( $this->get_last_used() > time() - MINUTE_IN_SECONDS ) {
			return true;
		}
		update_term_meta( $this->term->term_id, 'last_used', time() );
	}

	public function is_outdated() {
		foreach ( OAuth2\Access_Token_Storage::getAll() as $token ) {
			if ( $token['client_id'] === $this->get_client_id() && ! $token['expired'] ) {
				return false;
			}
		}
		foreach ( OAuth2\Authorization_Code_Storage::getAll() as $code ) {
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
		foreach ( get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		) as $term ) {
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
				'name'          => __( 'Mastodon Apps', 'enable-mastodon-apps' ),
				'singular_name' => __( 'Mastodon App', 'enable-mastodon-apps' ),
				'menu_name'     => __( 'Mastodon Apps', 'enable-mastodon-apps' ),
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'rewrite'      => false,
		);

		register_taxonomy( self::TAXONOMY, null, $args );

		register_term_meta(
			self::TAXONOMY,
			'client_secret',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_string( $value ) || strlen( $value ) < 16 || strlen( $value ) > 200 ) {
						throw new \Exception( 'invalid-client_secret,Client secret must be a string with a length between 16 and 200 chars.' );
					}
					return $value;
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'redirect_uris',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						$value = array( $value );
					}
					$urls = array();
					foreach ( $value as $url ) {
						if ( Mastodon_OAuth::OOB_REDIRECT_URI === $url ) {
							$urls[] = $url;
						} elseif ( preg_match( '#^[a-z0-9.-]+://?[a-z0-9.%-]*#i', $url ) ) {
							// custom protocols are ok.
							$urls[] = $url;
						}
					}

					if ( empty( $urls ) ) {
						throw new \Exception( 'invalid-redirect_uris,No valid redirect URIs given' );
					}

					return $urls;
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'client_name',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_string( $value ) || strlen( $value ) < 3 || strlen( $value ) > 200 ) {
						throw new \Exception( 'invalid-client_name,Client name must be a string with a length between 3 and 200 chars.' );
					}
					return $value;
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'scopes',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_string( $value ) ) {
						$value = '';
					}
					$scopes = array();
					foreach ( explode( ' ', $value ) as $s ) {
						if ( ! trim( $s ) ) {
							continue;
						}
						$scope_parts = explode( ':', $s, 2 );
						$scope = array_shift( $scope_parts );
						if ( ! in_array( $scope, self::VALID_SCOPES, true ) ) {
							throw new \Exception( 'invalid-scopes,Invalid scope given: ' . esc_html( $s ) );
						}
						$scopes[] = $s;
					}

					if ( empty( $scopes ) ) {
						throw new \Exception( 'invalid-scopes,No scopes given.' );
					}
					return implode( ' ', $scopes );
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'website',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => function ( $url ) {
					if ( ! $url ) {
						return '';
					}
					$host = parse_url( $url, PHP_URL_HOST );
					$protocol = parse_url( $url, PHP_URL_SCHEME );

					if ( ! $host || 0 !== strpos( 'https', $protocol ) ) {
						$url = '';
					}

					return $url;
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'creation_date',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'int',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_int( $value ) ) {
						$value = time();
					}
					return $value;
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'last_used',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'int',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_int( $value ) ) {
						$value = time();
					}
					return $value;
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'query_args',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$value = array_diff_key( $value, array( 'post_formats' ) );
					if ( isset( $value['post_formats'] ) ) {
						if ( ! is_array( $value['post_formats'] ) ) {
							$value['post_formats'] = array( $value['post_formats'] );
						}
						$value['post_formats'] = array_filter(
							$value['post_formats'],
							function ( $post_format ) {
								if ( ! in_array( $post_format, get_post_format_slugs(), true ) ) {
									return false;
								}
								return true;
							}
						);
						if ( empty( $value['post_formats'] ) ) {
							unset( $value['post_formats'] );
						}
					}
					return $value;
				},
			)
		);

		if ( get_option( 'mastodon_api_debug_mode' ) > time() ) {
			register_term_meta(
				self::TAXONOMY,
				'request',
				array(
					'show_in_rest'      => false,
					'single'            => false,
					'type'              => 'array',
					'sanitize_callback' => function ( $value ) {
						if ( ! is_array( $value ) ) {
							return array();
						}

						foreach ( array_keys( $value ) as $key ) {
							if ( 'path' === $key || 'user_agent' === $key ) {
								$value[ $key ] = preg_replace( '#[^A-Za-z0-9?&%=[\]+.:@_/-]#', ' ', $value[ $key ] );
								continue;
							}
							if ( 'status' === $key || 'current_user' === $key ) {
								$value[ $key ] = intval( $value[ $key ] );
								continue;
							}
							if ( 'timestamp' === $key ) {
								$value[ $key ] = floatval( $value[ $key ] );
								continue;
							}
							if ( 'method' === $key && preg_match( '/^[A-Z]{3,15}$/', $value[ $key ] ) ) {
								continue;
							}
							if ( ( 'files' === $key || 'params' === $key || 'json' === $key ) && ! empty( $value[ $key ] ) ) {
								continue;
							}
							unset( $value[ $key ] );
						}

						return $value;
					},
				)
			);
		}
	}

	public function modify_wp_query_args( $args ) {
		$tax_query = array();
		if ( isset( $args['tax_query'] ) ) {
			$tax_query = $args['tax_query'];
		}

		$filter_by_post_format = $this->get_post_formats();
		$post_formats = get_post_format_slugs();
		$filter_by_post_format = array_filter(
			$filter_by_post_format,
			function ( $post_format ) use ( $post_formats ) {
				return in_array( $post_format, $post_formats, true );
			}
		);
		if ( ! empty( $filter_by_post_format ) ) {
			if ( ! empty( $tax_query ) ) {
				$tax_query['relation'] = 'AND';
			}
			$post_format_query = array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
			);

			if ( in_array( 'standard', $filter_by_post_format, true ) ) {
				$post_format_query['operator'] = 'NOT IN';
				$post_format_query['terms']    = array_values(
					array_map(
						function ( $post_format ) {
							return 'post-format-' . $post_format;
						},
						array_diff( $post_formats, $filter_by_post_format )
					)
				);
			} else {
				$post_format_query['operator'] = 'IN';
				$post_format_query['terms']    = array_map(
					function ( $post_format ) {
						return 'post-format-' . $post_format;
					},
					$filter_by_post_format
				);
			}
			$tax_query[] = $post_format_query;
		}
		$args['tax_query'] = $tax_query;

		return $args;
	}

	public function has_scope( $requested_scope ) {
		return OAuth2\Scope_Util::checkSingleScope( $requested_scope, $this->get_scopes() );
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

	public static function get_debug_app() {
		$term = term_exists( self::DEBUG_CLIENT_ID, self::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( self::DEBUG_CLIENT_ID, self::TAXONOMY );
			add_metadata( 'term', $term['term_id'], 'client_name', 'Debugger', true );
		}
		$term = get_term( $term['term_id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term );
	}

	public static function save( $client_name, array $redirect_uris, $scopes, $website ) {
		$client_id = strtolower( wp_generate_password( 32, false ) );
		$client_secret = wp_generate_password( 128, false );

		$term = wp_insert_term( $client_id, self::TAXONOMY );

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$app_metadata = compact(
			'client_name',
			'redirect_uris',
			'scopes',
			'website'
		);

		$post_formats = get_option( 'mastodon_api_default_post_formats', array( 'status' ) );
		$post_formats = apply_filters( 'mastodon_api_new_app_post_formats', $post_formats, $app_metadata );

		$term_id = $term['term_id'];
		foreach ( $app_metadata as $key => $value ) {
			add_metadata( 'term', $term_id, $key, $value, true );
		}
		add_metadata( 'term', $term_id, 'client_secret', $client_secret, true );
		add_metadata( 'term', $term_id, 'creation_date', time(), true );

		$term = get_term( $term['term_id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term );
	}
}
