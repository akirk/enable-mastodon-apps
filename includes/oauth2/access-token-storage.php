<?php
/**
 * OAuth2 Storage: AccessTokenStorage class
 *
 * @package Friends
 */

namespace Friends\OAuth2;

use OAuth2\Storage\AccessTokenInterface;
use PO;

class AccessTokenStorage implements AccessTokenInterface {
    const META_KEY_PREFIX = 'friends_oa2_access_token';

    private static $access_token_data = array(
        'client_id'    => 'string', // client identifier.
        'expires'      => 'int',    // expires as unix timestamp.
        'scope'        => 'string', // scope as space-separated string.
    );

    public function __construct() {
        add_action( 'oidc_cron_hook', array( $this, 'cleanupOldCodes' ) );
    }

    public static function getAll() {
        $tokens = array();

        foreach ( get_user_meta( get_current_user_id() ) as $key => $value ) {
            if ( 0 !== strpos( $key, self::META_KEY_PREFIX ) ) {
                continue;
            }
            $key_parts = explode( '_', substr( $key, strlen( self::META_KEY_PREFIX ) + 1 ) );
            $token = array_pop( $key_parts );
            $key = implode( '_', $key_parts );
            $value = $value[0];

            if ( 'expires' === $key ) {
                $tokens[$token]['expired'] = time() > $value;
                $value = date( 'r', $value ) ;
            }

            $tokens[$token][$key] = $value;
        }

        return $tokens;
    }

    private function getUserIdByToken( $oauth_token ) {
        if ( empty( $oauth_token ) ) {
            return null;
        }

        $users = get_users(
            array(
                'number'       => 1,
                // Specifying blog_id does nothing for non-MultiSite installs. But for MultiSite installs, it allows you
                // to customize users of which site is supposed to be available for whatever sites
                // this plugin is meant to be activated on.
                'blog_id'      => apply_filters( 'oidc_auth_code_storage_blog_id', get_current_blog_id() ),
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_key'     => self::META_KEY_PREFIX . '_client_id_' . $oauth_token,
                // Using a meta_key EXISTS query is not slow, see https://github.com/WordPress/WordPress-Coding-Standards/issues/1871.
                'meta_compare' => 'EXISTS',
            )
        );

        if ( empty( $users ) ) {
            return null;
        }

        return absint( $users[0]->ID );
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
        $user_id = $this->getUserIdByToken( $oauth_token );
        if ( empty( $user_id ) ) {
            return null;
        }

        $user = new \WP_User( $user_id );

        $access_token = array(
            'user_id' => $user->ID,
        );
        foreach ( array_keys( self::$access_token_data ) as $key ) {
            $access_token[ $key ] = get_user_meta( $user_id, self::META_KEY_PREFIX . '_' . $key . '_' . $oauth_token, true );
        }

        return $access_token;
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
    public function setAccessToken($oauth_token, $client_id, $user_id, $expires, $scope = null) {
        if ( empty( $oauth_token ) ) {
            return;
        }
        $user = get_user_by( 'login', $user_id ); // We have chosen WordPress' user_login as the user identifier for OIDC context.

        if ( $user ) {
            foreach ( self::$access_token_data as $key => $data_type ) {
                if ( 'int' === $data_type ) {
                    $value = absint( $$key );
                } else {
                    $value = sanitize_text_field( $$key );
                }

                update_user_meta( $user->ID, self::META_KEY_PREFIX . '_' . $key . '_' . $oauth_token, $value );
            }
        }
    }

    /**
     * Expire an access token.
     *
     * This is not explicitly required in the spec, but if defined in a draft RFC for token
     * revoking (RFC 7009) https://tools.ietf.org/html/rfc7009
     *
     * @param $access_token
     * Access token to be expired.
     *
     * @return BOOL true if an access token was unset, false if not
     * @ingroup oauth2_section_6
     *
     * @todo v2.0 include this method in interface. Omitted to maintain BC in v1.x
     */
    public function unsetAccessToken( $access_token ) {
        $user_id = $this->getUserIdByToken( $access_token );
        if ( empty( $user_id ) ) {
            return null;
        }

        foreach ( array_keys( self::$access_token_data ) as $key ) {
            delete_user_meta( $user_id, self::META_KEY_PREFIX . '_' . $key . '_' . $access_token );
        }
    }

    /**
     * This function cleans up auth codes that are sitting in the database because of interrupted/abandoned OAuth flows.
     */
    public function cleanupOldCodes() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_key FROM $wpdb->usermeta WHERE meta_key LIKE %s AND meta_value < %d",
                self::META_KEY_PREFIX . '_expires_%',
                time() - 3600 // wait for an hour past expiry, to offer a chance at debug.
            )
        );
        if ( empty( $data ) ) {
            return;
        }

        foreach ( $data as $row ) {
            $code = substr( $row->meta_key, strlen( self::META_KEY_PREFIX . '_expires_' ) );
            foreach ( array_keys( self::$access_token_data ) as $key ) {
                delete_user_meta( $row->user_id, self::META_KEY_PREFIX . '_' . $key . '_' . $code );
            }
        }
    }

    public static function uninstall() {
        global $wpdb;

        // Following query is only possible via a direct query since meta_key is not a fixed string
        // and since it only runs at uninstall, we don't need it cached.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_key FROM $wpdb->usermeta WHERE meta_key LIKE %s",
                self::META_KEY_PREFIX . '_expires_%'
            )
        );
        if ( empty( $data ) ) {
            return;
        }

        foreach ( $data as $row ) {
            $code = substr( $row->meta_key, strlen( self::META_KEY_PREFIX . '_expires_' ) );
            foreach ( array_keys( self::$access_token_data ) as $key ) {
                delete_user_meta( $row->user_id, self::META_KEY_PREFIX . '_' . $key . '_' . $code );
            }
        }
    }
}
