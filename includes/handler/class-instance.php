<?php
/**
 * Instance handler.
 *
 * This contains the default Instance handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Entity\Extended_Description;
use Enable_Mastodon_Apps\Entity\Instance as Instance_Entity;
use Enable_Mastodon_Apps\Entity\Instance_V1;
use Enable_Mastodon_Apps\Mastodon_API;

/**
 * This is the class that implements the default handler for all Instance endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Instance extends Handler {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register the hooks for this handler.
	 */
	public function register_hooks() {
		add_filter( 'mastodon_api_instance_v1', array( $this, 'api_instance_v1' ) );
		add_filter( 'mastodon_api_instance_v2', array( $this, 'api_instance' ) );
		add_filter( 'mastodon_api_instance_extended_description', array( $this, 'api_instance_extended_description' ) );
		add_action( 'update_option_blogdescription', array( $this, 'save_created_at' ) );

		// Enable the link manager so peers can be added/updated.
		add_filter( 'pre_option_link_manager_enabled', '__return_true' );
	}

	/**
	 * Get the v1 instance data.
	 *
	 * @param array $instance_data The instance data.
	 * @return Instance_V1
	 */
	public function api_instance_v1( $instance_data ): Instance_V1 {
		$instance = new Instance_V1();

		$instance->uri               = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$instance->title             = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$instance->version           = $this->software_string();
		$instance->short_description = html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES );
		$instance->description       = html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES );
		$instance->email             = get_option( 'admin_email' );
		$instance->stats             = array(
			'user_count'   => count_users()['total_users'],
			'status_count' => intval( wp_count_posts()->publish ),
			'domain_count' => 1,
		);
		$instance->thumbnail         = \get_site_icon_url();
		$instance->registrations     = (bool) get_option( 'users_can_register' );
		$instance->approval_required = false;
		$instance->invites_enabled   = false;
		$instance->languages     = empty( get_available_languages() ) ? array( 'en' ) : get_available_languages();
		$instance->configuration = array(
			'media_attachment' => array(
				'supported_mime_types' => array_values( get_allowed_mime_types() ),
				'image_size_limit'     => \wp_max_upload_size(),
				'video_size_limit'     => \wp_max_upload_size(),
			),
			'translation'      => array(
				'enabled' => false,
			),
		);

		return $instance;
	}

	/**
	 * Get the instance data.
	 *
	 * @param array $instance_data The instance data.
	 * @return Instance_Entity
	 */
	public function api_instance( $instance_data ): Instance_Entity {
		$instance = new Instance_Entity();

		$instance->domain        = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$instance->title         = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$instance->version       = $this->software_string();
		$instance->source_url    = 'https://core.trac.wordpress.org/browser/trunk';
		$instance->description   = html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES );
		$instance->usage         = array(
			'users' => array(
				'active_months' => count_users()['total_users'],
			),
		);
		$instance->thumbnail     = array( 'url' => \get_site_icon_url() );
		$instance->languages     = empty( get_available_languages() ) ? array( 'en' ) : get_available_languages();
		$instance->configuration = array(
			'media_attachment' => array(
				'supported_mime_types' => array_values( get_allowed_mime_types() ),
				'image_size_limit'     => \wp_max_upload_size(),
				'video_size_limit'     => \wp_max_upload_size(),
			),
			'translation'      => array(
				'enabled' => false,
			),
		);
		$instance->registrations = array(
			'enabled'           => (bool) get_option( 'users_can_register' ),
			'approval_required' => false,
			'message'           => null,
		);
		$instance->contact       = array(
			'email'   => get_option( 'admin_email' ),
			'account' => null,
		);

		return $instance;
	}

	/**
	 * Get the extended instance description.
	 *
	 * @param string $description The instance description.
	 * @return Extended_Description
	 */
	public function api_instance_extended_description( $description ): Extended_Description {
		$updated_at = get_option( 'blogdescription_updated_at' );

		$description             = new Extended_Description();
		$description->updated_at = $updated_at ? gmdate( \DateTime::ATOM, $updated_at ) : '';
		$description->content    = html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES );

		return $description;
	}

	/**
	 * Save the created_at date.
	 */
	public function save_created_at() {
		update_option( 'blogdescription_updated_at', time() );
	}

	/**
	 * Get the software string.
	 *
	 * @return string
	 */
	private function software_string(): string {
		global $wp_version;
		$software = 'WordPress/' . $wp_version;

		if ( defined( 'ACTIVITYPUB_VERSION' ) ) {
			$software .= ', ActivityPub/' . ACTIVITYPUB_VERSION;
		}

		$software .= ', EMA/' . Mastodon_API::VERSION;

		return $software;
	}
}
