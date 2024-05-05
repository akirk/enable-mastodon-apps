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
use Enable_Mastodon_Apps\Handler\Handler;

/**
 * This is the class that implements the default handler for all Relationship endpoints.
 *
 * @package Enable_Mastodon_Apps
 */
class Relationship extends Handler {
	/**
	 * Relationship constructor.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_filter( 'mastodon_api_relationship', array( $this, 'api_relationship' ), 10, 3 );
		add_filter( 'mastodon_entity_relationship', array( $this, 'entity_relationship_ensure_numeric_id' ), 100, 2 );
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

		/**
		 * Modify account relationship.
		 *
		 * @param Relationship_Entity $relationship The account relationship.
		 * @param string              $user_id      The user ID.
		 * @param \WP_REST_Request    $request      The request object.
		 * @return Relationship_Entity The modified account relationship.
		 */
		return apply_filters( 'mastodon_entity_relationship', $relationship, $user_id, $request );
	}

	public function entity_relationship_ensure_numeric_id( $relationship, $user_id ) {
		if ( ! is_numeric( $relationship->id ) ) {
			$relationship->id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $relationship->id );
		}
		return $relationship;
	}
}
