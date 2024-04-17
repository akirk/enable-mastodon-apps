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
		add_action( 'wp_insert_comment', array( $this, 'create_comment_post' ), 10, 2 );
		add_action( 'delete_comment', array( $this, 'delete_comment_post' ) );
		add_action( 'transition_comment_status', array( $this, 'update_comment_post_status' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'update_comment_post' ), 10, 2 );
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

	public function remap_comment_id( $comment_id ) {
		$remapped_comment_id = get_comment_meta( $comment_id, self::META_KEY, true );
		if ( ! $remapped_comment_id ) {
			$remapped_comment_id = $this->create_comment_post( $comment_id, get_comment( $comment_id ) );
		}
		return $remapped_comment_id;
	}


	public function create_comment_post( $comment_id, $comment ) {
		$parent_post_id = $comment->comment_post_ID;
		$post    = get_post( $parent_post_id );
		if ( ! $post ) {
			return;
		}

		if ( $comment->comment_parent ) {
			$parent_comment = get_comment( $comment->comment_parent );
			if ( ! $parent_comment ) {
				return;
			}
			$parent_post_id = $this->remap_comment_id( $comment->comment_parent );
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
		return $post_id;
	}

	public function delete_comment_post( $comment_id ) {
		$post_id = $this->remap_comment_id( $comment_id );
		if ( ! $post_id ) {
			return;
		}

		wp_delete_post( $post_id, true );
	}

	public function update_comment_post_status( $new_status, $old_status, $comment ) {
		$post_id = $this->remap_comment_id( $comment->comment_ID );
		if ( ! $post_id ) {
			return;
		}

		$post_data = array(
			'ID'          => $post_id,
			'post_status' => 'publish' === $new_status ? 'publish' : 'private',
		);

		wp_update_post( $post_data );
	}

	public function update_comment_post( $comment_id, $comment ) {
		$post_id = $this->remap_comment_id( $comment_id );
		if ( ! $post_id ) {
			return;
		}

		$post_data = array(
			'ID'            => $post_id,
			'post_content'  => $comment->comment_content,
			'post_date'     => $comment->comment_date,
			'post_date_gmt' => $comment->comment_date_gmt,
		);

		wp_update_post( $post_data );
	}
}
