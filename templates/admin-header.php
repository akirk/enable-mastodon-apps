<?php
// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable

function output_request_log( $request, $rest_nonce ) {
	$date = \DateTimeImmutable::createFromFormat( 'U.u', $request['timestamp'] );
	$url  = add_query_arg(
		array(
			'_wpnonce' => $rest_nonce,
			'_pretty'  => 1,
		),
		$request['path']
	);
	$meta = array_diff_key( $request, array_flip( array( 'timestamp', 'path', 'method' ) ) );
	if ( empty( $meta ) ) {
		echo '<div style="margin-left: 1.5em">';
	} else {
		echo '<details><summary>';
	}
	if ( isset( $request['app'] ) ) {
		echo esc_html( $request['app']->get_client_name() );
	}
	?>
	[<?php echo esc_html( $date->format( 'Y-m-d H:i:s.v' ) ); ?>]
	<?php echo esc_html( ! empty( $request['status'] ) ? $request['status'] : 200 ); ?>
	<?php echo esc_html( $request['method'] ); ?>
	<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $request['path'] ); ?></a>
	<?php
	if ( ! empty( $meta ) ) {
		echo '</summary>';
		echo '<pre style="padding-left: 1em; border-left: 3px solid #999; margin: 0">';
		foreach ( $meta as $key => $value ) {
			echo esc_html( $key ), ' ', esc_html( var_export( $value, true ) ), '<br/>';
		}
		echo '</pre>';
		echo '</details>';
	} else {
		echo '</div>';
	}
}
function td_timestamp( $timestamp, $strikethrough_past = false ) {
	?>
	<td>
		<abbr title="<?php echo esc_attr( is_numeric( $timestamp ) ? gmdate( 'r', $timestamp ) : $timestamp ); ?>">
	<?php
	if ( ! $timestamp ) {
		echo esc_html( _x( 'Never', 'Code was never used', 'enable-mastodon-apps' ) );
	} elseif ( $timestamp > time() ) {
		echo esc_html(
			sprintf(
				// translators: %s is a relative time.
				__( 'in %s', 'enable-mastodon-apps' ),
				human_time_diff( $timestamp )
			)
		);
	} elseif ( $strikethrough_past ) {
		echo '<strike>';
		echo esc_html(
			sprintf(
				// translators: %s: Human-readable time difference.
				__( '%s ago' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				human_time_diff( $timestamp )
			)
		);
		echo '</strike>';
	} else {
		echo esc_html(
			sprintf(
				// translators: %s: Human-readable time difference.
				__( '%s ago' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				human_time_diff( $timestamp )
			)
		);
	}
	?>
		</abbr>
	</td>
	<?php
}

?>
<div class="enable-mastodon-apps-settings-header">
	<div class="enable-mastodon-apps-settings-title-section">
		<h1><?php \esc_html_e( 'Enable Mastodon Apps', 'enable-mastodon-apps' ); ?></h1>
	</div>

	<nav class="enable-mastodon-apps-settings-tabs-wrapper" aria-label="<?php \esc_attr_e( 'Secondary menu', 'enable-mastodon-apps' ); ?>">
		<a href="<?php echo \esc_url_raw( admin_url( 'options-general.php?page=enable-mastodon-apps' ) ); ?>" class="enable-mastodon-apps-settings-tab <?php echo \esc_attr( 'welcome' === ( $args['active'] ?? '' ) ? 'active' : '' ); ?>">
			<?php \esc_html_e( 'Welcome', 'enable-mastodon-apps' ); ?>
		</a>

		<a href="<?php echo \esc_url_raw( admin_url( 'options-general.php?page=enable-mastodon-apps&tab=settings' ) ); ?>" class="enable-mastodon-apps-settings-tab <?php echo \esc_attr( 'settings' === ( $args['active'] ?? '' ) ? 'active' : '' ); ?>">
			<?php \esc_html_e( 'Settings', 'enable-mastodon-apps' ); ?>
		</a>

		<a href="<?php echo \esc_url_raw( admin_url( 'options-general.php?page=enable-mastodon-apps&tab=registered-apps' ) ); ?>" class="enable-mastodon-apps-settings-tab <?php echo \esc_attr( 'registered-apps' === ( $args['active'] ?? '' ) ? 'active' : '' ); ?>">
			<?php \esc_html_e( 'Registered Apps', 'enable-mastodon-apps' ); ?>
		</a>

		<?php if ( $args['enable_debug'] ) : ?>
		<a href="<?php echo \esc_url_raw( admin_url( 'options-general.php?page=enable-mastodon-apps&tab=tester' ) ); ?>" class="enable-mastodon-apps-settings-tab <?php echo \esc_attr( 'tester' === ( $args['active'] ?? '' ) ? 'active' : '' ); ?>">
			<?php \esc_html_e( 'Tester', 'enable-mastodon-apps' ); ?>
		</a>

		<a href="<?php echo \esc_url_raw( admin_url( 'options-general.php?page=enable-mastodon-apps&tab=debug' ) ); ?>" class="enable-mastodon-apps-settings-tab <?php echo \esc_attr( 'debug' === ( $args['active'] ?? '' ) ? 'active' : '' ); ?>">
			<?php \esc_html_e( 'Debug', 'enable-mastodon-apps' ); ?>
		</a>
		<?php endif; ?>

	</nav>
</div>
<hr class="wp-header-end">
<?php
if ( isset( $_GET['success'] ) ) {
	?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( wp_unslash( $_GET['success'] ) ); ?></p></div>
	<?php
}
