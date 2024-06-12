<?php
/**
 * Plugin name: Enable Mastodon Apps
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/enable-mastodon-apps
 * Version: 0.9.3
 *
 * Description: Allow accessing your WordPress with Mastodon clients. Just enter your own blog URL as your instance.
 *
 * License: GPL2
 * Text Domain: enable-mastodon-apps
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;
use OAuth2;

defined( 'ABSPATH' ) || exit;
define( 'ENABLE_MASTODON_APPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ENABLE_MASTODON_APPS_VERSION', '0.9.3' );

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
				\wp_die( sprintf( esc_html__( 'Required class not found or not readable: %s', 'enable-mastodon-apps' ), esc_html( $file ) ) );
			}
		}
	}
);

add_action(
	'init',
	function () {
		new Mastodon_API();
		new Integration\Pixelfed();
		new Comment_CPT();
	}
);

if ( is_admin() && version_compare( get_option( 'ema_plugin_version', ENABLE_MASTODON_APPS_VERSION ), '<' ) ) {
	add_action( 'admin_init', array( __NAMESPACE__ . '\Mastodon_Admin', 'upgrade_plugin' ) );
}
