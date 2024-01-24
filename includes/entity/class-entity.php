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
abstract class Entity {
	protected $_types;
	public function to_array() {
		foreach ( $this->_types as $var => $type ) {
			if ( 'DateTime' === $type && $this->$var instanceof \DateTime ) {
				$this->$var = $this->$var->format( 'Y-m-d\TH:i:s.000P' );
				continue;
			}
			if ( in_array( $type, array( 'string', 'int', 'bool', 'array' ) ) ) {
				settype( $this->$var, $type );
				continue;
			}

			if ( class_exists( $type ) ) {
				if ( $this->$var instanceof Entity ) {
					$this->$var = $this->$var->to_array();
				} else {
					$this->$var = null;
				}
				continue;
			}
		}

		$array = get_object_vars( $this );
		unset( $array['_types'] );
		return $array;
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
