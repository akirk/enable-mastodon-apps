<?php
/**
 * Friends Mastodon OAuth
 *
 * This contains the OAuth handlers.
 *
 * @package Friends_Mastodon_API
 */

namespace Friends;

use OAuth2\Server;
use OAuth2\Request;
use OAuth2\Response;

/**
 * This is the class that implements the Mastodon Oauth endpoints.
 *
 * @since 0.1
 *
 * @package Friends_Mastodon_API
 * @author Alex Kirk
 */
class Mastodon_Oauth {
	const OOB_REDIRECT_URI = 'urn:ietf:wg:oauth:2.0:oob';

	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Contains a reference to the OAuth2 Server class.
	 *
	 * @var Server
	 */
	private $server;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$config = array(
		    'issuer'                => home_url( '/' ),
		    'enforce_state'         => false,
		);

		$this->server = new Server( new Oauth2\AuthorizationCodeStorage(), $config );
		$this->server->addStorage( new Oauth2\MastodonAppStorage(), 'client_credentials' );

		add_action( 'template_redirect', array( $this, 'handle_oauth' ) );
		add_action( 'login_form_mastodon-api-authenticate', array( $this, 'authenticate_handler' ) );
	}

	public function handle_oauth() {
		$handler = null;
		switch( strtok( $_SERVER['REQUEST_URI'], '?' ) ) {
			case '/oauth/authorize':

				$handler = new Oauth2\AuthorizeHandler( $this->server );
				break;
			case '/oauth/token':
				// $this->handle_oauth_token();
				break;
			case '/oauth/revoke':
				// $this->handle_oauth_revoke();
				break;
		}

		if ( is_null( $handler ) ) {
			return;
		}

		$request  = Request::createFromGlobals();
		$response = new Response();
		$response = $handler->handle( $request, $response );
		$response->send();
		exit;
	}

	function authenticate_handler() {
		$request  = Request::createFromGlobals();
		$response = new Response();

		$authenticate_handler = new OAuth2\AuthenticateHandler();
		$authenticate_handler->handle( $request, $response );
		exit;
	}


}
