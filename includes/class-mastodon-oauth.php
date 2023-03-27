<?php
/**
 * Mastodon OAuth
 *
 * This contains the OAuth handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use OAuth2\Server;
use OAuth2\Request;
use OAuth2\Response;

/**
 * This is the class that implements the Mastodon Oauth endpoints.
 *
 * @since 0.1
 *
 * @package Enable_Mastodon_Apps
 * @author Alex Kirk
 */
class Mastodon_OAuth {
	const OOB_REDIRECT_URI = 'urn:ietf:wg:oauth:2.0:oob';

	/**
	 * Contains a reference to the OAuth2 Server class.
	 *
	 * @var Server
	 */
	private $server;

	/**
	 * Constructor
	 */
	public function __construct() {
		$config = array(
			'issuer'          => home_url( '/' ),
			'enforce_state'   => false,
			'access_lifetime' => YEAR_IN_SECONDS * 2,
		);

		$this->server = new Server( new Oauth2\AuthorizationCodeStorage(), $config );
		$this->server->addStorage( new Oauth2\MastodonAppStorage(), 'client_credentials' );
		$this->server->addStorage( new Oauth2\AccessTokenStorage(), 'access_token' );

		add_action( 'template_redirect', array( $this, 'handle_oauth' ) );
		add_action( 'login_form_enable-mastodon-apps-authenticate', array( $this, 'authenticate_handler' ) );
	}

	public function handle_oauth() {
		switch ( strtok( $_SERVER['REQUEST_URI'], '?' ) ) {
			case '/oauth/authorize':
				if ( get_option( 'mastodon_api_disable_logins' ) ) {
					return;
				}
				$handler = new OAuth2\AuthorizeHandler( $this->server );
				break;

			case '/oauth/token':
				header( 'Access-Control-Allow-Methods: POST' );
				header( 'Access-Control-Allow-Headers: content-type' );
				header( 'Access-Control-Allow-Credentials: true' );
				if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
					header( 'Access-Control-Allow-Origin: *', true, 204 );
					exit;
				}
				header( 'Access-Control-Allow-Origin: *' );
				$handler = new OAuth2\TokenHandler( $this->server );
				break;

			case '/oauth/revoke':
				$handler = new OAuth2\RevokationHandler( $this->server );
				exit;
		}

		if ( ! isset( $handler ) ) {
			return;
		}

		$request  = Request::createFromGlobals();
		$response = new Response();
		$response = $handler->handle( $request, $response );
		$response->send();
		exit;
	}

	public function authenticate() {
		$request = Request::createFromGlobals();
		if ( ! $this->server->verifyResourceRequest( $request ) ) {
			$this->server->getResponse()->send();
			return null;
		}
		$token = $this->server->getAccessTokenData( $request );
		wp_set_current_user( $token['user_id'] );
		return $token;
	}

	public function authenticate_handler() {
		$request  = Request::createFromGlobals();
		$response = new Response();

		$authenticate_handler = new OAuth2\AuthenticateHandler();
		$authenticate_handler->handle( $request, $response );
		exit;
	}

	public function get_code_storage() {
		return $this->server->getStorage( 'authorization_code' );
	}

	public function get_token_storage() {
		return $this->server->getStorage( 'access_token' );
	}
}
