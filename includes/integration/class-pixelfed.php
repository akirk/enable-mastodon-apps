<?php
/**
 * Pixelfed Integration
 *
 * Add what it takes to make Pixelfed apps talk to WordPress.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Integration;

/**
 * This is the class that implements the Pixelfed adapations.
 *
 * @since 0.1
 *
 * @package Enable_Mastodon_Apps
 * @author Alex Kirk
 */
class Pixelfed {
	public function __construct() {
		add_filter( 'mastodon_api_nodeinfo_software', array( $this, 'mastodon_api_pixelfed_nodeinfo_software' ) );
		add_filter( 'mastodon_api_new_app_post_formats', array( $this, 'mastodon_api_pixelfed_post_formats' ), 10, 2 );
	}

	public function mastodon_api_pixelfed_nodeinfo_software( $software ) {
		if ( 'okhttp/4.9.2' === $_SERVER['HTTP_USER_AGENT'] ) {
			return array(
				'name'    => 'pixelfed',
				'version' => '0.11.5',
			);
		}

		return $software;
	}

	public function mastodon_api_pixelfed_post_formats( $post_formats, $app_metadata ) {
		if ( in_array( $app_metadata, array( 'Pixelfed' ) ) ) {
			$post_formats = array( 'image' );
		}

		return $post_formats;
	}
}
