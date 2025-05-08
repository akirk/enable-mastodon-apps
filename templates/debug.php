<?php
// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'active'       => 'debug',
		'enable_debug' => true,
	)
);
$rest_nonce = wp_create_nonce( 'wp_rest' );
?>

<div class="enable-mastodon-apps-settings enable-mastodon-apps-debug-page">
<form method="post">
	<?php wp_nonce_field( 'enable-mastodon-apps' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Login Fix', 'enable-mastodon-apps' ); ?></th>
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
		</tbody>
	</table>

	<button class="button button-primary"><?php esc_html_e( 'Save' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button>

	<?php
	if ( get_option( 'mastodon_api_debug_mode' ) > time() ) {
		?>
		<h2><?php esc_html_e( 'Recently Logged Requests', 'enable-mastodon-apps' ); ?></h2>
		<?php
		$debug_start_time  = \DateTimeImmutable::createFromFormat( 'U', time() - HOUR_IN_SECONDS );
		$all_last_requests = array();
		foreach ( \Enable_Mastodon_Apps\Mastodon_App::get_all() as $app ) {
			$last_requests = $app->get_last_requests();
			foreach ( $last_requests as $request ) {
				$date = \DateTimeImmutable::createFromFormat( 'U.u', $request['timestamp'] );
				if ( $date > $debug_start_time ) {
					$request['app'] = $app;
					$all_last_requests[ intval( $request['timestamp'] * 10000 ) ] = $request;
				}
			}
		}
		if ( $all_last_requests ) {
			krsort( $all_last_requests );
			?>
			<tt>
			<?php
			foreach ( $all_last_requests as $request ) {
				output_request_log( $request, $rest_nonce );
			}
			?>
			</tt>
			<?php
		} else {
			?>
			<p><?php esc_html_e( 'No requests logged in the last hour.', 'enable-mastodon-apps' ); ?></p>
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
							$userdata = get_user_by( 'ID', $data['user_id'] ); // phpcs:ignore
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
