<?php
/**
 * Plugin name: Mastodon API
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/mastodon-api
 * Version: 0.1.0
 *
 * Description: Allow accessing your WordPress with Mastodon clients. Just enter your own blog URL as your instance.
 *
 * License: GPL2
 * Text Domain: mastodon-api
 *
 * @package Mastodon_API
 */

defined( 'ABSPATH' ) || exit;
define( 'MASTODON_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MASTODON_API_VERSION', '0.1.0' );

require __DIR__ . '/vendor/bshaffer/oauth2-server-php/src/OAuth2/Autoloader.php';
OAuth2\Autoloader::register();

require_once __DIR__ . '/includes/oauth2/access-token-storage.php';
require_once __DIR__ . '/includes/oauth2/authenticate-handler.php';
require_once __DIR__ . '/includes/oauth2/authorization-code-storage.php';
require_once __DIR__ . '/includes/oauth2/authorize-handler.php';
require_once __DIR__ . '/includes/oauth2/mastodon-app-storage.php';
require_once __DIR__ . '/includes/oauth2/token-handler.php';
require_once __DIR__ . '/includes/class-mastodon-oauth.php';

require_once __DIR__ . '/includes/class-mastodon-admin.php';
require_once __DIR__ . '/includes/class-mastodon-api.php';
require_once __DIR__ . '/includes/class-mastodon-app.php';

if ( apply_filters( 'friends_debug', false ) ) {
    add_filter( 'friends_host_is_valid', function( $result, $host ) {
        if ( $host === 'localhost' ) {
            return true;
        }
        return $result;
    }, 10, 2 );
}

add_action(
    'init',
    function() {
        new Mastodon_API\Mastodon_API();
    }
);
