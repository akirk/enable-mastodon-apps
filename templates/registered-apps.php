<?php
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'active' => 'registered-apps',
	)
);

$rest_nonce = wp_create_nonce( 'wp_rest' );

function post_format_select( $name, $selected = array() ) {
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

?>
<div class="enable-mastodon-apps-settings enable-mastodon-apps-registered-apps-page">
	<form method="post">
		<?php wp_nonce_field( 'enable-mastodon-apps' ); ?>
			<h2><?php esc_html_e( 'Apps', 'enable-mastodon-apps' ); ?></h2>

					<?php
					echo esc_html(
						sprintf(
						// translators: %d is the number of apps.
							_n( '%d apps', '%d apps', count( $args['apps'] ), 'enable-mastodon-apps' ),
							count( $args['apps'] )
						)
					);
					?>
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
						foreach ( $args['apps'] as $app ) {
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
										<?php post_format_select( 'app_post_formats[' . $app->get_client_id() . ']', $app->get_post_formats() ); ?>
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
		<h2><?php esc_html_e( 'Authorization Codes', 'enable-mastodon-apps' ); ?></h2>

			<?php
				echo esc_html(
					sprintf(
						// translators: %d is the number of authorization codes.
						_n( '%d authorization code', '%d authorization codes', count( $args['codes'] ), 'enable-mastodon-apps' ),
						count( $args['codes'] )
					)
				);
				?>
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
						<tr id="code-<?php echo esc_attr( $code ); ?>" title="<?php echo esc_attr( $code ); ?>">
							<td>
								<?php
								if ( isset( $args['apps'][ $data['client_id'] ] ) ) {
									echo esc_html( $args['apps'][ $data['client_id'] ]->get_client_name() );
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

		<h2><?php esc_html_e( 'Access Tokens', 'enable-mastodon-apps' ); ?></h2>
				<?php
				echo esc_html(
					sprintf(
						// translators: %d is the number of tokens.
						_n( '%d token', '%d tokens', count( $args['tokens'] ), 'enable-mastodon-apps' ),
						count( $args['tokens'] )
					)
				);
				?>
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
						<tr id="token-<?php echo esc_attr( $token ); ?>" title="<?php echo esc_attr( $token ); ?>">
							<td>
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

	<?php if ( ! empty( $args['codes'] ) || ! empty( $args['tokens'] ) || ! empty( $args['apps'] ) ) : ?>
		<h2><?php esc_html_e( 'Cleanup', 'enable-mastodon-apps' ); ?></h2>
			<button name="delete-outdated" class="button"><?php esc_html_e( 'Delete outdated apps and tokens', 'enable-mastodon-apps' ); ?></button>
			<button name="delete-never-used" class="button"><?php esc_html_e( 'Delete never used apps and tokens', 'enable-mastodon-apps' ); ?></button>
			<button name="delete-apps-without-tokens" class="button"><?php esc_html_e( 'Delete apps without tokens', 'enable-mastodon-apps' ); ?></button>
			<button name="clear-all-app-logs" class="button button-link-delete"><?php esc_html_e( 'Clear all logs', 'enable-mastodon-apps' ); ?></button>

	<?php endif; ?>
</form>
</div>
