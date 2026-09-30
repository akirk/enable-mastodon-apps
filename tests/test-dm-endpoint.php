<?php
/**
 * Direct message regression tests.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Tests for issue #272.
 */
class DMEndpoint_Test extends Mastodon_API_TestCase {
	/**
	 * Existing mention links survive submission and rendering.
	 */
	public function test_submit_dm_preserves_mention_link() {
		$this->factory->user->create( array( 'user_login' => 'dmrecipient' ) );
		$content = '<a href="https://example.org/@dmrecipient">@dmrecipient</a> DM test';
		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', $content );
		$request->set_param( 'visibility', 'direct' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertEquals( 200, $response->get_status() );
		$status = $response->get_data();
		$this->assertSame( 'direct', $status->visibility );
		$this->assertStringContainsString( $content, get_post( $status->id )->post_content );
		$this->assertStringContainsString( $content, $status->content );
		$this->assertSame( 1, substr_count( $status->content, '<a ' ) );
	}

	/**
	 * Integration-generated links survive the same pipeline.
	 */
	public function test_submit_dm_preserves_integration_mention_link() {
		$link = '<a rel="mention" class="u-url mention" href="https://example.org/@dmrecipient">@dmrecipient</a>';
		$filter = static function ( $text ) use ( $link ) {
			return str_replace( '@dmrecipient', $link, $text );
		};
		add_filter( 'mastodon_api_submit_status_text', $filter );
		try {
			$this->factory->user->create( array( 'user_login' => 'dmrecipient' ) );
			$request = $this->api_request( 'POST', '/api/v1/statuses' );
			$request->set_param( 'status', '@dmrecipient DM test' );
			$request->set_param( 'visibility', 'direct' );
			$response = $this->dispatch_authenticated( $request );

			$this->assertEquals( 200, $response->get_status() );
			$status = $response->get_data();
			$this->assertStringContainsString( $link, get_post( $status->id )->post_content );
			$this->assertStringContainsString( $link, $status->content );
			$this->assertSame( 1, substr_count( $status->content, '<a ' ) );
		} finally {
			remove_filter( 'mastodon_api_submit_status_text', $filter );
		}
	}
}
