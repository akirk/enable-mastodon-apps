<?php
/**
 * Revokation Handler
 *
 * This file implements handling the revokation of the token.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\OAuth2;

use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Server as OAuth2Server;

/**
 * This class handles the revokation of a token.
 */
class Revokation_Handler {
	private $server;

	public function __construct( OAuth2Server $server ) {
		$this->server = $server;
	}

	public function handle( Request $request, Response $response ) {
		return $this->server->handleRevokeRequest( $request, $response );
	}
}
