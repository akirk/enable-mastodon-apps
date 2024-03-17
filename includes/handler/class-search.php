<?php
/**
 * Search handler.
 *
 * This contains the default Search handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Handler;
use Enable_Mastodon_Apps\Mastodon_API;
use Enable_Mastodon_Apps\Entity\Search as Search_Entity;

/**
 * This is the class that implements the default handler for all Search endpoints.
 *
 * @since 0.7.0
 *
 * @package Enable_Mastodon_Apps
 */
class Search extends Handler {
	public function __construct() {
		$this->register_hooks();
	}

	public function register_hooks() {
		add_filter( 'mastodon_api_search', array( $this, 'search' ), 20, 2 );
	}

	/**
	 * Handle action calls to search.
	 *
	 * @param mixed  $search The search result as object.
	 * @param object $request Request object from WP.
	 *
	 * @return array|array[]|Search_Entity
	 */
	public function search( $search, object $request ) {
		$type = $request->get_param( 'type' );
		$ret = array(
			'accounts' => array(),
			'statuses' => array(),
			'hashtags' => array(),
		);

		$q = $request->get_param( 'q' );
		$query_is_url = parse_url( $q );
		if ( $query_is_url ) {
			if ( 'true' !== $request->get_param( 'resolve' ) || ! is_user_logged_in() ) {
				return $ret;
			}
			$status = $this->get_json( $q, 'status-' . md5( $q ) );
			$ret['statuses'][] = $this->convert_activity_to_status( array( 'object' => $status ), $status['attributedTo'] );
		} else {
			if ( ! $type || 'accounts' === $type ) {
				if ( preg_match( '/^@?' . Mastodon_API::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $q ) && ! $request->get_param( 'offset' ) ) {
					$ret['accounts'][] = $this->get_friend_account_data( $q, array(), true );
				}
				$query = new \WP_User_Query(
					array(
						'search'         => '*' . $q . '*',
						'search_columns' => array(
							'user_login',
							'user_nicename',
							'user_email',
							'user_url',
							'display_name',
						),
						'offset'         => $request->get_param( 'offset' ),
						'number'         => $request->get_param( 'limit' ),
					)
				);
				$users = $query->get_results();
				foreach ( $users as $user ) {
					$ret['accounts'][] = $this->get_friend_account_data( $user->ID );
				}
			}
			if ( ! $type || 'statuses' === $type ) {
				$args = $this->get_posts_query_args( $request );
				if ( empty( $args ) ) {
					return array();
				}
				$args = apply_filters( 'mastodon_api_timelines_args', $args, $request );
				$valid_url = wp_parse_url( $q );
				if ( $valid_url && isset( $valid_url['host'] ) ) {
					if ( ! $request->get_param( 'offset' ) ) {
						$url = $q;
						$json = $this->get_json( $url, crc32( $url ) );
						if ( ! is_wp_error( $json ) && isset( $json['id'], $json['attributedTo'] ) ) {
							$user_id = $this->get_acct( $json['attributedTo'] );
							$ret['statuses'][] = $this->convert_activity_to_status(
								array(
									'id'     => $json['id'] . '#create-activity',
									'object' => $json,

								),
								$user_id
							);
						}
					}
				} elseif ( is_user_logged_in() ) {
					$args['s'] = $q;
					$args['offset'] = $request->get_param( 'offset' );
					$args['posts_per_page'] = $request->get_param( 'limit' );
					$ret['statuses'] = array_merge( $ret['statuses'], $this->get_posts( $args ) );
				}
			}
		}
		return $ret;
	}
}
