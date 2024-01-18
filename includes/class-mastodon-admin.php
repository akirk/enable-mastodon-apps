<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * The admin-specific functionality of the plugin.
 */
class Mastodon_Admin {
	private $oauth;

	public function __construct( Mastodon_OAuth $oauth ) {
		$this->oauth = $oauth;
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	public function admin_menu() {
		add_options_page(
			__( 'Mastodon Apps', 'enable-mastodon-apps' ),
			__( 'Mastodon Apps', 'enable-mastodon-apps' ),
			'edit_private_posts',
			'enable-mastodon-apps',
			array( $this, 'admin_page' )
		);
		add_action( 'load-settings_page_enable-mastodon-apps', array( $this, 'process_admin' ) );
	}

	public function process_admin() {
		if ( ! current_user_can( 'edit_private_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the settings.', 'enable-mastodon-apps' ) );
		}

		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'enable-mastodon-apps' ) ) {
			return;
		}

		if ( isset( $_POST['delete-code'] ) ) {
			$deleted = $this->oauth->get_code_storage()->expireAuthorizationCode( $_POST['delete-code'] );
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-codes',
				sprintf(
					// translators: %d: number of deleted codes.
					_n( 'Deleted %d authorization code.', 'Deleted %d authorization codes.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				)
			);
			return;
		}

		if ( isset( $_POST['delete-token'] ) ) {
			$deleted = $this->oauth->get_token_storage()->unsetAccessToken( $_POST['delete-token'] );
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-tokens',
				sprintf(
					// translators: %d: number of deleted tokens.
					_n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				)
			);
			return;
		}

		if ( isset( $_POST['delete-app'] ) ) {
			$deleted = Mastodon_App::get_by_client_id( $_POST['delete-app'] )->delete();
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-apps',
				sprintf(
					// translators: %d: number of deleted apps.
					_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				)
			);
			return;
		}

		if ( isset( $_POST['clear-app-logs'] ) ) {
			$deleted = Mastodon_App::get_by_client_id( $_POST['clear-app-logs'] )->delete_last_requests();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs were cleared.', 'enable-mastodon-apps' )
				);
			} else {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs could not be cleared.', 'enable-mastodon-apps' )
				);
			}
			return;
		}
		if ( isset( $_POST['clear-all-app-logs'] ) ) {
			$total_deleted = 0;
			foreach ( Mastodon_App::get_all() as $app ) {
				$deleted = $app->delete_last_requests();
				if ( $deleted ) {
					$total_deleted += 1;
				}
			}
			if ( $total_deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-all-app-logs',
					sprintf(
						// translators: %d: number of deleted apps.
						_n( '%d app logs were cleared.', '%d app logs were cleared.', $total_deleted, 'enable-mastodon-apps' ),
						$total_deleted
					)
				);
			} else {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs could not be cleared.', 'enable-mastodon-apps' )
				);
			}
			return;
		}

		if ( isset( $_POST['delete-outdated'] ) ) {
			$deleted = OAuth2\AccessTokenStorage::cleanupOldTokens();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-tokens',
					sprintf(
					// translators: %d: number of deleted tokens.
						_n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted, 'enable-mastodon-apps' ),
						$deleted
					)
				);
			}

			$deleted = OAuth2\AuthorizationCodeStorage::cleanupOldCodes();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-codes',
					sprintf(
					// translators: %d: number of deleted codes.
						_n( 'Deleted %d authorization code.', 'Deleted %d authorization codes.', $deleted, 'enable-mastodon-apps' ),
						$deleted
					)
				);
			}

			$deleted = Mastodon_App::delete_outdated();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-apps',
					sprintf(
					// translators: %d: number of deleted apps.
						_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted, 'enable-mastodon-apps' ),
						$deleted
					)
				);
			}
			return;
		}

		if ( isset( $_POST['delete-never-used'] ) ) {
			$deleted = 0;
			foreach ( Mastodon_App::get_all() as $app ) {
				if ( ! $app->get_last_used() ) {
					$deleted += 1;
					$app->delete();
				}
			}

			add_settings_error(
				'enable-mastodon-apps',
				'deleted-apps',
				sprintf(
					// translators: %d: number of deleted apps.
					_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted, 'enable-mastodon-apps' ),
					$deleted
				)
			);

			$deleted = 0;
			foreach ( OAuth2\AccessTokenStorage::getAll() as $token => $data ) {
				if ( empty( $data['last_used'] ) ) {
					if ( $this->oauth->get_token_storage()->unsetAccessToken( $token ) ) {
						$deleted += 1;
					}
				}
			}

			add_settings_error(
				'enable-mastodon-apps',
				'deleted-tokens',
				sprintf(
					// translators: %d: number of deleted tokens.
					_n( 'Deleted %d token.', 'Deleted %d tokens.', $deleted, 'enable-mastodon-apps' ),
					$deleted
				)
			);
			return;
		}

		if ( isset( $_POST['delete-apps-without-tokens'] ) ) {
			$app_tokens = array();
			foreach ( OAuth2\AccessTokenStorage::getAll() as $token => $data ) {
				if ( ! isset( $app_tokens[ $data['client_id'] ] ) ) {
					$app_tokens[ $data['client_id'] ] = array();
				}
				$app_tokens[ $data['client_id'] ][] = $token;
			}
			$deleted = 0;
			foreach ( Mastodon_App::get_all() as $app ) {
				if ( empty( $app_tokens[ $app->get_client_id() ] ) ) {
					$deleted += 1;
					$app->delete();
				}
			}

			add_settings_error(
				'enable-mastodon-apps',
				'deleted-apps',
				sprintf(
					// translators: %d: number of deleted apps.
					_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted, 'enable-mastodon-apps' ),
					$deleted
				)
			);
			return;
		}

		if ( isset( $_POST['mastodon_api_enable_logins'] ) ) {
			delete_option( 'mastodon_api_disable_logins' );
		} else {
			update_option( 'mastodon_api_disable_logins', true );
		}

		if ( isset( $_POST['mastodon_api_auto_app_reregister'] ) ) {
			update_option( 'mastodon_api_auto_app_reregister', true );
		} else {
			delete_option( 'mastodon_api_auto_app_reregister' );
		}

		if ( isset( $_POST['mastodon_api_reply_as_comment'] ) ) {
			update_option( 'mastodon_api_reply_as_comment', true );
		} else {
			delete_option( 'mastodon_api_reply_as_comment' );
		}

		if ( isset( $_POST['mastodon_api_debug_mode'] ) ) {
			update_option( 'mastodon_api_debug_mode', time() + 5 * MINUTE_IN_SECONDS );
		} else {
			delete_option( 'mastodon_api_debug_mode' );
		}

		if ( isset( $_POST['mastodon_api_default_post_formats'] ) && is_array( $_POST['mastodon_api_default_post_formats'] ) ) {
			$default_post_formats = array_filter(
				$_POST['mastodon_api_default_post_formats'],
				function ( $post_format ) {
					if ( ! in_array( $post_format, get_post_format_slugs(), true ) ) {
						return false;
					}
					return true;
				}
			);

			if ( ! empty( $default_post_formats ) ) {
				update_option( 'mastodon_api_default_post_formats', $default_post_formats );
			} else {
				delete_option( 'mastodon_api_default_post_formats' );
			}
		}

		if ( isset( $_POST['app_post_formats'] ) && is_array( $_POST['app_post_formats'] ) ) {
			foreach ( $_POST['app_post_formats'] as $client_id => $post_formats ) {
				$post_formats = array_filter(
					$post_formats,
					function ( $post_format ) {
						if ( ! in_array( $post_format, get_post_format_slugs(), true ) ) {
							return false;
						}
						return true;
					}
				);

				$app = Mastodon_App::get_by_client_id( $client_id );
				$app->set_post_formats( $post_formats );
			}
		}
	}

	private function post_format_select( $name, $selected = array() ) {
		?>
		<select name="<?php echo esc_attr( $name ); ?>[]" id="<?php echo esc_attr( $name ); ?>" size="10" multiple>
			<?php
			foreach ( get_post_format_slugs() as $format ) {
				?>
				<option value="<?php echo esc_attr( $format ); ?>" <?php selected( in_array( $format, $selected, true ) ); ?>><?php echo esc_html( $format ); ?></option>
				<?php
			}
			?>
		</select>
		<?php
	}

	public function admin_page() {
		$codes = OAuth2\AuthorizationCodeStorage::getAll();
		$tokens = OAuth2\AccessTokenStorage::getAll();
		$apps = Mastodon_App::get_all();
		$rest_nonce = wp_create_nonce( 'wp_rest' );

		wp_enqueue_script( 'plugin-install' );
		add_thickbox();
		wp_enqueue_script( 'updates' );

		$plugins = get_plugins();
		$activitypub_installed = isset( $plugins['activitypub/activitypub.php'] );
		$friends_installed = isset( $plugins['friends/friends.php'] );

		function output_request_log( $request, $rest_nonce ) {
			$date = \DateTimeImmutable::createFromFormat( 'U.u', $request['timestamp'] );
			$url = add_query_arg(
				array(
					'_wpnonce' => $rest_nonce,
					'_pretty'  => 1,
				),
				$request['path']
			);
			$meta = array_diff_key( $request, array_flip( array( 'timestamp', 'path', 'method' ) ) );
			if ( empty( $meta ) ) {
				echo '<div style="margin-left: 1.5em">';
			} else {
				echo '<details><summary>';
			}
			if ( isset( $request['app'] ) ) {
				echo esc_html( $request['app']->get_client_name() );
			}
			?>
			[<?php echo esc_html( $date->format( 'Y-m-d H:i:s.v' ) ); ?>]
			<?php echo esc_html( ! empty( $request['status'] ) ? $request['status'] : 200 ); ?>
			<?php echo esc_html( $request['method'] ); ?>
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $request['path'] ); ?></a>
			<?php
			if ( ! empty( $meta ) ) {
				echo '</summary>';
				echo '<pre style="padding-left: 1em; border-left: 3px solid #999; margin: 0">';
				foreach ( $meta as $key => $value ) {
					echo esc_html( $key ), ' ', esc_html( var_export( $value, true ) ), '<br/>';
				}
				echo '</pre>';
				echo '</details>';
			} else {
				echo '</div>';
			}
		}
		function td_timestamp( $timestamp, $strikethrough_past = false ) {
			?>
			<td>
				<abbr title="<?php echo esc_attr( is_numeric( $timestamp ) ? gmdate( 'r', $timestamp ) : $timestamp ); ?>">
					<?php
					if ( ! $timestamp ) {
						echo esc_html( _x( 'Never', 'Code was never used', 'enable-mastodon-apps' ) );
					} elseif ( $timestamp > time() ) {
						echo esc_html(
							sprintf(
								// translators: %s is a relative time.
								__( 'in %s', 'enable-mastodon-apps' ),
								human_time_diff( $timestamp )
							)
						);
					} elseif ( $strikethrough_past ) {
						echo '<strike>';
						echo esc_html(
							sprintf(
								// translators: %s: Human-readable time difference.
								__( '%s ago' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
								human_time_diff( $timestamp )
							)
						);
						echo '</strike>';
					} else {
						echo esc_html(
							sprintf(
								// translators: %s: Human-readable time difference.
								__( '%s ago' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
								human_time_diff( $timestamp )
							)
						);
					}
					?>
				</abbr>
			</td>
			<?php
		}
		?>
		<div class="wrap">
		<h1><?php esc_html_e( 'Mastodon Apps', 'enable-mastodon-apps' ); ?></h1>

		<h2><?php esc_html_e( 'Recommended Plugins', 'enable-mastodon-apps' ); ?></h2>
		<p><span><?php esc_html_e( 'On its own, this plugin allows you to publish status posts and show your own posts in the apps\' timelines.', 'enable-mastodon-apps' ); ?> <span><?php esc_html_e( 'To make the plugin more useful, you can install the following plugins:', 'enable-mastodon-apps' ); ?></span></p>

		<?php if ( $activitypub_installed ) : ?>
			<details><summary><?php esc_html_e( 'The ActivityPub plugin is already installed.', 'enable-mastodon-apps' ); ?></summary>
		<?php endif; ?>
		<p><?php esc_html_e( 'The ActivityPub plugin connects your blog to the fediverse: Other people can follow your WordPress using a Mastodon account. It also provides functionality for communicating with the ActivityPub protocol to other plugins.', 'enable-mastodon-apps' ); ?></p>
		<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=activitypub&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Activitypub Plugin', 'enable-mastodon-apps' ); ?></a></p>
		<?php if ( $activitypub_installed ) : ?>
			</details>
		<?php endif; ?>

		<?php if ( $friends_installed ) : ?>
			<details><summary><?php esc_html_e( 'The Friends plugin is already installed.', 'enable-mastodon-apps' ); ?></summary>
		<?php endif; ?>
		<p><span><?php esc_html_e( 'The Friends plugin allows you to follow other blogs or, if the ActivityPub plugin is also installed, Mastodon accounts.', 'enable-mastodon-apps' ); ?></span> <span><?php esc_html_e( 'You can then see the posts of people you follow inside your Mastodon compatible app.', 'enable-mastodon-apps' ); ?></span></p>

		<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=friends&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Friends Plugin', 'enable-mastodon-apps' ); ?></a></p>
		<?php if ( $friends_installed ) : ?>
			</details>
		<?php endif; ?>

		</p>
		<style type="text/css">
			details.tt {
				font-family: monospace;
				word-wrap: break-word;
			}
			summary {
				cursor: pointer;
			}
			td:last-child {
				white-space: nowrap;
			}
		</style>
		<form method="post">
			<?php wp_nonce_field( 'enable-mastodon-apps' ); ?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row" rowspan="2"><?php esc_html_e( 'Enable Logins', 'enable-mastodon-apps' ); ?></th>
						<td>
							<fieldset>
								<label for="mastodon_api_enable_logins">
									<input name="mastodon_api_enable_logins" type="checkbox" id="mastodon_api_enable_logins" value="1" <?php checked( '1', ! get_option( 'mastodon_api_disable_logins' ) ); ?> />
									<span><?php esc_html_e( 'Allow new and existing apps to sign in.', 'enable-mastodon-apps' ); ?></span>
								</label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'New apps can register on their own, so the list might grow if you keep this enabled.', 'enable-mastodon-apps' ); ?></p>
						</td>
					</tr>
					<tr>
						<td>
							<fieldset>
								<label for="mastodon_api_auto_app_reregister">
									<input name="mastodon_api_auto_app_reregister" type="checkbox" id="mastodon_api_auto_app_reregister" value="1" <?php checked( '1', get_option( 'mastodon_api_auto_app_reregister' ) ); ?> />
									<span><?php esc_html_e( 'Implicitly re-register the next unknown client.', 'enable-mastodon-apps' ); ?></span>
								</label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'When you (accidentally) delete an app, this allows to use the app when it tries to authorize again.', 'enable-mastodon-apps' ); ?><br/><?php esc_html_e( 'This setting will turn itself off again as soon as an app has done so.', 'enable-mastodon-apps' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Replies', 'enable-mastodon-apps' ); ?></th>
						<td>
							<fieldset>
								<label for="mastodon_api_reply_as_comment">
									<input name="mastodon_api_reply_as_comment" type="checkbox" id="mastodon_api_reply_as_comment" value="1" <?php checked( get_option( 'mastodon_api_reply_as_comment' ) ); ?> />
									<span><?php esc_html_e( 'Post replies to posts as comments.', 'enable-mastodon-apps' ); ?></span>
								</label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Since the ActivityPub plugin handles incoming replies this way, you might want to do this for your own replies as well.', 'enable-mastodon-apps' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Debug Mode', 'enable-mastodon-apps' ); ?></th>
						<td>
							<fieldset>
								<label for="mastodon_api_debug_mode">
									<input name="mastodon_api_debug_mode" type="checkbox" id="mastodon_api_debug_mode" value="1" <?php checked( get_option( 'mastodon_api_debug_mode' ) > time() ); ?> />
									<span><?php esc_html_e( 'Log requests to the plugin and expose more information.', 'enable-mastodon-apps' ); ?></span>
								</label>
							</fieldset>
							<p class="description">
								<?php
								if ( get_option( 'mastodon_api_debug_mode' ) > time() ) {
									echo esc_html(
										sprintf(
											// translators: %1$s is a relative time, %2$s is a specific time.
											__( 'Debug mode is active for %1$s (until %2$s).', 'enable-mastodon-apps' ),
											human_time_diff( get_option( 'mastodon_api_debug_mode' ) ),
											wp_date( 'H:i:s', get_option( 'mastodon_api_debug_mode' ) )
										)
									);
								} else {
									esc_html_e( 'Enable this to activate logging of requests.', 'enable-mastodon-apps' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default Post Formats', 'enable-mastodon-apps' ); ?></th>
						<td>
							<details>
								<summary><?php esc_html_e( 'Change the post formats new apps will receive by default.', 'enable-mastodon-apps' ); ?></summary>
								<fieldset>
									<label for="mastodon_api_default_post_formats">
										<?php $this->post_format_select( 'mastodon_api_default_post_formats', get_option( 'mastodon_api_default_post_formats', array( 'status' ) ) ); ?>
									</label>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'Note: If you change this post format after applications have already connected to your site you must manually change the post format for each individual application below in the "Apps" section.', 'enable-mastodon-apps' ); ?>
								</p>
							</details>
						</td>
					</tr>
				</tbody>
			</table>

			<button class="button button-primary"><?php esc_html_e( 'Save' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button>

			<?php
			if ( get_option( 'mastodon_api_debug_mode' ) > time() ) {
				$debug_start_time = \DateTimeImmutable::createFromFormat( 'U', time() - HOUR_IN_SECONDS );
				$all_last_requests = array();
				foreach ( Mastodon_App::get_all() as $app ) {
					$last_requests = $app->get_last_requests();
					foreach ( $last_requests as $request ) {
						$date = \DateTimeImmutable::createFromFormat( 'U.u', $request['timestamp'] );
						if ( $date > $debug_start_time ) {
							$request['app'] = $app;
							$all_last_requests[ $request['timestamp'] * 10000 ] = $request;
						}
					}
				}
				if ( $all_last_requests ) {
					ksort( $all_last_requests );
					?>
					<h2><?php esc_html_e( 'Recently Logged Requests', 'enable-mastodon-apps' ); ?></h2>
					<tt>
					<?php

					foreach ( $all_last_requests as $request ) {
						output_request_log( $request, $rest_nonce );
					}
					?>
				</tt>
					<?php
				}
			}
			?>

			<?php if ( ! empty( $codes ) ) : ?>
				<h2><?php esc_html_e( 'Authorization Codes', 'enable-mastodon-apps' ); ?></h2>
				<details>
					<summary>
						<?php
						echo esc_html(
							sprintf(
								// translators: %d is the number of authorization codes.
								_n( '%d authorization code', '%d authorization codes', count( $codes ), 'enable-mastodon-apps' ),
								count( $codes )
							)
						);
						?>
					</summary>
					<table class="widefat striped">
						<thead>
							<th><?php esc_html_e( 'App', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Redirect URI', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Scope', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'enable-mastodon-apps' ); ?></th>
						</thead>
						<tbody>
							<?php
							foreach ( $codes as $code => $data ) {
								?>
								<tr id="code-<?php echo esc_attr( $code ); ?>" title="<?php echo esc_attr( $code ); ?>">
									<td>
										<?php
										if ( isset( $apps[ $data['client_id'] ] ) ) {
											echo esc_html( $apps[ $data['client_id'] ]->get_client_name() );
										} else {
											echo esc_html( 'Unknown: ' . $data['client_id'] );
										}
										?>
									</td>
									<td><?php echo esc_html( $data['redirect_uri'] ); ?></td>
									<?php td_timestamp( $data['expires'] ); ?>
									<td><?php echo esc_html( $data['scope'] ); ?></td>
									<td><button name="delete-code" value="<?php echo esc_attr( $code ); ?>" class="button"><?php esc_html_e( 'Delete' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button></td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</details>
			<?php endif; ?>

			<?php if ( ! empty( $tokens ) ) : ?>
				<h2><?php esc_html_e( 'Access Tokens', 'enable-mastodon-apps' ); ?></h2>
				<details>
					<summary>
						<?php
						echo esc_html(
							sprintf(
								// translators: %d is the number of tokens.
								_n( '%d token', '%d tokens', count( $tokens ), 'enable-mastodon-apps' ),
								count( $tokens )
							)
						);
						?>
					</summary>
					<table class="widefat striped">
						<thead>
							<th><?php esc_html_e( 'App', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'User', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Last Used', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Scope', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'enable-mastodon-apps' ); ?></th>
						</thead>
						<tbody>
							<?php
							foreach ( $tokens as $token => $data ) {
								$user = 'app-level';
								if ( $data['user_id'] ) {
									$userdata = get_user_by( 'ID', $data['user_id'] );
									if ( $userdata ) {
										if ( is_wp_error( $userdata ) ) {
											$user = $userdata->get_error_message();
										} else {
											$user = $userdata->user_login;
										}
									} else {
										$user = 'error';
									}
								}
								?>
								<tr id="token-<?php echo esc_attr( $token ); ?>" title="<?php echo esc_attr( $token ); ?>">
									<td>
										<?php
										if ( isset( $apps[ $data['client_id'] ] ) ) {
											?>
											<a href="#app-<?php echo esc_attr( $data['client_id'] ); ?>"><?php echo esc_html( $apps[ $data['client_id'] ]->get_client_name() ); ?></a>
											<?php
										} else {
											echo esc_html(
												sprintf(
												// Translators: %s is the app ID.
													__( 'Unknown App: %s', 'enable-mastodon-apps' ),
													$data['client_id']
												)
											);
										}
										?>
									</td>
									<td><?php echo esc_html( $user ); ?></td>
									<?php td_timestamp( $data['last_used'] ); ?>
									<?php td_timestamp( $data['expires'], true ); ?>
									<td><?php echo esc_html( $data['scope'] ); ?></td>
									<td><button name="delete-token" value="<?php echo esc_attr( $token ); ?>" class="button"><?php esc_html_e( 'Delete' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button></td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</details>
			<?php endif; ?>

			<?php if ( ! empty( $apps ) ) : ?>
				<h2><?php esc_html_e( 'Apps', 'enable-mastodon-apps' ); ?></h2>

				<details>
					<summary>
						<?php
						echo esc_html(
							sprintf(
							// translators: %d is the number of apps.
								_n( '%d apps', '%d apps', count( $apps ), 'enable-mastodon-apps' ),
								count( $apps )
							)
						);
						?>
					</summary>
					<table class="widefat">
						<thead>
							<th><?php esc_html_e( 'Name', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Redirect URI', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Scope', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Post Formats', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Last Used', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Created', 'enable-mastodon-apps' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'enable-mastodon-apps' ); ?></th>
						</thead>
						<tbody>
							<?php
							$alternate = true;
							foreach ( $apps as $app ) {
								$alternate = ! $alternate;
								?>
								<tr id='app-<?php echo esc_attr( $app->get_client_id() ); ?>' class="<?php echo $alternate ? 'alternate' : ''; ?>">
									<td>
										<?php
										if ( $app->get_website() ) {
											?>
											<a href="<?php echo esc_url( $app->get_website() ); ?>"><?php echo esc_html( $app->get_client_name() ); ?></a>
											<?php
										} else {
											echo esc_html( $app->get_client_name() );
										}
										?>
									</td>
									<td><?php echo wp_kses( implode( '<br/>', is_array( $app->get_redirect_uris() ) ? $app->get_redirect_uris() : explode( ',', $app->get_redirect_uris() ) ), array( 'br' => array() ) ); ?></td>
									<td><?php echo esc_html( $app->get_scopes() ); ?></td>
									<td>
										<details>
											<summary><?php echo esc_html( implode( ', ', $app->get_post_formats() ) ); ?></summary>
											<?php $this->post_format_select( 'app_post_formats[' . $app->get_client_id() . ']', $app->get_post_formats() ); ?>
										</details>
									</td>
									<?php td_timestamp( $app->get_last_used() ); ?>
									<?php td_timestamp( $app->get_creation_date() ); ?>
									<td>
										<button name="save-app" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button button-secondary"><?php esc_html_e( 'Save' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button>
										<button name="delete-app" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button button-link-delete"><?php esc_html_e( 'Delete' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button>
									</td>
								</tr>
								<?php

								$last_requests = $app->get_last_requests();
								if ( $last_requests ) {
									?>
									<tr id='applog-<?php echo esc_attr( $app->get_client_id() ); ?>' class="<?php echo $alternate ? 'alternate' : ''; ?>">
										<td colspan="6">
											<details class="tt"><summary>
											<?php
											echo esc_html(
												sprintf(
													// translators: %ds is the number of requests.
													_n( '%d logged request', '%d logged requests', count( $last_requests ), 'enable-mastodon-apps' ),
													count( $last_requests )
												)
											);
											?>
											</summary>
											<?php
											foreach ( $last_requests as $request ) {
												output_request_log( $request, $rest_nonce );
											}
											?>
											</details>
										</td>
										<td>
											<button name="clear-app-logs" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button button-link-delete"><?php esc_html_e( 'Clear logs', 'enable-mastodon-apps' ); ?></button>
										</td>
									</tr>
									<?php
								}
							}
							?>
						</tbody>
					</table>
				</details>
			<?php endif; ?>

			<?php if ( ! empty( $codes ) || ! empty( $tokens ) || ! empty( $apps ) ) : ?>
				<h2><?php esc_html_e( 'Cleanup', 'enable-mastodon-apps' ); ?></h2>
					<button name="delete-outdated" class="button"><?php esc_html_e( 'Delete outdated apps and tokens', 'enable-mastodon-apps' ); ?></button>
					<button name="delete-never-used" class="button"><?php esc_html_e( 'Delete never used apps and tokens', 'enable-mastodon-apps' ); ?></button>
					<button name="delete-apps-without-tokens" class="button"><?php esc_html_e( 'Delete apps without tokens', 'enable-mastodon-apps' ); ?></button>
					<button name="clear-all-app-logs" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button button-link-delete"><?php esc_html_e( 'Clear all logs', 'enable-mastodon-apps' ); ?></button>

			<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
