<?php
namespace Mastodon_API;

/**
 * The admin-specific functionality of the plugin.
 *
 */
class Mastodon_Admin {
	private $oauth;

	public function __construct( Mastodon_OAuth $oauth ) {
		$this->oauth = $oauth;
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	public function admin_menu() {
		$page_type = 'settings';

		// Only show the menu if installed standalone.
		$friends_settings_exist = '' !== menu_page_url( 'friends', false );
		if ( $friends_settings_exist && class_exists( '\Friends\Friends' )  ) {
			add_submenu_page(
				'friends',
				__( 'Mastodon API', 'mastodon-api' ),
				__( 'Mastodon API', 'mastodon-api' ),
				'edit_private_posts',
				'mastodon-api',
				array( $this, 'admin_page' )
			);
			$menu_title = __( 'Friends', 'friends' ) . \Friends\Friends::get_instance()->admin->get_unread_badge();
			$page_type = sanitize_title( $menu_title );
		} else {
			add_options_page(
				__( 'Mastodon API', 'mastodon-api' ),
				__( 'Mastodon API', 'mastodon-api' ),
				'edit_private_posts',
				'mastodon-api',
				array( $this, 'admin_page' )
			);
		}
		add_action( 'load-' . $page_type . '_page_mastodon-api', array( $this, 'process_admin' ) );
	}

	public function process_admin() {
		if ( ! current_user_can( 'edit_private_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the settings.', 'mastodon-api' ) );
		}

		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'mastodon-api' ) ) {
			return;
		}

		if ( isset( $_POST['delete-code'] ) ) {
			$deleted = $this->oauth->get_code_storage()->expireAuthorizationCode( $_POST['delete-code'] );
			add_settings_error( 'mastodon-api', 'deleted-codes', sprintf( _n( 'Deleted %d authorization code.', 'Deleted %d authorization codes.', $deleted ? 1 : 0, 'mastodon-api' ), $deleted ? 1 : 0 ) );
		} elseif ( isset( $_POST['delete-token'] ) ) {
			$deleted = $this->oauth->get_token_storage()->unsetAccessToken( $_POST['delete-token'] );
			add_settings_error( 'mastodon-api', 'deleted-tokens', sprintf( _n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted ? 1 : 0, 'mastodon-api' ), $deleted ? 1 : 0 ) );
		} elseif ( isset( $_POST['delete-app'] ) ) {
			$deleted = Mastodon_App::get_by_client_id( $_POST['delete-app'] )->delete();
			add_settings_error( 'mastodon-api', 'deleted-apps', sprintf( _n( 'Deleted %d app.', 'Deleted %d apps.', $deleted ? 1 : 0, 'mastodon-api' ), $deleted ? 1 : 0 ) );
		} elseif ( isset( $_POST['delete-outdated'] ) ) {
			$deleted = OAuth2\AccessTokenStorage::cleanupOldTokens();
			if ( $deleted ) {
				add_settings_error( 'mastodon-api', 'deleted-tokens', sprintf( _n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted, 'mastodon-api' ), $deleted ) );
			}

			$deleted = OAuth2\AuthorizationCodeStorage::cleanupOldCodes();
			if ( $deleted ) {
				add_settings_error( 'mastodon-api', 'deleted-codes', sprintf( _n( 'Deleted %d authorization code.', 'Deleted %d authorization codes.', $deleted, 'mastodon-api' ), $deleted ) );
			}

			$deleted = Mastodon_App::delete_outdated();
			if ( $deleted ) {
				add_settings_error( 'mastodon-api', 'deleted-apps', sprintf( _n( 'Deleted %d app.', 'Deleted %d apps.', $deleted, 'mastodon-api' ), $deleted ) );
			}
		} else {
			if ( isset( $_POST['mastodon_api_enable_logins'] ) ) {
				delete_option( 'mastodon_api_disable_logins' );
			} else {
				update_option( 'mastodon_api_disable_logins', '1' );
			}
		}
	}

	public function admin_page() {
		$codes = OAuth2\AuthorizationCodeStorage::getAll();
		$tokens = OAuth2\AccessTokenStorage::getAll();
		$apps = Mastodon_App::get_all();
		?>
		<div class="wrap">
		<h1><?php esc_html_e( 'Mastodon API', 'mastodon-api' ); ?></h1>
		<?php settings_errors(); ?>

		<form method="post">
			<?php wp_nonce_field( 'mastodon-api' ); ?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Logins', 'mastodon-api' ); ?></th>
						<td>
							<fieldset>
								<label for="mastodon_api_enable_logins">
									<input name="mastodon_api_enable_logins" type="checkbox" id="mastodon_api_enable_logins" value="1" <?php checked( '1', ! get_option( 'mastodon_api_disable_logins' ) ); ?> />
									<?php esc_html_e( 'Allow new logins via the Mastodon API', 'mastodon-api' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<button class="button button-primary"><?php esc_html_e( 'Save' ); ?></button>
			<?php if ( ! empty( $codes ) ) : ?>
				<h2>Authorization Codes</h2>

				<table class="widefat striped">
					<thead>
						<th>App</th>
						<th>Redirect URI</th>
						<th>Expires</th>
						<th>Expired</th>
						<th>Scope</th>
						<th>Actions</th>
					</thead>
					<tbody>
						<?php
						foreach ( $codes as $code => $data ) {
							?>
							<tr id="code-<?php echo esc_attr( $code ); ?>" title="<?php echo esc_attr( $code ); ?>">
								<td>
									<?php
									if ( isset( $apps[ $data['client_id'] ] ) ) {
										echo esc_html( $apps[ $data['client_id'] ]->get_client_name() );
									} else {
										echo esc_html( 'Unknown: ' . $data['client_id'] );
									}
									?>
								</td>
								<td><?php echo esc_html( $data['redirect_uri'] ); ?></td>
								<td><?php echo esc_html( $data['expires'] ); ?></td>
								<td><?php echo esc_html( $data['expired'] ? 'expired' : '' ); ?></td>
								<td><?php echo esc_html( $data['scope'] ); ?></td>
								<td><button name="delete-code" value="<?php echo esc_attr( $code ); ?>" class="button">delete</button></td>
							</tr>
							<?php
						}
					?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $tokens ) ) : ?>
				<h2>Access Tokens</h2>
				<table class="widefat striped">
					<thead>
						<th>App</th>
						<th>Expires</th>
						<th>Expired</th>
						<th>Scope</th>
						<th>Actions</th>
					</thead>
					<tbody>
						<?php
						foreach ( $tokens as $token => $data ) {
							?>
							<tr id="token-<?php echo esc_attr( $token ); ?>" title="<?php echo esc_attr( $token ); ?>">
								<td>
									<?php
									if ( isset( $apps[ $data['client_id'] ] ) ) {
										?><a href="#app-<?php echo esc_attr( $data['client_id'] ); ?>"><?php echo esc_html( $apps[ $data['client_id'] ]->get_client_name() ); ?></a><?php
									} else {
										echo esc_html( 'Unknown: ' . $data['client_id'] );
									}
									?>
								</td>
								<td><?php echo esc_html( $data['expires'] ); ?></td>
								<td><?php echo esc_html( $data['expired'] ? 'expired' : '' ); ?></td>
								<td><?php echo esc_html( $data['scope'] ); ?></td>
								<td><button name="delete-token" value="<?php echo esc_attr( $token ); ?>" class="button">delete</button></td>
							</tr>
							<?php
						}
					?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $apps ) ) : ?>
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
						foreach ( $apps as $app ) {
							?>
							<tr id='app-<?php echo esc_attr( $app->get_client_id() ); ?>'>
								<td>
									<?php
									if ( $app->get_website() ) {
										?><a href="<?php echo esc_url( $app->get_website() ); ?>"><?php echo esc_html( $app->get_client_name() ); ?></a><?php
									} else {
										echo esc_html( $app->get_client_name() );
									}
									?>
								</td>
								<td><?php echo wp_kses( implode('<br/>', $app->get_redirect_uris() ), array( 'br' => array() ) ); ?></td>
								<td><?php echo esc_html( $app->get_scopes() ); ?></td>
								<td><?php echo esc_html( $app->get_creation_date() ); ?></td>
								<td><button name="delete-app" value="<?php echo esc_attr( $app->get_client_id() ); ?>" class="button">delete</button></td>
							</tr>
							<?php
						}
					?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $codes ) || ! empty( $tokens ) || ! empty( $apps ) ) : ?>
				<h2><?php esc_html_e( 'Cleanup', 'mastodon-api' ); ?></h2>
					<button name="delete-outdated" class="button"><?php esc_html_e( 'Delete outdated apps and tokens', 'mastodon-api' ); ?></button>
			<?php endif; ?>
			</form>
		</div>
		<?php

	}
}
