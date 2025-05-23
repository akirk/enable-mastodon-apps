<?php
/**
 * Comment CPT.
 *
 * This contains the automatic mapping of comments to a custom post type.
 *
 * The reason this exists in this plugin is that the ActivityPub plugin (rightfully) stores
 * incoming replies to posts as WordPress comments. Also the Friends plugin follows suite, so
 * a reply to a post is stored as a comment on the post, and then the ActivityPub plugin will
 * send it out via ActivityPub. In Mastodon, there is no distinction between a post and a
 * comment, so they all live in the same "id pool". Thus, clients expect them to appear in the
 * same stream and interspersed with each other. This plugin syncs comments to a custom post
 * type so that they can be queried and displayed in the same way as posts.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

use Enable_Mastodon_Apps\Entity\Status as Status_Entity;
use Enable_Mastodon_Apps\Entity\Status_Source as Status_Source_Entity;

/**
 * Comment Custom Post Type
 *
 * This class maps comments to a custom post type.
 */
class Comment_CPT {
	const CPT      = 'comment';
	const META_KEY = 'comment_id';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_hooks();
		$this->register_custom_post_type();
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'wp_insert_comment', array( get_called_class(), 'create_comment_post' ), 10, 2 );
		add_action( 'delete_comment', array( $this, 'delete_comment_post' ) );
		add_action( 'delete_post', array( $this, 'delete_post' ) );
		add_action( 'transition_comment_status', array( $this, 'update_comment_post_status' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'update_comment_post' ), 10, 2 );
		add_filter( 'mastodon_api_get_posts_query_args', array( $this, 'api_get_posts_query_args' ) );
		add_filter( 'mastodon_api_status', array( $this, 'api_status' ), 9, 3 );
		add_filter( 'mastodon_api_status_source', array( $this, 'api_status_source' ), 10, 3 );
		add_filter( 'mastodon_api_account', array( $this, 'api_account' ), 10, 4 );
		add_filter( 'mastodon_api_in_reply_to_id', array( $this, 'mastodon_api_in_reply_to_id' ), 15 );
		add_filter( 'mastodon_api_notification_type', array( $this, 'mastodon_api_notification_type' ), 10, 2 );
		add_filter( 'mastodon_api_get_notifications_query_args', array( $this, 'mastodon_api_get_notifications_query_args' ), 10, 2 );
	}

	public function register_custom_post_type() {
		$args = array(
			'labels'       => array(
				'name'          => __( 'Comments' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'singular_name' => __( 'Comment' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'menu_name'     => __( 'Comments' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'rewrite'      => false,
			'supports'     => array( 'post-formats' ),
		);

		register_post_type( self::CPT, $args );
	}

	public static function comment_id_to_post_id( $comment_id ) {
		$remapped_comment_id = get_comment_meta( $comment_id, self::META_KEY, true );
		if ( ! $remapped_comment_id ) {
			$remapped_comment_id = self::create_comment_post( $comment_id, get_comment( $comment_id ) );
		}
		return $remapped_comment_id;
	}

	public function mastodon_api_in_reply_to_id( $post_id ) {
		$comment_id = self::post_id_to_comment_id( $post_id );
		if ( $comment_id ) {
			return $comment_id;
		}
		return $post_id;
	}

	public static function post_id_to_comment_id( $post_id ) {
		if ( get_post_type( $post_id ) !== self::CPT ) {
			return null;
		}
		$comment_id = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! $comment_id ) {
			// There is no comment ID associated with this post.
			return null;
		}
		return $comment_id;
	}

	public static function create_comment_post( $comment_id, $comment ) {
		if ( ! $comment && $comment_id ) {
			$comment = get_comment( $comment_id );
		}
		if ( ! $comment || ! isset( $comment->comment_approved ) || '1' !== strval( $comment->comment_approved ) ) {
			return;
		}
		$parent_post_id = $comment->comment_post_ID;
		$post           = get_post( $parent_post_id );
		if ( ! $post ) {
			return;
		}
		$post_format = get_post_format( $post );

		if ( $comment->comment_parent ) {
			$parent_comment = get_comment( $comment->comment_parent );
			if ( ! $parent_comment ) {
				return;
			}
			$parent_post_id = self::comment_id_to_post_id( $comment->comment_parent );
		}

		$post_data = array(
			'post_title'    => '',
			'post_content'  => $comment->comment_content,
			'post_author'   => $comment->user_id,
			'post_status'   => 'publish',
			'post_type'     => self::CPT,
			'post_parent'   => $parent_post_id,
			'post_date'     => $comment->comment_date,
			'post_date_gmt' => $comment->comment_date_gmt,
			'meta_input'    => array(
				self::META_KEY => $comment_id,
			),
		);

		$post_id = wp_insert_post( $post_data );
		if ( $post_format ) {
			set_post_format( $post_id, $post_format );
		}

		update_comment_meta( $comment_id, self::META_KEY, $post_id );

		return $post_id;
	}

	public function delete_comment_post( $comment_id ) {
		$post_id = self::comment_id_to_post_id( $comment_id );
		if ( ! $post_id ) {
			return;
		}

		delete_comment_meta( $comment_id, self::META_KEY );
		wp_delete_post( $post_id, true );
	}

	public function delete_post( $post_id ) {
		if ( doing_action( 'delete_comment' ) ) {
			return;
		}
		$comment_id = self::post_id_to_comment_id( $post_id );
		if ( ! $comment_id ) {
			return;
		}

		wp_delete_comment( $comment_id, true );
	}

	public function update_comment_post_status( $new_status, $old_status, $comment ) {
		$post_id = self::comment_id_to_post_id( $comment->comment_ID );
		if ( ! $post_id ) {
			if (
				'approved' === $new_status &&
				'approved' !== $old_status
			) {
				self::create_comment_post( $comment->comment_ID, $comment );
			}
			return;
		}

		if ( 'approved' === $new_status ) {
			wp_update_post(
				array(
					'ID'            => $post_id,
					'post_content'  => $comment->comment_content,
					'post_date'     => $comment->comment_date,
					'post_date_gmt' => $comment->comment_date_gmt,
					'post_status'   => 'publish',
				)
			);
			return;
		}

		if (
			'trash' === $new_status ||
			'spam' === $new_status
		) {
			$this->delete_comment_post( $comment->comment_ID );
			return;
		}
	}

	public function update_comment_post( $comment_id, $data ) {
		$post_id = self::comment_id_to_post_id( $comment_id );
		if ( ! $post_id ) {
			return;
		}
		$post_data = array(
			'ID'            => $post_id,
			'post_content'  => $data['comment_content'],
			'post_date'     => $data['comment_date'],
			'post_date_gmt' => $data['comment_date_gmt'],
			'post_status'   => $data['comment_approved'] ? 'publish' : 'trash',
		);

		wp_update_post( $post_data );
	}

	public static function api_get_posts_query_args( $args ) {
		$args['post_type'][] = self::CPT;
		return $args;
	}

	/**
	 * Get a status array.
	 *
	 * @param Status_Entity $status Current status array.
	 * @param int           $object_id The object ID to get the status from.
	 * @param array         $data Additional status data.
	 * @return Status_Entity The status entity
	 */
	public function api_status( ?Status_Entity $status, int $object_id, array $data = array() ): ?Status_Entity {
		if ( $status instanceof Status_Entity ) {
			return $status;
		}

		if ( ! isset( $data['comment'] ) ) {
			$comment_id = self::post_id_to_comment_id( $object_id );
			if ( ! $comment_id ) {
				return $status;
			}

			$comment = get_comment( $comment_id );
		} else {
			$comment = $data['comment'];
		}
		if ( ! $comment ) {
			return $status;
		}

		if ( $comment->user_id ) {
			$account = apply_filters( 'mastodon_api_account', null, $comment->user_id, null, $comment );
		} else {
			$account = apply_filters( 'mastodon_api_account', null, $comment->comment_author_url, null, $comment );
		}
		if ( ! $account ) {
			return $status;
		}

		$status             = new Status_Entity();
		$status->id         = strval( $object_id );
		$status->created_at = new \DateTime( $comment->comment_date_gmt, new \DateTimeZone( 'UTC' ) );
		$status->visibility = 'public';
		$status->uri        = get_comment_link( $comment );
		$status->content    = $comment->comment_content;
		$status->account    = $account;
		if ( $comment->comment_parent ) {
			$parent_post_id = self::comment_id_to_post_id( $comment->comment_parent );
			$status->in_reply_to_id = strval( $parent_post_id );
		} else {
			$status->in_reply_to_id = strval( $comment->comment_post_ID );
		}
		$status->in_reply_to_account_id = apply_filters( 'mastodon_api_account_id', null, $status->in_reply_to_id );

		return $status;
	}


	/**
	 * Get a status source array.
	 *
	 * @param Status_Source_Entity $status_source Current status source array.
	 * @param int                  $object_id The object ID to get the status from.
	 * @return Status_Source_Entity The status source entity
	 */
	public function api_status_source( ?Status_Source_Entity $status_source, int $object_id ): ?Status_Source_Entity {
		if ( $status_source instanceof Status_Source_Entity ) {
			return $status_source;
		}

		$comment_id = self::post_id_to_comment_id( $object_id );
		if ( ! $comment_id ) {
			return $status_source;
		}

		$comment = get_comment( $comment_id );
		if ( isset( $comment ) && $comment instanceof \WP_Comment ) {
			$status_source       = new Status_Source_Entity();
			$status_source->id   = strval( $object_id );
			$status_source->text = trim( wp_strip_all_tags( $comment->comment_content ) );
		}

		return $status_source;
	}

	public function api_account( $account, $user_id, $request = null, $post = null ) {
		if ( ! $post instanceof \WP_Post ) {
			return $account;
		}
		if ( self::CPT !== $post->post_type ) {
			return $account;
		}
		if ( $user_id ) {
			// A local user can already be resolved.
			return $account;
		}
		$comment_id = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! $comment_id ) {
			return $account;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return $account;
		}

		$account = apply_filters( 'mastodon_api_account', null, $comment->comment_author_url, $request, null );
		if ( $account ) {
			return $account;
		}

		$account = new Entity\Account();
		$account->id             = $comment->comment_author_url;
		$account->username       = $comment->comment_author;
		$account->display_name   = $comment->comment_author;
		$account->avatar         = get_avatar_url( $comment->comment_author_email );
		$account->avatar_static  = get_avatar_url( $comment->comment_author_email );
		$account->acct           = $comment->comment_author_url;
		$account->note           = '';
		$account->created_at     = new \DateTime( $comment->comment_date );
		$account->statuses_count = 0;
		$account->last_status_at = new \DateTime( $comment->comment_date );
		$account->url            = $comment->comment_author_url;

		$account->source = array(
			'privacy'   => 'public',
			'sensitive' => false,
			'language'  => 'en',
			'note'      => 'Comment',
			'fields'    => array(),
		);

		return Handler\Account::api_account_ensure_numeric_id( $account, $comment->comment_author_url );
	}

	public function mastodon_api_notification_type( $type, $post ) {
		if ( self::CPT === $post->post_type ) {
			$comment_id = self::post_id_to_comment_id( $post->ID );
			return get_comment_type( $comment_id );
		}
		return $type;
	}

	public function mastodon_api_get_notifications_query_args( $args, $type ) {
		if ( 'mention' === $type ) {
			if ( ! isset( $args['post_type'] ) ) {
				$args['post_type'] = array();
			} elseif ( ! is_array( $args['post_type'] ) ) {
				$args['post_type'] = array( $args['post_type'] );
			}
			$args['post_type'][] = self::CPT;
		}

		return $args;
	}
}
