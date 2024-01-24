<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

/**
 * Class Entity
 */
class Entity {
	public function to_json() {
		return wp_json_encode( $this );
	}

	public function is_valid() {
		$attributes = get_object_vars( $this );

		foreach ( $attributes as $attribute ) {
			if ( is_null( $attribute ) ) {
				return false;
			}
		}

		return true;
	}
}
