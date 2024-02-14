<?php
/**
 * Plugin name: Enable Mastodon Apps
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/enable-mastodon-apps
 * Version: 0.6.6
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
define( 'ENABLE_MASTODON_APPS_VERSION', '0.6.6' );

require __DIR__ . '/vendor/bshaffer/oauth2-server-php/src/OAuth2/Autoloader.php';
OAuth2\Autoloader::register();

/**
 * Class Autoloader
 */
\spl_autoload_register(
	function ( $full_class ) {
		$base_dir = __DIR__ . '/includes/';
		$base     = 'Enable_Mastodon_Apps\\';

		if ( strncmp( $full_class, $base, strlen( $base ) ) === 0 ) {
			$maybe_uppercase = str_replace( $base, '', $full_class );
			$class = strtolower( $maybe_uppercase );
			// All classes should be capitalized. If this is instead looking for a lowercase method, we ignore that.
			if ( $maybe_uppercase === $class ) {
				return;
			}

			if ( false !== strpos( $class, '\\' ) ) {
				$parts    = explode( '\\', $class );
				$class    = array_pop( $parts );
				$sub_dir  = strtolower( strtr( implode( '/', $parts ), '_', '-' ) );
				$base_dir = $base_dir . $sub_dir . '/';
			}

			$filename = 'class-' . strtr( $class, '_', '-' );
			$file     = $base_dir . $filename . '.php';

			if ( file_exists( $file ) && is_readable( $file ) ) {
				require_once $file;
			} else {
				// translators: %s is the class name.
				\wp_die( sprintf( esc_html__( 'Required class not found or not readable: %s', 'enable-mastodon-apps' ), esc_html( $full_class ) ) );
			}
		}
	}
);

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
	function () {
		new Enable_Mastodon_Apps\Mastodon_API();
	}
);
