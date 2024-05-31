<?php
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'active' => 'welcome',
	)
);
?>

<div class="enable-mastodon-apps-settings enable-mastodon-apps-welcome-page hide-if-no-js">
	<div class="box">
		<h2><?php esc_html_e( 'Welcome', 'enable-mastodon-apps' ); ?></h2>
	</div>
	<div class="box">
		<h2><?php esc_html_e( 'Recommended Plugins', 'enable-mastodon-apps' ); ?></h2>
		<p><span><?php esc_html_e( 'On its own, this plugin allows you to publish status posts and show your own posts in the apps\' timelines.', 'enable-mastodon-apps' ); ?> <span><?php esc_html_e( 'To make the plugin more useful, you can install the following plugins:', 'enable-mastodon-apps' ); ?></span></p>

		<?php if ( $args['activitypub_installed'] ) : ?>
			<details><summary><?php esc_html_e( 'The ActivityPub plugin is already installed.', 'enable-mastodon-apps' ); ?></summary>
		<?php endif; ?>
		<p><?php esc_html_e( 'The ActivityPub plugin connects your blog to the fediverse: Other people can follow your WordPress using a Mastodon account. It also provides functionality for communicating with the ActivityPub protocol to other plugins.', 'enable-mastodon-apps' ); ?></p>
		<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=activitypub&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Activitypub Plugin', 'enable-mastodon-apps' ); ?></a></p>
		<?php if ( $args['activitypub_installed'] ) : ?>
			</details>
		<?php endif; ?>

		</p>
	</div>
</div>
