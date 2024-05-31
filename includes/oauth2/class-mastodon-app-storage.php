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
class Mastodon_App_Storage implements ClientCredentialsInterface {
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

		return in_array( $grant_type, array( 'authorization_code', 'client_credentials' ), true );
	}

	public function checkClientCredentials( $client_id, $client_secret = null ) {
		if ( ! $this->has( $client_id ) ) {
			return false;
		}

		$client = $this->get( $client_id );

		if ( is_null( $client_secret ) ) {
			return true;
		}

		if ( $client_secret !== $client->get_client_secret() ) {
			if ( get_option( 'mastodon_api_auto_app_reregister' ) === $client_id ) {
				$client->set_client_secret( $client_secret );
				delete_option( 'mastodon_api_auto_app_reregister' );
				return true;
			}
			return false;
		}

		return true;
	}

	public function isPublicClient( $client_id ) {
		if ( ! $this->has( $client_id ) ) {
			return false;
		}

		$client = $this->get( $client_id );

		return empty( $client->get_client_secret() );
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
		if ( is_wp_error( $client ) && get_option( 'mastodon_api_auto_app_reregister' ) ) {
			$term = wp_insert_term( $client_id, Mastodon_App::TAXONOMY );

			$app_metadata = array(
				'client_name'   => preg_replace( '/[^a-z0-9-]/', ' ', wp_parse_url( $_REQUEST['redirect_uri'], PHP_URL_HOST ) ) . ' (auto-generated)',
				'redirect_uris' => array( $_REQUEST['redirect_uri'] ),
				'scopes'        => $_REQUEST['scope'],
			);

			$post_formats = get_option( 'mastodon_api_default_post_formats', array() );
			$post_formats = apply_filters( 'mastodon_api_new_app_post_formats', $post_formats, $app_metadata );

			$term_id = $term['term_id'];
			foreach ( $app_metadata as $key => $value ) {
				add_metadata( 'term', $term_id, $key, $value, true );
			}
			add_metadata( 'term', $term_id, 'creation_date', time(), true );

			$term = get_term( $term['term_id'] );
			if ( ! is_wp_error( $term ) ) {
				$client = new Mastodon_App( $term );
				update_option( 'mastodon_api_auto_app_reregister', $client_id );
			}
		}

		return ! is_wp_error( $client );
	}
}
