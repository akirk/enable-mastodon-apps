<?php

namespace Friends\OAuth2;

use Friends\Mastodon_App;
use OAuth2\Request;
use OAuth2\Response;

class AuthenticateHandler {
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

		$scopes = $app->check_scopes( $_GET['scope'] );
		if ( is_wp_error( $scopes ) ) {
			return $scopes;
		}

		$data = array(
			'user'            => wp_get_current_user(),
			'client_name'     => $client_name,
			'body_class_attr' => implode( ' ', array_diff( get_body_class(), array( 'error404' ) ) ),
			'cancel_url'      => $this->get_cancel_url( $request ),
			'form_url'        => home_url( '/oauth/authorize' ),
			'form_fields'     => $_GET,
		);

		$has_permission = current_user_can( \Friends\Friends::REQUIRED_ROLE );
		if ( ! $has_permission ) {
			login_header( 'Authorize Mastodon Client', null, new \WP_Error( 'OIDC_NO_PERMISSION', __( "You don't have permission to use OpenID Connect.", 'friends' ) ) );
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
								__( 'Hi %s!', 'wp-openid-connect-server' ),
								$data['user']->user_nicename
							)
						);
						?>
					</h2>
					<br/>
					<p><?php esc_html_e( "You don't have permission to use OpenID Connect.", 'wp-openid-connect-server' ); ?></p>
					<br/>
					<p><?php esc_html_e( 'Contact your administrator for more details.', 'wp-openid-connect-server' ); ?></p>
					<br/>
					<p class="submit">
						<a class="button button-large" href="<?php echo esc_url( $data['cancel_url'] ); ?>" target="_top">
							<?php esc_html_e( 'Cancel', 'wp-openid-connect-server' ); ?>
						</a>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	private function render_consent_screen( $data ) {
		?>
		<div id="openid-connect-authenticate">
			<div id="openid-connect-authenticate-form-container" class="login">
				<form method="post" action="<?php echo esc_url( $data['form_url'] ); ?>" class="wp-core-ui">
					<h2>
						<?php
						echo esc_html(
							sprintf(
							// translators: %s is a username.
								__( 'Hi %s!', 'wp-openid-connect-server' ),
								$data['user']->user_nicename
							)
						);
						?>
					</h2>
					<br/>
					<p>
						<label>
							<?php
							echo wp_kses(
								sprintf(
								// translators: %1$s is the site name, %2$s is the username.
									__( 'Do you want to log in to <em>%1$s</em> with your <em>%2$s</em> account?', 'wp-openid-connect-server' ),
									$data['client_name'],
									get_bloginfo( 'name' )
								),
								array(
									'em' => array(),
								)
							);
							?>
						</label>
					</p>
					<br/>
					<?php wp_nonce_field( 'wp_rest' ); /* The nonce will give the REST call the userdata. */ ?>
					<?php foreach ( $data['form_fields'] as $key => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"/>
					<?php endforeach; ?>
					<p class="submit">
						<input type="submit" name="authorize" class="button button-primary button-large" value="<?php esc_attr_e( 'Authorize', 'wp-openid-connect-server' ); ?>"/>
						<a href="<?php echo esc_url( $data['cancel_url'] ); ?>" target="_top">
							<?php esc_html_e( 'Cancel', 'wp-openid-connect-server' ); ?>
						</a>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	private function redirect( Request $request ) {
		// Rebuild request with all parameters and send to authorize endpoint.
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array( '_wpnonce' => wp_create_nonce( 'wp_rest' ) ),
					$request->getAllQueryParameters()
				),
				home_url( '/oauth/authorize' )
			)
		);
	}

	/**
	 * TODO: Remove this function in favour of ClientCredentialsStorage?
	 */
	private function get_client_name( Request $request ) {
		$client_id = $request->query( 'client_id' );

		if ( ! isset( $this->clients[ $client_id ] ) ) {
			return '';
		}

		$client = $this->clients[ $client_id ];

		if ( empty( $client['name'] ) ) {
			return '';
		}

		return $client['name'];
	}

	private function get_cancel_url( Request $request ) {
		return add_query_arg(
			array(
				'error'             => 'access_denied',
				'error_description' => 'Access denied! Permission not granted.',
				'state'             => $request->query( 'state' ),
			),
			$request->query( 'redirect_uri' ),
		);
	}
}
