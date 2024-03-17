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
			unlink( $this->test_file );
		}

		$uploads  = wp_upload_dir();
		$iterator = new \RecursiveDirectoryIterator( $uploads['basedir'] );
		$objects  = new \RecursiveIteratorIterator( $iterator );
		foreach ( $objects as $name => $object ) {
			if ( is_file( $name ) ) {
				unlink( $name );
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
		global $wp_rest_server;
		$request = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v2/media' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 422, $response->get_status() );
	}

	public function test_post_media() {
		global $wp_rest_server;
		$request                       = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v2/media' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->test_file ),
					'name'     => 'fox.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
					'type'     => wp_get_image_mime( $this->test_file ),
				),
			)
		);
		$response = $wp_rest_server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertInstanceOf( Entity\Media_Attachment::class, $response->get_data() );
	}

	public function test_get_media() {
		global $wp_rest_server;
		$request                       = new \WP_REST_Request( 'POST', '/' . Mastodon_API::PREFIX . '/api/v2/media' );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->test_file ),
					'name'     => 'fox.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
					'type'     => wp_get_image_mime( $this->test_file ),
				),
			)
		);
		$response = $wp_rest_server->dispatch( $request );

		$request = new \WP_REST_Request( 'GET', '/' . Mastodon_API::PREFIX . '/api/v1/media/' . $response->get_data()->id );
		$response = $wp_rest_server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertInstanceOf( Entity\Media_Attachment::class, $response->get_data() );
	}
}
