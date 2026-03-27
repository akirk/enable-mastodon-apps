<?php
/**
 * Class DMEndpoint_Test
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * Testcases for DM (Direct Message) endpoints.
 *
 * @see https://github.com/akirk/enable-mastodon-apps/issues/272
 *
 * @package
 */
class DMEndpoint_Test extends Mastodon_API_TestCase {
	protected $dm_recipient;

	public function set_up() {
		parent::set_up();

		$this->dm_recipient = $this->factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'dmrecipient',
			)
		);
	}

	/**
	 * Test submitting a basic DM.
	 */
	public function test_submit_dm() {
		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', '@dmrecipient DM test' );
		$request->set_param( 'visibility', 'direct' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsString( $data->id );
		$this->assertEquals( 'direct', $data->visibility );
	}

	/**
	 * Test that DM content with pre-linked mentions is not double-linked.
	 *
	 * Simulates what the ActivityPub plugin's Mention::the_content does:
	 * converts @mentions to <a> tags via the mastodon_api_submit_status_text filter.
	 */
	public function test_dm_mention_not_double_linked() {
		add_filter(
			'mastodon_api_submit_status_text',
			function ( $text ) {
				// Simulate ActivityPub's enrich_content_data + Mention::replace_with_links.
				return preg_replace(
					'/@(dmrecipient)\b/',
					'<a rel="mention" class="u-url mention" href="https://example.org/@$1">@$1</a>',
					$text
				);
			}
		);

		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', '@dmrecipient DM test' );
		$request->set_param( 'visibility', 'direct' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		// Check stored content doesn't have nested <a> tags.
		$post = get_post( $data->id );
		$this->assertThat( $post->post_content, $this->logicalNot( $this->stringContains( '<a href="https://example.org/<a' ) ) );

		// Check rendered content doesn't have nested <a> tags.
		$this->assertThat( $data->content, $this->logicalNot( $this->stringContains( '<a href="https://example.org/<a' ) ) );

		// The mention link should exist exactly once.
		$this->assertEquals(
			1,
			substr_count( $data->content, 'href="https://example.org/@dmrecipient"' ),
			'Mention link should appear exactly once in content'
		);
	}

	/**
	 * Test that DM content with HTML mentions from a client is handled correctly.
	 *
	 * Some Mastodon clients send status text that already includes HTML <a> tags for mentions.
	 */
	public function test_dm_html_mention_from_client() {
		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', '<a href="https://example.org/@dmrecipient">@dmrecipient</a> DM test' );
		$request->set_param( 'visibility', 'direct' );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		// Check stored content doesn't have nested <a> tags.
		$post = get_post( $data->id );
		$this->assertThat( $post->post_content, $this->logicalNot( $this->stringContains( '<a href="https://example.org/<a' ) ) );

		// Check rendered content doesn't have nested <a> tags.
		$this->assertThat( $data->content, $this->logicalNot( $this->stringContains( '<a href="https://example.org/<a' ) ) );
	}

	/**
	 * Test that DM mention extraction works with domain-qualified mentions.
	 */
	public function test_dm_mention_with_domain() {
		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_param( 'status', '@dmrecipient@example.org DM test' );
		$request->set_param( 'visibility', 'direct' );
		$response = $this->dispatch_authenticated( $request );

		// The local user should be found by login, ignoring the domain part.
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'direct', $data->visibility );
	}

	/**
	 * Test that stored DM content doesn't get double-linked on re-read.
	 *
	 * Stores a DM with a mention link, reads it back via the API, and verifies
	 * the content hasn't been modified with nested <a> tags.
	 */
	public function test_dm_content_stable_on_reread() {
		$dm_cpt = Mastodon_API::get_dm_cpt();
		$content = '<a rel="mention" class="u-url mention" href="https://example.org/@dmrecipient">@dmrecipient</a> DM test';

		$post_id = wp_insert_post(
			array(
				'post_author'  => $this->administrator,
				'post_content' => $content,
				'post_title'   => '',
				'post_status'  => 'ema_unread',
				'post_type'    => $dm_cpt,
			)
		);

		$request  = $this->api_request( 'GET', '/api/v1/statuses/' . $post_id );
		$response = $this->dispatch_authenticated( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		// The rendered content should not have nested <a> tags.
		$this->assertThat( $data->content, $this->logicalNot( $this->stringContains( 'href="https://example.org/<a' ) ) );
		$this->assertThat( $data->content, $this->logicalNot( $this->stringContains( '</a>">' ) ) );
	}
}
