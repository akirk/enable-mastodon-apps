<?php
/**
 * ActivityPub Integration
 *
 * Perform follows through the ActivityPub plugin so that Mastodon apps can
 * follow remote accounts even when no other plugin owns following behavior.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Integration;

use Enable_Mastodon_Apps\Entity\Account as Account_Entity;
use Enable_Mastodon_Apps\Entity\Relationship as Relationship_Entity;

/**
 * This is the class that implements the ActivityPub adaptations.
 *
 * The ActivityPub plugin owns the follow relationships of a WordPress site, so
 * EMA only translates between Mastodon account ids and remote actor posts.
 *
 * @since 1.6.2
 *
 * @package Enable_Mastodon_Apps
 */
class Activitypub {
	/**
	 * The post type the ActivityPub plugin uses for remote actors.
	 *
	 * @var string
	 */
	const ACTOR_POST_TYPE = 'ap_actor';

	public function __construct() {
		if ( ! self::is_available() ) {
			return;
		}

		// Run before other integrations so that the account id is still the one the app was given.
		add_filter( 'mastodon_api_account_follow', array( $this, 'account_follow' ), 9 );
		add_action( 'mastodon_api_account_unfollow', array( $this, 'account_unfollow' ), 9 );
		add_filter( 'mastodon_entity_relationship', array( $this, 'entity_relationship' ), 20, 2 );
		add_filter( 'mastodon_api_account', array( $this, 'account_counts' ), 16, 2 );
		add_filter( 'mastodon_api_account_following', array( $this, 'account_following' ), 20, 3 );
	}

	/**
	 * Whether the ActivityPub plugin provides the API we rely on.
	 *
	 * @return bool True if follows can be handed to the ActivityPub plugin.
	 */
	public static function is_available() {
		return function_exists( '\Activitypub\follow' )
			&& function_exists( '\Activitypub\unfollow' )
			&& class_exists( '\Activitypub\Collection\Following' )
			&& class_exists( '\Activitypub\Collection\Remote_Actors' );
	}

	/**
	 * Get the local actor id to act as.
	 *
	 * In blog-only mode the individual user has no actor of their own, so the
	 * blog actor is used instead, mirroring what the ActivityPub plugin does.
	 *
	 * @return int|null The local actor id, or null if the site cannot federate.
	 */
	private function get_local_actor_id() {
		if ( ! function_exists( '\Activitypub\user_can_activitypub' ) ) {
			return null;
		}

		$user_id = get_current_user_id();
		if ( $user_id && \Activitypub\user_can_activitypub( $user_id ) ) {
			return $user_id;
		}

		$blog_user_id = \Activitypub\Collection\Actors::BLOG_USER_ID;
		if ( \Activitypub\user_can_activitypub( $blog_user_id ) ) {
			return $blog_user_id;
		}

		return null;
	}

	/**
	 * Resolve a Mastodon account id to a remote actor post id.
	 *
	 * @param string|int $user_id     The account id as it was handed out to the app.
	 * @param bool       $allow_fetch Whether the actor may be fetched from the remote server.
	 * @return int The remote actor post id, or 0 if this is not a remote actor.
	 */
	private function resolve_remote_actor( $user_id, $allow_fetch = false ) {
		if ( is_numeric( $user_id ) ) {
			// The ActivityPub plugin hands out remote actor post ids as account ids.
			$post = get_post( intval( $user_id ) );
			if ( $post && self::ACTOR_POST_TYPE === $post->post_type ) {
				return $post->ID;
			}

			return 0;
		}

		$identifier = $this->normalize_identifier( $user_id );
		if ( ! $identifier ) {
			return 0;
		}

		if ( filter_var( $identifier, FILTER_VALIDATE_URL ) ) {
			$post = \Activitypub\Collection\Remote_Actors::get_by_uri( $identifier );
			if ( ! is_wp_error( $post ) ) {
				return $post->ID;
			}
		} else {
			$post_id = $this->get_actor_post_id_by_acct( $identifier );
			if ( $post_id ) {
				return $post_id;
			}
		}

		if ( ! $allow_fetch ) {
			return 0;
		}

		$post = \Activitypub\Collection\Remote_Actors::fetch_by_various( $identifier );
		if ( is_wp_error( $post ) ) {
			return 0;
		}

		return $post->ID;
	}

	/**
	 * Turn an account id into a webfinger address or actor URL.
	 *
	 * @param string|int $user_id The account id.
	 * @return string The identifier, or an empty string if it cannot be used.
	 */
	private function normalize_identifier( $user_id ) {
		if ( ! is_string( $user_id ) ) {
			return '';
		}

		$identifier = trim( str_replace( 'acct:', '', $user_id ) );
		$identifier = trim( $identifier, '@' );

		if ( ! $identifier || ! preg_match( '/^[^\s@]+(@[^\s@]+)?$|^https?:\/\//i', $identifier ) ) {
			return '';
		}

		return $identifier;
	}

	/**
	 * Look up a locally known remote actor by its webfinger address.
	 *
	 * @param string $acct The webfinger address, without a leading `@`.
	 * @return int The remote actor post id, or 0 if it is not known locally.
	 */
	private function get_actor_post_id_by_acct( $acct ) {
		$posts = get_posts(
			array(
				'post_type'        => self::ACTOR_POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_key'         => '_activitypub_acct', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $acct, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		return intval( $posts[0] );
	}

	/**
	 * Follow a remote account through the ActivityPub plugin.
	 *
	 * @param string|int $user_id The account id to follow.
	 * @return string|int|\WP_Error The unmodified account id, or an error if the follow failed.
	 */
	public function account_follow( $user_id ) {
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$actor_post_id = $this->resolve_remote_actor( $user_id, true );
		if ( ! $actor_post_id ) {
			// A numeric id that is not a remote actor belongs to a local or plugin-owned account.
			if ( is_numeric( $user_id ) ) {
				return $user_id;
			}

			return $this->fail(
				$user_id,
				'mastodon_api_account_not_found',
				__( 'The account could not be found on the fediverse.', 'enable-mastodon-apps' ),
				404
			);
		}

		$local_actor_id = $this->get_local_actor_id();
		if ( null === $local_actor_id ) {
			return $this->fail(
				$user_id,
				'mastodon_api_follow_failed',
				__( 'This site cannot follow accounts on the fediverse.', 'enable-mastodon-apps' ),
				400
			);
		}

		// Another integration may have performed the follow already.
		if ( \Activitypub\Collection\Following::check_status( $local_actor_id, $actor_post_id ) ) {
			return $user_id;
		}

		$result = \Activitypub\follow( $actor_post_id, $local_actor_id );
		if ( is_wp_error( $result ) ) {
			return $this->fail( $user_id, $result->get_error_code(), $result->get_error_message(), 500 );
		}

		return $user_id;
	}

	/**
	 * Unfollow a remote account through the ActivityPub plugin.
	 *
	 * @param string|int $user_id The account id to unfollow.
	 */
	public function account_unfollow( $user_id ) {
		$actor_post_id = $this->resolve_remote_actor( $user_id );
		if ( ! $actor_post_id ) {
			return;
		}

		$local_actor_id = $this->get_local_actor_id();
		if ( null === $local_actor_id ) {
			return;
		}

		if ( ! \Activitypub\Collection\Following::check_status( $local_actor_id, $actor_post_id ) ) {
			return;
		}

		\Activitypub\unfollow( $actor_post_id, $local_actor_id );
	}

	/**
	 * Report a follow that could not be performed.
	 *
	 * Only reported when no other plugin handles follows: when one does, it gets
	 * to run with the account id it expects and decide the outcome itself.
	 *
	 * @param string|int $user_id The account id.
	 * @param string     $code    The error code.
	 * @param string     $message The error message.
	 * @param int        $status  The HTTP status to report.
	 * @return string|int|\WP_Error The account id, or an error.
	 */
	private function fail( $user_id, $code, $message, $status ) {
		if ( $this->another_integration_follows() ) {
			return $user_id;
		}

		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Whether another plugin also handles follows.
	 *
	 * @return bool True if something other than this integration is hooked into following.
	 */
	private function another_integration_follows() {
		global $wp_filter;

		if ( empty( $wp_filter['mastodon_api_account_follow'] ) ) {
			return false;
		}

		foreach ( $wp_filter['mastodon_api_account_follow']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && isset( $callback['function'][0] ) && $this === $callback['function'][0] ) {
					continue;
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Report the ActivityPub follow state in the relationship.
	 *
	 * @param Relationship_Entity $relationship The relationship.
	 * @param string|int          $user_id      The account id.
	 * @return Relationship_Entity The relationship.
	 */
	public function entity_relationship( $relationship, $user_id ) {
		if ( ! $relationship instanceof Relationship_Entity ) {
			return $relationship;
		}

		$actor_post_id = $this->resolve_remote_actor( $user_id );
		if ( ! $actor_post_id ) {
			return $relationship;
		}

		$local_actor_id = $this->get_local_actor_id();
		if ( null === $local_actor_id ) {
			return $relationship;
		}

		$status = \Activitypub\Collection\Following::check_status( $local_actor_id, $actor_post_id );
		if ( \Activitypub\Collection\Following::ACCEPTED === $status ) {
			$relationship->following = true;
		} elseif ( \Activitypub\Collection\Following::PENDING === $status ) {
			$relationship->requested = true;
		}

		if ( class_exists( '\Activitypub\Collection\Followers' ) && \Activitypub\Collection\Followers::follows( $actor_post_id, $local_actor_id ) ) {
			$relationship->followed_by = true;
		}

		return $relationship;
	}

	/**
	 * Report how many accounts a local user follows.
	 *
	 * @param mixed      $account The account.
	 * @param string|int $user_id The account id.
	 * @return mixed The account.
	 */
	public function account_counts( $account, $user_id ) {
		if ( ! $account instanceof Account_Entity || ! $this->is_local_actor( $user_id ) ) {
			return $account;
		}

		if ( ! $account->following_count ) {
			$account->following_count = \Activitypub\Collection\Following::count( $user_id );
		}

		return $account;
	}

	/**
	 * List the remote accounts a local user follows.
	 *
	 * @param Account_Entity[] $following Accounts another handler already found.
	 * @param string|int       $user_id   The account id.
	 * @param \WP_REST_Request $request   The request.
	 * @return Account_Entity[] The accounts.
	 */
	public function account_following( $following, $user_id, $request ) {
		if ( ! empty( $following ) || ! $this->is_local_actor( $user_id ) ) {
			return $following;
		}

		return $this->accounts_from_actors(
			\Activitypub\Collection\Following::get_many( $user_id, $this->get_limit( $request ) ),
			$request
		);
	}

	/**
	 * Turn remote actor posts into accounts.
	 *
	 * @param mixed            $actor_posts The remote actor posts.
	 * @param \WP_REST_Request $request     The request.
	 * @return Account_Entity[] The accounts.
	 */
	private function accounts_from_actors( $actor_posts, $request ) {
		if ( ! is_array( $actor_posts ) ) {
			return array();
		}

		$accounts = array();
		foreach ( $actor_posts as $actor_post ) {
			$account = apply_filters( 'mastodon_api_account', null, $actor_post->ID, $request, null );
			if ( $account instanceof Account_Entity ) {
				$accounts[] = $account;
			}
		}

		return $accounts;
	}

	/**
	 * Whether the account id belongs to a local actor of this site.
	 *
	 * @param string|int $user_id The account id.
	 * @return bool True if this is a local actor that can federate.
	 */
	private function is_local_actor( $user_id ) {
		if ( ! is_numeric( $user_id ) || ! function_exists( '\Activitypub\user_can_activitypub' ) ) {
			return false;
		}

		$user_id = intval( $user_id );
		if ( $user_id <= 0 || ! get_user_by( 'ID', $user_id ) ) {
			return false;
		}

		return (bool) \Activitypub\user_can_activitypub( $user_id );
	}

	/**
	 * Get the requested number of results.
	 *
	 * @param \WP_REST_Request|null $request The request.
	 * @return int The limit.
	 */
	private function get_limit( $request ) {
		$limit = 40;
		if ( $request instanceof \WP_REST_Request && $request->get_param( 'limit' ) ) {
			$limit = intval( $request->get_param( 'limit' ) );
		}

		return max( 1, min( 80, $limit ) );
	}
}
