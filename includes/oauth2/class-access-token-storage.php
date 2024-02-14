<?php
/**
 * OAuth2 Storage: Access_Token_Storage class
 *
 * @package Friends
 */

namespace Enable_Mastodon_Apps\OAuth2;

use OAuth2\Storage\AccessTokenInterface;

/**
 * This class describes an access token storage.
 */
class Access_Token_Storage implements AccessTokenInterface {
	const TAXONOMY = 'mastoapi-at';

	private static $access_token_data = array(
		'client_id'    => 'string', // client identifier.
		'user_id'      => 'int',    // The WordPress user id.
		'redirect_uri' => 'string', // redirect URI.
		'expires'      => 'int',    // expires as unix timestamp.
		'last_used'    => 'int',    // last used as unix timestamp.
		'scope'        => 'string', // scope as space-separated string.
	);

	public function __construct() {
		add_action( 'mastodon_api_cron_hook', array( $this, 'cleanupOldCodes' ) );

		// Store the access tokens in a taxonomy.
		register_taxonomy( self::TAXONOMY, null );
		foreach ( self::$access_token_data as $key => $type ) {
			register_term_meta(
				self::TAXONOMY,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_' . $key ),
				)
			);
		}
	}

	/**
	 * Sanitize the token content.
	 *
	 * @param      string $token   The token.
	 *
	 * @return     string  The sanitized token.
	 */
	public static function sanitize_token( $token ) {
		return substr( $token, 0, 200 );
	}

	/**
	 * Sanitize the client identifier.
	 *
	 * @param      string $client_id  The client id.
	 *
	 * @return     string  The sanitized client id.
	 */
	public static function sanitize_client_id( $client_id ) {
		return substr( $client_id, 0, 200 );
	}

	/**
	 * Sanitize the redirect URI.
	 *
	 * @param      string $redirect_uri  The redirect uri.
	 *
	 * @return     string  The sanitized redirect uri.
	 */
	public static function sanitize_redirect_uri( $redirect_uri ) {
		return substr( $redirect_uri, 0, 2000 );
	}

	/**
	 * Sanitize the scope.
	 *
	 * @param      string $scope  The scope.
	 *
	 * @return     string  The sanitized scope.
	 */
	public static function sanitize_scope( $scope ) {
		return substr( $scope, 0, 100 );
	}

	/**
	 * Sanitize the user id.
	 *
	 * @param      string $user_id  The user id.
	 *
	 * @return     string  The sanitized user id.
	 */
	public static function sanitize_user_id( $user_id ) {
		return intval( $user_id );
	}

	/**
	 * Sanitize the expires.
	 *
	 * @param      int $expires  The expires.
	 *
	 * @return     int  The sanitized expires.
	 */
	public static function sanitize_expires( $expires ) {
		return intval( $expires );
	}

	/**
	 * Sanitize the last used.
	 *
	 * @param      int $last_used  The last used.
	 *
	 * @return     int  The sanitized last used.
	 */
	public static function sanitize_last_used( $last_used ) {
		return intval( $last_used );
	}

	public static function getAll() {
		$tokens = array();

		$terms = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		foreach ( $terms->get_terms() as $term ) {
			$token = array();
			foreach ( array_keys( self::$access_token_data ) as $key ) {
				$token[ $key ] = get_term_meta( $term->term_id, $key, true );
			}
			$tokens[ $term->slug ] = $token;
		}

		return $tokens;
	}

	/**
	 * Look up the supplied oauth_token from storage.
	 *
	 * We need to retrieve access token data as we create and verify tokens.
	 *
	 * @param string $oauth_token - oauth_token to be check with.
	 *
	 * @return array|null - An associative array as below, and return NULL if the supplied oauth_token is invalid:
	 * @code
	 *     array(
	 *         'expires'   => $expires,   // Stored expiration in unix timestamp.
	 *         'client_id' => $client_id, // (optional) Stored client identifier.
	 *         'user_id'   => $user_id,   // (optional) Stored user identifier.
	 *         'scope'     => $scope,     // (optional) Stored scope values in space-separated string.
	 *         'id_token'  => $id_token   // (optional) Stored id_token (if "use_openid_connect" is true).
	 *     );
	 * @endcode
	 *
	 * @ingroup oauth2_section_7
	 */
	public function getAccessToken( $oauth_token ) {
		$term = get_term_by( 'slug', $oauth_token, self::TAXONOMY );

		if ( $term ) {
			$access_token = array(
				'access_token' => $oauth_token,
			);
			foreach ( array_keys( self::$access_token_data ) as $meta_key ) {
				$access_token[ $meta_key ] = get_term_meta( $term->term_id, $meta_key, true );
			}

			$access_token['created_at'] = $access_token['expires'] - YEAR_IN_SECONDS * 2;
			return $access_token;
		}

		return null;
	}

	/**
	 * Store the supplied access token values to storage.
	 *
	 * We need to store access token data as we create and verify tokens.
	 *
	 * @param string $oauth_token - oauth_token to be stored.
	 * @param mixed  $client_id   - client identifier to be stored.
	 * @param mixed  $user_id     - user identifier to be stored.
	 * @param int    $expires     - expiration to be stored as a Unix timestamp.
	 * @param string $scope       - OPTIONAL Scopes to be stored in space-separated string.
	 *
	 * @ingroup oauth2_section_4
	 */
	public function setAccessToken( $oauth_token, $client_id, $user_id, $expires, $scope = null ) {
		if ( $oauth_token ) {
			$this->unsetAccessToken( $oauth_token );

			$term = wp_insert_term( $oauth_token, self::TAXONOMY );
			if ( is_wp_error( $term ) || ! isset( $term['term_id'] ) ) {
				status_header( 500 );
				exit;
			}

			foreach ( array(
				'client_id' => $client_id,
				'user_id'   => $user_id,
				'expires'   => $expires,
				'scope'     => $scope,
			) as $key => $value ) {
				add_term_meta( $term['term_id'], $key, $value );
			}
		}
	}

	/**
	 * Expire an access token.
	 *
	 * This is not explicitly required in the spec, but if defined in a draft RFC for token
	 * revoking (RFC 7009) https://tools.ietf.org/html/rfc7009
	 *
	 * @param string $access_token Access token to be expired.
	 *
	 * @return BOOL true if an access token was unset, false if not
	 * @ingroup oauth2_section_6
	 *
	 * @todo v2.0 include this method in interface. Omitted to maintain BC in v1.x
	 */
	public function unsetAccessToken( $access_token ) {
		$term = get_term_by( 'slug', $access_token, self::TAXONOMY );

		if ( $term ) {
			wp_delete_term( $term->term_id, self::TAXONOMY );
		}
		return true;
	}

	/**
	 * Access token was used.
	 *
	 * @param      string $access_token  The access token.
	 */
	public static function was_used( $access_token ) {
		$term = get_term_by( 'slug', $access_token, self::TAXONOMY );

		if ( $term ) {
			$last_used = get_term_meta( $term->term_id, 'last_used', true );
			if ( $last_used > time() - MINUTE_IN_SECONDS ) {
				return;
			}
			update_term_meta( $term->term_id, 'last_used', time() );
		}
	}

	/**
	 * This function cleans up access tokens that are sitting in the database because of interrupted/abandoned OAuth flows.
	 */
	public static function cleanupOldTokens() {
		$terms = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => 'expires',
						'value'   => time(),
						'compare' => '<',
					),
				),
			)
		);

		foreach ( $terms->terms as $term_id ) {
			wp_delete_term( $term_id, self::TAXONOMY );
		}
	}

	/**
	 * Delete all auth codes when the plugin is uninstalled.
	 */
	public static function uninstall() {
		$terms = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		foreach ( $terms->terms as $term_id ) {
			wp_delete_term( $term_id, self::TAXONOMY );
		}
	}
}
