<?php
/**
 * Polls for ActivityPub integration.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Integration;

use Enable_Mastodon_Apps\Entity\Poll as Poll_Entity;
use Enable_Mastodon_Apps\Entity\Status as Status_Entity;

/**
 * Translate between Mastodon API polls and Polls for ActivityPub.
 */
class Polls_For_ActivityPub {
	/**
	 * Register integration hooks when the poll provider is available.
	 */
	public function __construct() {
		if ( ! self::is_available() ) {
			return;
		}

		add_filter( 'mastodon_api_submit_status', array( $this, 'submit_poll' ), 8, 8 );
		add_filter( 'mastodon_api_status_poll', array( $this, 'status_poll' ), 20, 2 );
		add_filter( 'mastodon_api_poll_vote', array( $this, 'vote' ), 20, 3 );
	}

	/**
	 * Whether Polls for ActivityPub provides the API used by this integration.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( '\\Polls_For_ActivityPub\\Poll_Repository' )
			&& class_exists( '\\Polls_For_ActivityPub\\Poll_Post_Type' );
	}

	/**
	 * Create a public or followers-only poll submitted through a Mastodon app.
	 *
	 * Direct polls are left to a messaging integration such as Friends, which
	 * supplies the recipient and creates the poll with a private post status.
	 *
	 * @param mixed           $status         Current result.
	 * @param string          $text           Poll question.
	 * @param int|string|null $reply          Reply target.
	 * @param array           $media           Attached media IDs.
	 * @param string          $format          Post format.
	 * @param string          $privacy         Mastodon visibility.
	 * @param string|null     $date            Scheduled date.
	 * @param array           $status_data     Additional normalized status fields.
	 * @return mixed
	 */
	public function submit_poll( $status, $text, $reply, $media, $format, $privacy, $date, $status_data = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( $status || empty( $status_data['poll'] ) || 'direct' === $privacy ) {
			return $status;
		}

		if ( ! empty( $media ) ) {
			return new \WP_Error( 'mastodon_api_poll_with_media', __( 'A poll cannot be attached to media.', 'enable-mastodon-apps' ), array( 'status' => 422 ) );
		}

		if ( $date ) {
			return new \WP_Error( 'mastodon_api_scheduled_poll', __( 'Scheduling polls is not supported.', 'enable-mastodon-apps' ), array( 'status' => 422 ) );
		}

		$poll_data = $status_data['poll'];
		$options   = isset( $poll_data['options'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $poll_data['options'] ) ) ) : array();
		$duration  = isset( $poll_data['expires_in'] ) ? absint( $poll_data['expires_in'] ) : 0;
		if ( count( $options ) < 2 || ! $duration ) {
			return new \WP_Error( 'mastodon_api_invalid_poll', __( 'A poll needs at least two options and an expiration time.', 'enable-mastodon-apps' ), array( 'status' => 422 ) );
		}

		$args = array(
			'question'    => wp_strip_all_tags( $text ),
			'options'     => $options,
			'duration'    => $duration,
			'multiple'    => ! empty( $poll_data['multiple'] ),
			'post_author' => get_current_user_id(),
			'status'      => 'publish',
			'visibility'  => self::activitypub_visibility( $privacy ),
		);

		if ( $reply ) {
			$args['in_reply_to_id'] = is_numeric( $reply ) ? get_permalink( (int) $reply ) : $reply;
		}

		$poll = \Polls_For_ActivityPub\Poll_Repository::get_instance()->create( $args );
		if ( is_wp_error( $poll ) ) {
			return $poll;
		}

		return apply_filters( 'mastodon_api_status', null, $poll->get_post_id(), array() );
	}

	/**
	 * Attach an authoritative local poll to a Mastodon status.
	 *
	 * @param mixed $entity    Current poll entity.
	 * @param int   $object_id Status object ID.
	 * @return mixed
	 */
	public function status_poll( $entity, $object_id ) {
		if ( $entity instanceof Poll_Entity ) {
			return $entity;
		}

		/**
		 * Map a containing status to its authoritative Polls for ActivityPub post.
		 *
		 * @param int $poll_id   Candidate poll post ID.
		 * @param int $object_id Containing status object ID.
		 */
		$poll_id = (int) apply_filters( 'mastodon_api_poll_post_id', $object_id, $object_id );

		return self::get_poll_entity( $poll_id, $entity );
	}

	/**
	 * Persist a local vote through Polls for ActivityPub.
	 *
	 * @param mixed $result  Current result.
	 * @param int   $poll_id Poll post ID.
	 * @param int[] $choices Selected option indexes.
	 * @return mixed
	 */
	public function vote( $result, $poll_id, $choices ) {
		if ( $result ) {
			return $result;
		}

		$poll = \Polls_For_ActivityPub\Poll_Repository::get_instance()->get( $poll_id );
		if ( is_wp_error( $poll ) ) {
			return $result;
		}

		if ( ! $poll->is_multiple_choice() && 1 !== count( $choices ) ) {
			return new \WP_Error( 'mastodon_api_poll_single_choice', __( 'Choose one option for this poll.', 'enable-mastodon-apps' ), array( 'status' => 422 ) );
		}

		$options = $poll->get_options();
		foreach ( $choices as $choice ) {
			if ( ! isset( $options[ $choice ] ) ) {
				return new \WP_Error( 'mastodon_api_poll_invalid_choice', __( 'The selected poll option does not exist.', 'enable-mastodon-apps' ), array( 'status' => 422 ) );
			}
		}

		foreach ( $choices as $choice ) {
			$added = $poll->add_vote( get_current_user_id(), $choice );
			if ( is_wp_error( $added ) ) {
				return $added;
			}
		}

		return self::get_poll_entity( $poll_id );
	}

	/**
	 * Convert a Polls for ActivityPub poll to a Mastodon API entity.
	 *
	 * @param int   $poll_id Poll post ID.
	 * @param mixed $fallback Value returned when the ID is not a poll.
	 * @return Poll_Entity|mixed
	 */
	public static function get_poll_entity( $poll_id, $fallback = null ) {
		$poll = \Polls_For_ActivityPub\Poll_Repository::get_instance()->get( (int) $poll_id );
		if ( is_wp_error( $poll ) ) {
			return $fallback;
		}

		$tallies = $poll->get_tallies();
		$options = array();
		foreach ( $poll->get_options() as $index => $title ) {
			$options[] = array(
				'title'       => $title,
				'votes_count' => $tallies[ $index ] ?? 0,
			);
		}

		$entity                = new Poll_Entity();
		$entity->id            = (string) $poll_id;
		$expires_at            = $poll->get_expires_at_iso();
		$entity->expires_at    = $expires_at ? $expires_at : null;
		$entity->expired       = $poll->is_expired();
		$entity->multiple      = $poll->is_multiple_choice();
		$entity->votes_count   = $poll->get_total_votes();
		$entity->voters_count  = $poll->get_unique_voters_count();
		$entity->options       = $options;
		$entity->emojis        = array();
		$entity->voted         = $poll->user_has_voted();
		$entity->own_votes     = $poll->get_user_votes();

		return $entity;
	}

	/**
	 * Convert Mastodon visibility to the ActivityPub plugin value.
	 *
	 * @param string $privacy Mastodon visibility.
	 * @return string
	 */
	private static function activitypub_visibility( $privacy ) {
		if ( 'unlisted' === $privacy && defined( 'ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC' ) ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC;
		}
		if ( in_array( $privacy, array( 'private', 'direct' ), true ) && defined( 'ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE' ) ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
		}

		return defined( 'ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC' ) ? ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC : 'public';
	}
}
