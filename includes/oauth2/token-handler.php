<?php

namespace Friends\OAuth2;

use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Server as OAuth2Server;
use OpenIDConnectServer\Http\RequestHandler;

class TokenHandler {
	private $server;

	public function __construct( OAuth2Server $server ) {
		$this->server = $server;
	}

	public function handle( Request $request, Response $response ): Response {
		return $this->server->handleTokenRequest( $request );
	}
}