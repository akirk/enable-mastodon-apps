<?php
/**
 * OAuth2 Scope Util
 *
 * @package Friends
 */

namespace Enable_Mastodon_Apps\OAuth2;

/**
 * This class overrides the scope checking to allow for fine grained scopes.
 */
class Scope_Util extends \OAuth2\Scope {
	public static function checkSingleScope( $required_scope, $available_scope ) {
		$required_main_scope = strtok( $required_scope, ':' );
		foreach ( explode( ' ', $available_scope ) as $scope ) {
			if ( $scope === $required_scope ) {
				return true;
			}

			if ( $scope === $required_main_scope ) {
				return true;
			}
		}

		return false;
	}
	public function checkScope( $required_scope, $available_scope ) {
		foreach ( explode( ' ', $required_scope ) as $scope ) {
			if ( ! self::checkSingleScope( $scope, $available_scope ) ) {
				return false;
			}
		}
		return true;
	}
}
