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
			<?php esc_html_e( 'Use App settings to customize an app\'s post types, post formats, and app-specific options, or to delete the app.', 'enable-mastodon-apps' ); ?>
			<?php if ( $args['enable_debug'] ) : ?>
				<br>
				<?php esc_html_e( 'Since debug mode is activated, you\'ll also be able to see and manage access tokens on the app settings page.', 'enable-mastodon-apps' ); ?>
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
		<table class="widefat enable-mastodon-apps-sortable-table">
			<thead>
				<tr>
					<th scope="col" data-sort-type="text"><button type="button"><?php esc_html_e( 'Name', 'enable-mastodon-apps' ); ?></button></th>
					<th scope="col" class="debug-hide" data-sort-type="text"><button type="button"><?php esc_html_e( 'Redirect URI', 'enable-mastodon-apps' ); ?></button></th>
					<th scope="col" data-sort-type="text"><button type="button"><?php echo esc_html_x( 'Create new posts as', 'select post type', 'enable-mastodon-apps' ); ?></button></th>
					<th scope="col" data-sort-type="text"><button type="button"><?php echo esc_html_x( 'in the post format', 'select post format', 'enable-mastodon-apps' ); ?></button></th>
					<th scope="col" data-sort-type="text"><button type="button"><?php esc_html_e( 'Post Formats', 'enable-mastodon-apps' ); ?></button></th>
					<th scope="col" class="debug-hide" data-sort-type="text"><button type="button"><?php esc_html_e( 'Scope', 'enable-mastodon-apps' ); ?></button></th>
					<th scope="col" data-sort-type="number" class="sort-desc" aria-sort="descending"><button type="button"><?php esc_html_e( 'Last Used', 'enable-mastodon-apps' ); ?></button></th>
					<th scope="col" data-sort-type="number"><button type="button"><?php esc_html_e( 'Created', 'enable-mastodon-apps' ); ?></button></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'enable-mastodon-apps' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$alternate = true;
				foreach ( $args['apps'] as $app ) {
					$alternate = ! $alternate;
					$redirect_uris = $app->get_redirect_uris();
					if ( ! is_array( $redirect_uris ) ) {
						$redirect_uris = explode( ',', $redirect_uris );
					}
					$post_formats       = $app->get_post_formats();
					$post_format_labels = array();
					foreach ( $post_formats as $slug ) {
						if ( isset( get_post_format_strings()[ $slug ] ) ) {
							$post_format_labels[] = get_post_format_strings()[ $slug ];
						}
					}
					$post_formats_title = empty( $post_format_labels ) ? __( 'All', 'enable-mastodon-apps' ) : implode( ', ', $post_format_labels );
					$post_formats_length = function_exists( 'mb_strlen' ) ? mb_strlen( $post_formats_title ) : strlen( $post_formats_title );
					$collapse_post_formats = count( $post_format_labels ) > 1 && $post_formats_length > 20;

					?>
					<tr id='app-<?php echo esc_attr( $app->get_client_id() ); ?>' class="<?php echo $alternate ? 'alternate' : ''; ?>">
						<td title='<?php echo esc_attr( $app->get_client_id() ); ?>' data-sort="<?php echo esc_attr( $app->get_client_name() ); ?>">
							<a href="<?php echo esc_url( $app->get_admin_page() ); ?>"><?php echo esc_html( $app->get_client_name() ); ?></a>
							<?php

							if ( ! $app->get_last_used() && Mastodon_App::DEBUG_CLIENT_ID !== $app->get_client_id() ) {
								echo ' <span class="pill pill-never-used" title="' . esc_html__( 'No tokens or authorization codes associated with this app.', 'enable-mastodon-apps' ) . '">' . esc_html__( 'Never Used', 'enable-mastodon-apps' ) . '</span>';
							} elseif ( $app->is_outdated() && Mastodon_App::DEBUG_CLIENT_ID !== $app->get_client_id() ) {
								echo ' <span class="pill pill-outdated" title="' . esc_html__( 'No tokens or authorization codes associated with this app.', 'enable-mastodon-apps' ) . '">' . esc_html__( 'Outdated', 'enable-mastodon-apps' ) . '</span>';
							}
							?>
						</td>
						<td class="debug-hide" data-sort="<?php echo esc_attr( implode( ', ', $redirect_uris ) ); ?>"><?php echo wp_kses( implode( '<br/>', $redirect_uris ), array( 'br' => array() ) ); ?></td>
						<td data-sort="<?php echo esc_attr( get_post_type_object( $app->get_create_post_type() )->labels->singular_name ); ?>">
							<?php
							$_post_type = get_post_type_object( $app->get_create_post_type() );
							echo esc_html( $_post_type->labels->singular_name );
							?>
						</td>
						<td data-sort="<?php echo esc_attr( $app->get_create_post_format() ? $app->get_create_post_format() : 'standard' ); ?>">
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
						<td data-sort="<?php echo esc_attr( $post_formats_title ); ?>" title="<?php echo esc_attr( $post_formats_title ); ?>">
							<?php

							if ( empty( $post_format_labels ) ) {
								echo esc_html( $post_formats_title );
							} elseif ( $collapse_post_formats ) {
								echo esc_html(
									sprintf(
										// translators: %d is the number of enabled post formats.
										_n( '%d post format', '%d post formats', count( $post_format_labels ), 'enable-mastodon-apps' ),
										count( $post_format_labels )
									)
								);
							} else {
								echo esc_html( $post_formats_title );
							}
							?>
						</td>
						<td class="debug-hide" data-sort="<?php echo esc_attr( $app->get_scopes() ); ?>"><?php echo esc_html( $app->get_scopes() ); ?></td>
						<?php td_timestamp( $app->get_last_used(), false, $app->get_last_used() ? $app->get_last_used() : $app->get_creation_date() ); ?>
						<?php td_timestamp( $app->get_creation_date(), false, $app->get_creation_date() ); ?>
						<td><a href="<?php echo esc_url( $app->get_admin_page() ); ?>"><?php esc_html_e( 'App settings', 'enable-mastodon-apps' ); ?></a></td>
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
				<button name="delete-orphaned-mappings" class="button"><?php esc_html_e( 'Delete orphaned reblog mappings', 'enable-mastodon-apps' ); ?></button>
				<button name="clear-all-app-logs" class="button button-destructive"><?php esc_html_e( 'Clear all logs', 'enable-mastodon-apps' ); ?></button>
			<?php endif; ?>
		<?php endif; ?>
	<?php else : ?>
		<div class="box help-box" style="max-width: 800px; margin: auto">
			<h3><?php esc_html_e( 'No apps have been registered yet.', 'enable-mastodon-apps' ); ?></h3>
			<p>
				<span>
				<?php
				echo wp_kses(
					sprintf(
						// translators: %s is the link to the Mastodon apps directory.
						__( 'You can find compatible apps in <a href=%s>the Mastodon app directory</a>.', 'enable-mastodon-apps' ),
						'"https://joinmastodon.org/apps#:~:text=Browse%20third-party%20apps" target="_blank"'
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
				<span><?php esc_html_e( 'When you first start a Mastodon app, it will ask you for your instance URL:', 'enable-mastodon-apps' ); ?></span>
				<input type="text" class="regular-text copyable" id="enable-mastodon-apps-instance" value="<?php echo esc_attr( $args['instance_url'] ); ?>" readonly="readonly">
			</p>
			<p>
				<span><?php esc_html_e( 'The Mastodon app will then redirect you to the login page of your own WordPress site.', 'enable-mastodon-apps' ); ?></span>
				<span><?php esc_html_e( 'Double-check the URL of the login form so that you don\'t enter your details on another site.', 'enable-mastodon-apps' ); ?></span>
				<span><?php esc_html_e( 'You\'ll need to log in there and then authorize the app.', 'enable-mastodon-apps' ); ?></span>
			</p>
		</div>
	<?php endif; ?>
	</form>
</div>
