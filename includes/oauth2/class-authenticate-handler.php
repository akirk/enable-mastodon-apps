<?php
/**
 * Authentication Handler
 *
 * This file implements handling the authentication.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\OAuth2;

use Enable_Mastodon_Apps\Mastodon_App;
use OAuth2\Request;
use OAuth2\Response;

/**
 * This class implements the Authentication handler.
 */
class Authenticate_Handler {
	public function handle( Request $request, Response $response ) {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$app = Mastodon_App::get_by_client_id( $_GET['client_id'] );
		if ( is_wp_error( $app ) ) {
			$response->setStatusCode( 404 );

			return $response;
		}

		$client_name = $app->get_client_name();

		$redirect_uri = $app->check_redirect_uri( $_GET['redirect_uri'] );
		if ( is_wp_error( $redirect_uri ) ) {
			return $redirect_uri;
		}

		$scopes = array();
		foreach ( explode( ' ', $_GET['scope'] ) as $scope ) {
			if ( $app->has_scope( $scope ) ) {
				$scopes[] = $scope;
			}
		}
		if ( empty( $scopes ) ) {
			$response->setError( 403, 'invalid_scopes', 'Invalid scope was requested.' );
			return $response;
		}

		$data = array(
			'user'            => wp_get_current_user(),
			'client_name'     => $client_name,
			'scopes'          => implode( ' ', $scopes ),
			'body_class_attr' => implode( ' ', array_diff( get_body_class(), array( 'error404' ) ) ),
			'cancel_url'      => $this->get_cancel_url( $request ),
			'form_url'        => home_url( '/oauth/authorize' ),
			'form_fields'     => $_GET,
		);

		$has_permission = current_user_can( 'edit_private_posts' );
		if ( ! $has_permission ) {
			login_header( 'Authorize Mastodon Client', null, new \WP_Error( 'no-permission', __( "You don't have permission to use Mastodon Apps.", 'enable-mastodon-apps' ) ) );
			$this->render_no_permission_screen( $data );
		} else {
			login_header( 'Authorize Mastodon Client' );
			$this->render_consent_screen( $data );
		}

		login_footer();

		return $response;
	}

	private function render_no_permission_screen( $data ) {
		?>
		<div id="openid-connect-authenticate">
			<div id="openid-connect-authenticate-form-container" class="login">
				<form class="wp-core-ui">
					<h2>
						<?php
						echo esc_html(
							sprintf(
							// translators: %s is a username.
								__( 'Hi %s!', 'enable-mastodon-apps' ),
								$data['user']->user_nicename
							)
						);
						?>
					</h2>
					<br/>
					<p><?php esc_html_e( "You don't have permission to use Mastodon Apps.", 'enable-mastodon-apps' ); ?></p>
					<br/>
					<p><?php esc_html_e( 'Contact your administrator for more details.', 'enable-mastodon-apps' ); ?></p>
					<br/>
					<p class="submit">
						<a class="button button-large" href="<?php echo esc_url( $data['cancel_url'] ); ?>" target="_top">
							<?php esc_html_e( 'Cancel', 'enable-mastodon-apps' ); ?>
						</a>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	private function render_consent_screen( $data ) {
		$scope_explanations = array(
			'read'   => __( 'Read information from your account, for example read your statuses.', 'enable-mastodon-apps' ),
			'write'  => __( 'Write information to your account, for example post a status on your behalf.', 'enable-mastodon-apps' ),
			'follow' => __( 'Follow other accounts using your account.', 'enable-mastodon-apps' ),
			'push'   => __( 'Subscribe to push events for your account.', 'enable-mastodon-apps' ),
		);

		$requested_scopes = array();
		foreach ( explode( ' ', $data['scopes'] ) as $scope ) {
			$p = strpos( $scope, ':' );
			if ( false === $p ) {
				$requested_scopes[ $scope ] = array( 'all' => true );
			} else {
				$main_scope = substr( $scope, 0, $p );
				if ( ! isset( $requested_scopes[ $main_scope ] ) ) {
					$requested_scopes[ $main_scope ] = array();
				}
				$requested_scopes[ $main_scope ][ substr( $scope, $p + 1 ) ] = true;
			}
		}

		?>
		<div id="openid-connect-authenticate">
			<div id="openid-connect-authenticate-form-container" class="login">
				<form method="post" action="<?php echo esc_url( $data['form_url'] ); ?>" class="wp-core-ui">
					<h2>
						<?php
						echo esc_html(
							sprintf(
							// translators: %s is a username.
								__( 'Hi %s!', 'enable-mastodon-apps' ),
								$data['user']->user_nicename
							)
						);
						?>
					</h2>
					<br/>
					<p>
						<?php
						echo wp_kses(
							sprintf(
								// translators: %1$s is the site name, %2$s is the username.
								__( 'Do you want to log in to <strong>%1$s</strong> with your <strong>%2$s</strong> account?', 'enable-mastodon-apps' ),
								$data['client_name'],
								get_bloginfo( 'name' )
							),
							array(
								'strong' => array(),
							)
						);
						?>
					</p>
					<br/>
					<p>
					<?php
					esc_html_e( 'Requested permissions:', 'enable-mastodon-apps' );
					?>
						</p>
					<ul style="margin-left: 1em">

						<?php
						foreach ( $requested_scopes as $main_scope => $subscopes ) {
							if ( ! isset( $scope_explanations[ $main_scope ] ) ) {
								continue;
							}
							echo '<li style="margin-top: .5em" title="', esc_attr( $main_scope ), '">', esc_html( $scope_explanations[ $main_scope ] );
							$all = isset( $subscopes['all'] ) && $subscopes['all'];
							unset( $subscopes['all'] );
							if ( ! empty( $subscopes ) && ! $all ) {
								echo ' ', __( 'Only the following sub-permissions:', 'enable-mastodon-apps' );
								echo '<ul style="margin-left: 1em">';
								foreach ( $subscopes as $subscope => $true ) {
									if ( ! isset( $scope_explanations[ $main_scope . ':' . $subscope ] ) ) {
										$scope_explanations[ $main_scope . ':' . $subscope ] = ucwords( $subscope ) . ' (' . $main_scope . ':' . $subscope . ')';
									}
									echo '<li style="margin-top: .5em" title="', esc_attr( $main_scope . ':' . $subscope ), '">', esc_html( $scope_explanations[ $main_scope . ':' . $subscope ] ), '</li>';
								}
								echo '</ul>';
							}
							echo '</li>';
						}
						?>
					</ul>
					<br/>
					<?php wp_nonce_field( 'wp_rest' ); /* The nonce will give the REST call the userdata. */ ?>
					<?php foreach ( $data['form_fields'] as $key => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"/>
					<?php endforeach; ?>
					<p class="submit">
						<input type="submit" name="authorize" class="button button-primary button-large" value="<?php esc_attr_e( 'Authorize', 'enable-mastodon-apps' ); ?>"/>
						<a href="<?php echo esc_url( $data['cancel_url'] ); ?>" target="_top">
							<?php esc_html_e( 'Cancel', 'enable-mastodon-apps' ); ?>
						</a>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	private function get_cancel_url( Request $request ) {
		return add_query_arg(
			array(
				'error'             => 'access_denied',
				'error_description' => 'Access denied! Permission not granted.',
				'state'             => $request->query( 'state' ),
			),
			$request->query( 'redirect_uri' )
		);
	}
}
