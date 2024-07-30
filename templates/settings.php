<?php
// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'active'       => 'settings',
		'enable_debug' => $args['enable_debug'],
	)
);
?>

<div class="enable-mastodon-apps-settings enable-mastodon-apps-settings-page">
	<form method="post">
		<?php wp_nonce_field( 'enable-mastodon-apps' ); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Logins', 'enable-mastodon-apps' ); ?></th>
					<td>
						<fieldset>
							<label for="mastodon_api_enable_logins">
								<input name="mastodon_api_enable_logins" type="checkbox" id="mastodon_api_enable_logins" value="1" <?php checked( '1', ! get_option( 'mastodon_api_disable_logins' ) ); ?> />
								<span><?php esc_html_e( 'Allow new and existing apps to sign in', 'enable-mastodon-apps' ); ?></span>
							</label>
						</fieldset>
						<p class="description"><?php esc_html_e( 'New apps can register on their own, so the list might grow if you keep this enabled.', 'enable-mastodon-apps' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Debugging', 'enable-mastodon-apps' ); ?></th>
					<td>
						<fieldset>
							<label for="mastodon_api_enable_debug">
								<input name="mastodon_api_enable_debug" type="checkbox" id="mastodon_api_enable_debug" value="1" <?php checked( '1', get_option( 'mastodon_api_enable_debug' ) ); ?> />
								<span><?php esc_html_e( 'Enable debugging settings', 'enable-mastodon-apps' ); ?></span>
							</label>
						</fieldset>
						<p class="description"><?php esc_html_e( 'This will enable the Tester and Debug tab, and add more information about registered apps.', 'enable-mastodon-apps' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<button class="button button-primary"><?php esc_html_e( 'Save' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></button>
	</form>
</div>
