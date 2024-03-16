<?php
/**
 * Relationship handler.
 *
 * This contains the default Relationship handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Entity\Relationship as Relationship_Entity;
use Enable_Mastodon_Apps\Mastodon_API;

/**
 * This is the class that implements the default handler for all Relationship endpoints.
 *
 * @package Enable_Mastodon_Apps
 */
class Relationship {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_relationship', array( $this, 'api_relationship' ), 10, 3 );
	}

	/**
	 * Creates a new relationship object.
	 *
	 * @param null             $relationship_data The default account relationship.
	 * @param string           $user_id           The user ID.
	 * @param \WP_REST_Request $request           The request object.
	 *
	 * @return Relationship_Entity|null The relationship object.
	 */
	public function api_relationship( $relationship_data, string $user_id, \WP_REST_Request $request ): Relationship_Entity {
		$relationship     = new Relationship_Entity();
		$relationship->id = $user_id;

		if ( $user_id > 1e10 ) {
			$remote_user_id = get_term_by( 'id', $user_id - 1e10, Mastodon_API::REMOTE_USER_TAXONOMY );
			if ( $remote_user_id ) {
				$user_id = $remote_user_id->name;
			}
		}

		/**
		 * Modify account relationship.
		 *
		 * @param array            $relationship The account relationship.
		 * @param string           $user_id      The user ID.
		 * @param \WP_REST_Request $request      The request object.
		 *
		 * @return array The modified account relationship.
		 *
		 * Example:
		 * ```php
		 * apply_filters( 'mastodon_api_account_relationships', function ( $relationship, $user_id, $request ) {
		 *      $user = get_user_by( 'ID', $user_id );
		 *
		 *      if ( $user && $user->has_cap( 'friend_request' ) ) {
		 *          $relationship['requested'] = true;
		 *      }
		 * } );
		 * ```
		 */
		return apply_filters( 'mastodon_entity_relationship', $relationship, $user_id, $request );
	}
}
