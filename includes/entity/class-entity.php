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
	private $_types = array();
	public function to_json() {
		foreach ( $this->_types as $var => $type ) {
			if ( strtoupper( substr( $type, 0, 1 ) ) === substr( $type, 0, 1 ) ) {
				if ( $this->$var instanceof $type ) {
					$this->$var = $this->$var->to_array();
				} else {
					$this->$var = null;
				}
			} else {
				if ( $this->$var ) {
					settype( $this->$var, $type );
				}
			}
		}

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
