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
$_post_types = \get_post_types( array( 'show_ui' => true ), 'objects' );
$app = $args['app'];

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
				?>
				<div id='applog-<?php echo esc_attr( $app->get_client_id() ); ?>' class="<?php echo $alternate ? 'alternate' : ''; ?>">
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
				</div>
				<?php
			}
		}
		?>

		<table class="form-table">
			<tbody>
				<tr class="debug-hide">
					<th scope="row"><?php esc_html_e( 'Redirect URI', 'enable-mastodon-apps' ); ?></th>
					<td>
						<?php echo wp_kses( implode( '<br>', $app->get_redirect_uris() ), array( 'br' => array() ) ); ?>
						<p class="description"><?php esc_html_e( 'The URI to redirect to after the user authorizes the app.', 'enable-mastodon-apps' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Scopes', 'enable-mastodon-apps' ); ?></th>
					<td>
						<?php echo wp_kses( str_replace( ' ', '<br>', $app->get_scopes() ), array( 'br' => array() ) ); ?>
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
				<tr>
					<th scope="row" class="post-formats"><?php esc_html_e( 'Post Formats', 'enable-mastodon-apps' ); ?></th>
					<td>
						<?php post_format_select( 'post_formats', $app->get_post_formats() ); ?>
						<p class="description">
							<span><?php esc_html_e( 'The post formats that will be used for this app.', 'enable-mastodon-apps' ); ?></span>
							<span><?php esc_html_e( 'You can select multiple using the Ctrl or Cmd key.', 'enable-mastodon-apps' ); ?></span>
							<button id="select_all_post_formats" class="as-link"><?php esc_html_e( 'Select all', 'enable-mastodon-apps' ); ?></button>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row" class="create-post-type"><?php echo esc_html( _x( 'Create new posts as', 'select post type', 'enable-mastodon-apps' ) ); ?></th>
					<td>
						<select name="create_post_type">
						<?php
						foreach ( $_post_types as $post_type ) :
							?>
								<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $post_type->name, $app->get_create_post_type() ); ?>><?php echo esc_html( $post_type->labels->singular_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<span><?php esc_html_e( 'When posting through the app, this post type will be created.', 'enable-mastodon-apps' ); ?></span>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row" class="view-post-type"><?php esc_html_e( 'Show these post types', 'enable-mastodon-apps' ); ?></th>
					<td>
						<select name="view_post_types[]" multiple>
						<?php
						foreach ( $_post_types as $post_type ) :
							?>
								<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( in_array( $post_type->name, $app->get_view_post_types(), true ) ); ?>><?php echo esc_html( $post_type->label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<span><?php esc_html_e( 'These post types will be displayed in the app.', 'enable-mastodon-apps' ); ?></span>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<button><?php esc_html_e( 'Save', 'enable-mastodon-apps' ); ?></button>
		<script>
			document.getElementById( 'select_all_post_formats' ).onclick = function ( event ) {
				Array.from( document.getElementById( 'app_post_formats' ).options ).forEach( item => item.selected = true )
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

</form>
</div>
