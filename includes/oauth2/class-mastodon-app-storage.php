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
			// Use wp_kses to avoid filtering out url-escaped characters.
			$redirect_uri = isset( $_REQUEST['redirect_uri'] ) ? wp_kses( wp_unslash( $_REQUEST['redirect_uri'] ), array() ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$scope = isset( $_REQUEST['scope'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['scope'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			$app_metadata = array(
				'client_name'   => preg_replace( '/[^a-z0-9-]/', ' ', wp_parse_url( $redirect_uri, PHP_URL_HOST ) ) . ' (auto-generated)',
				'redirect_uris' => array( $redirect_uri ),
				'scopes'        => $scope,
			);

			$post_formats = apply_filters( 'mastodon_api_new_app_post_formats', array(), $app_metadata );
			$app_metadata['query_args'] = array( 'post_formats' => $post_formats );

			$app_metadata['create_post_type'] = get_option( 'mastodon_api_posting_cpt', apply_filters( 'mastodon_api_default_post_type', \Enable_Mastodon_Apps\Mastodon_API::POST_CPT ) );
			$view_post_types = array( 'post', 'comment' );
			if ( ! in_array( $app_metadata['create_post_type'], $view_post_types ) ) {
				$view_post_types[] = $app_metadata['create_post_type'];
			}

			$app_metadata['view_post_types'] = apply_filters( 'mastodon_api_view_post_types', $view_post_types );

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
