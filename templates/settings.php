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
					<th scope="row"><?php esc_html_e( 'Posting', 'enable-mastodon-apps' ); ?></th>
					<td>
						<fieldset>
							<label for="mastodon_api_posting_cpt">
								<input name="mastodon_api_posting_cpt" type="checkbox" id="mastodon_api_posting_cpt" value="1" <?php checked( 'post' === get_option( 'mastodon_api_posting_cpt' ) ); ?> />
								<span><?php esc_html_e( 'Make posts through Mastodon apps appear on this WordPress', 'enable-mastodon-apps' ); ?></span>
							</label>
						</fieldset>
						<p class="description">
							<span><?php esc_html_e( 'New apps will be automatically assigned to this setting.', 'enable-mastodon-apps' ); ?></span>
							<span>
								<?php
								echo wp_kses(
									sprintf(
										// translators: %s: URL to the Mastodon API settings.
										__( 'You can change this setting for each app on the <a href="%s">Mastodon API settings</a>.', 'enable-mastodon-apps' ),
										esc_url( admin_url( 'admin.php?page=enable-mastodon-apps-settings' ) )
									),
									array( 'a' => array( 'href' => array() ) )
								);
								?>
							</span><br>
							<span>
								<?php
								echo wp_kses(
									sprintf(
										// translators: %s: Post type.
										__( 'When set, posts through Mastodon apps will have the post type: %s', 'enable-mastodon-apps' ),
										'<tt>' . \Enable_Mastodon_Apps\Mastodon_API::POST_CPT . '</tt>'
									),
									array( 'tt' => true )
								);
								?>
							</span>
						</p>
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
