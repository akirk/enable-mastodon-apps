<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps;

/**
 * The admin-specific functionality of the plugin.
 */
class Mastodon_Admin {
	private $oauth;

	public function __construct( Mastodon_OAuth $oauth ) {
		$this->oauth = $oauth;
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	public function admin_menu() {
		add_options_page(
			__( 'Mastodon Apps', 'enable-mastodon-apps' ),
			__( 'Mastodon Apps', 'enable-mastodon-apps' ),
			'edit_private_posts',
			'enable-mastodon-apps',
			array( $this, 'admin_page' )
		);
		add_action( 'load-settings_page_enable-mastodon-apps', array( $this, 'process_admin' ) );
	}

	public function admin_enqueue_scripts() {
		if ( 'settings_page_enable-mastodon-apps' !== get_current_screen()->id ) {
			return;
		}
		wp_enqueue_script( 'plugin-install' );
		add_thickbox();
		wp_enqueue_script( 'updates' );
		wp_enqueue_style( 'enable-mastodon-apps-admin-styles', plugins_url( 'admin.css', __DIR__ ), array(), '1.0.0' );
		wp_enqueue_script( 'enable-mastodon-apps-admin-js', plugins_url( 'admin.js', __DIR__ ), array( 'jquery' ), '1.0.0', false );
	}

	public function process_admin() {
		if ( ! current_user_can( 'edit_private_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the settings.', 'enable-mastodon-apps' ) );
		}

		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'enable-mastodon-apps' ) ) {
			return;
		}

		if ( isset( $_POST['delete-code'] ) ) {
			$deleted = $this->oauth->get_code_storage()->expireAuthorizationCode( $_POST['delete-code'] );
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-codes',
				sprintf(
				// translators: %d: number of deleted codes.
					_n( 'Deleted %d authorization code.', 'Deleted %d authorization codes.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				)
			);
			return;
		}

		if ( isset( $_POST['delete-token'] ) ) {
			$deleted = $this->oauth->get_token_storage()->unsetAccessToken( $_POST['delete-token'] );
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-tokens',
				sprintf(
				// translators: %d: number of deleted tokens.
					_n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				)
			);
			return;
		}

		if ( isset( $_POST['delete-app'] ) ) {
			$deleted = Mastodon_App::get_by_client_id( $_POST['delete-app'] )->delete();
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-apps',
				sprintf(
				// translators: %d: number of deleted apps.
					_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				)
			);
			return;
		}

		if ( isset( $_POST['clear-app-logs'] ) ) {
			$deleted = Mastodon_App::get_by_client_id( $_POST['clear-app-logs'] )->delete_last_requests();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs were cleared.', 'enable-mastodon-apps' )
				);
			} else {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs could not be cleared.', 'enable-mastodon-apps' )
				);
			}
			return;
		}
		if ( isset( $_POST['clear-all-app-logs'] ) ) {
			$total_deleted = 0;
			foreach ( Mastodon_App::get_all() as $app ) {
				$deleted = $app->delete_last_requests();
				if ( $deleted ) {
					$total_deleted += 1;
				}
			}
			if ( $total_deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-all-app-logs',
					sprintf(
					// translators: %d: number of deleted apps.
						_n( '%d app logs were cleared.', '%d app logs were cleared.', $total_deleted, 'enable-mastodon-apps' ),
						$total_deleted
					)
				);
			} else {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs could not be cleared.', 'enable-mastodon-apps' )
				);
			}
			return;
		}

		if ( isset( $_POST['delete-outdated'] ) ) {
			$deleted = OAuth2\Access_Token_Storage::cleanupOldTokens();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-tokens',
					sprintf(
					// translators: %d: number of deleted tokens.
						_n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted, 'enable-mastodon-apps' ),
						$deleted
					)
				);
			}

			$deleted = OAuth2\Authorization_Code_Storage::cleanupOldCodes();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-codes',
					sprintf(
					// translators: %d: number of deleted codes.
						_n( 'Deleted %d authorization code.', 'Deleted %d authorization codes.', $deleted, 'enable-mastodon-apps' ),
						$deleted
					)
				);
			}

			$deleted = Mastodon_App::delete_outdated();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-apps',
					sprintf(
					// translators: %d: number of deleted apps.
						_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted, 'enable-mastodon-apps' ),
						$deleted
					)
				);
			}
			return;
		}

		if ( isset( $_POST['delete-never-used'] ) ) {
			$deleted = 0;
			foreach ( Mastodon_App::get_all() as $app ) {
				if ( ! $app->get_last_used() ) {
					$deleted += 1;
					$app->delete();
				}
			}

			add_settings_error(
				'enable-mastodon-apps',
				'deleted-apps',
				sprintf(
				// translators: %d: number of deleted apps.
					_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted, 'enable-mastodon-apps' ),
					$deleted
				)
			);

			$deleted = 0;
			foreach ( OAuth2\Access_Token_Storage::getAll() as $token => $data ) {
				if ( empty( $data['last_used'] ) ) {
					if ( $this->oauth->get_token_storage()->unsetAccessToken( $token ) ) {
						$deleted += 1;
					}
				}
			}

			add_settings_error(
				'enable-mastodon-apps',
				'deleted-tokens',
				sprintf(
				// translators: %d: number of deleted tokens.
					_n( 'Deleted %d token.', 'Deleted %d tokens.', $deleted, 'enable-mastodon-apps' ),
					$deleted
				)
			);
			return;
		}

		if ( isset( $_POST['delete-apps-without-tokens'] ) ) {
			$app_tokens = array();
			foreach ( OAuth2\Access_Token_Storage::getAll() as $token => $data ) {
				if ( ! isset( $app_tokens[ $data['client_id'] ] ) ) {
					$app_tokens[ $data['client_id'] ] = array();
				}
				$app_tokens[ $data['client_id'] ][] = $token;
			}
			$deleted = 0;
			foreach ( Mastodon_App::get_all() as $app ) {
				if ( empty( $app_tokens[ $app->get_client_id() ] ) ) {
					$deleted += 1;
					$app->delete();
				}
			}

			add_settings_error(
				'enable-mastodon-apps',
				'deleted-apps',
				sprintf(
				// translators: %d: number of deleted apps.
					_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted, 'enable-mastodon-apps' ),
					$deleted
				)
			);
			return;
		}

		if ( isset( $_POST['mastodon_api_enable_logins'] ) ) {
			delete_option( 'mastodon_api_disable_logins' );
		} else {
			update_option( 'mastodon_api_disable_logins', true );
		}

		if ( isset( $_POST['mastodon_api_auto_app_reregister'] ) ) {
			update_option( 'mastodon_api_auto_app_reregister', true );
		} else {
			delete_option( 'mastodon_api_auto_app_reregister' );
		}

		if ( isset( $_POST['mastodon_api_debug_mode'] ) ) {
			update_option( 'mastodon_api_debug_mode', time() + 5 * MINUTE_IN_SECONDS );
		} else {
			delete_option( 'mastodon_api_debug_mode' );
		}

		if ( isset( $_POST['mastodon_api_default_post_formats'] ) && is_array( $_POST['mastodon_api_default_post_formats'] ) ) {
			$default_post_formats = array_filter(
				$_POST['mastodon_api_default_post_formats'],
				function ( $post_format ) {
					if ( ! in_array( $post_format, get_post_format_slugs(), true ) ) {
						return false;
					}
					return true;
				}
			);

			if ( ! empty( $default_post_formats ) ) {
				update_option( 'mastodon_api_default_post_formats', $default_post_formats );
			} else {
				delete_option( 'mastodon_api_default_post_formats' );
			}
		}

		if ( isset( $_POST['app_post_formats'] ) && is_array( $_POST['app_post_formats'] ) ) {
			foreach ( $_POST['app_post_formats'] as $client_id => $post_formats ) {
				$post_formats = array_filter(
					$post_formats,
					function ( $post_format ) {
						if ( ! in_array( $post_format, get_post_format_slugs(), true ) ) {
							return false;
						}
						return true;
					}
				);

				$app = Mastodon_App::get_by_client_id( $client_id );
				$app->set_post_formats( $post_formats );
			}
		}
	}

	private function post_format_select( $name, $selected = array() ) {
		?>
		<select name="<?php echo esc_attr( $name ); ?>[]" id="<?php echo esc_attr( $name ); ?>" size="10" multiple>
		<?php
		foreach ( get_post_format_slugs() as $format ) {
			?>
				<option value="<?php echo esc_attr( $format ); ?>" <?php selected( in_array( $format, $selected, true ) ); ?>><?php echo esc_html( $format ); ?></option>
				<?php
		}
		?>
		</select>
		<?php
	}

	public function admin_page() {
		$tab = $_GET['tab'] ?? 'welcome';
		switch ( $tab ) {
			case 'welcome':
				$this->admin_welcome_page();
				break;
			case 'settings':
				$this->admin_settings_page();
				break;
			case 'debug':
				$this->admin_debug_page();
				break;
			case 'registered-apps':
				$this->admin_registered_apps_page();
				break;
		}
	}

	public function admin_welcome_page() {
		$plugins = get_plugins();
		$activitypub_installed = isset( $plugins['activitypub/activitypub.php'] );

		load_template(
			__DIR__ . '/../templates/welcome.php',
			true,
			array(
				'activitypub_installed' => $activitypub_installed,
			)
		);
	}

	public function admin_settings_page() {
		load_template( __DIR__ . '/../templates/settings.php', true, array() );
	}

	public function admin_debug_page() {
		load_template( __DIR__ . '/../templates/debug.php', true, array() );
	}

	public function admin_registered_apps_page() {

		load_template(
			__DIR__ . '/../templates/registered-apps.php',
			true,
			array(
				'codes'      => OAuth2\Authorization_Code_Storage::getAll(),
				'tokens'     => OAuth2\Access_Token_Storage::getAll(),
				'apps'       => Mastodon_App::get_all(),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
