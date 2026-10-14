<?php
/**
 * Class MediaEndpoint_Test.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * A testcase for the media endpoint.
 */
class MediaEndpoint_Test extends Mastodon_API_TestCase {
	protected string $test_file;

	public function setUp(): void {
		parent::setUp();

		$orig_file       = __DIR__ . '/lib/images/fox.jpg';
		$this->test_file = get_temp_dir() . 'fox.jpg';
		copy( $orig_file, $this->test_file );
	}

	public function tearDown(): void {
		if ( file_exists( $this->test_file ) ) {
			wp_delete_file( $this->test_file );
		}

		$uploads  = wp_upload_dir();
		$iterator = new \RecursiveDirectoryIterator( $uploads['basedir'] );
		$objects  = new \RecursiveIteratorIterator( $iterator );
		foreach ( $objects as $name => $object ) {
			if ( is_file( $name ) ) {
				wp_delete_file( $name );
			}
		}

		parent::tearDown();
	}

	public function test_register_routes() {
		global $wp_rest_server;
		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/' . Mastodon_API::PREFIX . '/api/v2/media', $routes );
	}

	public function test_media_post_empty() {
		$request = $this->api_request( 'POST', '/api/v2/media' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 422, $response->get_status() );
	}

	public function test_post_media() {
		$request = $this->api_request( 'POST', '/api/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->test_file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					'name'     => 'fox.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
					'type'     => wp_get_image_mime( $this->test_file ),
				),
			)
		);
		$request->set_param( 'description', 'A fox & its den' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertInstanceOf( Entity\Media_Attachment::class, $response->get_data() );
		$this->assertSame( 'A fox & its den', get_post_meta( $response->get_data()->id, '_wp_attachment_image_alt', true ) );
	}

	public function test_media_description_updates_wordpress_alt_text_and_can_be_cleared() {
		$description = 'A "fox" & its \\\ den';
		$request = $this->api_request( 'PUT', '/api/v1/media/' . $this->friend_attachment_id );
		$request->set_param( 'description', $description );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $description, $response->get_data()->description );
		$this->assertSame( $description, get_post_meta( $this->friend_attachment_id, '_wp_attachment_image_alt', true ) );
		$this->assertSame( $description, get_post_field( 'post_excerpt', $this->friend_attachment_id ) );

		$request->set_param( 'description', '' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( '', $response->get_data()->description );
		$this->assertSame( '', get_post_meta( $this->friend_attachment_id, '_wp_attachment_image_alt', true ) );
	}

	public function test_media_reads_wordpress_alt_text_before_caption() {
		update_post_meta( $this->friend_attachment_id, '_wp_attachment_image_alt', 'A fox in its den' );
		wp_update_post(
			array(
				'ID'           => $this->friend_attachment_id,
				'post_excerpt' => 'Photo credit',
			)
		);
		$request = $this->api_request( 'GET', '/api/v1/media/' . $this->friend_attachment_id );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'A fox in its den', $response->get_data()->description );
	}

	public function test_get_media() {
		$request = $this->api_request( 'POST', '/api/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->test_file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					'name'     => 'fox.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
					'type'     => wp_get_image_mime( $this->test_file ),
				),
			)
		);
		$response = $this->dispatch_authenticated( $request );

		$request = $this->api_request( 'GET', '/api/v1/media/' . $response->get_data()->id );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertInstanceOf( Entity\Media_Attachment::class, $response->get_data() );
	}
}
