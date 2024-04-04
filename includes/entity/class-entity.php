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
	/**
	 * The types of variables that are expected.
	 *
	 * Example:
	 * ```
	 * protected $_types = array(
	 *     'id'             => 'string',
	 *     'created_at'     => 'DateTime', // DateTime is the PHP class.
	 *     'text'           => 'string',
	 *     'language'       => 'string?',  //  ? = Optional.
	 *     'in_reply_to_id' => 'string??', // ?? = Nullable.
	 * );
	 *
	 * @var array
	 */
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

			$object = rtrim( $type, '?' );
			$nullable = preg_match( '/\?\?$/', $type );
			$optional = preg_match( '/\?$/', $type );

			if ( ! isset( $this->$var ) && $nullable ) {
				$array[ $var ] = null;
				continue;
			}

			if ( in_array( $object, array( 'string', 'int', 'bool', 'array' ) ) ) {
				if ( ! isset( $this->$var ) ) {
					if ( $optional ) {
						continue;
					}

					return array(
						'error' => 'Required variable is missing: ' . $var,
					);
					continue;
				}

				settype( $this->$var, $object );
				$array[ $var ] = $this->__get( $var );
				continue;
			}

			if ( 'DateTime' === $object ) {
				if ( $this->$var instanceof \DateTime ) {
					$array[ $var ] = preg_replace( '/\+00:00$/', 'Z', $this->__get( $var )->format( 'Y-m-d\TH:i:s.000P' ) );
					continue;
				}

				if ( $optional ) {
					continue;
				}

				return array(
					'error' => 'Required object is missing: ' . $var,
				);
			}

			if ( class_exists( '\\Enable_Mastodon_Apps\\Entity\\' . $object ) ) {
				if ( $this->$var instanceof Entity ) {
					$array[ $var ] = $this->__get( $var )->jsonSerialize();
					continue;
				}

				if ( $optional ) {
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

	public function __get( $name ) {
		return $this->$name;
	}
}
