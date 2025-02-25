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
use Enable_Mastodon_Apps\OAuth2\Response;

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
			'issuer'                     => home_url( '/' ),
			'enforce_state'              => false,
			'require_exact_redirect_uri' => false,
			'access_lifetime'            => YEAR_IN_SECONDS * 2,
		);

		$this->server = new Server( new Oauth2\Authorization_Code_Storage(), $config );
		$this->server->addStorage( new Oauth2\Mastodon_App_Storage(), 'client_credentials' );
		$this->server->addStorage( new Oauth2\Access_Token_Storage(), 'access_token' );
		$this->server->setScopeUtil( new Oauth2\Scope_Util() );

		if ( '/oauth/token' === strtok( $_SERVER['REQUEST_URI'], '?' ) ) { // phpcs:ignore
			// Avoid interference with private site plugins.
			add_filter(
				'pre_option_blog_public',
				function () {
					return 0;
				}
			);
		}

		add_action( 'wp_loaded', array( $this, 'rewrite_rules' ) );
		add_action( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_oauth' ) );

		add_filter( 'determine_current_user', array( $this, 'authenticate' ), 10 );
		add_action( 'login_form_enable-mastodon-apps-authenticate', array( $this, 'authenticate_handler' ) );
	}

	public function rewrite_rules() {
		$existing_rules = get_option( 'rewrite_rules' );
		$needs_flush    = false;

		$generic =
			array(
				'oauth/authorize' => 'authorize',
				'oauth/token'     => 'token',
				'oauth/revoke'    => 'revoke',
			);

		foreach ( $generic as $path => $rule ) {
			if ( empty( $existing_rules[ '^' . $path ] ) ) {
				// Add a specific rewrite rule so that we can also catch requests without our prefix.
				$needs_flush = true;
			}
			add_rewrite_rule( '^' . $path, 'index.php?name=' . $path . '&mastodon-oauth=' . $rule, 'top' );
		}

		if ( $needs_flush ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}

	public function query_vars( $query_vars ) {
		$query_vars[] = 'mastodon-oauth';
		return $query_vars;
	}

	public function handle_oauth( $return_value = false ) {
		global $wp_query;
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && empty( $_POST ) && ! empty( $_REQUEST ) ) { // phpcs:ignore
			$_POST = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$endpoint = null;

		if ( isset( $wp_query->query['mastodon-oauth'] ) ) {
			$endpoint = $wp_query->query['mastodon-oauth'];
		} else {
			$request_uri = trim( strtok( $_SERVER['REQUEST_URI'], '?' ), '/' ); // phpcs:ignore

			if ( in_array( $request_uri, array( 'oauth/authorize', 'oauth/token', 'oauth/revoke' ) ) ) {
				$endpoint = explode( '/', $request_uri )[1];
			}
		}

		switch ( $endpoint ) {
			case 'authorize':
				if ( get_option( 'mastodon_api_disable_logins' ) ) {
					return null;
				}
				$wp_query->is_404 = false;
				$handler          = new OAuth2\Authorize_Handler( $this->server );
				break;

			case 'token':
				header( 'Access-Control-Allow-Methods: POST' );
				header( 'Access-Control-Allow-Headers: content-type' );
				header( 'Access-Control-Allow-Credentials: true' );
				if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore
					header( 'Access-Control-Allow-Origin: *', true, 204 );
					exit;
				}
				header( 'Access-Control-Allow-Origin: *' );
				$wp_query->is_404 = false;
				$handler          = new OAuth2\Token_Handler( $this->server );
				break;

			case 'revoke':
				$wp_query->is_404 = false;
				$handler          = new OAuth2\Revokation_Handler( $this->server );
				break;
		}

		if ( ! isset( $handler ) ) {
			return null;
		}

		if ( get_option( 'mastodon_api_debug_mode' ) > time() ) {
			// phpcs:disable
			$app     = Mastodon_App::get_debug_app();
			$request = new \WP_REST_Request( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );
			$request->set_query_params( $_GET );
			$request->set_body_params( $_POST );
			// phpcs:enable
			$request->set_headers( getallheaders() );
			$app->was_used( $request );
		}

		$request  = Request::createFromGlobals();
		$response = new Response();
		if ( 'handler' === $return_value ) {
			return $handler;
		}

		$response = $handler->handle( $request, $response );
		if ( 'response' === $return_value ) {
			return $response;
		}

		$response->send();
		exit;
	}

	public function get_token() {
		$request  = Request::createFromGlobals();
		$response = new Response();
		if ( ! $this->server->verifyResourceRequest( $request, $response ) ) {
			return null;
		}
		return $this->server->getAccessTokenData( $request, $response );
	}

	public function authenticate( $user_id ) {
		$token = $this->get_token();
		if ( is_array( $token ) && isset( $token['user_id'] ) ) {
			return intval( $token['user_id'] );
		}
		return $user_id;
	}

	public function authenticate_handler() {
		$request  = Request::createFromGlobals();
		$response = new Response();

		$authenticate_handler = new OAuth2\Authenticate_Handler();
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
