<?php
/**
 * Token Handler
 *
 * This file implements handling the issuance of the token.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\OAuth2;

use OAuth2\Request;
use OAuth2\Server as OAuth2Server;

/**
 * Token Handler
 *
 * This class implements handling the issuance of the token.
 */
class Token_Handler {
	private $server;

	public function __construct( OAuth2Server $server ) {
		$this->server = $server;
	}

	public function handle( Request $request, Response $response ) {
		// For authorization_code grants, limit the requested scope to what was actually authorized.
		// Some apps (like Pixelfed) request more scopes in the token request than they registered with.
		if ( 'authorization_code' === $request->request( 'grant_type' ) ) {
			$code = $request->request( 'code' );
			if ( $code ) {
				$code_storage = new Authorization_Code_Storage();
				$auth_code    = $code_storage->getAuthorizationCode( $code );
				if ( $auth_code && ! empty( $auth_code['scope'] ) ) {
					$authorized_scopes = explode( ' ', $auth_code['scope'] );
					$requested_scopes  = explode( ' ', $request->request( 'scope', '' ) );
					$allowed_scopes    = array_intersect( $requested_scopes, $authorized_scopes );

					if ( ! empty( $allowed_scopes ) ) {
						$request->request['scope'] = implode( ' ', $allowed_scopes );
					} else {
						$request->request['scope'] = $auth_code['scope'];
					}
				}
			}
		}

		return $this->server->handleTokenRequest( $request, $response );
	}
}
