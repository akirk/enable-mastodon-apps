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

$rest_nonce  = wp_create_nonce( 'wp_rest' );
$_post_types = \get_post_types( array( 'show_ui' => true ), 'objects' );
$app         = $args['app'];
$confirm     = esc_html(
	sprintf(
	// translators: %s is the app name.
		__( 'Are you sure you want to delete %s?', 'enable-mastodon-apps' ),
		$app->get_client_name()
	)
);
?>
<div class="enable-mastodon-apps-settings enable-mastodon-apps-registered-apps-page <?php echo $args['enable_debug'] ? 'enable-debug' : 'disable-debug'; ?>">
	<form method="post">
		<?php wp_nonce_field( 'enable-mastodon-apps' ); ?>
		<input type="hidden" name="app" value="<?php echo esc_attr( $app->get_client_id() ); ?>" />
		<h2><?php echo esc_html( $args['app']->get_client_name() ); ?></h2>

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

				echo esc_html(
					sprintf(
						// translators: %ds is the number of requests.
						_n( '%d logged request', '%d logged requests', count( $last_requests ), 'enable-mastodon-apps' ),
						count( $last_requests )
					)
				);

				foreach ( $last_requests as $request ) {
					output_request_log( $request, $rest_nonce );
				}
			}
		}
		?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Website', 'enable-mastodon-apps' ); ?></th>
					<td>
						<?php if ( $app->get_website() ) : ?>
							<a href="<?php echo esc_url( $app->get_website() ); ?>" target="_blank"><?php echo esc_html( $app->get_website() ); ?></a>
						<?php else : ?>
							<em><?php esc_html_e( 'No website provided by the app.', 'enable-mastodon-apps' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr class="debug-hide">
					<th scope="row"><?php esc_html_e( 'Redirect URI', 'enable-mastodon-apps' ); ?></th>
					<td>
						<?php echo wp_kses( implode( '<br>', $app->get_redirect_uris() ), array( 'br' => array() ) ); ?>
						<p class="description">
							<span><?php esc_html_e( 'The URI to redirect to after the user authorizes the app.', 'enable-mastodon-apps' ); ?></span>
							<span>
								<?php
								echo wp_kses(
									sprintf(
										// translators: %s is a link to the OAuth standard.
										__( 'This is part of the <a href="%s">OAuth standard</a> and only useful in debugging scenarios.', 'enable-mastodon-apps' ),
										'https://docs.joinmastodon.org/spec/oauth/'
									),
									array( 'a' => array( 'href' => array() ) )
								);
								?>
							</span>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Scopes', 'enable-mastodon-apps' ); ?></th>
					<td>
						<?php echo esc_html( $app->get_scopes() ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Created', 'enable-mastodon-apps' ); ?></th>
					<?php td_timestamp( $app->get_creation_date() ); ?>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Last Used', 'enable-mastodon-apps' ); ?></th>
					<?php td_timestamp( $app->get_last_used() ); ?>
				</tr>
				<tr class="post-formats">
					<th scope="row"><?php esc_html_e( 'Post Formats', 'enable-mastodon-apps' ); ?></th>
					<td>
						<fieldset>
						<?php foreach ( get_post_format_strings() as $format => $label ) : ?>
							<label><input type="checkbox" name="post_formats[]" value="<?php echo esc_attr( $format ); ?>"<?php checked( in_array( $format, $app->get_post_formats(), true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<span><?php esc_html_e( 'The post formats that will be used for this app.', 'enable-mastodon-apps' ); ?></span>
							<button id="toggle_all_post_formats" class="as-link"><?php esc_html_e( 'Toggle all', 'enable-mastodon-apps' ); ?></button>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row" class="create-post-type"><?php echo esc_html( _x( 'Create new posts as', 'select post type', 'enable-mastodon-apps' ) ); ?></th>
					<td>
						<select name="create_post_type">
						<?php
						foreach ( $_post_types as $post_type ) : // phpcs:ignore
							?>
								<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $post_type->name, $app->get_create_post_type() ); ?>><?php echo esc_html( $post_type->labels->singular_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<span><?php esc_html_e( 'When posting through the app, this post type will be created.', 'enable-mastodon-apps' ); ?></span>
						</p>
					</td>
				</tr>
				<tr class="view-post-types">
					<th scope="row" class="view-post-type"><?php esc_html_e( 'Show these post types', 'enable-mastodon-apps' ); ?></th>
					<td>
						<fieldset>
						<?php foreach ( $_post_types as $post_type ) : /* phpcs:ignore */ ?>
							<label><input type="checkbox" name="view_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>"<?php checked( in_array( $post_type->name, $app->get_view_post_types(), true ) ); ?> /> <?php echo esc_html( $post_type->label ); ?></label>
						<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<span><?php esc_html_e( 'These post types will be displayed in the app.', 'enable-mastodon-apps' ); ?></span>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<button class="button button-primary"><?php esc_html_e( 'Save', 'enable-mastodon-apps' ); ?></button>
		<button name="delete-app" data-confirm="<?php echo esc_attr( $confirm ); ?>" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button button-destructive">
			<?php
			echo esc_html(
				sprintf(
					// translators: %s is an app name.
					_x( 'Delete %s', 'app', 'enable-mastodon-apps' ),
					$app->get_client_name()
				)
			);
			?>
			</button>
		<script>
			document.getElementById( 'toggle_all_post_formats' ).onclick = function ( event ) {
				document.querySelectorAll( '.post-formats input[type="checkbox"]' ).forEach( function ( element ) {
					element.checked = ! element.checked;
				} );

				event.preventDefault();
				return false;
			}
		</script>

		<h3><?php esc_html_e( 'Access Tokens', 'enable-mastodon-apps' ); ?></h3>

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
					<tr id="token-<?php echo esc_attr( $token ); ?>">
						<td><?php echo esc_html( $user ); ?></td>
						<?php td_timestamp( $data['last_used'] ); ?>
						<?php td_timestamp( $data['expires'], true ); ?>
						<td><?php echo esc_html( $data['scope'] ); ?></td>
						<td><button name="delete-token" value="<?php echo esc_attr( $token ); ?>" data-confirm="<?php esc_attr_e( 'Do you really want to delete this access token?', 'enable-mastodon-apps' ); ?>" class="button button-destructive"><?php esc_html_e( 'Delete' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>

</form>
</div>
