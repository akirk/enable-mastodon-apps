<?php
namespace Friends;

/**
 * The admin-specific functionality of the plugin.
 *
 */
class Mastodon_Admin {
	private $friends;

	public function __construct( $friends ) {
		$this->friends = $friends;
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	public function admin_menu() {
		// Only show the menu if installed standalone.
		$friends_settings_exist = '' !== menu_page_url( 'friends', false );
		if ( $friends_settings_exist ) {
			add_submenu_page(
				'friends',
				__( 'Mastodon', 'friends' ),
				__( 'Mastodon', 'friends' ),
				'edit_private_posts',
				'friends-mastodon',
				array( $this, 'admin_page' )
			);
		} else {
			add_menu_page( 'friends', __( 'Friends', 'friends' ), 'edit_private_posts', 'friends-mastodon', null, 'dashicons-groups', 3 );
			add_submenu_page(
				'friends',
				__( 'Mastodon', 'friends' ),
				__( 'Mastodon', 'friends' ),
				'edit_private_posts',
				'friends-mastodon',
				array( $this, 'admin_page' )
			);
		}
	}

	public function admin_page() {
		?>
		<div class="wrap">
		<h1><?php esc_html_e( 'Mastodon', 'friends' ); ?></h1>
		<h2>Authorization Codes</h2>

		<table class="widefat striped">
			<thead>
				<th>Code</th>
				<th>Client ID</th>
				<th>Redirect URI</th>
				<th>Expires</th>
				<th>Scope</th>
				<th>Actions</th>
			</thead>
			<tbody>
				<?php
				foreach ( OAuth2\AuthorizationCodeStorage::getAll() as $code => $data ) {
					?>
					<tr id='code-<?php echo esc_attr( $code ); ?>'>
						<td></td>
						<td><?php echo esc_html( $data['client_id'] ); ?></td>
						<td><?php echo esc_html( $data['redirect_uri'] ); ?></td>
						<td><?php echo esc_html( $data['expires'] ); ?></td>
						<td><?php echo esc_html( $data['scope'] ); ?></td>
						<td>delete</td>
					</tr>
					<?php
				}
			?>
			</tbody>
		</table>

		<h2>Access Tokens</h2>
		<table class="widefat striped">
			<thead>
				<th>Token</th>
				<th>Client ID</th>
				<th>Expires</th>
				<th>Expired</th>
				<th>Scope</th>
				<th>Actions</th>
			</thead>
			<tbody>
				<?php
				foreach ( OAuth2\AccessTokenStorage::getAll() as $token => $data ) {
					?>
					<tr id='token-<?php echo esc_attr( $token ); ?>'>
						<td><?php echo esc_html( $data['access_data'] ); ?></td>
						<td><?php echo esc_html( $data['client_id'] ); ?></td>
						<td><?php echo esc_html( $data['expires'] ); ?></td>
						<td><?php echo esc_html( $data['expired'] ? 'expired' : '' ); ?></td>
						<td><?php echo esc_html( $data['scope'] ); ?></td>
						<td>delete</td>
					</tr>
					<?php
				}
			?>
			</tbody>
		</table>

		<h2>Apps</h2>

		<table class="widefat striped">
			<thead>
				<th>Name</th>
				<th>Redirect URI</th>
				<th>Scope</th>
				<th>Created</th>
				<th>Actions</th>
			</thead>
			<tbody>
				<?php
				foreach ( Mastodon_App::get_all() as $app ) {
					?>
					<tr id='client-<?php echo esc_attr( $app->get_client_id() ); ?>'>
						<td><?php echo esc_html( $app->get_client_name() ); ?></td>
						<td><ul><li><?php echo wp_kses( implode('</li><li>', $app->get_redirect_uris() ), array( 'li' => array() ) ); ?></li></ul></td>
						<td><?php echo esc_html( $app->get_scopes() ); ?></td>
						<td><?php echo esc_html( $app->get_creation_date() ); ?></td>
						<td>delete</td>
					</tr>
					<?php
				}
			?>
			</tbody>
		</table>
		</div>
		<?php

	}
}
