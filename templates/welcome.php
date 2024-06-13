<?php
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'active'       => 'welcome',
		'enable_debug' => $args['enable_debug'],
	)
);
?>

<div class="enable-mastodon-apps-settings enable-mastodon-apps-welcome-page">
	<div class="box">
		<h2><?php esc_html_e( 'Welcome', 'enable-mastodon-apps' ); ?></h2>
		<p>
			<span><?php esc_html_e( 'This plugin enables you to access your WordPress with Mastodon clients.', 'enable-mastodon-apps' ); ?></span>
			<span>
			<?php
				echo wp_kses(
					sprintf(
						// translators: %s: URL to Mastodon app directory.
						__( 'Check the <a href=%s>Mastodon app directory</a> to find one that you like.', 'enable-mastodon-apps' ),
						'"https://joinmastodon.org/apps" target="_blank"'
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
			<?php esc_html_e( 'When you first start a Mastodon app, it will ask you for your instance URL:', 'enable-mastodon-apps' ); ?>
		</p>
		<input type="text" class="regular-text copyable" id="enable-mastodon-apps-instance" value="<?php echo esc_attr( $args['instance_url'] ); ?>" readonly="readonly">
		<p>
			<span><?php esc_html_e( 'The Mastodon app will then redirect you to the login page of your own WordPress site.', 'enable-mastodon-apps' ); ?></span>
			<span><?php esc_html_e( 'Double-check the URL of the login form so that you don\'t enter your details on another site.', 'enable-mastodon-apps' ); ?></span>
			<span><?php esc_html_e( 'You\'ll need to log in there and then authorize the app.', 'enable-mastodon-apps' ); ?></span>
		</p>
		<p>
			<?php esc_html_e( 'Depending on the plugins you use (see below), you\'ll then see posts in the timeline and can start interacting with them.', 'enable-mastodon-apps' ); ?>
		</p>
		<p>
			<span><?php esc_html_e( 'Since the task of this plugin to achieve Mastodon API compatibility is complex, it is possible that you encounter issues.', 'enable-mastodon-apps' ); ?></span>
			<span>
			<?php
				echo wp_kses(
					sprintf(
						// translators: %1$s: URL to Github issues, %2$s: URL to WordPress.org plugin support forum.
						__( 'Please reach out in a <a href=%1$s>Github issue</a> or the <a href=%2$s>WordPress.org plugin support forum</a>.', 'enable-mastodon-apps' ),
						'"https://github.com/akirk/enable-mastodon-apps/issues" target="_blank"',
						'"https://wordpress.org/support/plugin/enable-mastodon-apps" target="_blank"'
					),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				);
				?>
				</span>
		</p>
	</div>
	<div class="box plugin-recommendations">
		<h2><?php esc_html_e( 'Recommended Plugins', 'enable-mastodon-apps' ); ?></h2>
		<p>
			<span><?php esc_html_e( 'The Enable Mastodon Apps plugin works well on its own: it allows you to publish posts and show your all blog posts in the apps\' timelines.', 'enable-mastodon-apps' ); ?></span>
			<span><?php esc_html_e( 'This can already be very useful on a blog where multiple people interact.', 'enable-mastodon-apps' ); ?></span>
		</p>

		<p>
			<span><?php esc_html_e( 'That said, it was designed so that it can be meaningfully extended by other plugins.', 'enable-mastodon-apps' ); ?></span>
			<span><?php esc_html_e( 'In particular this is the case for the following plugins:', 'enable-mastodon-apps' ); ?></span>
		</p>
	</div>
	<div class="enable-mastodon-apps-settings-accordion">
		<h4 class="enable-mastodon-apps-settings-accordion-heading">
			<button aria-expanded="false" class="enable-mastodon-apps-settings-accordion-trigger" aria-controls="enable-mastodon-apps-settings-accordion-block-activitypub-plugin" type="button">
				<span class="title">
					<?php \esc_html_e( 'Interact with the Fediverse', 'enable-mastodon-apps' ); ?>
					<?php if ( defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ) : ?>
						✅
					<?php endif; ?>
				</span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="enable-mastodon-apps-settings-accordion-block-activitypub-plugin" class="enable-mastodon-apps-settings-accordion-panel plugin-card-activitypub" hidden="hidden">
			<p>
				<span><?php \esc_html_e( 'The Activitypub plugin allows you interact with the Fediverse, for example Mastodon.', 'enable-mastodon-apps' ); ?></span>
				<span><?php \esc_html_e( 'With this plugin installed, for example, you can search for Mastodon users and view their posts.', 'enable-mastodon-apps' ); ?></span>
			</p>
			<?php if ( defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ) : ?>
				<p><strong><?php \esc_html_e( 'The Activitypub plugin is installed.', 'enable-mastodon-apps' ); ?></strong></p>
			<?php else : ?>
				<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=activitypub&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Activitypub Plugin', 'enable-mastodon-apps' ); ?></a></p>
			<?php endif; ?>
		</div>

		<h4 class="enable-mastodon-apps-settings-accordion-heading">
			<button aria-expanded="false" class="enable-mastodon-apps-settings-accordion-trigger" aria-controls="enable-mastodon-apps-settings-accordion-block-friends-plugin" type="button">
				<span class="title">
					<?php \esc_html_e( 'Following Others', 'enable-mastodon-apps' ); ?>
					<?php if ( defined( 'FRIENDS_VERSION' ) ) : ?>
						✅
					<?php endif; ?>
				</span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="enable-mastodon-apps-settings-accordion-block-friends-plugin" class="enable-mastodon-apps-settings-accordion-panel plugin-card-friends" hidden="hidden">
			<p>
				<span><?php \esc_html_e( 'The Friends plugin allows you to follow others from within your own WordPress.', 'enable-mastodon-apps' ); ?></span>
				<span><?php \esc_html_e( 'This will give you a feed of your friends\' posts which will also be displayed in the Enable Mastodon Apps plugin.', 'enable-mastodon-apps' ); ?></span>
				<span><?php \esc_html_e( 'Combined with ActivityPub plugin you can also follow and interact with people over the ActivityPub protocol, for example on Mastodon.', 'enable-mastodon-apps' ); ?></span>
			</p>
			<?php if ( defined( 'FRIENDS_VERSION' ) ) : ?>
				<p><strong><?php \esc_html_e( 'The Friends plugin is installed.', 'enable-mastodon-apps' ); ?></strong></p>
			<?php else : ?>
			<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=friends&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Friends Plugin', 'enable-mastodon-apps' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>

</div>
