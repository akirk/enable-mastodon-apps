<?php

use Enable_Mastodon_Apps\Mastodon_App;

\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'active'       => 'registered-apps',
		'enable_debug' => $args['enable_debug'],
	)
);

$rest_nonce = wp_create_nonce( 'wp_rest' );

?>
<div class="enable-mastodon-apps-settings enable-mastodon-apps-registered-apps-page <?php echo $args['enable_debug'] ? 'enable-debug' : 'disable-debug'; ?>">
	<form method="post">
		<?php wp_nonce_field( 'enable-mastodon-apps' ); ?>
		<h2><?php esc_html_e( 'Apps', 'enable-mastodon-apps' ); ?></h2>
		<span class="count">
			<?php
			echo esc_html(
				sprintf(
				// translators: %d is the number of apps.
					_n( '%d apps', '%d apps', count( $args['apps'] ), 'enable-mastodon-apps' ),
					count( $args['apps'] )
				)
			);
			?>
		</span>
		<table class="widefat">
			<thead>
				<th><?php esc_html_e( 'Name', 'enable-mastodon-apps' ); ?></th>
				<th class="debug-hide"><?php esc_html_e( 'Redirect URI', 'enable-mastodon-apps' ); ?></th>
				<th><?php esc_html_e( 'Scope', 'enable-mastodon-apps' ); ?></th>
				<th class="debug-hide"><?php esc_html_e( 'Post Formats', 'enable-mastodon-apps' ); ?></th>
				<th><?php esc_html_e( 'Last Used', 'enable-mastodon-apps' ); ?></th>
				<th><?php esc_html_e( 'Created', 'enable-mastodon-apps' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'enable-mastodon-apps' ); ?></th>
			</thead>
			<tbody>
				<?php
				$alternate = true;
				foreach ( $args['apps'] as $app ) {
					$alternate = ! $alternate;
					$confirm = esc_html(
						sprintf(
						// translators: %s is the app name.
							__( 'Are you sure you want to delete %s?', 'enable-mastodon-apps' ),
							$app->get_client_name()
						)
					);

					?>
					<tr id='app-<?php echo esc_attr( $app->get_client_id() ); ?>' class="<?php echo $alternate ? 'alternate' : ''; ?>">
						<td title='<?php echo esc_attr( $app->get_client_id() ); ?>'>
							<a href="<?php echo esc_url( $app->get_admin_page() ); ?>"><?php echo esc_html( $app->get_client_name() ); ?></a>
							<?php

							if ( $app->is_outdated() && Mastodon_App::DEBUG_CLIENT_ID !== $app->get_client_id() ) {
								echo ' <span class="pill pill-outdated" title="' . esc_html__( 'No tokens or authorization codes associated with this app.', 'enable-mastodon-apps' ) . '">' . esc_html__( 'Outdated', 'enable-mastodon-apps' ) . '</span>';
							}
							?>
						</td>
						<td class="debug-hide"><?php echo wp_kses( implode( '<br/>', is_array( $app->get_redirect_uris() ) ? $app->get_redirect_uris() : explode( ',', $app->get_redirect_uris() ) ), array( 'br' => array() ) ); ?></td>
						<td><?php echo esc_html( $app->get_scopes() ); ?></td>
						<td class="debug-hide">
							<details>
								<summary><?php echo esc_html( implode( ', ', $app->get_post_formats() ) ); ?></summary>
								<?php post_format_select( 'app_post_formats[' . $app->get_client_id() . ']', $app->get_post_formats() ); ?>
							</details>
						</td>
							<?php td_timestamp( $app->get_last_used() ); ?>
							<?php td_timestamp( $app->get_creation_date() ); ?>
						<td>
							<button name="save-app" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button save-app button-secondary"><?php esc_html_e( 'Save' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button>
							<button name="delete-app" data-confirm="<?php echo esc_attr( $confirm ); ?>" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button button-destructive"><?php esc_html_e( 'Delete' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button>
						</td>
					</tr>
					<?php
					if ( $args['enable_debug'] ) {
						$last_requests = $app->get_last_requests();
						if ( $last_requests ) {
							$confirm = esc_html(
								sprintf(
								// translators: %s is the app name.
									__( 'Are you sure you want to delete all logs for %s?', 'enable-mastodon-apps' ),
									$app->get_client_name()
								)
							);
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
									<button name="clear-app-logs" data-confirm="<?php echo esc_attr( $confirm ); ?>" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button button-destructive"><?php esc_html_e( 'Clear logs', 'enable-mastodon-apps' ); ?></button>
								</td>
							</tr>
							<?php
						}
					}
				}
				?>
			</tbody>
		</table>

		<?php if ( $args['enable_debug'] ) : ?>
			<h2><?php esc_html_e( 'Authorization Codes', 'enable-mastodon-apps' ); ?></h2>

			<span class="count">
				<?php
				echo esc_html(
					sprintf(
						// translators: %d is the number of authorization codes.
						_n( '%d authorization code', '%d authorization codes', count( $args['codes'] ), 'enable-mastodon-apps' ),
						count( $args['codes'] )
					)
				);
				?>
			</span>
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
					foreach ( $args['codes'] as $code => $data ) {
						?>
						<tr id="code-<?php echo esc_attr( $code ); ?>">
							<td title="<?php echo esc_attr( $code ); ?>">
								<?php
								if ( isset( $args['apps'][ $data['client_id'] ] ) ) {
									echo esc_html( $args['apps'][ $data['client_id'] ]->get_client_name() );
								} else {
									echo esc_html(
										sprintf(
										// Translators: %s is the app ID.
											__( 'Unknown App: %s', 'enable-mastodon-apps' ),
											$data['client_id']
										)
									);
									echo ' <span class="pill pill-outdated" title="' . esc_html__( 'Associated with an app that no longer exists.', 'enable-mastodon-apps' ) . '">' . esc_html__( 'Outdated', 'enable-mastodon-apps' ) . '</span>';
								}

								?>
							</td>
							<td><?php echo esc_html( $data['redirect_uri'] ); ?></td>
							<?php td_timestamp( $data['expires'] ); ?>
							<td><?php echo esc_html( $data['scope'] ); ?></td>
							<td><button name="delete-code" value="<?php echo esc_attr( $code ); ?>" class="button button-destructive"><?php esc_html_e( 'Delete' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Access Tokens', 'enable-mastodon-apps' ); ?></h2>
			<span class="count">
				<?php
				echo esc_html(
					sprintf(
						// translators: %d is the number of tokens.
						_n( '%d token', '%d tokens', count( $args['tokens'] ), 'enable-mastodon-apps' ),
						count( $args['tokens'] )
					)
				);
				?>
			</span>
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
					foreach ( $args['tokens'] as $token => $data ) {
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
						<tr id="token-<?php echo esc_attr( $token ); ?>">
							<td title="<?php echo esc_attr( $token ); ?>">
								<?php
								if ( isset( $args['apps'][ $data['client_id'] ] ) ) {
									?>
									<a href="#app-<?php echo esc_attr( $data['client_id'] ); ?>"><?php echo esc_html( $args['apps'][ $data['client_id'] ]->get_client_name() ); ?></a>
									<?php
								} else {
									echo esc_html(
										sprintf(
										// Translators: %s is the app ID.
											__( 'Unknown App: %s', 'enable-mastodon-apps' ),
											$data['client_id']
										)
									);
									echo ' <span class="pill pill-outdated" title="' . esc_html__( 'Associated with an app that no longer exists.', 'enable-mastodon-apps' ) . '">' . esc_html__( 'Outdated', 'enable-mastodon-apps' ) . '</span>';
								}
								?>
							</td>
							<td><?php echo esc_html( $user ); ?></td>
							<?php td_timestamp( $data['last_used'] ); ?>
							<?php td_timestamp( $data['expires'], true ); ?>
							<td><?php echo esc_html( $data['scope'] ); ?></td>
							<td><button name="delete-token" value="<?php echo esc_attr( $token ); ?>" class="button button-destructive"><?php esc_html_e( 'Delete' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>

			<?php if ( ! empty( $args['codes'] ) || ! empty( $args['tokens'] ) || ! empty( $args['apps'] ) ) : ?>
				<h2><?php esc_html_e( 'Cleanup', 'enable-mastodon-apps' ); ?></h2>
				<button name="delete-outdated" class="button"><?php esc_html_e( 'Delete outdated apps and tokens', 'enable-mastodon-apps' ); ?></button>
				<button name="delete-never-used" class="button"><?php esc_html_e( 'Delete never used apps and tokens', 'enable-mastodon-apps' ); ?></button>
				<button name="delete-apps-without-tokens" class="button"><?php esc_html_e( 'Delete apps without tokens', 'enable-mastodon-apps' ); ?></button>
				<button name="clear-all-app-logs" class="button button-destructive"><?php esc_html_e( 'Clear all logs', 'enable-mastodon-apps' ); ?></button>
		<?php endif; ?>
	<?php endif; ?>
</form>
</div>
