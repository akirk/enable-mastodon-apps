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
								<input name="mastodon_api_posting_cpt" type="checkbox" id="mastodon_api_posting_cpt" value="1" <?php checked( ! get_option( 'mastodon_api_posting_cpt' ) ); ?> />
								<span><?php esc_html_e( 'Hide posts through Mastodon apps from appearing on the WordPress frontend', 'enable-mastodon-apps' ); ?></span>
							</label>
						</fieldset>
						<p class="description">
							<span>
								<?php
								echo wp_kses(
									sprintf(
										// translators: %s: Help tab link.
										__( 'To understand this setting better, please check <a href="" onclick="%s">Help tab</a> for some use cases.', 'enable-mastodon-apps' ),
										'document.getElementById(\'contextual-help-link\').click(); return false;'
									),
									array(
										'a' => array(
											'href'    => true,
											'onclick' => true,
										),
									)
								);
								?>
							</span>
							<br>
							<span>
								<?php
								echo wp_kses(
									sprintf(
										// translators: %1$s: URL to the Mastodon API settings page Registered Apps tab, %2$s: Registered Apps tab title.
										__( 'This setting only applies to newly registered apps, but you can change it individually for each app on the <a href="%1$s">%2$s</a> page.', 'enable-mastodon-apps' ),
										esc_url( admin_url( 'admin.php?page=enable-mastodon-apps-settings&tab=registered-apps' ) ),
										__( 'Registered Apps', 'enable-mastodon-apps' )
									),
									array( 'a' => array( 'href' => array() ) )
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
