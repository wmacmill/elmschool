<?php
/**
 * UI module.
 * Functions that render some output on the admin site are here.
 * This module also takes care of most hooks and Ajax calls.
 *
 * @since  4.0.0
 */
class WPMUDEV_Dashboard_Ui {

	/**
	 * An object that defines all the URLs for the Dashboard menu/submenu items.
	 *
	 * @var object
	 */
	public $page_urls = null;

	/**
	 * Identifies the currently displayed Dashboard module. If current page is
	 * no dashboard page then this value stays false.
	 *
	 * @var bool|string
	 */
	public $current_module = false;

	/**
	 * Is the current admin screen a WPMUDEV Dashboard page?
	 *
	 * @var bool
	 */
	public $is_dashboard = false;

	/**
	 * Set up the UI module. This adds all the initial hooks for the plugin
	 *
	 * @since 4.0.0
	 * @internal
	 */
	public function __construct() {
		// Redirect to login screen on first plugin activation.
		add_action( 'admin_init', array( $this, 'first_redirect' ) );

		// Localize the plugin.
		add_action( 'plugins_loaded', array( $this, 'localization' ) );

		// Hook up our WordPress customizations.
		add_action( 'init', array( $this, 'setup_branding' ) );

		// Get admin page location.
		$urls = new stdClass();
		if ( is_multisite() ) {
			$urls->dashboard_url = network_admin_url( 'admin.php?page=wpmudev' );
			$urls->settings_url  = network_admin_url( 'admin.php?page=wpmudev-settings' );
			$urls->plugins_url   = network_admin_url( 'admin.php?page=wpmudev-plugins' );
			$urls->themes_url    = network_admin_url( 'admin.php?page=wpmudev-themes' );
			$urls->support_url   = network_admin_url( 'admin.php?page=wpmudev-support' );
		} else {
			$urls->dashboard_url = admin_url( 'admin.php?page=wpmudev' );
			$urls->settings_url  = admin_url( 'admin.php?page=wpmudev-settings' );
			$urls->plugins_url   = admin_url( 'admin.php?page=wpmudev-plugins' );
			$urls->themes_url    = admin_url( 'admin.php?page=wpmudev-themes' );
			$urls->support_url   = admin_url( 'admin.php?page=wpmudev-support' );
		}

		// This URL changes depending on the current admin page.
		$urls->real_support_url = $urls->support_url;

		if ( WPMUDEV_CUSTOM_API_SERVER ) {
			$urls->remote_site = trailingslashit( WPMUDEV_CUSTOM_API_SERVER );
		} else {
			$urls->remote_site = 'https://premium.wpmudev.org/';
		}

		$this->page_urls = $urls;

		add_filter(
			'wp_prepare_themes_for_js',
			array( $this, 'hide_upfront_theme' ), 100
		);

		/**
		 * Deprecated customization option:
		 * Load special code if included with the Dashboard plugin.
		 *
		 * Better option: Create a mu-plugin and use the hook
		 * `wpmudev_dashboard_init` to load and setup custom code.
		 */
		if ( file_exists( dirname( __FILE__ ) . '/includes/custom-module.php' ) ) {
			include_once dirname( __FILE__ ) . '/includes/custom-module.php';
		}

		/**
		 * Run custom initialization code for the UI module.
		 *
		 * @since  4.0.0
		 * @var  WPMUDEV_Dashboard_Ui The dashboards UI module.
		 */
		do_action( 'wpmudev_dashboard_ui_init', $this );
	}


	/*
	 * *********************************************************************** *
	 * *     INTERNAL ACTION HANDLERS
	 * *********************************************************************** *
	 */


	/**
	 * Load the translations if WordPress uses non-english language.
	 *
	 * For this you need a ".mo" file with translations.
	 * Name the file "wpmudev-[value in wp-config].mo"  (e.g. wpmudev-de_De.mo)
	 * Save the file to the folder "wp-content/languages/plugins/"
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 */
	public function localization() {
		load_plugin_textdomain(
			'wpmudev',
			false,
			WPMUDEV_Dashboard::$site->plugin_dir . '/language/'
		);
	}

	/**
	 * Checks if plugin was just activated, and redirects to login page.
	 * No redirect if plugin was actiavted via bulk-update.
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 */
	public function first_redirect() {
		$redirect = true;

		// This is not a valid request.
		if ( defined( 'DOING_AJAX' ) ) {
			$redirect = false;
		} elseif ( ! current_user_can( 'install_plugins' ) ) {
			// User is not allowed to login to the dashboard.
			$redirect = false;
		} elseif ( isset( $_GET['page'] ) && 'wpmudev' == $_GET['page'] ) {
			// User is already on Login page.
			$redirect = false;

			// Save the flag to not redirect again.
			WPMUDEV_Dashboard::$site->set_option( 'redirected_v4', 1 );
		} elseif ( WPMUDEV_Dashboard::$site->get_option( 'redirected_v4' ) ) {
			// We already redirected the user to login page before.
			$redirect = false;
		}

		/* ----- Save the flag and redirect if needed ----- */

		if ( $redirect ) {
			WPMUDEV_Dashboard::$site->set_option( 'redirected_v4', 1 );

			// Force refresh of all data during first redirect.
			WPMUDEV_Dashboard::$site->set_option( 'refresh_remote_flag', 1 );
			WPMUDEV_Dashboard::$site->set_option( 'refresh_local_flag', 1 );
			WPMUDEV_Dashboard::$site->set_option( 'refresh_profile_flag', 1 );

			header( 'X-Redirect-From: UI first_redirect' );
			wp_safe_redirect( $this->page_urls->dashboard_url );
			exit;
		}
	}

	/**
	 * Register the WPMUDEV Dashboard menu structure.
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 */
	public function setup_menu() {
		$is_logged_in = WPMUDEV_Dashboard::$api->has_key();
		$count_output = '';
		$remote_granted = false;
		$update_plugins = 0;
		$update_themes = 0;

		if ( $is_logged_in ) {
			// Show total number of available updates.
			$updates = WPMUDEV_Dashboard::$site->get_option( 'updates_available' );
			if ( is_array( $updates ) ) {
				foreach ( $updates as $item ) {
					if ( 'plugin' == $item['type'] ) {
						$update_plugins += 1;
					} elseif ( 'theme' == $item['type'] ) {
						$update_themes += 1;
					}
				}
			}
			$count = $update_plugins + $update_themes;

			if ( $count > 0 ) {
				$count_output = sprintf(
					'<span class="countval">%s</span>',
					$count
				);
			}
			$count_label = array();
			if ( 1 == $update_plugins ) {
				$count_label[] = __( '1 Plugin update', 'wpmudev' );
			} elseif ( $update_plugins > 1 ) {
				$count_label[] = sprintf( __( '%s Plugin updates', 'wpmudev' ), $update_plugins );
			}
			if ( 1 == $update_themes ) {
				$count_label[] = __( '1 Theme update', 'wpmudev' );
			} elseif ( $update_themes > 1 ) {
				$count_label[] = sprintf( __( '%s Theme updates', 'wpmudev' ), $update_themes );
			}

			$count_output = sprintf(
				' <span class="update-plugins total-updates count-%s" title="%s">%s</span>',
				$count,
				implode( ', ', $count_label ),
				$count_output
			);

			$staff_login = WPMUDEV_Dashboard::$api->remote_access_details();
			$remote_granted = $staff_login->enabled;
		} else {
			// Show icon if user is not logged in.
			$count_output = sprintf(
				' <span style="float:right;margin:-1px 13px 0 0;vertical-align:top;border-radius:10px;background:#F8F8F8;width:18px;height:18px;text-align:center" title="%s">%s</span>',
				__( 'Log in to your WPMU DEV account to use all features!', 'wpmudev' ),
				'<i class="dashicons dashicons-lock" style="font-size:14px;width:auto;line-height:18px;color:#333"></i>'
			);
		}

		$need_cap = 'manage_options'; // Single site.
		if ( is_multisite() ) {
			$need_cap = 'manage_network_options'; // Multi site.
		}

		// Dashboard Main Menu.
		$page = add_menu_page(
			__( 'WPMU DEV Dashboard', 'wpmudev' ),
			'WPMU DEV' . $count_output,
			$need_cap,
			'wpmudev',
			array( $this, 'render_dashboard' ),
			$this->get_menu_icon(),
			WPMUDEV_MENU_LOCATION
		);
		add_action( 'admin_print_styles-' . $page, array( $this, 'admin_styles' ) );

		$this->add_submenu(
			'wpmudev',
			__( 'WPMU DEV Dashboard', 'wpmudev' ),
			__( 'Dashboard', 'wpmudev' ),
			array( $this, 'render_dashboard' )
		);

		if ( $is_logged_in ) {
			$data = WPMUDEV_Dashboard::$api->get_membership_data();

			/**
			 * Use this action to register custom sub-menu items.
			 *
			 * The action is called before each of the default submenu items
			 * is registered, so other plugins can hook into any position they
			 * like by checking the action parameter.
			 *
			 * @var  WPMUDEV_Dashboard_ui $ui Use $ui->add_submenu() to register
			 *       new menu items.
			 * @var  string $menu The menu-item that is about to be set up.
			 */
			do_action( 'wpmudev_dashboard_setup_menu', $this, 'plugins' );

			$plugin_badge = sprintf(
				' <span class="update-plugins plugin-updates count-%s"><span class="countval">%s</span></span>',
				$update_plugins,
				$update_plugins
			);
			// Plugins page.
			$this->add_submenu(
				'plugins',
				__( 'WPMU DEV Plugins', 'wpmudev' ),
				__( 'Plugins', 'wpmudev' ) . $plugin_badge,
				array( $this, 'render_plugins' ),
				'install_plugins'
			);

			do_action( 'wpmudev_dashboard_setup_menu', 'themes' );

			$theme_badge = sprintf(
				' <span class="update-plugins theme-updates count-%s"><span class="countval">%s</span></span>',
				$update_themes,
				$update_themes
			);
			$this->add_submenu(
				'themes',
				__( 'WPMU DEV Themes', 'wpmudev' ),
				__( 'Themes', 'wpmudev' ) . $theme_badge,
				array( $this, 'render_themes' ),
				'install_themes'
			);

			do_action( 'wpmudev_dashboard_setup_menu', 'support' );

			// Support page.
			$support_icon = '';
			if ( $remote_granted ) {
				$support_icon = sprintf(
					' <i class="dashicons dashicons-unlock wdev-access-granted" title="%s"></i>',
					__( 'Support Access enabled', 'wpmudev' )
				);
			}
			$this->add_submenu(
				'support',
				__( 'WPMU DEV Support', 'wpmudev' ),
				__( 'Support', 'wpmudev' ) . $support_icon,
				array( $this, 'render_support' ),
				$need_cap
			);

			do_action( 'wpmudev_dashboard_setup_menu', 'settings' );

			// Manage (Settings).
			$this->add_submenu(
				'settings',
				__( 'WPMU DEV Settings', 'wpmudev' ),
				__( 'Manage', 'wpmudev' ),
				array( $this, 'render_settings' ),
				$need_cap
			);

			do_action( 'wpmudev_dashboard_setup_menu', 'end' );
		}
	}

	/**
	 * Compatibility URLs with old plugin version.
	 * This can be dropped sometime in the future, when members updated to v4
	 *
	 * @since  4.0.3
	 */
	public function admin_menu_redirect_compat() {
		global $pagenow;
		if ( 'admin.php' && isset( $_GET['page'] ) ) {
			$redirect_to = false;

			switch ( $_GET['page'] ) {
				case 'wpmudev-updates':
					$redirect_to = $this->page_urls->dashboard_url;
					break;
			}

			if ( $redirect_to ) {
				header( 'X-Redirect-From: UI redirect_compat' );
				wp_safe_redirect( $redirect_to );
				exit;
			}
		}
	}

	/**
	 * Load the CSS styles.
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 */
	public function admin_styles() {
		// Remember: Current page is on the WPMUDEV Dashboard!
		$this->is_dashboard = true;
		$this->current_module = 'dashboard';

		// Find out what items to display in the search field.
		$screen = get_current_screen();
		$search_for = 'all';
		$search_class = 'plugins';

		if ( is_object( $screen ) ) {
			$base = (string) $screen->base;

			switch ( true ) {
				case false !== strpos( $base, 'themes' ):
					$search_for = 'theme';
					$search_class = 'themes';
					$this->current_module = 'themes';
					break;

				case false !== strpos( $base, 'plugins' ):
					$search_for = 'plugin';
					$this->current_module = 'plugins';
					break;

				case false !== strpos( $base, 'support' ):
					$this->current_module = 'support';
					break;

				case false !== strpos( $base, 'settings' ):
					$this->current_module = 'settings';
					break;
			}
		}

		if ( $this->current_module ) {
			add_filter(
				'admin_body_class',
				array( $this, 'admin_body_class' )
			);
		}

		// Enqueue styles =====================================================.
		wp_enqueue_style(
			'wpmudev-admin-google_fonts',
			'https://fonts.googleapis.com/css?family=Roboto+Condensed:400,700|Roboto:400,500,300,300italic',
			false,
			WPMUDEV_Dashboard::$version
		);
		wp_enqueue_style(
			'wpmudev-admin-css',
			WPMUDEV_Dashboard::$site->plugin_url . 'css/dashboard.css',
			array( 'wpmudev-admin-google_fonts' ),
			WPMUDEV_Dashboard::$version
		);

		// Register scripts ===================================================.
		wp_register_script(
			'wpmudev-dashboard-lib',
			WPMUDEV_Dashboard::$site->plugin_url . 'js/dashboard.js',
			array( 'jquery' ),
			WPMUDEV_Dashboard::$version
		);
		wp_register_script(
			'wpmudev-dashboard-modules',
			WPMUDEV_Dashboard::$site->plugin_url . 'js/modules.js',
			array( 'jquery', 'wpmudev-dashboard-lib' ),
			WPMUDEV_Dashboard::$version
		);

		// Enqueue the localized scripts ======================================.
		wp_enqueue_script( 'wpmudev-dashboard-modules' );

		/**
		 * To add a custom admin notice on the Dashboard pages use the hook
		 * 'wpmudev-dashboard-notice' which is defined in the function
		 * `render_header()`
		 */

		// Hide all default admin notices from another source on these pages.
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Enqueue Dashboard styles on all non-dashboard admin pages.
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 */
	public function notification_styles() {
		echo '<style>#toplevel_page_wpmudev .wdev-access-granted { font-size: 14px; line-height: 13px; height: 13px; float: right; color: #1ABC9C; }</style>';
	}

	/**
	 * Adds the page-specific class to the admin page body tag.
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 * @param  string $classes List of CSS classes of the body tag.
	 * @return string Updated list of CSS classes.
	 */
	public function admin_body_class( $classes ) {
		$classes .= ' wpmud';
		$classes .= ' wpmud-' . $this->current_module;
		$classes .= ' ';

		return $classes;
	}


	/*
	 * *********************************************************************** *
	 * *     PUBLIC INTERFACE FOR OTHER MODULES
	 * *********************************************************************** *
	 */


	/**
	 * Should we hide the one-click-installation message from current user?
	 * This message is used on the theme and plugin pages.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function hide_install_notice() {
		return WPMUDEV_Dashboard::$site->get_usermeta( '_wpmudev_install_message' );
	}

	/**
	 * Official way to add new submenu items to the WPMUDEV Dashboard.
	 * The Dashboard styles are automatically enqueued for the new page.
	 *
	 * @since 4.0.0
	 * @param  string   $id The ID is prefixed with 'wpmudev-' for the page body class.
	 * @param  string   $title The documents title-tag.
	 * @param  string   $label The menu label.
	 * @param  callable $handler Function that is executed to render page content.
	 * @param  string   $capability Optional. Required capability. Default: manage_options.
	 * @return string Page hook_suffix of the new menu item.
	 */
	public function add_submenu( $id, $title, $label, $handler, $capability = 'manage_options' ) {
		static $Registered = array();

		// Prevent duplicates of the same menu item.
		if ( isset( $Registered[ $id ] ) ) { return; }
		$Registered[ $id ] = true;

		if ( false === strpos( $id, 'wpmudev' ) ) {
			$id = 'wpmudev-' . $id;
		}

		$page = add_submenu_page(
			'wpmudev',
			$title,
			$label,
			$capability,
			$id,
			$handler
		);
		add_action( 'admin_print_styles-' . $page, array( $this, 'admin_styles' ) );

		return $page;
	}


	/*
	 * *********************************************************************** *
	 * *     HANDLE BRANDING
	 * *********************************************************************** *
	 */


	/**
	 * Register our plugin branding.
	 *
	 * I.e. Setup all the things that are NOT on the dashboard page but modify
	 * the look & feel of WordPress core pages.
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 */
	public function setup_branding() {
		$add_branding = false;
		if ( ! is_admin() ) { return false; }

		/*
		 * If the current user has access to the WPMUDEV Dashboard then we
		 * always set up our branding hooks.
		 */
		if ( WPMUDEV_Dashboard::$site->allowed_user() ) {
			$add_branding = true;

			// Add branded links to install/update process.
			add_filter(
				'install_plugin_complete_actions',
				array( $this, 'branding_install_plugin_done' ), 10, 3
			);
			add_filter(
				'install_theme_complete_actions',
				array( $this, 'branding_install_theme_done' ), 10, 4
			);
			add_filter(
				'update_plugin_complete_actions',
				array( $this, 'branding_update_plugin_done' ), 10, 2
			);
			add_filter(
				'update_theme_complete_actions',
				array( $this, 'branding_update_theme_done' ), 10, 2
			);

			// Add the menu icon to the admin menu.
			if ( is_multisite() ) {
				$menu_hook = 'network_admin_menu';
			} else {
				$menu_hook = 'admin_menu';
			}

			add_action(
				$menu_hook,
				array( $this, 'admin_menu_redirect_compat' )
			);

			add_action(
				$menu_hook,
				array( $this, 'setup_menu' )
			);

			// Always load notification css.
			add_action(
				'admin_print_styles',
				array( $this, 'notification_styles' )
			);
		}
	}

	/**
	 * Add WPMUDEV link as return action after installing DEV plugins.
	 *
	 * Default actions are "Return to Themes/Plugins" and "Return to WP Updates"
	 * This filter adds a "Return to WPMUDEV Updates"
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 * @param  array  $install_actions Array of further actions to display.
	 * @param  object $api The update API details.
	 * @param  string $plugin_file Main plugin file.
	 * @return array
	 */
	public function branding_install_plugin_done( $install_actions, $api, $plugin_file ) {
		if ( ! empty( $api->download_link ) ) {
			if ( WPMUDEV_Dashboard::$api->is_server_url( $api->download_link ) ) {
				$install_actions['plugins_page'] = sprintf(
					'<a href="%s" title="%s" target="_parent">%s</a>',
					$this->page_urls->plugins_url,
					esc_attr__( 'Return to WPMU DEV Plugins', 'wpmudev' ),
					__( 'Return to WPMU DEV Plugins', 'wpmudev' )
				);
			}
		}

		return $install_actions;
	}

	/**
	 * Add WPMUDEV link as return action after upgrading DEV plugins.
	 *
	 * Default actions are "Return to Themes/Plugins" and "Return to WP Updates"
	 * This filter adds a "Return to WPMUDEV Updates"
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 * @param  array  $update_actions Array of further actions to display.
	 * @param  string $plugin Main plugin file.
	 * @return array
	 */
	public function branding_update_plugin_done( $update_actions, $plugin ) {
		$updates = WPMUDEV_Dashboard::$site->get_transient( 'update_plugins', false );

		if ( ! empty( $updates->response[ $plugin ] ) ) {
			if ( WPMUDEV_Dashboard::$api->is_server_url( $updates->response[ $plugin ]->package ) ) {
				$update_actions['plugins_page'] = sprintf(
					'<a href="%s" title="%s" target="_parent">%s</a>',
					$this->page_urls->plugins_url,
					esc_attr__( 'Return to WPMU DEV Plugins', 'wpmudev' ),
					__( 'Return to WPMU DEV Plugins', 'wpmudev' )
				);
			}
		}

		return $update_actions;
	}

	/**
	 * Add WPMUDEV link as return action after installing DEV themes.
	 *
	 * Default actions are "Return to Themes/Plugins" and "Return to WP Updates"
	 * This filter adds a "Return to WPMUDEV Updates"
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 * @param  array  $install_actions Array of further actions to display.
	 * @param  object $api The update API details.
	 * @param  string $stylesheet Theme stylesheet name.
	 * @param  object $theme_info Further details about the theme.
	 * @return array
	 */
	public function branding_install_theme_done( $install_actions, $api, $stylesheet, $theme_info ) {
		/*
		 * If just installed an Upfront child theme and Upfront is not
		 * installed warn them with a link.
		 */
		$need_upfront = ('upfront' == $theme_info->template && 'upfront' != $stylesheet);

		if ( $need_upfront && ! WPMUDEV_Dashboard::$site->is_upfront_installed() ) {
			$install_link = WPMUDEV_Dashboard::$site->auto_install_url( WPMUDEV_Dashboard::$site->id_upfront );

			if ( $install_link ) {
				$install_actions = array(
					'install_upfront' => sprintf(
						'<a id="install_upfront" href="%s" title="%s" target="_parent"><strong>%s</strong></a>',
						$install_link,
						esc_attr__( 'You must install the Upfront parent theme for this theme to work.', 'wpmudev' ),
						__( 'Install Upfront (Required)', 'wpmudev' )
					),
				);
				// User cannot activate the theme yet, so offer only 1 action.
				return $install_actions;
			}
		}

		/*
		 * If we just installed Upfront (parent theme) then don't show the
		 * action links which won't work for the parent theme.
		 */
		if ( 'upfront' == $stylesheet ) {
			unset( $install_actions['network_enable'] );
			unset( $install_actions['activate'] );
			unset( $install_actions['preview'] );
		}

		if ( isset( $api->download_link ) ) {
			if ( WPMUDEV_Dashboard::$api->is_server_url( $api->download_link ) ) {
				$install_actions['themes_page'] = sprintf(
					'<a href="%s" title="%s" target="_parent">%s</a>',
					$this->page_urls->themes_url,
					esc_attr__( 'Return to WPMU DEV Themes', 'wpmudev' ),
					__( 'Return to WPMU DEV Themes', 'wpmudev' )
				);
			}
		}

		return $install_actions;
	}

	/**
	 * Add WPMUDEV link as return action after upgrading DEV themes.
	 *
	 * Default actions are "Return to Themes/Plugins" and "Return to WP Updates"
	 * This filter adds a "Return to WPMUDEV Updates"
	 *
	 * @since  1.0.0
	 * @internal Action hook
	 * @param  array  $update_actions Array of further actions to display.
	 * @param  string $theme Name of the theme (= folder name).
	 * @return array
	 */
	public function branding_update_theme_done( $update_actions, $theme ) {
		$updates = WPMUDEV_Dashboard::$site->get_transient( 'update_themes', false );

		if ( ! empty( $updates->response[ $theme ] ) ) {
			/*
			 * If we just installed Upfront (parent theme) then don't show the
			 * action links which won't work for the parent theme.
			 */
			if ( 'upfront' == $theme ) {
				unset( $update_actions['network_enable'] );
				unset( $update_actions['activate'] );
				unset( $update_actions['preview'] );
			}

			if ( WPMUDEV_Dashboard::$api->is_server_url( $updates->response[ $theme ]['package'] ) ) {
				$update_actions['themes_page'] = sprintf(
					'<a href="%s" title="%s" target="_parent">%s</a>',
					$this->page_urls->themes_url,
					esc_attr__( 'Return to WPMU DEV Themes', 'wpmudev' ),
					__( 'Return to WPMU DEV Themes', 'wpmudev' )
				);
			}
		}

		return $update_actions;
	}

	/**
	 * Removes Upfront from being activatable in the theme browser.
	 *
	 * @since  3.0.0
	 * @internal Action hook
	 * @param  array $prepared_themes List of installed WordPress themes.
	 * @return array
	 */
	public function hide_upfront_theme( $prepared_themes ) {
		unset( $prepared_themes['upfront'] );
		return $prepared_themes;
	}


	/*
	 * *********************************************************************** *
	 * *     RENDER MENU PAGES
	 * *********************************************************************** *
	 */


	/**
	 * Outputs the Main Dashboard admin page
	 *
	 * @since  1.0.0
	 * @internal Menu callback
	 */
	public function render_dashboard() {
		// These two variables are used in template login.php.
		$connection_error = false;
		$key_valid = true;

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->load_template( 'no_access' );
		}

		if ( ! empty( $_GET['clear_key'] ) ) {
			// User requested to log-out.
			WPMUDEV_Dashboard::$api->set_key( '' );
			WPMUDEV_Dashboard::$site->set_option( 'limit_to_user', 0 );
			WPMUDEV_Dashboard::$api->refresh_membership_data();

			// After logout we remove the clear_key param from the URL so it
			// does not get stored in the browser history.
			$url = esc_url_raw(
				remove_query_arg( array( 'clear_key', 'set_apikey' ) )
			);
			$this->redirect_to( $url );
		} elseif ( ! empty( $_REQUEST['set_apikey'] ) ) {
			// User tried to log-in.
			WPMUDEV_Dashboard::$api->set_key( trim( $_REQUEST['set_apikey'] ) );
			$result = WPMUDEV_Dashboard::$api->refresh_membership_data();

			if ( ! $result || empty( $result['membership'] ) ) {
				WPMUDEV_Dashboard::$api->set_key( '' );
				$key_valid = false;

				if ( false === $result ) { $connection_error = true; }
			} else {
				// You did it! Login was successful :)
				// The current user is our new hero-user with Dashboard access.
				global $current_user;
				$key_valid = true;
				WPMUDEV_Dashboard::$site->set_option( 'limit_to_user', $current_user->ID );
				WPMUDEV_Dashboard::$api->refresh_profile();

				// Login worked, so remove the API key again from the URL so it
				// does not get stored in the browser history.
				$url = esc_url_raw(
					remove_query_arg( array( 'clear_key', 'set_apikey' ) )
				);
				$this->redirect_to( $url );
			}
		}

		$data = WPMUDEV_Dashboard::$api->get_membership_data();
		$member = WPMUDEV_Dashboard::$api->get_profile();
		$is_logged_in = WPMUDEV_Dashboard::$api->has_key();
		$type = WPMUDEV_Dashboard::$api->get_membership_type( $project_id );
		$urls = $this->page_urls;
		$my_project = false;

		echo '<div id="container" class="wrap wrap-dashboard">';

		if ( ! $is_logged_in ) {
			// User did not log in to WPMUDEV -> Show login page!
			$this->load_template(
				'login',
				compact( 'key_valid', 'connection_error', 'urls' )
			);
		} elseif ( ! WPMUDEV_Dashboard::$site->allowed_user() ) {
			// User has no permission to view the page.
			$this->load_template( 'no_access' );
		} else {

			/**
			 * Custom hook to display own notifications inside Dashboard.
			 */
			do_action( 'wpmudev_dashboard_notice-dashboard' );

			if ( $project_id ) {
				$my_project = WPMUDEV_Dashboard::$site->get_project_infos( $project_id );
			}

			$this->load_template(
				'dashboard',
				compact( 'data', 'member', 'urls', 'type', 'my_project' )
			);

			if ( 'free' == $type ) {
				$this->render_upgrade_box( 'free' );
			}
		}

		echo '</div>';

	}

	/**
	 * Outputs the Plugins admin page
	 *
	 * @since  1.0.0
	 * @internal Menu callback
	 */
	public function render_plugins() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			$this->load_template( 'no_access' );
		}

		$data = WPMUDEV_Dashboard::$api->get_membership_data();
		$tags = $this->tags_data( 'plugin' );
		$urls = $this->page_urls;

		/**
		 * Custom hook to display own notifications inside Dashboard.
		 */
		do_action( 'wpmudev_dashboard_notice-plugins' );

		echo '<div id="container" class="wrap wrap-plugins">';
		$this->load_template(
			'plugins',
			compact( 'data', 'urls', 'tags' )
		);
		echo '</div>';

		if ( 'full' != $data['membership'] ) {
			$this->render_upgrade_box( 'single', false );
		}
	}

	/**
	 * Outputs the Themes admin page
	 *
	 * @since  1.0.0
	 * @internal Menu callback
	 */
	public function render_themes() {
		if ( ! current_user_can( 'install_themes' ) ) {
			$this->load_template( 'no_access' );
		}

		$data = WPMUDEV_Dashboard::$api->get_membership_data();
		$urls = $this->page_urls;

		// Remove plugins and legacy themes.
		foreach ( $data['projects'] as $key => $project ) {
			if ( 'theme' != $project['type'] ) {
				unset( $data['projects'][ $key ] );
			}
			if ( WPMUDEV_Dashboard::$site->is_legacy_theme( $project['id'] ) ) {
				unset( $data['projects'][ $key ] );
			}
		}

		/**
		 * Custom hook to display own notifications inside Dashboard.
		 */
		do_action( 'wpmudev_dashboard_notice-themes' );

		echo '<div id="container" class="wrap wrap-themes">';
		$this->load_template(
			'themes',
			compact( 'data', 'urls' )
		);
		echo '</div>';

		if ( 'full' != $data['membership'] ) {
			$this->render_upgrade_box( 'single', false );
		}
	}

	/**
	 * Outputs the Support admin page.
	 *
	 * @since  1.0.0
	 * @internal Menu callback
	 */
	public function render_support() {
		$required = (is_multisite() ? 'manage_network_options' : 'manage_options');
		if ( ! current_user_can( $required ) ) {
			$this->load_template( 'no_access' );
		}

		$this->page_urls->real_support_url = $this->page_urls->remote_site . 'dashboard/support/';

		$profile = WPMUDEV_Dashboard::$api->get_profile();
		$data    = WPMUDEV_Dashboard::$api->get_membership_data();
		$spinner = WPMUDEV_Dashboard::$site->plugin_url . 'includes/images/spinner-dark.gif';
		$urls    = $this->page_urls;
		$staff_login = WPMUDEV_Dashboard::$api->remote_access_details();
		$notes   = WPMUDEV_Dashboard::$site->get_option( 'staff_notes' );
		$access = WPMUDEV_Dashboard::$site->get_option( 'remote_access' );
		if ( empty( $access['logins'] ) || ! is_array( $access['logins'] ) ) {
			$access_logs = array();
		} else {
			$access_logs = $access['logins'];
		}

		/**
		 * Custom hook to display own notifications inside Dashboard.
		 */
		do_action( 'wpmudev_dashboard_notice-support' );

		echo '<div id="container" class="wrap wrap-support">';
		$this->load_template(
			'support',
			compact( 'profile', 'data', 'urls', 'staff_login', 'notes', 'access_logs' )
		);
		echo '</div>';
	}

	/**
	 * Outputs the Manage/Settings admin page
	 *
	 * @since  1.0.0
	 * @internal Menu callback
	 */
	public function render_settings() {
		$required = (is_multisite() ? 'manage_network_options' : 'manage_options');
		if ( ! current_user_can( $required ) ) {
			$this->load_template( 'no_access' );
		}

		$data = WPMUDEV_Dashboard::$api->get_membership_data();
		$member = WPMUDEV_Dashboard::$api->get_profile();
		$urls = $this->page_urls;
		$membership_label = __( 'Free', 'wpmudev' );
		$allowed_users = WPMUDEV_Dashboard::$site->get_allowed_users();
		$auto_update = WPMUDEV_Dashboard::$site->get_option( 'autoupdate_dashboard' );

		if ( 'full' == $data['membership'] ) {
			$membership_label = __( 'Full', 'wpmudev' );
		} elseif ( is_numeric( $data['membership'] ) ) {
			$membership_label = __( 'Single', 'wpmudev' );
		}

		/**
		 * Custom hook to display own notifications inside Dashboard.
		 */
		do_action( 'wpmudev_dashboard_notice-settings' );

		echo '<div id="container" class="wrap wrap-settings">';
		$this->load_template(
			'settings',
			compact( 'data', 'member', 'urls', 'membership_label', 'allowed_users', 'auto_update' )
		);
		echo '</div>';
	}

	/**
	 * Renders the template header that is repeated on every page.
	 *
	 * @since  4.0.0
	 * @param  string $page_title The page caption.
	 */
	protected function render_header( $page_title ) {
		$urls = $this->page_urls;
		$url_support = $urls->real_support_url;
		$url_dash = 'https://premium.wpmudev.org/dashboard/';
		$url_logout = $urls->dashboard_url . '&clear_key=1';

		if ( $url_support != $urls->support_url ) {
			$support_target = '_blank';
		}

		?>
		<section id="header">
			<div class="actions">
				<a href="<?php echo esc_url( $url_support ); ?>" target="<?php echo esc_url( $support_target ); ?>" class="button">
					<?php esc_html_e( 'Get Support', 'wpmudev' ); ?>
				</a>
				<a href="<?php echo esc_url( $url_dash ); ?>" target="_blank" class="button button-light">
					<?php esc_html_e( 'My Dashboard', 'wpmudev' ); ?>
				</a>
				<?php if ( ! defined( 'WPMUDEV_APIKEY' ) || WPMUDEV_APIKEY ) : ?>
				<a href="<?php echo esc_url( $url_logout ); ?>" class="button button-light">
					<?php esc_html_e( 'Logout', 'wpmudev' ); ?>
				</a>
				<?php endif; ?>
			</div>
			<h1>
				<?php echo $page_title; ?>
			</h1>
		</section>
		<?php
		$i10n = array(
			'empty_search' => __( 'Nothing found', 'wpmudev' ),
			'default_msg_ok' => __( 'Okay, we saved your changes!', 'wpmudev' ),
			'default_msg_err' => __( 'Oops, we could not do this...', 'wpmudev' ),
		);

		$data = array();
		$data[] = 'window.WDP = window.WDP || {}';
		$data[] = 'WDP.data = WDP.data || {}';
		$data[] = 'WDP.data.site_url = ' . json_encode( get_site_url() );
		$data[] = 'WDP.lang = ' . json_encode( $i10n );

		if ( isset( $_GET['success'] ) && ! isset( $_GET['wpmudev_msg'] ) ) {
			$stamp = intval( $_GET['success'] );
			if ( $stamp && $stamp >= time() ) {
				$data[] = 'WDP.showSuccess()';
			}
		}

		/**
		 * Display a custom success message on the WPMU Dashboard pages.
		 *
		 * @var string|array The message to display.
		 *      Array options:
		 *      'type' => [ok|err]  (default: 'ok')
		 *      'delay' => 3000     (default: 3000ms)
		 *      'message' => '...'  (required!)
		 */
		$notice = apply_filters( 'wpmudev-admin-notice', false );
		if ( $notice ) {
			$command = 'WDP';
			if ( is_array( $notice ) && ! empty( $notice['type'] ) ) {
				$command .= sprintf( '.showMessage("type", "%s")', esc_attr( $notice['type'] ) );
			}
			if ( is_array( $notice ) && ! empty( $notice['delay'] ) ) {
				$command .= sprintf( '.showMessage("delay", %s)', intval( $notice['delay'] ) );
			}
			if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
				$command .= sprintf( '.showMessage("message", "%s")', esc_html( $notice['message'] ) );
			} elseif ( is_string( $notice ) ) {
				$command .= sprintf( '.showMessage("message", "%s")', esc_html( $notice ) );
			}
			$command .= '.showMessage("show")';
			$data[] = $command;
		}

		foreach ( $data as $item ) {
			printf(
				"<script>;jQuery(function(){%s;});</script>\n",
				$item
			);
		}

		/**
		 * Custom hook to display own notifications inside Dashboard.
		 */
		do_action( 'wpmudev_dashboard_notice' );
	}

	/**
	 * Display the modal overlay that tells the user to upgrade his membership.
	 *
	 * @since  4.0.0
	 * @param  string $reason The reason why the user needs to upgrade.
	 * @param  string $auto_show If the popup should be displayed on page load.
	 */
	protected function render_upgrade_box( $reason, $auto_show = true ) {
		$is_logged_in = WPMUDEV_Dashboard::$api->has_key();
		$urls = $this->page_urls;
		$user = wp_get_current_user();

		$username = $user->user_firstname;
		if ( empty( $username ) ) {
			$username = $user->display_name;
		}
		if ( empty( $username ) ) {
			$username = $user->user_login;
		}

		$this->load_template(
			'popup-no-access',
			compact( 'is_logged_in', 'urls', 'username', 'reason', 'auto_show' )
		);
	}

	/**
	 * Renders the "card" that displays a single project in the Plugins/Themes
	 * page.
	 *
	 * @since  4.0.0
	 * @param  int    $pid The project-ID.
	 * @param  array  $other_pids Additional projects to include in response.
	 * @param  string $message Additional template to parse and return (ajax).
	 */
	public function render_project( $pid, $other_pids = false, $message = false ) {
		$as_json = defined( 'DOING_AJAX' ) && DOING_AJAX;
		if ( $as_json ) { ob_start(); }

		$this->load_template(
			'element-project-info',
			compact( 'pid' )
		);

		if ( $as_json ) {
			$code = ob_get_clean();
			$data = array( 'html' => $code );

			// Optionally include other projets in AJAX response.
			if ( $other_pids && is_array( $other_pids ) ) {
				$data['other'] = array();
				foreach ( $other_pids as $pid2 ) {
					ob_start();
					$this->load_template(
						'element-project-info',
						array( 'pid' => $pid2 )
					);
					$code = ob_get_clean();
					$data['other'][ $pid2 ] = $code;
				}
			}

			if ( $message ) {
				ob_start();
				$this->load_template( $message, compact( 'pid' ) );
				$code = ob_get_clean();
				$data['overlay'] = $code;
			}

			wp_send_json_success( $data );
		}
	}

	/**
	 * Outputs the contents of a dashboard-popup (i.e. a <dialog> element)
	 * The function does not return any value but directly output the popup
	 * HTML code.
	 *
	 * This function is used by the ajax handler for `wdp-show-popup`
	 *
	 * @since  4.0.0
	 * @param  string $type The type (i.e. contents) of the popup.
	 * @param  int    $pid Project-ID.
	 */
	public function show_popup( $type, $pid = 0 ) {
		$as_json = defined( 'DOING_AJAX' ) && DOING_AJAX;
		if ( $as_json ) { ob_start(); }

		switch ( $type ) {
			// Project-Info/overview.
			case 'info':
				$this->load_template(
					'popup-project-info',
					compact( 'pid' )
				);
				break;

			// Update information.
			case 'update':
				$this->load_template(
					'popup-update-info',
					compact( 'pid' )
				);
				break;

			// Show the changelog.
			case 'changelog':
				$this->load_template(
					'popup-project-changelog',
					compact( 'pid' )
				);
				break;
		}

		if ( $as_json ) {
			$code = ob_get_clean();
			wp_send_json_success( array( 'html' => $code ) );
		}
	}


	/*
	 * *********************************************************************** *
	 * *     INTERNAL HELPER FUNCTIONS
	 * *********************************************************************** *
	 */


	/**
	 * Redirect to the specified URL, even after page output already started.
	 *
	 * @since  4.0.0
	 * @param  string $url The URL.
	 */
	public function redirect_to( $url ) {
		if ( headers_sent() ) {
			printf(
				'<script>window.location.href="%s";</script>',
				esc_js( $url )
			);
		} else {
			header( 'X-Redirect-From: UI redirect_to' );
			wp_safe_redirect( $url );
		}
		exit;
	}

	/**
	 * Get's a list of tags for given project type. Used for search or dropdowns.
	 *
	 * @since  1.0.0
	 * @param  string $type [plugin|theme].
	 * @return array
	 */
	public function tags_data( $type ) {
		$res = array();
		$data = WPMUDEV_Dashboard::$api->get_membership_data();

		if ( 'plugin' == $type ) {
			if ( isset( $data['plugin_tags'] ) ) {
				$tags = (array) $data['plugin_tags'];
				$res = array(
					// Important: Index 0 is "All", added automatically.
					1 => array(
						'name' => __( 'Business', 'wpmudev' ),
						'pids' => (array) $tags[32]['pids'],
					),
					2 => array(
						'name' => __( 'SEO', 'wpmudev' ),
						'pids' => (array) $tags[50]['pids'],
					),
					3 => array(
						'name' => __( 'Marketing', 'wpmudev' ),
						'pids' => (array) $tags[498]['pids'],
					),
					4 => array(
						'name' => __( 'Publishing', 'wpmudev' ),
						'pids' => (array) $tags[31]['pids'],
					),
					5 => array(
						'name' => __( 'Community', 'wpmudev' ),
						'pids' => (array) $tags[29]['pids'],
					),
					6 => array(
						'name' => __( 'BuddyPress', 'wpmudev' ),
						'pids' => (array) $tags[489]['pids'],
					),
					7 => array(
						'name' => __( 'Multisite', 'wpmudev' ),
						'pids' => (array) $tags[16]['pids'],
					),
				);
			}
		} elseif ( 'theme' == $type ) {
			if ( isset( $data['theme_tags'] ) ) {
				$res = (array) $data['theme_tags'];
			}
		}

		return $res;
	}

	/**
	 * Returns a base64 encoded SVG image that is used as Dashboard menu icon.
	 *
	 * Source image is file includes/images/logo.svg
	 * The source file is included with the plugin but not used.
	 *
	 * @since  4.0.0
	 * @return string Base64 encoded icon.
	 */
	protected function get_menu_icon() {
		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
		?><svg width="16px" height="16px" xmlns="http://www.w3.org/2000/svg">
		<g id="WPMUDEV" stroke="none" fill="#a0a5aa">
			<path d="M15.998562,5.5816674 L15.998562,16 L14.2408131,14.692746 L14.2408131,5.5816674 L14.2391807,5.5816674 C14.2391807,5.04815952 13.8361414,4.61558958 13.3389689,4.61558958 C12.8417964,4.61558958 12.4387571,5.04812238 12.4387571,5.5816674 L12.4415554,10.4183326 C12.4415554,11.9955714 11.2501603,13.27423 9.78056317,13.27423 C8.31096601,13.27423 7.11957092,11.9955714 7.11957092,10.4183326 L7.11957092,10.4183326 L7.11906566,5.5816674 C7.11906566,5.04815952 6.71606522,4.61558958 6.21885384,4.61558958 C5.72164247,4.61558958 5.31868089,5.04812238 5.31868089,5.5816674 L5.32202336,10.4183326 C5.32202336,11.9955714 4.13062827,13.27423 2.66099225,13.27423 C1.19135623,13.27423 0,11.9955714 0,10.4183326 L0,10.4183326 L0.00139917216,10.4183326 L0.00139917216,0 L1.75914806,1.30729112 L1.76081929,10.4183326 C1.76081929,10.9518776 2.16385861,11.3844104 2.66099225,11.3844104 C3.15812589,11.3844104 3.5611652,10.9518776 3.5611652,10.4183326 L3.55778387,5.5816674 C3.55778387,4.00442857 4.74917896,2.72580709 6.21881498,2.72580709 C7.688451,2.72580709 8.87984609,4.00446571 8.87984609,5.5816674 L8.87844692,5.5816674 L8.88039021,10.4183326 C8.88039021,10.9518776 9.28339066,11.3844104 9.78056317,11.3844104 C10.2777357,11.3844104 10.680775,10.9518776 10.680775,10.4183326 L10.6779766,5.5816674 C10.6779766,4.00442857 11.8693717,2.72580709 13.3390078,2.72580709 C14.8086438,2.72580709 16,4.00446571 16,5.5816674 L15.9986008,5.5816674 L15.998562,5.5816674 L15.998562,5.5816674 Z" id="logo"></path>
		</g>
		</svg><?php
		$svg = ob_get_clean();
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Loads the specified template.
	 *
	 * The template name should only contain the filename, without the .php
	 * extension, and without the template/ folder.
	 * If you want to pass variables to the template use the $data parameter
	 * and specify each variable as an array item. The array key will become the
	 * variable name.
	 *
	 * Using this function offers other plugins two filters to output content
	 * before or after the actual template.
	 *
	 * E.g.
	 *   load_template( 'no_access', array( 'msg' => 'test' ) );
	 *   will load the file template/no_access.php and pass it variable $msg
	 *
	 * Views:
	 *   If the REQUEST variable 'view' is set, then this function will attempt
	 *   to load the template file <name>-<view>.php with fallback to default
	 *   <name>.php if the view file does not exist.
	 *
	 * @since  4.0.0
	 * @param  string $name The template name.
	 * @param  array  $data Variables passed to the template, key => value pairs.
	 */
	protected function load_template( $name, $data = array() ) {
		if ( ! empty( $_REQUEST['view'] ) ) {
			$view = strtolower( sanitize_html_class( $_REQUEST['view'] ) );
			$file_1 = $name . '-' . $view . '.php';
			$file_2 = $name . '.php';
		} else {
			$file_1 = $name . '.php';
			$file_2 = $name . '.php';
		}

		$path_1 = WPMUDEV_Dashboard::$site->plugin_path . 'template/' . $file_1;
		$path_2 = WPMUDEV_Dashboard::$site->plugin_path . 'template/' . $file_2;

		$path = false;
		if ( file_exists( $path_1 ) ) {
			$path = $path_1;
		} elseif ( file_exists( $path_2 ) ) {
			$path = $path_2;
		}

		if ( $path ) {
			/**
			 * Output some content before the template is loaded, or modify the
			 * variables passed to the template.
			 *
			 * @var  array $data The
			 */
			$new_data = apply_filters( 'wpmudev_dashboard_before-' . $name, $data );
			if ( isset( $new_data ) && is_array( $new_data ) ) {
				$data = $new_data;
			}

			extract( $data );
			require $path;

			/**
			 * Output code or do stuff after the template was loaded.
			 */
			do_action( 'wpmudev_dashboard_after-' . $name );
		} else {
			printf(
				'<div class="error"><p>%s</p></div>',
				sprintf(
					esc_html__( 'Error: The template %s does not exist. Please re-install the plugin.', 'wpmudev' ),
					'"' . esc_html( $name ) . '"'
				)
			);
		}
	}
}