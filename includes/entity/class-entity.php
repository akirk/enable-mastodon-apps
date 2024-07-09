<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Entity;

use Enable_Mastodon_Apps\Mastodon_API;

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

			$object   = rtrim( $type, '?' );
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
				$valid = $this->validate( $var );
				if ( is_wp_error( $valid ) ) {
					return array(
						'error' => $var . ': ' . $valid->get_error_message(),
					);
					continue;
				}

				settype( $this->$var, $object );
				$array[ $var ] = $this->__get( $var );
				continue;
			}

			if ( 'DateTime' === $object ) {
				if ( $this->__get( $var ) instanceof \DateTime ) {
					$array[ $var ] = preg_replace( '/\+00:00$/', 'Z', $this->__get( $var )->format( 'Y-m-d\TH:i:s.000P' ) );
					continue;
				}

				if ( $optional ) {
					continue;
				}

				if ( $this->__get( $var ) ) {
					return array(
						'error' => 'Expected DateTime for ' . $var,
					);
				}

				return array(
					'error' => 'Required DateTime is missing: ' . $var,
				);
			}

			if ( 'Date' === $object ) {
				if ( $this->__get( $var ) instanceof \DateTime ) {
					$array[ $var ] = $this->__get( $var )->format( 'Y-m-d' );
					continue;
				}

				if ( $optional ) {
					continue;
				}

				if ( $this->__get( $var ) ) {
					return array(
						'error' => 'Expected DateTime: ' . $var,
					);
				}

				return array(
					'error' => 'Required DateTime is missing: ' . $var,
				);
			}

			if ( preg_match( '/array\[([^\]]+)\]/', $object, $matches ) ) {
				$object = $matches[1];
				if ( substr( $object, -1 ) === '?' ) {
					$object    = rtrim( $object, '?' );
					$skippable = true;
				}
				if ( ! class_exists( '\\Enable_Mastodon_Apps\\Entity\\' . $object ) ) {
					return array(
						'error' => 'Invalid object type: ' . $object,
					);
				}
				if ( ! is_array( $this->$var ) ) {
					if ( $optional ) {
						continue;
					}

					return array(
						'error' => 'Required object is missing: ' . $var,
					);
				}

				$array[ $var ] = array();
				foreach ( $this->__get( $var ) as $key => $value ) {
					if ( $value instanceof Entity ) {
						$array[ $var ][ $key ] = $value->jsonSerialize();
						if ( isset( $array[ $var ][ $key ]['error'] ) ) {
							if ( $skippable ) {
								unset( $array[ $var ][ $key ] );
								continue;
							}
							return array(
								'error' => $key . ': ' . $array[ $var ][ $key ]['error'],
							);
						}
						continue;
					}
				}
				$array[ $var ] = array_values( $array[ $var ] );
				continue;
			}

			if ( class_exists( '\\Enable_Mastodon_Apps\\Entity\\' . $object ) ) {
				if ( $this->$var instanceof Entity ) {
					$array[ $var ] = $this->__get( $var )->jsonSerialize();
					if ( isset( $array[ $var ]['error'] ) ) {
						return $array[ $var ];
					}
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
		if ( empty( $array['error'] ) ) {
			return true;
		}

		Mastodon_API::set_last_error( get_called_class() . ': ' . $array['error'] );
		return false;
	}

	public function __get( $name ) {
		return $this->$name;
	}

	/**
	 * Validate the variable.
	 *
	 * @param string $key The variable to validate.
	 *
	 * @return bool|\WP_Error
	 */
	public function validate( $key ) {
		if ( isset( $this->$key ) ) {
			return true;
		}
		// We don't make this decision here.
		return true;
	}
}
