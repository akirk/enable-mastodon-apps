<?php
/**
 * Conversation handler.
 *
 * This contains the default Conversation handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Handler\Handler;
use Enable_Mastodon_Apps\Comment_CPT;
use Enable_Mastodon_Apps\Mastodon_API;
use Enable_Mastodon_Apps\Mastodon_App;
use Enable_Mastodon_Apps\Entity\Status as Status_Entity;
use WP_REST_Response;

/**
 * This is the class that implements the default handler for all Status endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Conversation extends Status {
	const READ_STATUSES = array( 'ema_read', 'friends_read' );
	const UNREAD_STATUSES = array( 'ema_unread', 'friends_unread' );

	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_conversation', array( $this, 'api_conversation' ), 10, 2 );
		add_filter( 'mastodon_api_conversations', array( $this, 'api_conversations' ), 10, 3 );
		add_filter( 'mastodon_api_conversation_mark_read', array( $this, 'api_conversation_mark_read' ), 10 );
		add_filter( 'mastodon_api_conversation_delete', array( $this, 'delete_conversation' ), 10 );
		add_filter( 'mastodon_api_status_context_post_types', array( $this, 'conversation_post_type' ), 10, 2 );
		add_filter( 'mastodon_api_status_context_post_statuses', array( $this, 'conversation_post_status' ), 10, 2 );
		add_filter( 'mastodon_api_get_notifications_query_args', array( $this, 'conversation_query_args' ), 20, 2 );
		add_filter( 'the_title', array( $this, 'show_dm_text' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'dm_row_actions' ), 10, 2 );
	}

	public function api_conversation( $conversation, $post_id ) {
		$message = get_post( $post_id );
		if ( ! $message ) {
			return new \WP_Error( 'mastodon_api_conversation', 'Record not found', array( 'status' => 404 ) );
		}

		$last_status = get_posts(
			array(
				'post_type'   => Mastodon_API::get_dm_cpt(),
				'post_parent' => $message->ID,
				'post_status' => $this->get_post_statuses(),
				'orderby'     => 'date',
				'order'       => 'DESC',
				'numberposts' => 1,
			)
		);
		if ( ! $last_status ) {
			$last_status = $message;
		} else {
			$last_status = $last_status[0];
		}

		$unread = in_array( $message->post_status, self::UNREAD_STATUSES, true );
		if ( ! $unread ) {
			$unread_posts = get_children(
				array(
					'post_parent' => $message->ID,
					'post_type'   => Mastodon_API::get_dm_cpt(),
					'post_status' => self::UNREAD_STATUSES,
				)
			);
			if ( $unread_posts ) {
				$unread = true;
			}
		}
		$conversation = new \Enable_Mastodon_Apps\Entity\Conversation();
		$conversation->id = $message->ID;
		$conversation->unread = $unread;
		$conversation->last_status = apply_filters( 'mastodon_api_status', null, $last_status->ID );
		$conversation->accounts = $this->get_participant_accounts( $message );

		return $conversation;
	}

	public function api_conversations( $conversations, $user_id, $limit = 20 ) {
		$messages = new \WP_Query();
		$messages->set( 'post_type', Mastodon_API::get_dm_cpt() );
		$messages->set( 'post_parent', '0' );
		$messages->set( 'post_status', $this->get_post_statuses() );
		$messages->set( 'posts_per_page', $limit );
		$messages->set( 'order', 'DESC' );

		foreach ( $messages->get_posts() as $message ) {
			$conversation = $this->api_conversation( null, $message->ID );
			if ( $conversation && ! is_wp_error( $conversation ) ) {
				$conversations[] = $conversation;
			}
		}

		return $conversations;
	}

	private function get_post_statuses() {
		return array_merge( self::READ_STATUSES, self::UNREAD_STATUSES );
	}

	private function get_participant_accounts( \WP_Post $message ) {
		$accounts = array();
		$posts = array_merge(
			array( $message ),
			get_children(
				array(
					'post_parent' => $message->ID,
					'post_type'   => Mastodon_API::get_dm_cpt(),
					'post_status' => $this->get_post_statuses(),
					'orderby'     => 'date',
					'order'       => 'ASC',
				)
			)
		);

		foreach ( $posts as $post ) {
			if ( intval( $post->post_author ) === get_current_user_id() ) {
				continue;
			}

			$account = apply_filters( 'mastodon_api_account', null, $post->post_author, null, $post );
			if ( ! $account instanceof \Enable_Mastodon_Apps\Entity\Account ) {
				continue;
			}

			$accounts[ $account->id ] = $account;
		}

		return array_values( $accounts );
	}

	/**
	 * Mark a conversation and its replies as read.
	 *
	 * @param int $id The conversation id, which is the id of the first message.
	 */
	public function api_conversation_mark_read( $id ) {
		$message = $this->get_own_conversation( $id );
		if ( ! $message ) {
			return;
		}

		foreach ( $this->get_conversation_posts( $message ) as $post ) {
			$read_status = $this->get_read_status( $post->post_status );
			if ( ! $read_status ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => $read_status,
				)
			);
		}
	}

	/**
	 * Delete a conversation and its replies.
	 *
	 * @param int $id The conversation id, which is the id of the first message.
	 */
	public function delete_conversation( $id ) {
		$message = $this->get_own_conversation( $id );
		if ( ! $message ) {
			return;
		}

		foreach ( $this->get_conversation_posts( $message ) as $post ) {
			wp_trash_post( $post->ID );
		}
	}

	/**
	 * Get a conversation that belongs to the current user.
	 *
	 * Direct messages are stored in a post type that is specific to the recipient,
	 * so requiring the post to live in the current user's post type is what limits
	 * this to their own conversations.
	 *
	 * @param int $id The conversation id.
	 * @return \WP_Post|null The first message of the conversation, or null.
	 */
	private function get_own_conversation( $id ) {
		$message = get_post( $id );
		if ( ! $message || Mastodon_API::get_dm_cpt() !== $message->post_type ) {
			return null;
		}

		return $message;
	}

	/**
	 * Get all messages of a conversation, starting with the first one.
	 *
	 * @param \WP_Post $message The first message of the conversation.
	 * @return \WP_Post[] The messages of the conversation.
	 */
	private function get_conversation_posts( \WP_Post $message ) {
		return array_merge(
			array( $message ),
			get_children(
				array(
					'post_parent' => $message->ID,
					'post_type'   => Mastodon_API::get_dm_cpt(),
					'post_status' => $this->get_post_statuses(),
				)
			)
		);
	}

	/**
	 * Get the read post status that corresponds to an unread one.
	 *
	 * READ_STATUSES and UNREAD_STATUSES hold the same sources in the same order.
	 *
	 * @param string $post_status The current post status.
	 * @return string|null The matching read status, or null if already read.
	 */
	private function get_read_status( $post_status ) {
		$index = array_search( $post_status, self::UNREAD_STATUSES, true );
		if ( false === $index ) {
			return null;
		}

		return self::READ_STATUSES[ $index ];
	}

	public function conversation_post_type( $post_types, $context_post_id ) {
		$post_type = get_post_type( $context_post_id );
		if ( ! $post_type ) {
			return $post_types;
		}
		if ( strpos( $post_type, 'ema-dm-' ) === 0 ) {
			return array(
				Mastodon_API::get_dm_cpt(),
			);
		}

		return $post_types;
	}

	public function conversation_post_status( $post_types, $context_post_id ) {
		$post_type = get_post_type( $context_post_id );
		if ( ! $post_type ) {
			return $post_types;
		}
		if ( strpos( $post_type, 'ema-dm-' ) === 0 ) {
			return $this->get_post_statuses();
		}

		return $post_types;
	}

	public function conversation_query_args( $args, $type ) {
		if ( 'mention' !== $type ) {
			return $args;
		}
		if ( ! isset( $args['post_type'] ) ) {
			$args['post_type'] = array();
		} elseif ( ! is_array( $args['post_type'] ) ) {
			$args['post_type'] = array( $args['post_type'] );
		}
		$args['post_type'][] = Mastodon_Api::get_dm_cpt();

		if ( ! isset( $args['post_status'] ) ) {
			$args['post_status'] = array();
		} elseif ( ! is_array( $args['post_status'] ) ) {
			$args['post_status'] = array( $args['post_status'] );
		}
		foreach ( $this->get_post_statuses() as $post_status ) {
			if ( ! in_array( $post_status, $args['post_status'], true ) ) {
				$args['post_status'][] = $post_status;
			}
		}

		return $args;
	}

	public function show_dm_text( $title, $post_id ) {
		if ( is_admin() && get_post_type( $post_id ) === 'ema-dm-' . get_current_user_id() ) {
			if ( $title ) {
				$title .= ': ';
			}
			return $title . wp_strip_all_tags( get_the_content( $post_id ) );
		}

		return $title;
	}

	public function dm_row_actions( $actions, $post ) {
		if ( is_admin() && get_post_type( $post ) === 'ema-dm-' . get_current_user_id() ) {
			return array();
		}
		return $actions;
	}
}
