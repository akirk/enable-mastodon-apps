<?php
/**
 * Class Test_Reblog_Mapping
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * A testcase for the reblog mapping cleanup.
 *
 * @package
 */
class ReblogMapping_Tests extends \WP_UnitTestCase {
	public function test_remap_reblog_id() {
		$post = $this->factory->post->create_and_get();
		$mapping_count = wp_count_posts( Mastodon_API::CPT );

		$remapped_id = Mastodon_API::remap_reblog_id( $post->ID );

		$this->assertNotEquals( $post->ID, $remapped_id );
		$this->assertEquals( $post->ID, get_post_meta( $remapped_id, 'mastodon_reblog_id', true ) );
		$this->assertEquals( $remapped_id, get_post_meta( $post->ID, 'mastodon_reblog_id', true ) );

		$mapping_post = get_post( $remapped_id );
		$this->assertEquals( Mastodon_API::CPT, $mapping_post->post_type );

		wp_cache_delete( _count_posts_cache_key( Mastodon_API::CPT ), 'counts' );
		$new_count = wp_count_posts( Mastodon_API::CPT );
		$this->assertEquals( 1 + $mapping_count->publish, $new_count->publish );
	}

	public function test_remap_reblog_id_is_idempotent() {
		$post = $this->factory->post->create_and_get();

		$remapped_id_1 = Mastodon_API::remap_reblog_id( $post->ID );
		$remapped_id_2 = Mastodon_API::remap_reblog_id( $post->ID );

		$this->assertEquals( $remapped_id_1, $remapped_id_2 );
	}

	public function test_cleanup_on_original_post_delete() {
		$post = $this->factory->post->create_and_get();
		$mapping_count = wp_count_posts( Mastodon_API::CPT );

		$remapped_id = Mastodon_API::remap_reblog_id( $post->ID );
		$this->assertNotNull( get_post( $remapped_id ) );

		wp_delete_post( $post->ID, true );

		$this->assertNull( get_post( $remapped_id ) );
		$this->assertEmpty( get_post_meta( $post->ID, 'mastodon_reblog_id', true ) );

		wp_cache_delete( _count_posts_cache_key( Mastodon_API::CPT ), 'counts' );
		$new_count = wp_count_posts( Mastodon_API::CPT );
		$this->assertEquals( $mapping_count->publish, $new_count->publish );
	}

	public function test_cleanup_on_original_post_trash() {
		$post = $this->factory->post->create_and_get();

		$remapped_id = Mastodon_API::remap_reblog_id( $post->ID );
		$this->assertNotNull( get_post( $remapped_id ) );

		wp_trash_post( $post->ID );

		$this->assertNull( get_post( $remapped_id ) );
		$this->assertEmpty( get_post_meta( $post->ID, 'mastodon_reblog_id', true ) );
	}

	public function test_cleanup_on_mapping_post_delete() {
		$post = $this->factory->post->create_and_get();

		$remapped_id = Mastodon_API::remap_reblog_id( $post->ID );

		wp_delete_post( $remapped_id, true );

		$this->assertEmpty( get_post_meta( $post->ID, 'mastodon_reblog_id', true ) );
	}

	public function test_cleanup_orphaned_mappings() {
		$post1 = $this->factory->post->create_and_get();
		$post2 = $this->factory->post->create_and_get();

		$remapped_id_1 = Mastodon_API::remap_reblog_id( $post1->ID );
		$remapped_id_2 = Mastodon_API::remap_reblog_id( $post2->ID );

		// Directly delete the original without triggering hooks, simulating pre-cleanup data.
		global $wpdb;
		$wpdb->delete( $wpdb->posts, array( 'ID' => $post1->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- test-only: simulate orphaned data by bypassing hooks
		clean_post_cache( $post1->ID );

		$deleted = Mastodon_API::cleanup_orphaned_reblog_mappings();

		$this->assertEquals( 1, $deleted );
		$this->assertNull( get_post( $remapped_id_1 ) );
		$this->assertNotNull( get_post( $remapped_id_2 ) );
	}
}
