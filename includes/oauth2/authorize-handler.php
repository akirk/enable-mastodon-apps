<?php
/**
 * Authorize Handler
 *
 * @package Enable_Mastodon_Apps
 */

// phpcs:disable WordPress.Security.NonceVerification.Missing

namespace Enable_Mastodon_Apps\OAuth2;

use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Server as OAuth2Server;

/**
 * Authorize Handler
 *
 * This class implements handling the authorization.
 */
class AuthorizeHandler {
	private $server;

	public function __construct( OAuth2Server $server ) {
		$this->server = $server;
	}

	public function handle( Request $request, Response $response ) {
		// Our dependency bshaffer's OAuth library currently has a bug where it doesn't pick up nonce correctly if it's a POST request to the Authorize endpoint.
		// Fix has been contributed upstream (https://github.com/bshaffer/oauth2-server-php/pull/1032) but it doesn't look it would be merged anytime soon based on recent activity.
		// Hence, as a temporary fix, we are copying over the nonce from parsed $_POST values to parsed $_GET values in $request object here.
		if ( isset( $request->request['nonce'] ) && ! isset( $request->query['nonce'] ) ) {
			$request->query['nonce'] = $request->request['nonce'];
		}

		if ( ! $this->server->validateAuthorizeRequest( $request, $response ) ) {
			return $response;
		}

		// The initial request will come without a nonce, thus unauthenticated.
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_private_posts' ) || ! isset( $_POST['authorize'] ) ) {
			// This is handled by a hook in wp-login.php which will display a form asking the user to consent.
			$response->setRedirect( 302, add_query_arg( array_map( 'rawurlencode', array_merge( $request->getAllQueryParameters(), array( 'action' => 'enable-mastodon-apps-authenticate' ) ) ), wp_login_url() ) );
			return $response;
		}

		$user = wp_get_current_user();
		if ( ! isset( $_POST['authorize'] ) || 'Authorize' !== $_POST['authorize'] ) {
			$response->setError( 403, 'user_authorization_required', 'This application requires your consent.' );
			return $response;
		}

		return $this->server->handleAuthorizeRequest( $request, $response, true, $user->ID );
	}

	public function test_authorize( Request $request, $user_id ) {
		$response = new Response();
		if ( ! $this->server->validateAuthorizeRequest( $request, $response ) ) {
			$response->setError( 403, 'invalid_test_client', 'This test client is invalid.' );
			return $response;
		}
		return $this->server->handleAuthorizeRequest( $request, $response, true, $user_id );
	}
}
