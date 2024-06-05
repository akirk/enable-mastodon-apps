<?php
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'active'       => 'tester',
		'enable_debug' => true,
	)
);

?>
<div class="enable-mastodon-apps-settings enable-mastodon-apps-tester-page">
<div class="box">
	<h2>Test if everything works</h2>
	<p>
	<?php esc_html_e( 'To test whether the Mastodon API is working correctly, we\'ve bundled an API tester.', 'enable-mastodon-apps' ); ?>
	<?php esc_html_e( 'This tester will use the same API endpoints that Mastodon Apps will use to interact with your site.', 'enable-mastodon-apps' ); ?>
</p>
</div>
<div class="box">
	<iframe src="<?php echo esc_url( add_query_arg( 'url', home_url(), plugins_url( 'tester.html', __DIR__ ) ) ); ?>" width="800" height="500"></iframe>
</div>
</div>
