<?php
/**
 * Plugin name: Enable Mastodon Apps
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/enable-mastodon-apps
 * Version: 0.3.3
 *
 * Description: Allow accessing your WordPress with Mastodon clients. Just enter your own blog URL as your instance.
 *
 * License: GPL2
 * Text Domain: enable-mastodon-apps
 *
 * @package Enable_Mastodon_Apps
 */

defined( 'ABSPATH' ) || exit;
define( 'ENABLE_MASTODON_APPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ENABLE_MASTODON_APPS_VERSION', '0.3.3' );

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

function mastodon_api_pixelfed_nodeinfo_software( $software ) {
	if ( 'okhttp/4.9.2' === $_SERVER['HTTP_USER_AGENT'] ) {
		return array(
			'name'    => 'pixelfed',
			'version' => '0.11.4',
		);
	}

	return $software;
}
add_filter( 'mastodon_api_nodeinfo_software', 'mastodon_api_pixelfed_nodeinfo_software' );

function mastodon_api_pixelfed_post_formats( $post_formats, $app_metadata ) {
	if ( in_array( $app_metadata, array( 'Pixelfed' ) ) ) {
		$post_formats = array( 'image' );
	}

	return $post_formats;
}
add_filter( 'mastodon_api_new_app_post_formats', 'mastodon_api_pixelfed_post_formats', 10, 2 );

add_action(
	'init',
	function() {
		new Enable_Mastodon_Apps\Mastodon_API();
	}
);
