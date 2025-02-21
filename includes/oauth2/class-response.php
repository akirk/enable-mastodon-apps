<?php
/**
 * Response
 *
 * This file implements the response class for OAuth2 that adds OOB support.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\OAuth2;

/**
This extends the bshaffer OAuth2\Response to add support for the Out of Band flow (OOB), see https://googleapis.github.io/google-api-python-client/docs/oauth-installed.html

## urn:ietf:wg:oauth:2.0:oob

This value signals to the Google Authorization Server that the authorization code should be returned in the title bar of the browser, with the page text prompting the user to copy the code and paste it in the application. This is useful when the client (such as a Windows application) cannot listen on an HTTP port without significant client configuration.

When you use this value, your application can then detect that the page has loaded, and can read the title of the HTML page to obtain the authorization code. It is then up to your application to close the browser window if you want to ensure that the user never sees the page that contains the authorization code. The mechanism for doing this varies from platform to platform.

If your platform doesn’t allow you to detect that the page has loaded or read the title of the page, you can have the user paste the code back to your application, as prompted by the text in the confirmation page that the OAuth 2.0 server generates.
urn:ietf:wg:oauth:2.0:oob:auto

## urn:ietf:wg:oauth:2.0:oob:auto

This is identical to urn:ietf:wg:oauth:2.0:oob, but the text in the confirmation page that the OAuth 2.0 server generates won’t instruct the user to copy the authorization code, but instead will simply ask the user to close the window.

This is useful when your application reads the title of the HTML page (by checking window titles on the desktop, for example) to obtain the authorization code, but can’t close the page on its own.
 */
class Response extends \OAuth2\Response {
	private $output_code = false;
	private $ask_to_close = false;

	public function setRedirect( $statusCode, $url, $state = null, $error = null, $errorDescription = null, $errorUri = null ) { // phpcs:ignore
		if ( substr( $url, 0, 27 ) !== 'urn://ietf:wg:oauth:2.0:oob' ) {
			return parent::setRedirect( $statusCode, $url, $state, $error, $errorDescription, $errorUri ); // phpcs:ignore
		}

		if ( substr( $url, 0, 32 ) === 'urn://ietf:wg:oauth:2.0:oob:auto' ) {
			$this->ask_to_close = true;
		}
		$result = array();
		$p = strpos( $url, '?' );
		if ( false === $p ) {
			throw new \Exception( 'Invalid URN' );
		}

		parse_str( substr( $url, $p + 1 ), $result );
		$this->output_code = $result['code'];
	}

	public function send( $format = 'json' ) { // phpcs:ignore
		if ( false === $this->output_code ) {
			return parent::send( $format );
		}

		foreach ( $this->getHttpHeaders() as $name => $header ) {
			header( sprintf( '%s: %s', $name, $header ) );
		}

		?><html>
			<head>
				<title><?php echo esc_html( $this->output_code ); ?></title>
			</head>
			<body>
				<?php if ( $this->ask_to_close ) : ?>
					<p><?php esc_html_e( 'You can close this window now', 'enable-mastodon-apps' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Copy the following code and paste it into the application:', 'enable-mastodon-apps' ); ?></p>
					<pre><?php echo esc_html( $this->output_code ); ?></pre>
				<?php endif; ?>
			</body>

		</html>
		<?php
	}
}
