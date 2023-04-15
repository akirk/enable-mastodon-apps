<?php
/**
 * Mastodon App Storage
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\OAuth2;

use Enable_Mastodon_Apps\Mastodon_App;
use OAuth2\Storage\ClientCredentialsInterface;

/**
 * This class describes a mastodon application storage.
 */
class MastodonAppStorage implements ClientCredentialsInterface {
	public function getClientDetails( $client_id ) {
		if ( ! $this->has( $client_id ) ) {
			return false;
		}

		$client = $this->get( $client_id );

		return array(
			'client_id'    => $client_id,
			'redirect_uri' => implode( ' ', $client->get_redirect_uris() ),
			'scope'        => $client->get_scopes(),
		);
	}

	public function getClientScope( $client_id ) {
		if ( ! $this->has( $client_id ) ) {
			return '';
		}

		$client = $this->get( $client_id );

		return $client->get_scopes();
	}

	public function checkRestrictedGrantType( $client_id, $grant_type ) {
		if ( ! $this->has( $client_id ) ) {
			return false;
		}

		return in_array( $grant_type, array( 'authorization_code' ), true );
	}

	public function checkClientCredentials( $client_id, $client_secret = null ) {
		if ( ! $this->has( $client_id ) ) {
			return false;
		}

		$client = $this->get( $client_id );

		if ( empty( $client->get_client_secret() ) ) {
			return true;
		}

		return $client_secret === $client->get_client_secret();
	}

	public function isPublicClient( $client_id ) {
		if ( ! $this->has( $client_id ) ) {
			return false;
		}

		$client = $this->get( $client_id );

		return empty( $client->get_secret() );
	}

	/**
	 * Gets the specified client identifier.
	 *
	 * @param      string $client_id  The client identifier.
	 *
	 * @return     Mastodon_App|null  The client.
	 */
	private function get( $client_id ) {
		return Mastodon_App::get_by_client_id( $client_id );
	}

	private function has( $client_id ) {
		$client = $this->get( $client_id );
		return ! is_wp_error( $client );
	}
}
