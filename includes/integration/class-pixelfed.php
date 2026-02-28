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
		add_filter( 'nodeinfo2_data', array( $this, 'mastodon_api_pixelfed_nodeinfo_software_root' ), 99 );
		add_filter( 'mastodon_api_nodeinfo', array( $this, 'mastodon_api_pixelfed_nodeinfo_software_root' ), 99 );
		add_filter( 'mastodon_api_nodeinfo_software', array( $this, 'mastodon_api_pixelfed_nodeinfo_software' ) );
		add_filter( 'mastodon_api_new_app_post_formats', array( $this, 'mastodon_api_pixelfed_post_formats' ), 10, 2 );
		add_filter( 'mastodon_api_statuses', array( $this, 'filter_statuses_without_media' ), 20 );
		add_action( 'mastodon_api_new_app', array( $this, 'set_pixelfed_app_options' ) );
	}

	private function is_pixelfed_client() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		if ( 'okhttp/4.9.2' === $user_agent ) {
			return 'android';
		}

		if ( strpos( $user_agent, 'Pixelfed/1 CFNetwork/' ) !== false ) {
			return 'ios';
		}

		return false;
	}

	public function mastodon_api_pixelfed_nodeinfo_software_root( $ret ) {
		if ( ! $this->is_pixelfed_client() ) {
			return $ret;
		}
		$ret['software'] = $this->mastodon_api_pixelfed_nodeinfo_software( array() );

		return $ret;
	}

	public function mastodon_api_pixelfed_nodeinfo_software( $software ) {
		$pixelfed = $this->is_pixelfed_client();
		if ( ! $pixelfed ) {
			return $software;
		}
		if ( 'ios' === $pixelfed ) {
			return array(
				'name'    => 'pixelfed',
				'version' => '0.12.4',
			);
		}
		return array(
			'name'    => 'pixelfed',
			'version' => '0.11.5',
		);
	}

	public function mastodon_api_pixelfed_post_formats( $post_formats, $app_metadata ) {
		if ( in_array( $app_metadata, array( 'Pixelfed' ) ) ) {
			$post_formats = array( 'image' );
		}

		return $post_formats;
	}

	public function filter_statuses_without_media( $statuses ) {
		if ( ! is_array( $statuses ) ) {
			return $statuses;
		}

		$app = \Enable_Mastodon_Apps\Mastodon_App::get_current_app();
		if ( ! $app || ! $app->get_media_only() ) {
			return $statuses;
		}

		return array_values(
			array_filter(
				$statuses,
				function ( $status ) {
					return ! empty( $status->media_attachments );
				}
			)
		);
	}

	public function set_pixelfed_app_options( $app ) {
		if ( false !== strpos( $app->get_client_name(), 'Pixelfed' ) ) {
			$app->set_media_only( true );
		}
	}
}
