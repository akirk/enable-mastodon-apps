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

	/**
	 * Provide the data that should be serialized as JSON.
	 *
	 * The annotation is necessary for PHP 5.6 compatibility, when we are PHP 7+, we need to add mixed as the return type.
	 *
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$array = array();
		foreach ( $this->_types as $var => $type ) {
			if ( is_wp_error( $this->$var ) ) {
				continue;
			}

			$object = trim( $type, '?' );
			$required = strlen( $object ) === strlen( $type );

			if ( in_array( $object, array( 'string', 'int', 'bool', 'array' ) ) ) {
				if ( ! $required ) {
					continue;
				}

				settype( $this->$var, $type );
				$array[ $var ] = $this->$var;
				continue;
			}

			if ( 'DateTime' === $object ) {
				if ( $this->$var instanceof \DateTime ) {
					$array[ $var ] = $this->$var->format( 'Y-m-d\TH:i:s.000P' );
					continue;
				}

				if ( ! $required ) {
					continue;
				}

				return array(
					'error' => 'Required object is missing: ' . $var,
				);
			}

			if ( class_exists( '\\Enable_Mastodon_Apps\\Entity\\' . $object ) ) {
				if ( $this->$var instanceof Entity ) {
					$array[ $var ] = $this->$var->to_array();
					continue;
				}

				if ( ! $required ) {
					continue;
				}

				return array(
					'error' => 'Required object is missing: ' . $var,
				);
			}
		}

		return $array;
	}

	public function is_valid() {
		$array = $this->jsonSerialize();
		return empty( $array['error'] );
	}
}
