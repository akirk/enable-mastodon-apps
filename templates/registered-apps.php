<?php

use Enable_Mastodon_Apps\Mastodon_App;
// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable

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
	<?php if ( ! empty( $args['apps'] ) ) : ?>
		<p>
			<?php esc_html_e( 'These are the Mastodon apps that have been used with this WordPress site.', 'enable-mastodon-apps' ); ?>
			<?php esc_html_e( 'You can customize the apps post types, or delete it by click on the Details link.', 'enable-mastodon-apps' ); ?>
			<?php if ( $args['enable_debug'] ) : ?>
				<br>
				<?php esc_html_e( 'Since debug mode is activated, you\'ll also be able to see and manage access tokens on the details page.', 'enable-mastodon-apps' ); ?>
			<?php endif; ?>
		</p>
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
				<th><?php echo esc_html_x( 'Create new posts as', 'select post type', 'enable-mastodon-apps' ); ?></th>
				<th><?php echo esc_html_x( 'in the post format', 'select post format', 'enable-mastodon-apps' ); ?></th>
				<th><?php esc_html_e( 'Post Formats', 'enable-mastodon-apps' ); ?></th>
				<th class="debug-hide"><?php esc_html_e( 'Scope', 'enable-mastodon-apps' ); ?></th>
				<th><?php esc_html_e( 'Last Used', 'enable-mastodon-apps' ); ?></th>
				<th><?php esc_html_e( 'Created', 'enable-mastodon-apps' ); ?></th>
				<th></th>
			</thead>
			<tbody>
				<?php
				$alternate = true;
				foreach ( $args['apps'] as $app ) {
					$alternate = ! $alternate;

					?>
					<tr id='app-<?php echo esc_attr( $app->get_client_id() ); ?>' class="<?php echo $alternate ? 'alternate' : ''; ?>">
						<td title='<?php echo esc_attr( $app->get_client_id() ); ?>'>
							<a href="<?php echo esc_url( $app->get_admin_page() ); ?>"><?php echo esc_html( $app->get_client_name() ); ?></a>
							<?php

							if ( ! $app->get_last_used() && Mastodon_App::DEBUG_CLIENT_ID !== $app->get_client_id() ) {
								echo ' <span class="pill pill-never-used" title="' . esc_html__( 'No tokens or authorization codes associated with this app.', 'enable-mastodon-apps' ) . '">' . esc_html__( 'Never Used', 'enable-mastodon-apps' ) . '</span>';
							} elseif ( $app->is_outdated() && Mastodon_App::DEBUG_CLIENT_ID !== $app->get_client_id() ) {
								echo ' <span class="pill pill-outdated" title="' . esc_html__( 'No tokens or authorization codes associated with this app.', 'enable-mastodon-apps' ) . '">' . esc_html__( 'Outdated', 'enable-mastodon-apps' ) . '</span>';
							}
							?>
						</td>
						<td class="debug-hide"><?php echo wp_kses( implode( '<br/>', is_array( $app->get_redirect_uris() ) ? $app->get_redirect_uris() : explode( ',', $app->get_redirect_uris() ) ), array( 'br' => array() ) ); ?></td>
						<td>
							<?php
							$_post_type = get_post_type_object( $app->get_create_post_type() );
							echo esc_html( $_post_type->labels->singular_name );
							?>
						</td>
						<td>
							<?php
							if ( ! $app->get_create_post_format() ) {
								echo esc_html_x( 'Standard', 'Post format' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain

							} else {
								foreach ( get_post_format_strings() as $slug => $name ) {
									if ( $slug === $app->get_create_post_format() ) {
										echo esc_html( $name );
										break;
									}
								}
							}
							?>
						</td>
						<td>
							<?php

							$post_formats = $app->get_post_formats();
							if ( empty( $post_formats ) ) {
								echo esc_html( __( 'All' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
							} else {
								echo esc_html(
									implode(
										', ',
										array_map(
											function ( $slug ) {
												return get_post_format_strings()[ $slug ];
											},
											$post_formats
										)
									)
								);
							}
							?>
						</td>
						<td class="debug-hide"><?php echo esc_html( $app->get_scopes() ); ?></td>
						<?php td_timestamp( $app->get_last_used() ); ?>
						<?php td_timestamp( $app->get_creation_date() ); ?>
						<td><a href="<?php echo esc_url( $app->get_admin_page() ); ?>"><?php esc_html_e( 'Details', 'enable-mastodon-apps' ); ?></a></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>

		<?php if ( $args['enable_debug'] ) : ?>
			<?php if ( ! empty( $args['codes'] ) ) : ?>
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
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $args['codes'] ) || ! empty( $args['tokens'] ) || ! empty( $args['apps'] ) ) : ?>
				<h2><?php esc_html_e( 'Cleanup', 'enable-mastodon-apps' ); ?></h2>
				<button name="delete-outdated" class="button"><?php esc_html_e( 'Delete outdated apps and tokens', 'enable-mastodon-apps' ); ?></button>
				<button name="delete-never-used" class="button"><?php esc_html_e( 'Delete never used apps and tokens', 'enable-mastodon-apps' ); ?></button>
				<button name="delete-apps-without-tokens" class="button"><?php esc_html_e( 'Delete apps without tokens', 'enable-mastodon-apps' ); ?></button>
				<button name="clear-all-app-logs" class="button button-destructive"><?php esc_html_e( 'Clear all logs', 'enable-mastodon-apps' ); ?></button>
			<?php endif; ?>
		<?php endif; ?>
	<?php else : ?>
		<div class="box help-box" style="max-width: 800px">
			<p>
				<span><?php esc_html_e( 'No apps have been registered yet.', 'enable-mastodon-apps' ); ?></span>
				<span>
				<?php
				echo wp_kses(
					sprintf(
						// translators: %s is the link to the Mastodon apps directory.
						__( 'You can find compatible apps in <a href="%s">the Mastodon app directory</a>.', 'enable-mastodon-apps' ),
						'https://joinmastodon.org/apps" target="_blank'
					),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
						),
					)
				);
				?>
				</span>
				<span><?php esc_html_e( 'Make sure you scroll down to the "Browse third-party apps" section for more choice.', 'enable-mastodon-apps' ); ?></span>
			</p>
			<p>
				<span><?php esc_html_e( 'Enter this URL as your Mastodon instance:', 'enable-mastodon-apps' ); ?></span>
				<input type="text" class="regular-text copyable" id="enable-mastodon-apps-instance" value="<?php echo esc_attr( $args['instance_url'] ); ?>" readonly="readonly">
			</p>
		</div>
	<?php endif; ?>
	</form>
</div>
