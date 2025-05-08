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
				'post_status' => array( 'ema_read', 'ema_unread' ),
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

		$unread = 'ema_unread' === $message->post_status;
		if ( ! $unread ) {
			$unread_posts = get_children(
				array(
					'post_parent' => $message->ID,
					'post_type'   => Mastodon_API::get_dm_cpt(),
					'post_status' => array( 'ema_unread' ),
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
		$conversation->accounts = array();
		$conversation->accounts[] = apply_filters( 'mastodon_api_account', null, $message->post_author );

		return $conversation;
	}

	public function api_conversations( $conversations, $user_id, $limit = 20 ) {
		$messages = new \WP_Query();
		$messages->set( 'post_type', Mastodon_API::get_dm_cpt() );
		$messages->set( 'post_parent', '0' );
		$messages->set( 'post_status', array( 'ema_read', 'ema_unread' ) );
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
			return array(
				'ema_read',
				'ema_unread',
			);
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
		if ( ! in_array( 'ema_unread', $args['post_status'] ) ) {
			$args['post_status'][] = 'ema_unread';
		}
		if ( ! in_array( 'ema_read', $args['post_status'] ) ) {
			$args['post_status'][] = 'ema_read';
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

	public function dm_row_actions() {
		return array();
	}
}
