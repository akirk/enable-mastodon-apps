<?php
/**
 * Comment CPT.
 *
 * This contains the automatic mapping of comments to a custom post type.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Comment Custom Post Type
 *
 * This class maps comments to a custom post type.
 */
class Comment_CPT {
	const CPT = 'comment';
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
		add_filter( 'mastodon_api_account', array( $this, 'api_account' ), 10, 4 );
	}

	public function register_custom_post_type() {
		$args = array(
			'labels'       => array(
				'name'          => 'Comment',
				'singular_name' => 'Comment',
				'menu_name'     => 'Comments',
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'rewrite'      => false,
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

	public function post_id_to_comment_id( $post_id ) {
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
		$post    = get_post( $parent_post_id );
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
			'post_title'    => 'Comment',
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
		$comment_id = $this->post_id_to_comment_id( $post_id );
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
		return apply_filters( 'mastodon_api_account', null, $comment->comment_author_url, $request, null );
	}
}
