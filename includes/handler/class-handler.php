<?php
/**
 * Generic handler.
 *
 * This contains the default Account handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Mastodon_API;
use Enable_Mastodon_Apps\Mastodon_App;

/**
 * This is the generic handler to provide needed helper functions.
 */
class Handler {
	protected function get_posts_query_args( $request ) {
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 10;
		}

		$args = array(
			'posts_per_page'   => $limit,
			'post_type'        => array( 'post', ),
			'suppress_filters' => false,
			'post_status'      => array( 'publish', 'private' ),
		);

		$pinned = $request->get_param( 'pinned' );
		if ( $pinned || 'true' === $pinned ) {
			$args['pinned'] = true;
			$args['post__in'] = get_option( 'sticky_posts' );
			if ( empty( $args['post__in'] ) ) {
				// No pinned posts, we need to find nothing.
				$args['post__in'] = array( -1 );
			}
		}

		$app = Mastodon_App::get_current_app();
		if ( $app ) {
			$args = $app->modify_wp_query_args( $args );
		} else {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( 'post-format-status' ),
				),
			);
		}

		$post_id = $request->get_param( 'post_id' );
		if ( $post_id ) {
			$args['p'] = $post_id;
		}

		return apply_filters( 'enable_mastodon_apps_get_posts_query_args', $args, $request );
	}

	protected function get_posts( $args, $min_id = null, $max_id = null ) {
		if ( $min_id ) {
			$min_filter_handler = function ( $where ) use ( $min_id ) {
				global $wpdb;
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $min_id );
			};
			add_filter( 'posts_where', $min_filter_handler );
		}

		if ( $max_id ) {
			$max_filter_handler = function ( $where ) use ( $max_id ) {
				global $wpdb;
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", $max_id );
			};
			add_filter( 'posts_where', $max_filter_handler );
		}

		$posts = get_posts( $args );
		if ( $min_id ) {
			remove_filter( 'posts_where', $min_filter_handler );
		}
		if ( $max_id ) {
			remove_filter( 'posts_where', $max_filter_handler );
		}

		$statuses = array();
		foreach ( $posts as $post ) {
			$status = $this->get_status_array( $post );
			if ( $status ) {
				$statuses[ $post->post_date ] = $status;
			}
		}

		if ( ! isset( $args['pinned'] ) || ! $args['pinned'] ) {
			// Comments cannot be pinned for now.
			$comments = get_comments(
				array(
					'meta_key'   => 'protocol',
					'meta_value' => 'activitypub',
				)
			);

			foreach ( $comments as $comment ) {
				$status = $this->get_comment_status_array( $comment );
				if ( $status ) {
					$statuses[ $comment->comment_date ] = $status;
				}
			}
		}
		ksort( $statuses );

		if ( $min_id ) {
			$min_id = strval( $min_id );
			$min_id_exists_in_statuses = false;
			foreach ( $statuses as $status ) {
				if ( $status['id'] === $min_id ) {
					$min_id_exists_in_statuses = true;
					break;
				}
			}
			if ( ! $min_id_exists_in_statuses ) {
				// We don't need to watch the min_id.
				$min_id = null;
			}
		}

		$ret = array();
		$c = $args['posts_per_page'];
		$next_max_id = false;
		foreach ( $statuses as $status ) {
			if ( false === $next_max_id ) {
				$next_max_id = $status['id'];
			}
			if ( $min_id ) {
				if ( $status['id'] !== $min_id ) {
					continue;
				}
				// We can now include results but need to skip this one.
				$min_id = null;
				continue;
			}
			if ( $max_id && strval( $max_id ) === $status['id'] ) {
				break;
			}
			if ( $c-- <= 0 ) {
				break;
			}
			array_unshift( $ret, $status );
		}

		if ( ! empty( $ret ) ) {
			if ( $next_max_id ) {
				header( 'Link: <' . add_query_arg( 'max_id', $next_max_id, home_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ) ) . '>; rel="next"', false );
			}
			header( 'Link: <' . add_query_arg( 'min_id', $ret[0]['id'], home_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ) ) . '>; rel="prev"', false );
		}

		return $ret;
	}

	protected function get_comment_status_array( \WP_Comment $comment ) {
		if ( ! $comment ) {
			return new \WP_Error( 'mastodon_' . __FUNCTION__, 'Record not found', array( 'status' => 404 ) );
		}

		$post = (object) array(
			'ID'           => $this->remap_comment_id( $comment->comment_ID ),
			'guid'         => $comment->guid . '#comment-' . $comment->comment_ID,
			'post_author'  => $comment->user_id,
			'post_content' => $comment->comment_content,
			'post_date'    => $comment->comment_date,
			'post_status'  => $comment->post_status,
			'post_type'    => $comment->post_type,
			'post_title'   => '',
		);

		return $this->get_status_array(
			$post,
			array(
				'in_reply_to_id' => $comment->comment_post_ID,
			)
		);
	}

	/**
	 * Strip empty whitespace
	 *
	 * @param      string $post_content  The post content.
	 *
	 * @return     string  The normalized content.
	 */
	protected function normalize_whitespace( $post_content ) {
		$post_content = preg_replace( '#<!-- /?wp:paragraph -->\s*<!-- /?wp:paragraph -->#', PHP_EOL, $post_content );
		$post_content = preg_replace( '#\n\s*\n+#', PHP_EOL, $post_content );

		return trim( $post_content );
	}

	protected function get_status_array( $post, $data = array() ) {
		$meta = get_post_meta( $post->ID, 'activitypub', true );
		$feed_url = get_post_meta( $post->ID, 'feed_url', true );

		$user_id = $post->post_author;
		if ( class_exists( '\Friends\User' ) && $post instanceof \WP_Post ) {
			$user = \Friends\User::get_post_author( $post );
			$user_id = $user->ID;
		} elseif ( isset( $meta['attributedTo']['id'] ) && $meta['attributedTo']['id'] ) {
			// It's an ActivityPub post, so the feed_url is the ActivityPub URL.
			if ( $feed_url ) {
				$user_id = $feed_url;
			} else {
				$user_id = $meta['attributedTo']['id'];
			}
		}

		if ( ! $user_id ) {
			return null;
		}
		$account_data = $this->get_friend_account_data( $user_id, $meta );
		if ( is_wp_error( $account_data ) ) {
			return null;
		}

		$reblogged = get_post_meta( $post->ID, 'reblogged' );
		$reblogged_by = array();
		if ( $reblogged ) {
			$reblog_user_ids = get_post_meta( $post->ID, 'reblogged_by' );
			if ( ! is_array( $reblog_user_ids ) ) {
				$reblog_user_ids = array();
			}
			$reblog_user_ids = array_map( 'intval', $reblog_user_ids );
			$reblogged_by = array_map(
				function ( $user_id ) {
					return $this->get_friend_account_data( $user_id );
				},
				$reblog_user_ids
			);
			$reblogged = in_array( get_current_user_id(), $reblog_user_ids, true );
		} else {
			$reblogged = false;
		}

		$data = array_merge(
			array(
				'id'                     => strval( $post->ID ),
				'created_at'             => mysql2date( 'Y-m-d\TH:i:s.000P', $post->post_date, false ),
				'in_reply_to_id'         => null,
				'in_reply_to_account_id' => null,
				'sensitive'              => false,
				'spoiler_text'           => '',
				'visibility'             => 'publish' === $post->post_status ? 'public' : 'unlisted',
				'language'               => null,
				'uri'                    => $post->guid,
				'url'                    => null,
				'replies_count'          => 0,
				'reblogs_count'          => 0,
				'favourites_count'       => 0,
				'edited_at'              => null,
				'favourited'             => false,
				'reblogged'              => $reblogged,
				'reblogged_by'           => $reblogged_by,
				'muted'                  => false,
				'bookmarked'             => false,
				'content'                => $this->normalize_whitespace( $post->post_title . PHP_EOL . $post->post_content ),
				'filtered'               => array(),
				'reblog'                 => null,
				'account'                => $account_data,
				'media_attachments'      => array(),
				'mentions'               => array(),
				'tags'                   => array(),
				'emojis'                 => array(),
				'pinned'                 => is_sticky( $post->ID ),
				'card'                   => null,
				'poll'                   => null,
			),
			$data
		);

		if ( ! $reblogged ) {
			unset( $data['reblogged_by'] );
		}

		if ( ! $data['pinned'] ) {
			unset( $data['pinned'] );
		}

		// get the attachments for the post.
		$attachments = get_attached_media( '', $post->ID );
		$p = strpos( $data['content'], '<!-- wp:image' );
		while ( false !== $p ) {
			$e = strpos( $data['content'], '<!-- /wp:image', $p );
			if ( ! $e ) {
				break;
			}
			$img = substr( $data['content'], $p, $e - $p + 19 );
			if ( preg_match( '#<img(?:\s+src="(?P<url>[^"]+)"|\s+width="(?P<width>\d+)"|\s+height="(?P<height>\d+)"|\s+class="(?P<class>[^"]+)|\s+.*="[^"]+)+"#i', $img, $img_tag ) ) {
				if ( ! empty( $img_tag['url'] ) ) {
					$url = $img_tag['url'];
					$media_id = crc32( $url );
					$img_meta = array();
					foreach ( $attachments as $attachment_id => $attachment ) {
						if (
						wp_get_attachment_url( $attachment_id ) === $url
						|| ( isset( $img_tag['class'] ) && preg_match( '#\bwp-image-' . $attachment_id . '\b#', $img_tag['class'] ) )

						) {
							$media_id = $attachment_id;
							$attachment_metadata = \wp_get_attachment_metadata( $attachment_id );
							$img_tag['width'] = $attachment_metadata['width'];
							$img_tag['height'] = $attachment_metadata['height'];
							unset( $attachments[ $attachment_id ] );

							$img_meta['original'] = array(
								'width'  => intval( $img_tag['width'] ),
								'height' => intval( $img_tag['height'] ),
								'size'   => $img_tag['width'] . 'x' . $img_tag['height'],
								'aspect' => $img_tag['width'] / max( 1, $img_tag['height'] ),
							);

							break;
						}
					}
					$data['media_attachments'][] = array(
						'id'                 => strval( $media_id ),
						'type'               => 'image',
						'url'                => $url,
						'preview_remote_url' => $url,
						'remote_url'         => $url,
						'preview_url'        => $url,
						'text_url'           => $url,
						'meta'               => array_merge(
							$img_meta,
							array(
								'description' => isset( $attachment ) && $attachment ? $attachment->post_excerpt : '',
							)
						),
					);
				}
			}
			$data['content'] = $this->normalize_whitespace( substr( $data['content'], 0, $p ) . substr( $data['content'], $e + 18 ) );
			$p = strpos( $data['content'], '<!-- wp:image' );
		}

		foreach ( $attachments as $attachment_id => $attachment ) {
			$url = wp_get_attachment_url( $attachment_id );
			$attachment_metadata = wp_get_attachment_metadata( $attachment_id );

			$type = 'image';
			if ( preg_match( '#^image/#', $attachment_metadata['mime-type'] ) || preg_match( '#\.(gif|png|jpe?g)$#i', $url ) ) {
				$type = 'image';
			} elseif ( preg_match( '#^audio/#', $attachment_metadata['mime-type'] ) || preg_match( '#\.(mp3|m4a|wav|aiff)$#i', $url ) ) {
				$type = 'audio';
			} elseif ( preg_match( '#^video/#', $attachment_metadata['mime-type'] ) || preg_match( '#\.(mov|mkv|mp4)$#i', $url ) ) {
				$type = 'video';
			}

			$data['media_attachments'][] = array(
				'id'          => strval( $attachment_id ),
				'type'        => $type,
				'url'         => $url,
				'preview_url' => $url,
				'text_url'    => $url,
				'meta'        => array(
					'original' => array(
						'width'  => intval( $attachment_metadata['width'] ),
						'height' => intval( $attachment_metadata['height'] ),
						'size'   => $attachment_metadata['width'] . 'x' . $attachment_metadata['height'],
						'aspect' => $attachment_metadata['width'] / max( 1, $attachment_metadata['height'] ),
					),
				),
				'description' => $attachment->post_excerpt,
			);
		}
		$author_name = $data['account']['display_name'];
		$override_author_name = get_post_meta( $post->ID, 'author', true );

		if ( isset( $meta['reblog'] ) && $meta['reblog'] && isset( $meta['attributedTo']['id'] ) ) {
			$data['reblog'] = $data;
			$data['reblog']['id'] = $this->remap_reblog_id( $data['reblog']['id'] ); // ensure that the id is different from the post as it might crash some clients (Ivory).
			$data['media_attachments'] = array();
			$data['mentions'] = array();
			$data['tags'] = array();
			unset( $data['pinned'] );
			$data['content'] = '';
			$data['reblog']['account'] = $this->get_friend_account_data( $this->get_acct( $meta['attributedTo']['id'] ), $meta );
			if ( ! $data['reblog']['account']['acct'] ) {
				$data['reblog'] = null;
			}
		} elseif ( $override_author_name && $author_name !== $override_author_name ) {
			$data['account']['display_name'] = $override_author_name;
		}

		$reactions = apply_filters( 'friends_get_user_reactions', array(), $post->ID );
		if ( ! empty( $reactions ) ) {
			$data['favourited'] = true;
		}

		return $data;
	}

	protected function convert_outbox_to_status( $outbox, $user_id ) {
		$items = array();
		foreach ( $outbox['orderedItems'] as $item ) {
			$status = $this->convert_activity_to_status( $item, $user_id );
			if ( $status ) {
				$items[] = $status;
			}
		}
		return $items;
	}

	public function convert_activity_to_status( $activity, $user_id ) {
		if ( ! isset( $activity['object']['type'] ) || 'Note' !== $activity['object']['type'] ) {
			return null;
		}

		$id_parts = explode( '/', $activity['object']['id'] );
		return array(
			'id'                     => array_pop( $id_parts ),
			'created_at'             => $activity['object']['published'],
			'in_reply_to_id'         => $activity['object']['inReplyTo'],
			'in_reply_to_account_id' => null,
			'sensitive'              => $activity['object']['sensitive'],
			'spoiler_text'           => '',
			'language'               => null,
			'uri'                    => $activity['object']['id'],
			'url'                    => $activity['object']['url'],
			'muted'                  => false,
			'replies_count'          => 0, // could be fetched.
			'reblogs_count'          => 0,
			'favourites_count'       => 0,
			'edited_at'              => null,
			'favourited'             => false,
			'reblogged'              => false,
			'muted'                  => false,
			'bookmarked'             => false,
			'content'                => $activity['object']['content'],
			'filtered'               => array(),
			'reblog'                 => null,
			'account'                => $this->get_friend_account_data( $user_id ),
			'media_attachments'      => array_map(
				function ( $attachment ) {
					return array(
						'id'          => crc32( $attachment['url'] ),
						'type'        => strtok( $attachment['mediaType'], '/' ),
						'url'         => $attachment['url'],
						'preview_url' => $attachment['url'],
						'text_url'    => $attachment['url'],
						'meta'        => array(
							'original' => array(
								'width'  => intval( $attachment['width'] ),
								'height' => intval( $attachment['height'] ),
								'size'   => $attachment['width'] . 'x' . $attachment['height'],
								'aspect' => $attachment['width'] / max( 1, $attachment['height'] ),
							),
						),
						'description' => ! empty( $attachment['description'] ) ? $attachment['description'] : '',
					);
				},
				$activity['object']['attachment']
			),
			'mentions'               => array_values(
				array_map(
					function ( $mention ) {
						return array(
							'id'       => $mention['href'],
							'username' => $mention['name'],
							'acct'     => $mention['name'],
							'url'      => $mention['href'],
						);
					},
					array_filter(
						$activity['object']['tag'],
						function ( $tag ) {
							if ( isset( $tag['type'] ) ) {
								return 'Mention' === $tag['type'];
							}
							return false;
						}
					)
				)
			),

			'tags'                   => array_values(
				array_map(
					function ( $tag ) {
						return array(
							'name' => $tag['name'],
							'url'  => $tag['href'],
						);
					},
					array_filter(
						$activity['object']['tag'],
						function ( $tag ) {
							if ( isset( $tag['type'] ) ) {
								return 'Hashtag' === $tag['type'];
							}
							return false;
						}
					)
				)
			),

			'emojis'                 => array(),
			'card'                   => null,
			'poll'                   => null,
		);
	}

	/**
	 * Check whether this is a valid URL
	 *
	 * @param string $url The URL to check.
	 * @return false|string URL or false on failure.
	 */
	public static function check_url( $url ) {
		$host = parse_url( $url, PHP_URL_HOST );

		$check_url = apply_filters( 'friends_host_is_valid', null, $host );
		if ( ! is_null( $check_url ) ) {
			return $check_url;
		}

		return wp_http_validate_url( $url );
	}

	public function get_json( $url, $transient_key, $fallback = null, $force_retrieval = false ) {
		$expiry_key = $transient_key . '_expiry';
		$transient_expiry = \get_transient( $expiry_key );
		$response = \get_transient( $transient_key );
		if ( $transient_expiry < time() ) {
			// Re-retrieve it later.
			wp_schedule_single_event( time(), 'enable_mastodon_apps_get_json', array( $url, $transient_key, $fallback, true ) );
		}
		if ( $response && ! $force_retrieval ) {
			if ( is_wp_error( $response ) ) {
				if ( $fallback && 'http_request_failed' !== $response->get_error_code() ) {
					return $fallback;
				}
			} else {
				return $response;
			}
		}

		$response = \wp_remote_get(
			$url,
			array(
				'headers'     => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 2,
				'timeout'     => 5,
			)
		);

		if ( \is_wp_error( $response ) ) {
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			if ( $fallback ) {
				return $fallback;
			}
			return $response;
		}

		if ( \is_wp_error( $response ) ) {
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			if ( $fallback ) {
				return $fallback;
			}
			return $response;
		}

		$body = \wp_remote_retrieve_body( $response );
		$body = \json_decode( $body, true );

		\set_transient( $transient_key, $body, YEAR_IN_SECONDS );
		\set_transient( $expiry_key, time() + HOUR_IN_SECONDS );

		return $body;
	}

	protected function update_account_data_with_meta( $data, $meta, $full_metadata = false ) {
		if ( ! $meta || is_wp_error( $meta ) || isset( $meta['error'] ) ) {
			if ( empty( $data['username'] ) || ! $data['username'] ) {
				$data['username'] = strtok( $data['id'], '@' );
			}
			if ( empty( $data['display_name'] ) || ! $data['display_name'] ) {
				$data['display_name'] = $data['username'];
			}

			if ( empty( $data['created_at'] ) || ! $data['created_at'] ) {
				$data['created_at'] = '1970-01-01T00:00:00Z';
			}
			if ( ! $data['last_status_at'] ) {
				$data['last_status_at'] = $data['created_at'];
			}

			return $data;
		}
		if ( $full_metadata ) {
			$followers = $this->get_json( $meta['followers'], 'followers-' . $data['acct'], array( 'totalItems' => 0 ) );
			$following = $this->get_json( $meta['following'], 'following-' . $data['acct'], array( 'totalItems' => 0 ) );
			$outbox = $this->get_json( $meta['outbox'], 'outbox-' . $data['acct'], array( 'totalItems' => 0 ) );
			$data['followers_count'] = intval( $followers['totalItems'] );
			$data['following_count'] = intval( $following['totalItems'] );
			$data['statuses_count'] = intval( $outbox['totalItems'] );
		}

		if ( empty( $data['username'] ) || ! $data['username'] ) {
			if ( isset( $meta['preferredUsername'] ) && $meta['preferredUsername'] ) {
				$data['username'] = $meta['preferredUsername'];
			} else {
				$data['username'] = strtok( $data['id'], '@' );
			}
		}
		if ( empty( $data['display_name'] ) || ! $data['display_name'] ) {
			if ( isset( $meta['name'] ) && $meta['name'] ) {
				$data['display_name'] = $meta['name'];
			} else {
				$data['display_name'] = $data['username'];
			}
		}
		if ( ! empty( $meta['summary'] ) ) {
			$data['note'] = (string) $meta['summary'];
		}
		if ( empty( $data['created_at'] ) || ! $data['created_at'] ) {
			if ( isset( $meta['published'] ) && $meta['published'] ) {
				$data['created_at'] = $meta['published'];
			} else {
				$data['created_at'] = '1970-01-01T00:00:00Z';
			}
		}
		if ( ! $data['last_status_at'] ) {
			$data['last_status_at'] = $data['created_at'];
		}
		$data['url'] = $meta['url'];
		if ( isset( $meta['icon'] ) ) {
			$data['avatar'] = $meta['icon']['url'];
			$data['avatar_static'] = $meta['icon']['url'];
		}
		if ( isset( $meta['image'] ) ) {
			$data['header'] = $meta['image']['url'];
			$data['header_static'] = $meta['image']['url'];
		}
		if ( isset( $meta['discoverable'] ) ) {
			$data['discoverable'] = $meta['discoverable'];
		}
		return $data;
	}

	protected function get_friend_account_data( $user_id, $meta = array(), $full_metadata = false ) {
		$external_user = apply_filters( 'mastodon_api_external_mentions_user', null );
		$is_external_mention = $external_user && strval( $external_user->ID ) === strval( $user_id );
		if ( $is_external_mention && isset( $meta['attributedTo']['id'] ) ) {
			$user_id = $meta['attributedTo']['id'];
		}

		$cache_key = 'account-' . $user_id;

		$url = false;
		if (
			preg_match( '#^https?://[^/]+/@[a-z0-9-]+$#i', $user_id )
			|| preg_match( '#^https?://[^/]+/(users|author)/[a-z0-9-]+$#i', $user_id )
		) {
			$url = $user_id;
		}

		if (
			preg_match( '/^@?' . Mastodon_API::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $user_id )
			|| $url
		) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'not-logged-in', 'Not logged in', array( 'status' => 401 ) );
			}
			$account = $this->get_acct( $user_id );

			if ( $account ) {
				$remote_user_id = get_term_by( 'name', $account, Mastodon_API::REMOTE_USER_TAXONOMY );
				if ( $remote_user_id ) {
					$remote_user_id = $remote_user_id->term_id;
				} else {
					$remote_user_id = wp_insert_term( $account, Mastodon_API::REMOTE_USER_TAXONOMY );
					if ( ! is_wp_error( $remote_user_id ) ) {
						$remote_user_id = $remote_user_id['term_id'];
					}
				}
			} elseif ( $user_id ) {
				$remote_user_id = get_term_by( 'name', $user_id, Mastodon_API::REMOTE_USER_TAXONOMY );
				if ( $remote_user_id ) {
					$remote_user_id = $remote_user_id->term_id;
				} else {
					$remote_user_id = wp_insert_term( $user_id, Mastodon_API::REMOTE_USER_TAXONOMY );
					if ( ! is_wp_error( $remote_user_id ) ) {
						$remote_user_id = $remote_user_id['term_id'];
					}
				}
			}

			if ( $remote_user_id ) {
				$cache_key = 'account-' . ( 1e10 + $remote_user_id );
			}
		}

		$ret = wp_cache_get( $cache_key, 'enable-mastodon-apps' );
		if ( false !== $ret ) {
			return $ret;
		}

		// Data URL of an 1x1px transparent png.
		$placeholder_image = 'https://files.mastodon.social/media_attachments/files/003/134/405/original/04060b07ddf7bb0b.png';
		// $placeholder_image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

		if (
			preg_match( '/^@?' . Mastodon_API::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $user_id )
			|| $url
		) {
			if ( ! $remote_user_id ) {
				return null;
			}

			if ( ! $url ) {
				if ( isset( $meta['attributedTo']['id'] ) ) {
					$url = $meta['attributedTo']['id'];
					$user_id = $meta['attributedTo']['id'];
				} else {
					$url = $this->get_activitypub_url( $user_id );
				}
			}
			if ( ! $url ) {
				$data = new \WP_Error( 'user-not-found', 'User not found.', array( 'status' => 404 ) );
				wp_cache_set( $cache_key, $data, 'enable-mastodon-apps' );
				return $data;
			}
			$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $url );

			$data = array(
				'id'              => strval( 1e10 + $remote_user_id ),
				'username'        => '',
				'acct'            => $account,
				'display_name'    => '',
				'locked'          => false,
				'bot'             => false,
				'discoverable'    => true,
				'group'           => false,
				'created_at'      => gmdate( 'Y-m-d\TH:i:s.000P' ),
				'note'            => '',
				'url'             => '',
				'avatar'          => $placeholder_image,
				'avatar_static'   => $placeholder_image,
				'header'          => $placeholder_image,
				'header_static'   => $placeholder_image,
				'followers_count' => 0,
				'following_count' => 0,
				'statuses_count'  => 0,
				'last_status_at'  => gmdate( 'Y-m-d' ),
				'emojis'          => array(),
				'fields'          => array(),
			);

			$data = $this->update_account_data_with_meta( $data, $meta, $full_metadata );

			wp_cache_set( $cache_key, $data, 'enable-mastodon-apps' );

			return $data;
		}

		$user = false;
		if ( class_exists( '\Friends\User' ) ) {
			$user = \Friends\User::get_user_by_id( $user_id );

			if ( $user instanceof \Friends\Subscription ) {
				$remote_user_id = get_term_by( 'name', $user->ID, Mastodon_API::REMOTE_USER_TAXONOMY );
				if ( $remote_user_id ) {
					$remote_user_id = $remote_user_id->term_id;
				} else {
					$remote_user_id = wp_insert_term( $user->ID, Mastodon_API::REMOTE_USER_TAXONOMY );
					if ( ! is_wp_error( $remote_user_id ) ) {
						$remote_user_id = $remote_user_id['term_id'];
					}
				}
				$user->ID = 1e10 + $remote_user_id;
			}
		}

		if ( ! $user || is_wp_error( $user ) ) {
			$user = new \WP_User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				$data = new \WP_Error( 'user-not-found', 'User not found.', array( 'status' => 404 ) );
				wp_cache_set( $cache_key, $data, 'enable-mastodon-apps' );
				return $data;
			}
		}
		$followers_count = 0;
		$following_count = 0;
		$avatar = get_avatar_url( $user->ID );
		if ( $user instanceof \Friends\User ) {
			$posts = $user->get_post_count_by_post_format();
		} else {
			// Get post count for the post format status for the user.
			$posts = array(
				'status' => count_user_posts( $user->ID, 'post', true ),
			);
		}
		if ( get_current_user_id() === $user->ID ) {
			if ( class_exists( '\Activitypub\Peer\Followers', false ) ) {
				$followers_count = count( \Activitypub\Peer\Followers::get_followers( $user->ID ) );
			}
			if ( class_exists( '\Activitypub\Collection\Followers', false ) ) {
				$followers_count = count( \Activitypub\Collection\Followers::get_followers( $user->ID ) );
			}
		}

		$data = array(
			'id'              => strval( $user->ID ),
			'username'        => $user->user_login,
			'display_name'    => $user->display_name,
			'avatar'          => $avatar,
			'avatar_static'   => $avatar,
			'header'          => $placeholder_image,
			'header_static'   => $placeholder_image,
			'acct'            => $user->user_login,
			'note'            => '',
			'created_at'      => mysql2date( 'Y-m-d\TH:i:s.000P', $user->user_registered, false ),
			'followers_count' => $followers_count,
			'following_count' => $following_count,
			'statuses_count'  => isset( $posts['status'] ) ? intval( $posts['status'] ) : 0,
			'last_status_at'  => mysql2date( 'Y-m-d\TH:i:s.000P', $user->user_registered, false ),
			'fields'          => array(),
			'locked'          => false,
			'emojis'          => array(),
			'url'             => get_author_posts_url( $user->ID ),
			'source'          => array(
				'privacy'   => apply_filters( 'mastodon_api_account_visibility', 'public', $user ),
				'sensitive' => false,
				'language'  => self::get_mastodon_language( get_user_locale( $user->ID ) ),
				'note'      => '',
				'fields'    => array(),
			),
			'bot'             => false,
			'discoverable'    => true,
		);

		if ( isset( $meta['attributedTo']['id'] ) && $is_external_mention ) {
			$data['acct'] = $this->get_acct( $meta['attributedTo']['id'] );
			$data['id'] = $data['acct'];
			$data['username'] = strtok( $data['acct'], '@' );

			$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $meta['attributedTo']['id'] );
			$data = $this->update_account_data_with_meta( $data, $meta, $full_metadata );
		} else {
			$acct = $this->get_user_acct( $user );
			if ( $acct ) {
				$data['acct'] = $acct;
			}

			foreach ( apply_filters( 'friends_get_user_feeds', array(), $user ) as $feed ) {
				$meta = apply_filters( 'friends_get_feed_metadata', array(), $feed );
				if ( $meta && ! is_wp_error( $meta ) && ! isset( $meta['error'] ) ) {
					$data['acct'] = $this->get_acct( $meta['id'] );
					$data = $this->update_account_data_with_meta( $data, $meta, $full_metadata );
					break;
				}
			}
		}

		wp_cache_set( $cache_key, $data, 'enable-mastodon-apps' );
		return $data;
	}

	public static function get_mastodon_language( $lang ) {
		if ( false === strpos( $lang, '_' ) ) {
			return $lang . '_' . strtoupper( $lang );
		}
		return $lang;
	}

	public function get_user_acct( $user ) {
		return strtok( $this->get_acct( get_author_posts_url( $user->ID ) ), '@' );
	}

	public function get_acct( $id_or_url ) {
		if ( is_wp_error( $id_or_url ) ) {
			return '';
		}
		$webfinger = $this->webfinger( $id_or_url );
		if ( is_wp_error( $webfinger ) || ! isset( $webfinger['subject'] ) ) {
			return '';
		}
		if ( substr( $webfinger['subject'], 0, 5 ) === 'acct:' ) {
			return substr( $webfinger['subject'], 5 );
		}
		return $webfinger['subject'];
	}

	public function get_activitypub_url( $id_or_url ) {
		$webfinger = $this->webfinger( $id_or_url );
		if ( is_wp_error( $webfinger ) || empty( $webfinger['links'] ) ) {
			return false;
		}
		foreach ( $webfinger['links'] as $link ) {
			if ( ! isset( $link['rel'] ) ) {
				continue;
			}
			if ( 'self' === $link['rel'] && 'application/activity+json' === $link['type'] && in_array( $link['href'], $webfinger['aliases'] ) ) {
				return $link['href'];
			}
		}

		if ( ! empty( $webfinger['aliases'] ) ) {
			return $webfinger['aliases'][0];
		}

		return false;
	}

	protected function webfinger( $id_or_url ) {
		if ( strpos( $id_or_url, 'acct:' ) === 0 ) {
			$id_or_url = substr( $id_or_url, 5 );
		}

		$body = apply_filters( 'mastodon_api_webfinger', null, $id_or_url );
		if ( $body ) {
			return $body;
		}

		$id = $id_or_url;
		if ( preg_match( '#^https://([^/]+)/(?:@|users/|author/)([^/]+)/?$#', $id_or_url, $m ) ) {
			$id = $m[2] . '@' . $m[1];
			$host = $m[1];
		} elseif ( false !== strpos( $id_or_url, '@' ) ) {
			$parts = explode( '@', ltrim( $id_or_url, '@' ) );
			$host = $parts[1];
		} else {
			return null;
		}

		$transient_key = 'mastodon_api_webfinger_' . md5( $id_or_url );

		$body = \get_transient( $transient_key );
		if ( $body ) {
			if ( is_wp_error( $body ) ) {
				return $id;
			}
			return $body;
		}

		$url = \add_query_arg( 'resource', 'acct:' . ltrim( $id, '@' ), 'https://' . $host . '/.well-known/webfinger' );
		if ( ! self::check_url( $url ) ) {
			$response = new \WP_Error( 'invalid_webfinger_url', null, $url );
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $response;
		}

		$body = $this->get_json( $url, $transient_key );

		if ( \is_wp_error( $body ) ) {
			$body = new \WP_Error( 'webfinger_url_not_accessible', null, $url );
			\set_transient( $transient_key, $body, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $body;
		}

		\set_transient( $transient_key, $body, WEEK_IN_SECONDS );
		return $body;
	}

	protected function remap_reblog_id( $post_id ) {
		$remapped_post_id = get_post_meta( $post_id, 'mastodon_reblog_id', true );
		if ( ! $remapped_post_id ) {
			$remapped_post_id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_author' => 0,
					'post_status' => 'publish',
					'post_title'  => 'Reblog of ' . $post_id,
					'meta_input'  => array(
						'mastodon_reblog_id' => $post_id,
					),
				)
			);

			update_post_meta( $post_id, 'mastodon_reblog_id', $remapped_post_id );
		}
		return $remapped_post_id;
	}

	protected function maybe_get_remapped_reblog_id( $remapped_post_id ) {
		$post_id = get_post_meta( $remapped_post_id, 'mastodon_reblog_id', true );
		if ( $post_id ) {
			return $post_id;
		}
		return $remapped_post_id;
	}

	protected function remap_comment_id( $comment_id ) {
		$remapped_comment_id = get_comment_meta( $comment_id, 'mastodon_comment_id', true );
		if ( ! $remapped_comment_id ) {
			$remapped_comment_id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_author' => 0,
					'post_status' => 'publish',
					'post_title'  => 'Comment ' . $comment_id,
					'meta_input'  => array(
						'mastodon_comment_id' => $comment_id,
					),
				)
			);

			update_comment_meta( $comment_id, 'mastodon_comment_id', $remapped_comment_id );
		}
		return $remapped_comment_id;
	}

	protected function get_remapped_comment_id( $remapped_comment_id ) {
		$comment_id = get_post_meta( $remapped_comment_id, 'mastodon_comment_id', true );
		if ( $comment_id ) {
			return $comment_id;
		}
		return false;
	}
}
