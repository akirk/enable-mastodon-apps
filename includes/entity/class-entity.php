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
abstract class Entity implements \JsonSerializable {
	protected $_types;
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$array = array();
		foreach ( $this->_types as $var => $type ) {
			if ( in_array( $type, array( 'string', 'int', 'bool', 'array' ) ) ) {
				settype( $this->$var, $type );
				$array[ $var ] = $this->$var;
				continue;
			}

			$object = trim( $type, '?' );
			$required = strlen( $object ) === strlen( $type );

			if ( 'DateTime' === $object ) {
				if ( $this->$var instanceof \DateTime ) {
					$array[ $var ] = $this->$var->format( 'Y-m-d\TH:i:s.000P' );
					continue;
				}

				if ( ! $required ) {
					continue;
				}

				// A required object is missing, we need to dismiss this object so that the response stays valid.
				return null;
			}

			if ( class_exists( '\\Enable_Mastodon_Apps\\Entity\\' . $object ) ) {
				if ( $this->$var instanceof Entity ) {
					$array[ $var ] = $this->$var->to_array();
					continue;
				}

				if ( ! $required ) {
					continue;
				}

				// A required object is missing, we need to dismiss this object so that the response stays valid.
				return null;
			}
		}

		return $array;
	}

	public function is_valid() {
		return ! is_null( $this->jsonSerialize() );
	}
}
