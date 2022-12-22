<?php
/**
 * Plugin name: Friends Mastodon API
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends-mastodon-api
 * Version: 0.1
 *
 * Description: Implements the Mastodon API so that you can use apps that support it.
 *
 * License: GPL2
 * Text Domain: friends
 *
 * @package Friends_Mastodon_API
 */

defined( 'ABSPATH' ) || exit;
define( 'FRIENDS_MASTODON_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/includes/class-mastodon-api.php';

add_action(
	'friends_loaded',
	function( $friends ) {
		new Friends\Mastodon_API( $friends );
	}
);
