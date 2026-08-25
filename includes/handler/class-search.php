<?php
/**
 * Search handler.
 *
 * This contains the default Search handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Handler\Handler;
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
		add_filter( 'mastodon_api_search', array( $this, 'search' ), 10, 2 );
		add_filter( 'mastodon_api_search', array( $this, 'api_search_status_ensure_numeric_id' ), 100 );
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
		$ret  = array(
			'accounts' => array(),
			'statuses' => array(),
			'hashtags' => array(),
		);

		$q = trim( $request->get_param( 'q' ) );
		$q = esc_attr( $q );
		// Only start searching when there are 3 or more characters.
		if ( strlen( $q ) < 3 ) {
			return $ret;
		}

		if ( ! $type || 'accounts' === $type ) {
			if ( preg_match( '/^@?' . Mastodon_API::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $q ) && ! $request->get_param( 'offset' ) ) {
				$ret['accounts'][] = apply_filters( 'mastodon_api_account', null, $q, null, null );
			}
			$query = new \WP_User_Query(
				array(
					'search'         => "*$q*",
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
				$ret['accounts'][] = apply_filters( 'mastodon_api_account', null, $user->ID, null, null );
			}
			$ret['accounts'] = array_filter( $ret['accounts'] );
		}
		if ( ! $type || 'statuses' === $type ) {
			$args = $this->get_posts_query_args( array(), $request );
			if ( empty( $args ) ) {
				return array();
			}
			$args      = apply_filters( 'mastodon_api_timelines_args', $args, $request );
			$valid_url = wp_parse_url( $q );
			if ( $valid_url && isset( $valid_url['host'] ) ) {
				if ( ! $request->get_param( 'offset' ) ) {
					$url = $q;
					// TODO allow lookup by URL.
				}
			} elseif ( is_user_logged_in() ) {
				$date_args = self::get_date_query_args( $q );
				if ( $date_args ) {
					$args = array_merge( $args, $date_args );
				} else {
					$args['s'] = $q;
				}
				$args['offset']         = $request->get_param( 'offset' );
				$args['posts_per_page'] = $request->get_param( 'limit' );
				$ret['statuses']        = array_merge( $ret['statuses'], $this->get_posts( $args )->get_data() );
			}
		}
		if ( ! $type || 'hashtags' === $type ) {
			$q_param    = $request->get_param( 'q' );
			$categories = get_categories(
				array(
					'orderby'    => 'name',
					'hide_empty' => false,
					'search'     => $q_param,
				)
			);
			foreach ( $categories as $category ) {
				$ret['hashtags'][] = array(
					'name'    => $category->name,
					'url'     => get_category_link( $category ),
					'history' => array(),
				);
			}
			$tags = get_tags(
				array(
					'orderby'    => 'name',
					'hide_empty' => false,
					'search'     => $q_param,
				)
			);
			foreach ( $tags as $tag ) {
				$ret['hashtags'][] = array(
					'name'    => $tag->name,
					'url'     => get_tag_link( $tag ),
					'history' => array(),
				);
			}
		}

		$ret = array_merge( $search, $ret );
		return $ret;
	}


	/**
	 * Turn a date-like query into WP_Query date arguments.
	 *
	 * Searching for "2016", "2016-05" or "2016-05-17" returns the posts from that
	 * year, month or day instead of the posts mentioning that string.
	 *
	 * @param string $q The search query.
	 *
	 * @return array WP_Query arguments, or an empty array if the query is not a date.
	 */
	public static function get_date_query_args( $q ) {
		if ( ! preg_match( '/^(\d{4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?$/', trim( $q ), $m ) ) {
			return array();
		}
		$year  = intval( $m[1] );
		$month = isset( $m[2] ) ? intval( $m[2] ) : 0;
		$day   = isset( $m[3] ) ? intval( $m[3] ) : 0;

		if ( $year < 1970 || $year > intval( gmdate( 'Y' ) ) + 1 ) {
			return array();
		}
		if ( $month && ( $month < 1 || $month > 12 ) ) {
			return array();
		}
		if ( $day && ! checkdate( $month, $day, $year ) ) {
			return array();
		}

		$date_args = array( 'year' => $year );
		if ( $month ) {
			$date_args['monthnum'] = $month;
		}
		if ( $day ) {
			$date_args['day'] = $day;
		}
		return $date_args;
	}

	public function api_search_status_ensure_numeric_id( $ret ) {
		if ( ! empty( $ret['statuses'] ) && is_array( $ret['statuses'] ) ) {
			foreach ( $ret['statuses'] as $key => $status ) {
				$ret['statuses'][ $key ] = $this->api_status_ensure_numeric_id( $status );
			}
		}
		return $ret;
	}
}
