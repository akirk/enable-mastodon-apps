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
		add_filter( 'plugin_action_links_' . ENABLE_MASTODON_APPS_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
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
					'<ul><li><strong>' . esc_html__( 'Hide posts through Mastodon apps from appearing on the WordPress frontend', 'enable-mastodon-apps' ) . '</strong>' .

					'<ul>' .
					'<li><span>' .
					__( 'Check this if you want keep your use of Mastodon separate from your activity on your site.', 'enable-mastodon-apps' ) . '</span> <span>' .
					__( 'Posting through a Mastodon app would still save the post in your WordPress but invisibly to visitors and subscribers of your WordPress.', 'enable-mastodon-apps' ) . '</span> <span>' .
					wp_kses(
						sprintf(
							// translators: Links to the plugin.
							__( 'Remember that for posting to Mastodon, you\'ll need the <a href="%s">ActivityPub plugin</a>.', 'enable-mastodon-apps' ),
							'https://wordpress.org/plugins/activitypub/" target="_blank'
						),
						array(
							'a' => array(
								'href'   => true,
								'target' => true,
							),
						)
					) . '</span> <span>' .
					wp_kses(
						sprintf(
							// translators: Links to the plugin.
							__( 'If you also want to follow people and interact with them, please also install the <a href="%s">Friends plugin</a>.', 'enable-mastodon-apps' ),
							'https://wordpress.org/plugins/friends/" target="_blank'
						),
						array(
							'a' => array(
								'href'   => true,
								'target' => true,
							),
						)
					) .
					'</span></li>' .
					// translators: %s: a post type name.
					'<li>' . wp_kses( sprintf( __( 'With this setting, newly registered apps will be configured to save new posts as %s.', 'enable-mastodon-apps' ), '<tt>' . $ema_post_cpt->labels->singular_name . '</tt>' ), array( 'tt' => true ) ) . '</span> <span>' .
					// translators: %s: a post type name.
					wp_kses( sprintf( __( 'You can see and edit these posts in the sidebar menu of WP Admin under %s.', 'enable-mastodon-apps' ), '<a href="' . self_admin_url( 'edit.php?post_type=' . $ema_post_cpt->name ) . '">' . $ema_post_cpt->labels->name . '</a>' ), array( 'a' => array( 'href' => true ) ) ) . '</span> <span>' .
					esc_html__( 'This setting can be changed individually per app in its individual settings page.', 'enable-mastodon-apps' ) . '</span></li>' .
					'</ul>' .
					'<li><strong>' . esc_html__( 'Show posts through Mastodon apps on the WordPress frontend', 'enable-mastodon-apps' ) . '</strong>' .
					'<ul>' .
					'<li>' . esc_html__( 'Check this if you want to use Mastodon apps to post to the site directly.', 'enable-mastodon-apps' ) . '</span> <span>' .
					// translators: %s: a post format name.
					wp_kses( sprintf( __( 'Your posts will appear in your RSS feed and be sent to your subscribers unless you specify a post format, for example %s.', 'enable-mastodon-apps' ), '<tt>' . _x( 'Status', 'Post format' ) . '</tt>' ), array( 'tt' => true ) ) . '</span> <span>' . // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					esc_html__( 'That way, those posts will only appear in the specific RSS feed, and are typically not sent to subcribers.', 'enable-mastodon-apps' ) . '</span> <span>' .
					wp_kses(
						sprintf(
							// translators: Links to the plugin.
							__( 'Like above, if you combine it with the <a href="%s">ActivityPub plugin</a>, people on Mastodon will be able to read your posts.', 'enable-mastodon-apps' ),
							'https://wordpress.org/plugins/activitypub/" target="_blank'
						),
						array(
							'a' => array(
								'href'   => true,
								'target' => true,
							),
						)
					) . '</span> <span>' .
					wp_kses(
						sprintf(
							// translators: Links to the plugin.
							__( 'Also, with the <a href="%s">Friends plugin</a>, you will be able to follow people and interact with them', 'enable-mastodon-apps' ),
							'https://wordpress.org/plugins/friends/" target="_blank'
						),
						array(
							'a' => array(
								'href'   => true,
								'target' => true,
							),
						)
					) .
					// translators: %s: a post type name.
					'<li>' . wp_kses( sprintf( __( 'With this setting, newly registered apps will be configured to save new posts as %s.', 'enable-mastodon-apps' ), '<tt>' . $post_cpt->labels->singular_name . '</tt>' ), array( 'tt' => true ) ) .
					'</span> <span>' . esc_html__( 'This setting can be changed individually per app in its individual settings page.', 'enable-mastodon-apps' ) . '</span></li>' .
					'</ul>',
			)
		);
	}

	public function plugin_action_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=enable-mastodon-apps' ) ) . '">' . esc_html__( 'Settings', 'enable-mastodon-apps' ) . '</a>';
		return $links;
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
			if ( $app && ! is_wp_error( $app ) ) {
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
			if ( $app && ! is_wp_error( $app ) ) {
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
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'enable-mastodon-apps' ) ) {
			return;
		}

		if ( isset( $_POST['mastodon_api_enable_logins'] ) ) {
			delete_option( 'mastodon_api_disable_logins' );
		} else {
			update_option( 'mastodon_api_disable_logins', true );
		}

		if ( isset( $_POST['mastodon_api_default_create_post_format'] ) ) {
			$post_format = sanitize_text_field( wp_unslash( $_POST['mastodon_api_default_create_post_format'] ) );
			if ( in_array( $post_format, get_post_format_slugs(), true ) ) {
				update_option( 'mastodon_api_default_create_post_format', $post_format, false );
			} else {
				delete_option( 'mastodon_api_default_create_post_format' );
			}
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

		if ( isset( $_POST['mastodon_api_posting_cpt'] ) && intval( $_POST['mastodon_api_posting_cpt'] ) === 1 ) {
			delete_option( 'mastodon_api_posting_cpt' );

			if ( defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ) {
				$supported_post_types = (array) \get_option( 'activitypub_support_post_types', array( 'post' ) );
				if ( ! in_array( $default_ema_post_type, $supported_post_types, true ) ) {
					$supported_post_types[] = $default_ema_post_type;
					\update_option( 'activitypub_support_post_types', $supported_post_types );
				}
			}
		} elseif ( isset( $_POST['mastodon_api_posting_cpt'] ) && intval( $_POST['mastodon_api_posting_cpt'] ) === 0 ) {
			update_option( 'mastodon_api_posting_cpt', 'post', false );

			if ( defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ) {
				$supported_post_types = (array) \get_option( 'activitypub_support_post_types', array( 'post' ) );
				if ( in_array( $default_ema_post_type, $supported_post_types, true ) ) {
					$supported_post_types = array_diff( $supported_post_types, array( $default_ema_post_type ) );
					\update_option( 'activitypub_support_post_types', $supported_post_types );
				}
			}
		}

		if ( isset( $_POST['mastodon_api_disable_ema_announcements'] ) ) {
			delete_option( 'mastodon_api_disable_ema_announcements' );
		} else {
			update_option( 'mastodon_api_disable_ema_announcements', true, false );
		}

		if ( isset( $_POST['mastodon_api_disable_ema_app_settings_changes'] ) ) {
			delete_option( 'mastodon_api_disable_ema_app_settings_changes' );
		} else {
			update_option( 'mastodon_api_disable_ema_app_settings_changes', true, false );
		}

		if ( isset( $_POST['mastodon_api_enable_debug'] ) ) {
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
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'enable-mastodon-apps' ) ) {
			return;
		}

		if ( isset( $_POST['mastodon_api_debug_mode'] ) ) {
			update_option( 'mastodon_api_debug_mode', time() + apply_filters( 'enable_mastodon_apps_debug_time', 5 * MINUTE_IN_SECONDS ) );
		} else {
			delete_option( 'mastodon_api_debug_mode' );
		}
		if ( isset( $_POST['mastodon_api_auto_app_reregister'] ) ) {
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
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'enable-mastodon-apps' ) ) {
			return;
		}

		if ( isset( $_POST['delete-code'] ) ) {
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

		if ( isset( $_POST['delete-app'] ) ) {
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

		if ( isset( $_POST['clear-app-logs'] ) ) {
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
				'instance_url' => preg_replace( '#^https?://([a-z0-9.-:]+)/?$#i', '$1', home_url() ),
				'enable_debug' => $this->enable_debug,
				'codes'        => $codes,
				'tokens'       => $tokens,
				'apps'         => $apps,
			)
		);
	}

	public function process_admin_app_page( Mastodon_App $app ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'enable-mastodon-apps' ) ) {
			return;
		}

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
		$post_formats = $app->get_post_formats();

		$_post_types = array();
		foreach ( array(
			\Enable_Mastodon_Apps\Mastodon_Api::ANNOUNCE_CPT,
			\Enable_Mastodon_Apps\Mastodon_Api::POST_CPT,
			\Enable_Mastodon_Apps\Comment_CPT::CPT,
		) as $extra_post_type ) {
			$_post_types[ $extra_post_type ] = get_post_type_object( $extra_post_type );
		}

		$_post_types = array_merge(
			$_post_types,
			\get_post_types( array( 'show_ui' => true ), 'objects' ),
		);

		if ( isset( $_POST['create_post_type'] ) ) {
			$create_post_type = sanitize_text_field( wp_unslash( $_POST['create_post_type'] ) );
			if ( isset( $_post_types[ $create_post_type ] ) ) {
				$app->set_create_post_type( $create_post_type );
			}
		}

		if ( isset( $_POST['create_post_format'] ) ) {
			$create_post_format = sanitize_text_field( wp_unslash( $_POST['create_post_format'] ) );
			if ( in_array( $create_post_format, $post_formats ) ) {
				$app->set_create_post_format( $create_post_format );
			} else {
				$app->set_create_post_format( '' );
			}
		}

		if ( isset( $_POST['view_post_types'] ) && is_array( $_POST['view_post_types'] ) ) {
			$view_post_types = array(
				Mastodon_API::ANNOUNCE_CPT => true,
				Mastodon_API::POST_CPT     => true,
			);
			foreach ( wp_unslash( $_POST['view_post_types'] ) as $post_type ) {
				if ( isset( $_post_types[ $post_type ] ) ) {
					$view_post_types[ $post_type ] = true;
				}
			}
			if ( empty( $view_post_types ) ) {
				$view_post_types = array( 'post' );
			}
			$app->set_view_post_types( array_keys( $view_post_types ) );
		}

		if ( isset( $_POST['disable_blocks'] ) ) {
			$app->set_disable_blocks( true );
		} else {
			$app->set_disable_blocks( false );
		}

		$app->post_current_settings();
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

		if ( ! get_option( 'mastodon_api_disable_ema_announcements' ) ) {
			$post_id = false;
			if ( ! $old_version ) {
				$title = __( 'Welcome to Enable Mastodon Apps!', 'enable-mastodon-apps' );
				$content = __( 'This is a message from the Enable Mastodon Apps plugin that you have installed on your WordPress.', 'enable-mastodon-apps' );
				$content .= PHP_EOL . '<br>';
				$content .= __( 'The plugin allows you to see all posts on your site inside this app.', 'enable-mastodon-apps' );
				if ( ! defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ) {
					// When standalone, we want to post to your WordPress.
					update_option( 'mastodon_api_posting_cpt', 'post', false );
					$content .= __( 'If you submit a status, it will be posted to your site.', 'enable-mastodon-apps' );
					$content .= ' ' . __( "Be aware that this means that it will also be shown in your site's feed.", 'enable-mastodon-apps' );
				} else {
					$default_ema_post_type = apply_filters( 'mastodon_api_default_post_type', \Enable_Mastodon_Apps\Mastodon_API::POST_CPT );
					$supported_post_types = (array) \get_option( 'activitypub_support_post_types', array( 'post' ) );
					if ( ! in_array( $default_ema_post_type, $supported_post_types, true ) ) {
						$supported_post_types[] = $default_ema_post_type;
						\update_option( 'activitypub_support_post_types', $supported_post_types );
					}
					$content .= __( 'If you submit a status, it will be posted so that it is hidden from your site (by using a custom post type).', 'enable-mastodon-apps' );
					$content .= ' ';
					$content .= __( 'By means of the ActivityPub plugin that you also have installed, it will still be federated to your followers.', 'enable-mastodon-apps' );
				}
				$content .= ' ';
				$content .= __( 'This can be changed individually per app (see the link below).', 'enable-mastodon-apps' );
				$content .= PHP_EOL . '<br>';
				$content .= __( 'How it works: By providing the same API that Mastodon offers, it brings two worlds together that were not really designed for this interoperability. Thus, while it typically functions well, you might still experience some unexpected behavior.', 'enable-mastodon-apps' );
				$content .= ' ';
				// translators: %s is a clickable URL.
				$content .= make_clickable( sprintf( __( 'Please visit %s to get help in such a case.', 'enable-mastodon-apps' ), 'https://github.com/akirk/enable-mastodon-apps/issues' ) );
				$content .= PHP_EOL . '<br>';
				// translators: %s is a URL.
				$content .= sprintf( __( 'If you enjoy using this plugin, please let us know at the <a href=%s>EMA WordPress.org plugin page</a>.', 'enable-mastodon-apps' ), '"https://wordpress.org/plugins/enable-mastodon-apps/"' );

				$post_id = wp_insert_post(
					array(
						'post_type'    => Mastodon_API::ANNOUNCE_CPT,
						'post_title'   => $title,
						'post_status'  => 'publish',
						'post_content' => $content,

					)
				);

			} else {
				$readme = file_get_contents( ENABLE_MASTODON_APPS_PLUGIN_DIR . 'README.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$changelog = substr( $readme, strpos( $readme, '## Changelog' ) );
				$first_section = strpos( $changelog, '###' );
				$changelog = substr( $changelog, $first_section, strpos( $changelog, '###', $first_section + 1 ) - $first_section );

				$changelog = preg_replace( '/\[#(\d+)\]/', '<a href="https://github.com/akirk/enable-mastodon-apps/pull/\1">#\1</a>', $changelog );
				list( $headline, $changes ) = explode( PHP_EOL, $changelog, 2 );

				// translators: %s: version number.
				$title = sprintf( __( "What's new in EMA %s?", 'enable-mastodon-apps' ), trim( $headline, ' #' ) );

				$posts = get_posts(
					array(
						'post_type'      => Mastodon_API::ANNOUNCE_CPT,
						'posts_per_page' => 1,
						'post_status'    => 'publish',
						'title'          => $title,
					)
				);
				if ( ! $posts ) {
					$changes = wp_kses( $changes, array( 'a' => array( 'href' => true ) ) );

					$post_id = wp_insert_post(
						array(
							'post_type'    => Mastodon_API::ANNOUNCE_CPT,
							'post_title'   => $title,
							'post_status'  => 'publish',
							'post_content' => $changes,
						)
					);
				}
			}
			if ( $post_id ) {
				// Assign all post formats so that it will be shown regardless of the app's (potentially later changed) post format settings.
				wp_set_object_terms(
					$post_id,
					array_map(
						function ( $slug ) {
							return 'post-format-' . $slug;
						},
						get_post_format_slugs()
					),
					'post_format'
				);
			}
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
