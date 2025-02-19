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
	private $enable_debug;

	public function __construct( Mastodon_OAuth $oauth ) {
		$this->oauth = $oauth;
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'current_screen', array( $this, 'register_help' ) );
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

	public function register_help( $screen ) {
		if ( 'settings_page_enable-mastodon-apps' !== $screen->id ) {
			return;
		}
		$ema_post_cpt = get_post_type_object( apply_filters( 'mastodon_api_default_post_type', \Enable_Mastodon_Apps\Mastodon_API::POST_CPT ) );
		$post_cpt = get_post_type_object( 'post' );

		$screen->add_help_tab(
			array(
				'id'      => 'enable-mastodon-apps-help',
				'title'   => __( 'Settings' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'content' =>
					'<p><strong>' . esc_html__( 'Make posts through Mastodon apps appear on this WordPress', 'enable-mastodon-apps' ) . '</strong><br>' .
					'<span>' . esc_html__( 'Setting this depends on your use case:', 'enable-mastodon-apps' ) . '</span>' .
					'<ul>' .
					'<li>' . esc_html__( 'If you want to use Mastodon apps to read just the posts on your site (for example in a blog with many authors, and you are interested in what the other authors post), and post to the site directly, check the box.', 'enable-mastodon-apps' ) . '</li>' .
					'<li>' .
					wp_kses(
						sprintf(
							// translators: Links to the plugins.
							__( 'If you want to use Mastodon apps to post to Mastodon (when combining with the <a href="%1$s">Friends plugin</a> and <a href="%2$s">ActivityPub plugin</a>) but want to avoid posting it visibly to your site (which for example would be sent to your subscribers), leave it unchecked.', 'enable-mastodon-apps' ),
							'https://wordpress.org/plugins/friends/" target="_blank',
							'https://wordpress.org/plugins/activitypub/" target="_blank'
						),
						array(
						'a' => array(
						'href'   => true,
						'target' => true,
						),
						)
					) .
					'</li>' .
					'<li>' .
					wp_kses(
						sprintf(
							// translators: Links to the plugins.
							__( 'If you do want to expose such posts also on your site (for example in a <a href="%s">POSSE</a> use case), check the box.', 'enable-mastodon-apps' ),
							'https://indieweb.org/POSSE" target="_blank'
						),
						array(
						'a' => array(
						'href'   => true,
						'target' => true,
						),
						)
					) .
					'</li>' .
					'</ul>' .
					'<span>' .
					wp_kses(
						sprintf(
							// translators: %1$s and %2$s: a post type.
							__( 'When checked, new posts made through Mastodon apps will have the post type %1$s, otherwise %2$s when unchecked.', 'enable-mastodon-apps' ),
							'<strong>' . $ema_post_cpt->labels->singular_name . '</strong>',
							'<strong>' . $post_cpt->labels->singular_name . '</strong>'
						),
						array( 'strong' => true )
					) .
					'</span>' .
					'</p>',
			)
		);
	}



	public function process_admin() {
		if ( ! current_user_can( 'edit_private_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the settings.', 'enable-mastodon-apps' ) );
		}

		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'enable-mastodon-apps' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'welcome';
		if ( isset( $_POST['app'] ) ) {
			$app = Mastodon_App::get_by_client_id( sanitize_text_field( wp_unslash( $_POST['app'] ) ) );
			if ( $app ) {
				return $this->process_admin_app_page( $app );
			}
			$tab = 'registered-apps';
		}
		switch ( $tab ) {
			case 'settings':
				$this->process_admin_settings_page();
				// We need to reload the page so that the POST_CPT shows up in the correct state.
				wp_safe_redirect( admin_url( 'options-general.php?page=enable-mastodon-apps&tab=settings' ) );
				break;
			case 'debug':
				$this->process_admin_debug_page();
				break;
			case 'registered-apps':
				$this->process_admin_registered_apps_page();
				break;
		}
	}

	public function admin_page() {
		$this->enable_debug = get_option( 'mastodon_api_enable_debug' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'welcome';
		if ( isset( $_GET['app'] ) ) {
			$app = Mastodon_App::get_by_client_id( sanitize_text_field( wp_unslash( $_GET['app'] ) ) );
			if ( $app ) {
				return $this->admin_app_page( $app );
			}
			$tab = 'registered-apps';
		}
		// phpcs:enable
		switch ( $tab ) {
			case 'welcome':
				$this->admin_welcome_page();
				break;
			case 'settings':
				$this->admin_settings_page();
				break;
			case 'tester':
				$this->admin_tester_page();
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
		load_template(
			__DIR__ . '/../templates/welcome.php',
			true,
			array(
				'instance_url' => preg_replace( '#^https?://([a-z0-9.-:]+)/?$#i', '$1', home_url() ),
				'enable_debug' => $this->enable_debug,
			)
		);
	}

	public function process_admin_settings_page() {
		if ( isset( $_POST['mastodon_api_enable_logins'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			delete_option( 'mastodon_api_disable_logins' );
		} else {
			update_option( 'mastodon_api_disable_logins', true );
		}

		/**
		 * The default post type for posting from Mastodon apps when the configured to do so.
		 *
		 * This only applies if the user unchekcks: Make posts through Mastodon apps appear on this WordPress
		 *
		 * @param string $post_type The default post type.
		 *
		 * Example:
		 * ```php
		 * add_filter( 'mastodon_api_default_post_type', function( $post_type ) {
		 *    return 'my-own-custom-post-type';
		 * } );
		 * ```
		 */
		$default_ema_post_type = apply_filters( 'mastodon_api_default_post_type', \Enable_Mastodon_Apps\Mastodon_API::POST_CPT );

		if ( isset( $_POST['mastodon_api_posting_cpt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			delete_option( 'mastodon_api_posting_cpt' );

			$supported_post_types = (array) \get_option( 'activitypub_support_post_types', array( 'post' ) );
			$supported_post_types[] = $default_ema_post_type;
			\update_option( 'activitypub_support_post_types', $supported_post_types );
		} else {
			update_option( 'mastodon_api_posting_cpt', 'post' );

			$supported_post_types = (array) \get_option( 'activitypub_support_post_types', array( 'post' ) );
			if ( in_array( $default_ema_post_type, $supported_post_types, true ) ) {
				$supported_post_types = array_diff( $supported_post_types, array( $default_ema_post_type ) );
				\update_option( 'activitypub_support_post_types', $supported_post_types );
			}
		}

		if ( isset( $_POST['mastodon_api_enable_debug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( 'mastodon_api_enable_debug', true );
		} else {
			delete_option( 'mastodon_api_enable_debug' );
		}
	}

	public function admin_settings_page() {
		load_template(
			__DIR__ . '/../templates/settings.php',
			true,
			array(
				'enable_debug' => $this->enable_debug,
			)
		);
	}

	public function process_admin_debug_page() {
		if ( isset( $_POST['mastodon_api_debug_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( 'mastodon_api_debug_mode', time() + 5 * MINUTE_IN_SECONDS );
		} else {
			delete_option( 'mastodon_api_debug_mode' );
		}
		if ( isset( $_POST['mastodon_api_auto_app_reregister'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( 'mastodon_api_auto_app_reregister', true );
		} else {
			delete_option( 'mastodon_api_auto_app_reregister' );
		}
	}

	public function admin_debug_page() {
		load_template( __DIR__ . '/../templates/debug.php', true, array() );
	}

	public function admin_tester_page() {
		load_template( __DIR__ . '/../templates/tester.php', true, array() );
	}

	public function process_admin_registered_apps_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['delete-code'] ) ) {
			 // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$deleted = $this->oauth->get_code_storage()->expireAuthorizationCode( sanitize_text_field( wp_unslash( $_POST['delete-code'] ) ) );
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-codes',
				sprintf(
				// translators: %d: number of deleted codes.
					_n( 'Deleted %d authorization code.', 'Deleted %d authorization codes.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				),
				'success'
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['delete-token'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$deleted = $this->oauth->get_token_storage()->unsetAccessToken( sanitize_text_field( wp_unslash( $_POST['delete-token'] ) ) );
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-tokens',
				sprintf(
				// translators: %d: number of deleted tokens.
					_n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				),
				'success'
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['delete-app'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$deleted = Mastodon_App::get_by_client_id( sanitize_text_field( wp_unslash( $_POST['delete-app'] ) ) )->delete();
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-apps',
				sprintf(
				// translators: %d: number of deleted apps.
					_n( 'Deleted %d app.', 'Deleted %d apps.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				),
				'success'
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['clear-app-logs'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$deleted = Mastodon_App::get_by_client_id( sanitize_text_field( wp_unslash( $_POST['clear-app-logs'] ) ) )->delete_last_requests();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs were cleared.', 'enable-mastodon-apps' ),
					'success'
				);
			} else {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs could not be cleared.', 'enable-mastodon-apps' ),
					'error'
				);
			}
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['clear-all-app-logs'] ) ) {
			$total_deleted = 0;
			foreach ( Mastodon_App::get_all() as $app ) {
				$deleted = $app->delete_last_requests();
				if ( $deleted ) {
					++$total_deleted;
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
					),
					'success'
				);
			} else {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs could not be cleared.', 'enable-mastodon-apps' ),
					'error'
				);
			}
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['delete-outdated'] ) ) {
			$apps    = Mastodon_App::get_all();
			$deleted = OAuth2\Access_Token_Storage::cleanupOldTokens();
			if ( ! $deleted ) {
				$deleted = 0;
			}
			foreach ( OAuth2\Access_Token_Storage::getAll() as $token => $data ) {
				if ( ! isset( $apps[ $data['client_id'] ] ) ) {
					++$deleted;
					$this->oauth->get_token_storage()->unsetAccessToken( $token );
				}
			}
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-tokens',
					sprintf(
					// translators: %d: number of deleted tokens.
						_n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted, 'enable-mastodon-apps' ),
						$deleted
					),
					'success'
				);
			}

			$deleted = OAuth2\Authorization_Code_Storage::cleanupOldCodes();
			if ( ! $deleted ) {
				$deleted = 0;
			}
			foreach ( OAuth2\Authorization_Code_Storage::getAll() as $code => $data ) {
				if ( ! isset( $apps[ $data['client_id'] ] ) ) {
					++$deleted;
					$this->oauth->get_code_storage()->expireAuthorizationCode( $code );
				}
			}

			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-codes',
					sprintf(
					// translators: %d: number of deleted codes.
						_n( 'Deleted %d authorization code.', 'Deleted %d authorization codes.', $deleted, 'enable-mastodon-apps' ),
						$deleted
					),
					'success'
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
					),
					'success'
				);
			}
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['delete-never-used'] ) ) {
			$deleted = 0;
			foreach ( Mastodon_App::get_all() as $app ) {
				if ( ! $app->get_last_used() ) {
					++$deleted;
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
				),
				'success'
			);

			$deleted = 0;
			foreach ( OAuth2\Access_Token_Storage::getAll() as $token => $data ) {
				if ( empty( $data['last_used'] ) ) {
					if ( $this->oauth->get_token_storage()->unsetAccessToken( $token ) ) {
						++$deleted;
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
				),
				'success'
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
					++$deleted;
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
				),
				'success'
			);
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['app_post_formats'] ) && is_array( $_POST['app_post_formats'] ) ) {
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( wp_unslash( $_POST['app_post_formats'] ) as $client_id => $post_formats ) {
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

	public function admin_registered_apps_page() {
		$codes = OAuth2\Authorization_Code_Storage::getAll();

		uasort(
			$codes,
			function ( $a, $b ) {
				return $b['expires'] <=> $a['expires'];
			}
		);

		$tokens = OAuth2\Access_Token_Storage::getAll();

		uasort(
			$tokens,
			function ( $a, $b ) {
				if ( $a['last_used'] === $b['last_used'] && $a['last_used'] ) {
					return $a['expires'] <=> $b['expires'];
				}

				return $b['last_used'] <=> $a['last_used'];
			}
		);

		$apps = Mastodon_App::get_all();

		uasort(
			$apps,
			function ( $a, $b ) {
				$a_date = $a->get_last_used();
				if ( ! $a_date ) {
					$a_date = $a->get_creation_date();
				}
				$b_date = $b->get_last_used();
				if ( ! $b_date ) {
					$b_date = $b->get_creation_date();
				}
				return $b_date <=> $a_date;
			}
		);

		load_template(
			__DIR__ . '/../templates/registered-apps.php',
			true,
			array(
				'enable_debug' => $this->enable_debug,
				'codes'        => $codes,
				'tokens'       => $tokens,
				'apps'         => $apps,
			)
		);
	}

	public function process_admin_app_page( Mastodon_App $app ) {

		if ( isset( $_POST['delete-app'] ) && $_POST['delete-app'] === $app->get_client_id() ) {
			$name    = $app->get_client_name();
			$deleted = $app->delete();
			if ( $deleted ) {
				$message = sprintf(
					// translators: %s: name of the app.
					__( 'Deleted app "%s".', 'enable-mastodon-apps' ),
					$name
				);
				wp_safe_redirect( add_query_arg( 'success', $message, admin_url( 'options-general.php?page=enable-mastodon-apps&tab=registered-apps' ) ) );
				exit;
			} else {
				add_settings_error(
					'enable-mastodon-apps',
					'deleted-apps',
					__( 'Could not delete app.', 'enable-mastodon-apps' ),
					'error'
				);
				return;

			}
		}

		if ( isset( $_POST['delete-token'] ) ) {
			$deleted = $this->oauth->get_token_storage()->unsetAccessToken( sanitize_text_field( wp_unslash( $_POST['delete-token'] ) ) );
			add_settings_error(
				'enable-mastodon-apps',
				'deleted-tokens',
				sprintf(
				// translators: %d: number of deleted tokens.
					_n( 'Deleted %d access token.', 'Deleted %d access tokens.', $deleted ? 1 : 0, 'enable-mastodon-apps' ),
					$deleted ? 1 : 0
				),
				'success'
			);
			return;
		}

		if ( isset( $_POST['clear-app-logs'] ) ) {
			$deleted = $app->delete_last_requests();
			if ( $deleted ) {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs were cleared.', 'enable-mastodon-apps' ),
					'success'
				);
			} else {
				add_settings_error(
					'enable-mastodon-apps',
					'clear-app-logs',
					__( 'App logs could not be cleared.', 'enable-mastodon-apps' ),
					'error'
				);
			}
			return;
		}

		$post_formats = array();
		if ( isset( $_POST['post_formats'] ) && is_array( $_POST['post_formats'] ) ) {
			$post_formats = array_filter(
				wp_unslash( $_POST['post_formats'] ),
				function ( $post_format ) {
					if ( ! in_array( $post_format, get_post_format_slugs(), true ) ) {
						return false;
					}
					return true;
				}
			);

		}
		$app->set_post_formats( $post_formats );

		$post_types = array_flip(
			array_map(
				function ( $post_type ) {
					return $post_type->name;
				},
				get_post_types( array(), 'objects' )
			)
		);

		if ( isset( $_POST['create_post_type'] ) ) {
			$create_post_type = sanitize_text_field( wp_unslash( $_POST['create_post_type'] ) );
			if ( isset( $post_types[ $create_post_type ] ) ) {
				$app->set_create_post_type( $create_post_type );
			}
		}

		if ( isset( $_POST['view_post_types'] ) && is_array( $_POST['view_post_types'] ) ) {
			$view_post_types = array(
				Mastodon_API::ANNOUNCE_CPT => true,
				Mastodon_API::POST_CPT     => true,
			);
			foreach ( wp_unslash( $_POST['view_post_types'] ) as $post_type ) {
				if ( isset( $post_types[ $post_type ] ) ) {
					$view_post_types[ $post_type ] = true;
				}
			}
			if ( empty( $view_post_types ) ) {
				$view_post_types = array( 'post' );
			}
			$app->set_view_post_types( array_keys( $view_post_types ) );
		}

		wp_insert_post(
			array(
				'post_type'    => Mastodon_API::ANNOUNCE_CPT,
				'post_title'   => __( 'Only Visible to You', 'enable-mastodon-apps' ),
				'post_content' =>
					sprintf(
						// translators: %s: app name.
						__( 'The settings for %s were changed as follows:', 'enable-mastodon-apps' ),
						$app->get_client_name()
					) . PHP_EOL .
					'Post Formats: ' . implode( ', ', $post_formats ) . PHP_EOL .
					'Create Post Type: ' . $create_post_type . PHP_EOL .
					'Post Types: ' . implode( ', ', array_keys( $view_post_types ) ),
				'post_status'  => 'publish',
				'meta_input'   => array(
					'ema_app_id' => $app->get_client_id(),
				),
			)
		);
	}

	public function admin_app_page( Mastodon_App $app ) {
		$tokens = array_filter(
			OAuth2\Access_Token_Storage::getAll(),
			function ( $token ) use ( $app ) {
				return $token['client_id'] === $app->get_client_id();
			}
		);

		load_template(
			__DIR__ . '/../templates/app.php',
			true,
			array(
				'enable_debug' => $this->enable_debug,
				'app'          => $app,
				'tokens'       => $tokens,
			)
		);
	}

	public static function upgrade_plugin( $override_old_version = false ) {
		$old_version = get_option( 'ema_plugin_version' );
		if ( preg_match( '/^(\d+\.\d+\.\d+)$/', $override_old_version ) ) {
			// Used in tests.
			$old_version = $override_old_version;
		} else {
			update_option( 'ema_plugin_version', ENABLE_MASTODON_APPS_VERSION );
		}

		if ( version_compare( $old_version, '1.0.0', '<' ) ) {
			// If the friends plugin is installed, add the friends post type to the list of post types that can be viewed.
			if ( class_exists( 'Friends\Friends' ) ) {
				$apps = Mastodon_App::get_all();
				foreach ( $apps as $app ) {
					$view_post_types = $app->get_view_post_types();
					$view_post_types[] = \Friends\Friends::CPT;
					$app->set_view_post_types( $view_post_types );
				}
			}
		}

		if ( version_compare( $old_version, '0.9.1', '<' ) ) {
			$comment_posts = get_posts(
				array(
					'post_type'      => Comment_CPT::CPT,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				)
			);
			foreach ( $comment_posts as $comment_post ) {
				$comment_id = get_post_meta( $comment_post->ID, Comment_CPT::META_KEY, true );
				if ( ! $comment_id ) {
					continue;
				}
				$comment = get_comment( $comment_id );
				if ( ! $comment || 1 !== intval( $comment->comment_approved ) ) {
					if ( Comment_CPT::CPT === get_post_type( $comment_post->ID ) ) {
						wp_delete_post( $comment_post->ID, true );
					}
				}
			}
		}
	}
}
