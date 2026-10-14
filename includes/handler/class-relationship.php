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
use Enable_Mastodon_Apps\Entity\Account as Account_Entity;
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
		add_filter( 'mastodon_api_account', array( $this, 'api_account_counts' ), 15, 2 );
		add_filter( 'mastodon_api_account_following', array( $this, 'api_account_following' ), 10, 3 );
		add_filter( 'mastodon_api_account_followers', array( $this, 'api_account_followers' ), 10, 3 );
		add_filter( 'mastodon_entity_relationship', array( $this, 'entity_relationship_ensure_numeric_id' ), 100, 2 );
	}

	/**
	 * Whether a follow integration is active.
	 *
	 * @return bool True if another plugin owns following behavior.
	 */
	private function has_following_integration(): bool {
		$has_integration = class_exists( 'Friends\Friends' ) || class_exists( '\Activitypub\Activitypub' );

		/**
		 * Filters whether another plugin owns following behavior.
		 *
		 * @param bool $has_integration Whether another plugin owns following behavior.
		 */
		return (bool) apply_filters( 'mastodon_api_has_following_integration', $has_integration );
	}

	/**
	 * Determine whether a user is a local member of the current blog.
	 *
	 * @param string|int $user_id User ID.
	 * @return bool True if the user belongs to the current blog.
	 */
	private function is_local_blog_user( $user_id ): bool {
		if ( ! is_numeric( $user_id ) ) {
			return false;
		}

		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! get_user_by( 'ID', $user_id ) ) {
			return false;
		}

		if ( function_exists( 'is_user_member_of_blog' ) ) {
			return is_user_member_of_blog( $user_id, get_current_blog_id() );
		}

		return true;
	}

	/**
	 * Get local accounts automatically followed by a local blog user.
	 *
	 * @param string           $user_id User ID whose follows are requested.
	 * @param \WP_REST_Request $request Request object.
	 * @return Account_Entity[] Local account entities.
	 */
	private function get_local_blog_accounts( string $user_id, \WP_REST_Request $request ): array {
		if ( $this->has_following_integration() || ! $this->is_local_blog_user( $user_id ) ) {
			return array();
		}

		$accounts = array();
		$user_ids = $this->get_local_blog_user_ids();

		foreach ( $user_ids as $blog_user_id ) {
			if ( (int) $blog_user_id === (int) $user_id ) {
				continue;
			}

			$account = apply_filters( 'mastodon_api_account', null, $blog_user_id, $request, null );
			if ( $account instanceof Account_Entity ) {
				$accounts[] = $account;
			}
		}

		return $accounts;
	}

	/**
	 * Get local user IDs for the current blog.
	 *
	 * @return int[] Local blog user IDs.
	 */
	private function get_local_blog_user_ids(): array {
		return array_map(
			'intval',
			get_users(
				array(
					'blog_id' => get_current_blog_id(),
					'fields'  => 'ID',
				)
			)
		);
	}

	/**
	 * Set local follow counts when EMA owns local following behavior.
	 *
	 * @param mixed      $account Account entity.
	 * @param string|int $user_id User ID.
	 * @return mixed Account entity.
	 */
	public function api_account_counts( $account, $user_id ) {
		if ( $this->has_following_integration() || ! $account instanceof Account_Entity || ! $this->is_local_blog_user( $user_id ) ) {
			return $account;
		}

		$local_follow_count        = max( count( $this->get_local_blog_user_ids() ) - 1, 0 );
		$account->followers_count = $local_follow_count;
		$account->following_count = $local_follow_count;

		return $account;
	}

	/**
	 * Add automatically followed local blog accounts.
	 *
	 * @param Account_Entity[] $following Existing following accounts.
	 * @param string           $user_id   User ID.
	 * @param \WP_REST_Request $request   Request object.
	 * @return Account_Entity[] Following accounts.
	 */
	public function api_account_following( $following, string $user_id, \WP_REST_Request $request ) {
		if ( $this->has_following_integration() || ! empty( $following ) ) {
			return $following;
		}

		return $this->get_local_blog_accounts( $user_id, $request );
	}

	/**
	 * Add automatic local blog followers.
	 *
	 * @param Account_Entity[] $followers Existing follower accounts.
	 * @param string           $user_id   User ID.
	 * @param \WP_REST_Request $request   Request object.
	 * @return Account_Entity[] Follower accounts.
	 */
	public function api_account_followers( $followers, string $user_id, \WP_REST_Request $request ) {
		if ( $this->has_following_integration() || ! empty( $followers ) ) {
			return $followers;
		}

		return $this->get_local_blog_accounts( $user_id, $request );
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

		if ( ! $this->has_following_integration() && $this->is_local_blog_user( $user_id ) && get_current_user_id() !== (int) $user_id ) {
			$relationship->following   = true;
			$relationship->followed_by = true;
		}

		return apply_filters( 'mastodon_entity_relationship', $relationship, $user_id, $request );
	}

	public function entity_relationship_ensure_numeric_id( $relationship, $user_id ) {
		if ( ! is_numeric( $relationship->id ) ) {
			$relationship->id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $relationship->id );
		}
		return $relationship;
	}
}
