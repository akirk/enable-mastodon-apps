<?php
/**
 * Authorization Code Storage
 *
 * This file implements authorization code storage.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\OAuth2;

use OAuth2\Storage\AuthorizationCodeInterface;

/**
 * This class implements the OAuth2 authorization code storage.
 */
class Authorization_Code_Storage implements AuthorizationCodeInterface {
	const TAXONOMY = 'mastoapi-ac';

	private static $authorization_code_data = array(
		'client_id'    => 'string', // client identifier.
		'user_id'      => 'int',    // The WordPress user id.
		'redirect_uri' => 'string', // redirect URI.
		'expires'      => 'int',    // expires as unix timestamp.
		'scope'        => 'string', // scope as space-separated string.
	);

	public function __construct() {
		add_action( 'mastodon_api_cron_hook', array( $this, 'cleanupOldCodes' ) );

		// Store the authorization codes in a taxonomy.
		register_taxonomy( self::TAXONOMY, null );
		foreach ( self::$authorization_code_data as $key => $type ) {
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
	 * Gets all authorization codes.
	 *
	 * @return     array  All codes.
	 */
	public static function getAll() {
		$codes = array();

		$terms = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		foreach ( $terms->get_terms() as $term ) {
			$code = array();
			foreach ( array_keys( self::$authorization_code_data ) as $key ) {
				$code[ $key ] = get_term_meta( $term->term_id, $key, true );
			}
			$codes[ $term->slug ] = $code;
		}

		return $codes;
	}

	/**
	 * Fetch authorization code data (probably the most common grant type).
	 *
	 * Retrieve the stored data for the given authorization code.
	 *
	 * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
	 *
	 * @param string $code  Authorization code to be check with.
	 *
	 * @return An associative array as below, and NULL if the code is invalid
	 *
	 * @code
	 * return array(
	 *     "client_id"    => CLIENT_ID,      // REQUIRED Stored client identifier
	 *     "user_id"      => USER_ID,        // REQUIRED Stored user identifier
	 *     "expires"      => EXPIRES,        // REQUIRED Stored expiration in unix timestamp
	 *     "redirect_uri" => REDIRECT_URI,   // REQUIRED Stored redirect URI
	 *     "scope"        => SCOPE,          // OPTIONAL Stored scope values in space-separated string
	 * );
	 * @endcode
	 *
	 * @see http://tools.ietf.org/html/rfc6749#section-4.1
	 *
	 * @ingroup oauth2_section_4
	 */
	public function getAuthorizationCode( $code ) {
		$term = get_term_by( 'slug', $code, self::TAXONOMY );

		if ( $term ) {
			$authorization_code = array();
			foreach ( array(
				'client_id'    => 'client_id',
				'user_id'      => 'user_id',
				'expires'      => 'expires',
				'redirect_uri' => 'redirect_uri',
				'scope'        => 'scope',
			) as $key => $meta_key ) {
				$authorization_code[ $key ] = get_term_meta( $term->term_id, $meta_key, true );
			}

			return $authorization_code;
		}

		return null;
	}

	/**
	 * Take the provided authorization code values and store them somewhere.
	 *
	 * This function should be the storage counterpart to getAuthCode().
	 *
	 * If storage fails for some reason, we're not currently checking for
	 * any sort of success/failure, so you should bail out of the script
	 * and provide a descriptive fail message.
	 *
	 * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
	 *
	 * @param string $code          - authorization code to be stored.
	 * @param mixed  $client_id     - client identifier to be stored.
	 * @param mixed  $user_id       - user identifier to be stored.
	 * @param string $redirect_uri  - redirect URI(s) to be stored in a space-separated string.
	 * @param int    $expires       - expiration to be stored as a Unix timestamp.
	 * @param string $scope         - OPTIONAL scopes to be stored in space-separated string.
	 *
	 * @ingroup oauth2_section_4
	 */
	public function setAuthorizationCode( $code, $client_id, $user_id, $redirect_uri, $expires, $scope = null ) {
		if ( $code ) {
			$this->expireAuthorizationCode( $code );

			$term = wp_insert_term( $code, self::TAXONOMY );
			if ( is_wp_error( $term ) || ! isset( $term['term_id'] ) ) {
				status_header( 500 );
				exit;
			}

			foreach ( array(
				'client_id'    => $client_id,
				'user_id'      => $user_id,
				'redirect_uri' => $redirect_uri,
				'expires'      => $expires,
				'scope'        => $scope,
			) as $key => $value ) {
				add_term_meta( $term['term_id'], $key, $value );
			}
		}
	}

	/**
	 * Once an Authorization Code is used, it must be expired
	 *
	 * @param      string $code   The code.
	 *
	 * @see http://tools.ietf.org/html/rfc6749#section-4.1.2
	 *
	 *    The client MUST NOT use the authorization code
	 *    more than once.  If an authorization code is used more than
	 *    once, the authorization server MUST deny the request and SHOULD
	 *    revoke (when possible) all tokens previously issued based on
	 *    that authorization code
	 */
	public function expireAuthorizationCode( $code ) {
		$term = get_term_by( 'slug', $code, self::TAXONOMY );

		if ( $term ) {
			wp_delete_term( $term->term_id, self::TAXONOMY );
		}
		return true;
	}

	/**
	 * This function cleans up auth codes that are sitting in the database because of interrupted/abandoned OAuth flows.
	 */
	public static function cleanupOldCodes() {
		$terms = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'expires',
						'value'   => time(),
						'compare' => '<',
					),
				),
			)
		);

		if ( ! $terms ) {
			return;
		}
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
		if ( ! $terms ) {
			return;
		}
		foreach ( $terms->terms as $term_id ) {
			wp_delete_term( $term_id, self::TAXONOMY );
		}
	}
}
