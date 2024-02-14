<?php
/**
 * Class Test_Apps_Endpoint
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * A testcase for the apps endpoint.
 *
 * @package
 */
class Mastodon_API_TestCase extends \WP_UnitTestCase {
	protected $token;
	protected $post;
	protected $friend_post;
	protected $private_post;
	protected $administrator;
	protected $friend;
	protected $app;
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$this->administrator = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$this->friend = $this->factory->user->create(
			array(
				'role' => 'read',
			)
		);

		$this->post = wp_insert_post(
			array(
				'post_author'  => $this->administrator,
				'post_content' => 'Test post',
				'post_title'   => '',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => '2023-01-03 00:00:00',
			)
		);
		set_post_format( $this->post, 'status' );

		$this->private_post = wp_insert_post(
			array(
				'post_author'  => $this->administrator,
				'post_content' => 'Private post',
				'post_title'   => '',
				'post_status'  => 'private',
				'post_type'    => 'post',
				'post_date'    => '2023-01-03 00:00:00',
			)
		);
		set_post_format( $this->post, 'status' );
		$args = array(
			'supports'   => array( 'title', 'editor', 'author', 'revisions', 'thumbnail', 'excerpt', 'comments', 'post-formats' ),
			'taxonomies' => array( 'post_tag', 'post_format' ),
		);

		register_post_type( 'friend_post_cache', $args );

		$this->friend_post = wp_insert_post(
			array(
				'post_author'  => $this->friend,
				'post_content' => '<!-- wp:paragraph -->
<p>Hello test</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Another paragraph</p>
<!-- /wp:paragraph -->

<!-- wp:image {"id":1919066,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.org/image.png" alt="" class="wp-image-1919066"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Last paragrah</p>
<!-- /wp:paragraph -->',
				'post_title'   => 'Friend title',
				'post_status'  => 'publish',
				'post_type'    => 'friend_post_cache',
				'post_date'    => '2023-01-04 00:00:00',
			)
		);
		set_post_format( $this->friend_post, 'status' );
		add_filter(
			'friends_frontend_post_types',
			function ( $post_types ) {
				return array_merge( array( 'friend_post_cache' ), $post_types );
			}
		);
		$this->app = Mastodon_App::save( 'Test App', array( 'https://test' ), 'read write follow push', 'https://mastodon.local' );
		$oauth = new Mastodon_OAuth();
		$this->token = wp_generate_password( 128, false );
		$userdata = get_userdata( $this->administrator );
		$oauth->get_token_storage()->setAccessToken( $this->token, $this->app->get_client_id(), $userdata->ID, time() + HOUR_IN_SECONDS, $this->app->get_scopes() );
		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		add_filter( 'pre_http_request', array( $this, 'block_http_requests' ), 10 );
	}

	public function block_http_requests() {
		return new \WP_Error( 'http_request_failed', 'HTTP requests have been blocked.' );
	}
}
