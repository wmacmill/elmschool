<?php

GFForms::include_feed_addon_framework();

require_once( 'includes/deprecated.php' );

class GF_User_Registration extends GFFeedAddOn {

	protected $_version                  = GF_USER_REGISTRATION_VERSION;
	protected $_min_gravityforms_version = '1.9.10';
	protected $_slug                     = 'gravityformsuserregistration';
	protected $_path                     = 'gravityformsuserregistration/userregistration.php';
	protected $_full_path                = __FILE__;
	protected $_url                      = 'http://www.gravityforms.com';
	protected $_title                    = 'User Registration Add-On';
	protected $_short_title              = 'User Registration';
	protected $_single_feed_submission   = true;

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_user_registration', 'gravityforms_user_registration_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_user_registration';
	protected $_capabilities_form_settings = 'gravityforms_user_registration';
	protected $_capabilities_uninstall     = 'gravityforms_user_registration_uninstall';
	protected $_enable_rg_autoupgrade      = true;

	private static $_instance = null;

	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => ! is_multisite() ? esc_html__( 'Register user only when a payment is received.', 'gravityformsuserregistration' ) : esc_html__( 'Register user and create site only when a payment is received.', 'gravityformsuserregistration' )
			)
		);

		add_filter( 'gform_addon_navigation', array( $this, 'maybe_create_temporary_menu_item' ) );
		add_filter( 'gaddon_no_output_field_properties', array( $this, 'no_output_field_properties' ) );
		add_filter( 'gform_enable_password_field', '__return_true' );

		add_action( 'wp',        array( $this, 'maybe_activate_user' ) );
		add_action( 'wp_loaded', array( $this, 'custom_registration_page' ) );

		add_action( 'gform_pre_render',                   array( __class__, 'maybe_prepopulate_form' ) );
		add_filter( 'gform_validation',                   array( $this, 'validate' ) );
		add_action( 'gform_pre_submission',               array( $this, 'handle_existing_images_submission' ) );

		add_action( 'gform_user_registration_validation', array( $this, 'validate_multisite_submission' ), 10, 3 );
		add_action( 'gform_user_registered',              array( $this, 'create_site' ), 10, 4 );
		add_action( 'gform_user_updated',                 array( $this, 'create_site' ), 10, 4 );

		// BuddyPress
		if ( self::is_bp_active() ) {
			add_action( 'gform_user_registered', array( $this, 'update_buddypress_data' ), 10, 3 );
			add_action( 'gform_user_updated',    array( $this, 'update_buddypress_data' ), 10, 3);
			add_action( 'gform_user_registered', array( $this, 'do_buddypress_user_signup' ) );
		}

		// process users from unspammed entries
		add_action( 'gform_update_status', array( $this, 'process_feed_when_unspammed' ), 10, 3 );

		// PayPal options
		remove_action( 'gform_paypal_action_fields',     array( $this, 'add_paypal_settings' ), 10, 2 );
		remove_filter( 'gform_paypal_save_config',       array( $this, 'save_paypal_settings' ) );
		add_filter( 'gform_paypal_feed_settings_fields', array( $this, 'add_paypal_settings' ), 10, 2 );

		// add paypal ipn hooks
		add_action( 'gform_subscription_canceled', array( $this, 'downgrade_user' ), 10, 2 );
		add_action( 'gform_subscription_canceled', array( $this, 'downgrade_site' ), 10, 2 );

		$this->load_pending_activations();

	}

	public function load_pending_activations() {
		require_once( $this->get_base_path() . '/includes/class-gf-pending-activations.php' );
		gf_pending_activations();
	}

	public function downgrade_user( $entry, $feed ) {

		if ( ! $feed || ! rgars( $feed, 'meta/cancellationActionUserEnable' ) ) {
			return;
		}

		$user = $this->get_user_by_entry_id( $entry['id'] );
		if ( is_wp_error( $user ) ) {
			$this->log( 'No user found.' );
			return;
		}

		$user->set_role( rgars( $feed, 'meta/cancellationActionUserValue' ) );

	}

	public function downgrade_site( $entry, $feed ) {
		global $current_site;

		if ( ! is_multisite() ) {
			return;
		}

		if ( ! rgars( $feed, 'meta/cancellationActionSiteEnable' ) ) {
			return;
		}


		$action  = rgars( $feed, 'meta/cancellationActionSiteValue' );
		$site_id = $this->get_site_by_entry_id( $entry['id'] );

		if ( ! $site_id ) {
			$this->log( 'No site found.' );
			return;
		}

		switch( $action ) {
			case 'deactivate':
				do_action( 'deactivate_blog', $site_id );
				update_blog_status( $site_id, 'deleted', '1' );
				break;
			case 'delete':
				require_once( ABSPATH . 'wp-admin/includes/ms.php' );
				if ( $site_id != '0' && $site_id != $current_site->blog_id ) {
					wpmu_delete_blog( $site_id, true );
				}
				break;
		}

	}

	/**
	 * Redirect to the custom registration page as specified in the User Registration settings.
	 *
	 * By default, this function checks if the user is accessing the default WP registration page
	 * "/wp-login.php?action=register" and if so, processes the redirect.
	 *
	 * If BuddyPress is active, it checks if the current page is the the BP registration page
	 * (as specified in the BP Page settings) and if so, processes the redirect. We also check
	 * to ensure that the User Registration Custom Registration Page ID is not the same as the
	 * BP Register Page ID.
	 *
	 */
	public function custom_registration_page() {
		global $bp;

		$action   = rgget( 'action' );
		$redirect = false;

		// if BP is active and this is the registration page, redirect
		if ( self::is_bp_active() && bp_is_register_page() ) {
			$redirect = true;
		}

		// if "wp-login.php?action=register", aka default WP registration page
		$script_name = substr( $_SERVER['SCRIPT_NAME'], - 12, 12 ); // get last 12 characters of script name (we want wp-login.php);
		if ( $script_name == 'wp-login.php' && $action == 'register' ) {
			$redirect = true;
		}

		// add support for multi-site
		$script_name = substr( $_SERVER['SCRIPT_NAME'], - 13, 13 ); // get last 12 characters of script name (we want wp-login.php);
		if ( is_multisite() && $script_name == 'wp-signup.php' ) {
			$redirect = true;
		}

		if ( ! $redirect ) {
			return;
		}

		$ur_settings = get_option( 'gf_userregistration_settings' );
		$reg_page_id = rgar( $ur_settings, 'custom_reg_page' );

		if ( empty( $ur_settings ) || ! rgar( $ur_settings, 'enable_custom_reg_page' ) ) {
			return;
		}

		// if BP is active, BP Register Page is set and BP Register Page ID is the same as the UR Register Page ID, cancel redirect
		if ( self::is_bp_active() && isset( $bp->pages->register->id ) && $bp->pages->register->id == $reg_page_id ) {
			return;
		}

		wp_redirect( get_permalink( $reg_page_id ) );
		exit;
	}





	// # USER CREATION -------------------------------------------------------------------------------------------------

	public function validate( $validation_result ) {

		$form  = $validation_result['form'];
		$entry = GFFormsModel::get_current_lead();
		$feed  = $this->get_single_submission_feed( $entry, $form );

		if ( ! $feed ) {
			return $validation_result;
		}

		$submitted_page = rgpost( sprintf( 'gform_source_page_number_%d', $form['id'] ) );

		$username_field = GFFormsModel::get_field( $form, rgars( $feed, 'meta/username' ) );
		$email_field    = GFFormsModel::get_field( $form, rgars( $feed, 'meta/email' ) );
		$password_field = GFFormsModel::get_field( $form, $feed['meta']['password'] );

		$is_username_hidden = GFFormsModel::is_field_hidden( $form, $username_field, array() );
		$is_email_hidden    = ! $email_field || GFFormsModel::is_field_hidden( $form, $email_field, array() );
		$is_password_hidden = GFFormsModel::is_field_hidden( $form, $password_field, array() );

		$username   = $this->get_meta_value( 'username', $feed, $form, $entry );
		$user_email = $this->get_meta_value( 'email', $feed, $form, $entry );
		$user_pass  = $this->get_meta_value( 'password', $feed, $form, $entry );

		$username = gf_apply_filters( 'gform_username', $form['id'], $username, $feed, $form, $entry );

		if ( ! function_exists( 'username_exists' ) ) {
			require_once( ABSPATH . WPINC . '/registration.php' );
		}

		if ( ! $is_password_hidden && $password_field['pageNumber'] == $submitted_page  ) {
			if ( strpos( $user_pass, "\\" ) !== false ) {
				$form = $this->add_validation_error( $password_field, $form, __( 'Passwords may not contain the character "\"', 'gravityformsuserregistration' ) );
			}
		}

		$user_id = gf_apply_filters( 'gform_user_registration_update_user_id', $form['id'], get_current_user_id(), $entry, $form, $feed );

		if ( is_multisite() ) {

			// if multisite is defined and true, lowercase name for validation
			$username = strtolower( $username );

			$result = wpmu_validate_user_signup( $username, $user_email );
			$errors = $result['errors']->errors;

			// special validation overrides for update feeds
			if ( $this->is_update_feed( $feed ) ) {

				// do not validate username on update feeds
				if ( isset( $errors['user_name'] ) ) {
					unset( $errors['user_name'] );
				}

				// do not validate if email belongs to user
				if ( isset( $errors['user_email'] ) ) {

					for ( $i = count( $errors['user_email'] ) - 1; $i >= 0; $i -- ) {

						$error_message = $errors['user_email'][ $i ];

						// if user is re-submitting their own email address, don't give 'already used' error
						if ( $error_message == __( 'Sorry, that email address is already used!' ) && $this->is_users_email( $user_email, $user_id ) ) {
							unset( $errors['user_email'][$i] );
						}
						// made as a separate else for ease of readability
						elseif ( $error_message == __( 'That email address has already been used. Please check your inbox for an activation email. It will become available in a couple of days if you do nothing.' ) && $this->is_users_email( $user_email, $user_id ) ) {
							unset( $errors['user_email'][$i] );
						}

					}

					// if no other user email errors remain, unset
					if ( count( $errors['user_email'] ) <= 0 ) {
						unset( $errors['user_email'] );
					}

				}

			}

			if ( ! empty( $errors ) ) {

				foreach ( $errors as $type => $error_msgs ) {
					foreach ( $error_msgs as $error_msg ) {
						switch ( $type ) {
							case 'user_name':
								if ( ! $is_username_hidden && ( $username_field['pageNumber'] == $submitted_page || $submitted_page == 0 ) ) {
									$form = $this->add_validation_error( $feed['meta']['username'], $form, $error_msg );
								}
								break;
							case 'user_email':
								if ( ! $is_email_hidden && $email_field['pageNumber'] == $submitted_page || $submitted_page == 0 ) {
									$form = $this->add_validation_error( $feed['meta']['email'], $form, $error_msg );
								}
								break;
						}
					}
				}

			}

		} else {

			if ( ! $is_email_hidden && $email_field['pageNumber'] == $submitted_page ) {
				$email_valid  = true;
				$email_exists = email_exists( $user_email );

				if ( ! $user_email ) {
					$email_valid = false;
					$form        = $this->add_validation_error( $feed['meta']['email'], $form, __( 'The email address can not be empty', 'gravityformsuserregistration' ) );
				}

				if ( $email_valid && $this->pending_activation_exists( 'user_email', $user_email ) ) {
					$email_valid = false;
					$form        = $this->add_validation_error( $feed['meta']['email'], $form, __( 'That email address has already been used. Please check your inbox for an activation email. It will become available in a couple of days if you do nothing.' ) );
				}

				if ( $email_valid && ! $this->is_update_feed( $feed ) && $email_exists ) {
					$form = $this->add_validation_error( $feed['meta']['email'], $form, __( 'This email address is already registered', 'gravityformsuserregistration' ) );
				} elseif ( $email_valid && $this->is_update_feed( $feed ) && $email_exists && ! $this->is_users_email( $user_email, $user_id ) ) {
					$form = $this->add_validation_error( $feed['meta']['email'], $form, __( 'This email address is already registered', 'gravityformsuserregistration' ) );
				}

			}

			// do not validate the user name if this is an update feed, if the user name field is hidden or if we are not on the correct page
			if ( ! $this->is_update_feed( $feed ) && ! $is_username_hidden && $username_field['pageNumber'] == $submitted_page ) {
				$username_valid = true;

				if ( empty( $username ) ) {
					$username_valid = false;
					$form           = $this->add_validation_error( $feed['meta']['username'], $form, __( 'The username can not be empty', 'gravityformsuserregistration' ) );
				}

				if ( $username_valid && ! validate_username( $username ) ) {
					$username_valid = false;
					$form           = $this->add_validation_error( $feed['meta']['username'], $form, __( 'The username can only contain alphanumeric characters (A-Z, 0-9), underscores, dashes and spaces', 'gravityformsuserregistration' ) );
				}

				if ( $username_valid && self::is_bp_active() && strpos( $username, " " ) !== false ) {
					$username_valid = false;
					$form           = $this->add_validation_error( $feed['meta']['username'], $form, __( 'The username can only contain alphanumeric characters (A-Z, 0-9), underscores and dashes', 'gravityformsuserregistration' ) );
				}

				if ( $username_valid && username_exists( $username ) ) {
					$username_valid = false;
					$form           = $this->add_validation_error( $feed['meta']['username'], $form, __( 'This username is already registered', 'gravityformsuserregistration' ) );
				}

				if ( $username_valid && $this->pending_activation_exists( 'user_login', $username ) ) {
					$form = $this->add_validation_error( $feed['meta']['username'], $form, __( 'That username is currently reserved but may be available in a couple of days' ) );
				}
			}

		}

		$form                          = apply_filters( 'gform_user_registration_validation', $form, $feed, $submitted_page );
		$validation_result['is_valid'] = $this->is_form_valid( $form );
		$validation_result['form']     = $form;

		return $validation_result;
	}

	public function delay_feed( $feed, $entry, $form ) {

		$user_data = $this->get_user_data( $entry, $form, $feed );
		$password  = rgar( $user_data, 'password' );

		if ( $password ) {
			gform_update_meta( $entry['id'], 'userregistration_password', GFCommon::encrypt( $password ) );
		}

	}

	public function process_feed( $feed, $entry, $form ) {

		$this->log( "form #{$form['id']} - starting process_feed()." );

		$user_data = $this->get_user_data( $entry, $form, $feed );
		if ( ! $user_data ) {
			$this->log( 'aborting. user_login or user_email are empty.' );
			return;
		}

		$disable_registration = apply_filters( 'gform_disable_registration', false, $form, $entry, null /* $fullfilled deprecated */ );
		if ( $disable_registration ) {
			$this->log( 'aborting. gform_disable_registration hook was used.' );
			return;
		}

		if ( $this->is_update_feed( $feed ) ) {

			$this->update_user( $entry, $form, $feed );

		} else {

			$is_user_activation = rgars( $feed, 'meta/userActivationEnable' ) == true;

			if ( $is_user_activation ) {

				$this->log( 'Calling handle_user_activation().' );

				$this->handle_user_activation( $entry, $form, $feed );

			} else {

				$this->log( 'Calling create_user().' );

				$this->create_user( $entry, $form, $feed );

			}

		}

		// password will be stored in entry meta for delayed feeds, delete after processing feed
		gform_delete_meta( $entry['id'], 'userregistration_password' );

	}

	public function process_feed_when_unspammed( $entry_id, $status, $prev_status ) {

		$is_unspammed = $prev_status == 'spam' && $status == 'active';
		if ( ! $is_unspammed ) {
			return;
		}

		$this->log( sprintf( 'Entry has been unspammed (ID: %d).', $entry_id ) );

		// check if user has already been created for this entry (prevents multiple users being created if an entry has been unspammed before)
		if ( self::get_user_id_by_meta( '_gform-entry-id', $entry_id ) ) {
			return;
		}

		$this->log( sprintf( 'User has not been created for this entry (ID: %d).', $entry_id ) );

		$entry = GFAPI::get_entry( $entry_id );
		$form  = GFAPI::get_form( $entry['form_id'] );

		$this->log( 'Calling maybe_process_feed(). The first feed with matching conditions will be processed.' );

		$this->maybe_process_feed( $entry, $form );

	}

	public function create_user( $entry, $form, $feed = false, $password = '' ) {

		$this->log( sprintf( 'Start with form id: %s; entry: %s', $form['id'], print_r( $entry, true ) ) );

		if ( ! $feed ) {
			// @todo not sure this will work...
			$feed = $this->get_feeds_by_entry( $entry['id'] );
		}

		$meta      = rgar( $feed, 'meta' );
		$user_data = $this->get_user_data( $entry, $form, $feed );

		if ( ! empty( $password ) ) {
			$user_data['password'] = $password;
		}

		$user_id = $this->user_login_exists( $user_data['user_login'] );
		if ( $user_id ) {
			$this->log( sprintf( 'User already exists. User ID: %s', $user_id ) );
			return false;
		}

		$generated_password = false;
		if ( empty( $user_data['password'] ) ) {
			$user_data['password'] = wp_generate_password();
			$generated_password = true;
		}

		$this->log( sprintf( 'Calling wp_create_user() for login "%s" with email "%s".', $user_data['user_login'], $user_data['user_email'] ) );

		$user_id = wp_create_user( $user_data['user_login'], addslashes( $user_data['password'] ), $user_data['user_email'] );

		if ( is_wp_error( $user_id ) ) {
			return false;
		}

		if ( $generated_password ) {
			update_user_option( $user_id, 'default_password_nag', true );
		}

		$this->add_user_meta( $user_id, $feed, $form, $entry, array() );

		// updating display name (after user meta because of dependency)
		$user_data['ID']           = $user_id;
		$user_data['display_name'] = $this->get_display_name( $user_id, $feed );

		wp_update_user( $user_data );

		$role = rgar( $meta, 'role' );
		if ( $role ) {
			$user = new WP_User( $user_id );
			$user->set_role( $role );
		}

		// set post author
		if ( rgar( $meta, 'setPostAuthor' ) ) {
			if ( $entry['post_id'] ) {
				$this->set_user_as_post_author( $entry['post_id'] );
			}
		}

		$this->log( sprintf( 'Calling wp_new_user_notification() for user id: %s', $user_id ) );

		// send notifications
		if ( rgar( $meta, 'sendEmail' ) ) {
			wp_new_user_notification( $user_id, $user_data['password'] );
		} else {
			// sending a blank password only sends notification to admin
			wp_new_user_notification( $user_id, '' );
		}

		$this->log( 'Done with wp_new_user_notification(). Email with username should have been sent.' );

		do_action( 'gform_user_registered', $user_id, $feed, $entry, $user_data['password'] );

		$user_data['user_id'] = $user_id;

		return $user_data;
	}


	public function set_user_as_post_author( $post_id ) {

		$entry_id = get_post_meta( $post_id, '_gform-entry-id', true );
		$user_id  = self::get_user_id_by_meta( '_gform-entry-id', $entry_id );

		if ( $entry_id && $user_id ) {
			$this->attribute_post_author( $user_id, $post_id );
		}

	}

	public function add_user_meta( $user_id, $feed, $form, $entry, $name_fields ) {

		$is_update_feed = $this->is_update_feed( $feed );
		$this->log( sprintf( '%s user meta.', $is_update_feed ? 'Updating' : 'Adding' ) );

		$standard_meta = array(
			'first_name',
			'last_name'
		);

		foreach ( $standard_meta as $meta_key ) {
			$this->log( sprintf( 'Processing meta item: %s', $meta_key ) );

			if ( ! $this->is_meta_key_mapped( $meta_key, $feed ) ) {
				$this->Log( 'Meta item is empty. Skipping it.' );
				continue;
			}

			$meta_value = rgars( $feed, "meta/$meta_key" );
			$value      = $this->get_meta_value( $meta_key, $feed, $form, $entry );
			$this->log( sprintf( 'Meta item mapped to field: %s; Value: %s', $meta_value, $value ) );

			$result = update_user_meta( $user_id, $meta_key, $value );
			$this->log( sprintf( 'Result: %s', var_export( (bool) $result, 1 ) ) );
		}

		// to track which entry the user was registered from
		update_user_meta( $user_id, 'entry_id', $entry['id'] ); // @deprecated
		update_user_meta( $user_id, '_gform-entry-id', $entry['id'] ); // @future

		// add custom user meta
		$custom_meta = $this->get_custom_meta( $feed );

		if ( ! is_array( $custom_meta ) || empty( $custom_meta ) ) {
			return;
		}

		foreach ( $custom_meta as $meta_key => $meta_value ) {

			$this->log( sprintf( 'Adding meta item: %s', $meta_key ) );

			// skip empty meta items
			if ( ! $meta_key || ! $meta_value ) {
				$this->Log( 'Meta item is empty. Skipping it.' );
				continue;
			}

			$value = $this->get_meta_value( $meta_key, $custom_meta, $form, $entry ); // @review

			$this->log( sprintf( 'Meta item mapped to field: %s; value: %s', $meta_value, $value ) );

			if ( $meta_key == 'user_url' && $value ) {
				$result = $this->update_user_property( $user_id, 'user_url', $value );
			} elseif ( rgblank( $value ) ) {
				$result = $is_update_feed ? delete_user_meta( $user_id, $meta_key ) : true;
			} else {
				$result = update_user_meta( $user_id, $meta_key, $value );
			}

			$this->log( sprintf( 'Result: %s', var_export( (bool) $result, 1 ) ) );

		}

	}





	// # USER UPDATE ---------------------------------------------------------------------------------------------------

	public static function maybe_prepopulate_form( $form ) {

		$feed = gf_user_registration()->get_update_feed( $form['id'] );

		// if no feed, return form unmodified
		if ( ! $feed ) {
			return $form;
		} else {

			$user_id = gf_apply_filters( 'gform_user_registration_update_user_id', $form['id'], get_current_user_id(), false, $form, $feed );

			// add action to hide form and display error message if no user id supplied
			if ( empty( $user_id ) ) {
				add_action( 'gform_get_form_filter_' . $form['id'], array( gf_user_registration(), 'hide_form' ) );

				return $form;
			} else {
				// prepopulate the form
				$form = gf_user_registration()->prepopulate_form( $form, $feed, $user_id );
			}
		}

		return $form;
	}

	public function hide_form( $form_string ) {
		return __( 'Oops! You need to be logged in to use this form.', 'gravityformsuserregistration' );
	}

	public static function prepopulate_form( $form, $feed, $user_id = false ) {

		$mapped_fields = array();
		$meta          = rgar( $feed, 'meta' );
		$user          = empty( $user_id ) ? wp_get_current_user() : get_userdata( $user_id );

		foreach ( array( 'username', 'last_name', 'first_name', 'email' ) as $meta_key ) {
			if ( $field_id = rgar( $meta, $meta_key ) ) {
				switch ( $meta_key ) {
					case 'email':
						$meta_key = 'user_email';
						break;
				}
				$mapped_fields[ (string) $field_id ] = $user->get( $meta_key );
			}
		}

		$custom_meta = gf_user_registration()->get_custom_meta( $feed );
		foreach ( $custom_meta as $meta_key => $meta_value ) {
			if ( $meta_value ) {
				$field_id                            = $meta_value;
				$mapped_fields[ (string) $field_id ] = $user->get( $meta_key );
			}
		}

		if ( function_exists( 'xprofile_get_field_data' ) ) {

			$bp_meta = gf_user_registration()->get_buddypress_meta( $feed );

			foreach ( $bp_meta as $bp_field_id => $gf_field_id ) {

				$bp_field = xprofile_get_field( $bp_field_id );

				if ( in_array( $bp_field->type, array( 'url', 'datebox' ) ) ) {
					$value = BP_XProfile_ProfileData::get_value_byid( $bp_field_id, $user->ID );
				} else {
					$value = xprofile_get_field_data( $bp_field_id, $user->ID );
				}

				if ( $bp_field->type == 'datebox' && $value ) {
					$gf_field = GFFormsModel::get_field( $form, $gf_field_id );
					$value    = GFCommon::date_display( call_user_func( 'array_shift', explode( ' ', $value ) ), 'ymd_dash', $gf_field->dateFormat ? $gf_field->dateFormat : 'mdy' );
				}

				if ( ! empty( $value ) ) {
					$mapped_fields[ (string) $gf_field_id ] = is_array( $value ) ? array_map( 'html_entity_decode', $value ) : html_entity_decode( $value );
				}

			}

		}

		$mapped_fields = apply_filters( 'gform_user_registration_user_data_pre_populate', $mapped_fields, $form, $feed );

		// get all fields for cheap check inside field loop
		$mapped_field_ids = array_map( 'intval', array_keys( $mapped_fields ) );

		foreach ( $form['fields'] as &$field ) {

			if ( ! in_array( $field->id, $mapped_field_ids ) ) {
				continue;
			}

			$value = false;

			switch ( $field->get_input_type() ) {

				case 'fileupload':

					$value     = rgar( $mapped_fields, $field->id );
					$path_info = pathinfo( $value );

					// check if file has been "deleted" via form UI
					$upload_files = json_decode( rgpost( 'gform_uploaded_files' ), ARRAY_A );
					$input_name   = "input_{$field->id}";
					if ( is_array( $upload_files ) && array_key_exists( $input_name, $upload_files ) && ! $upload_files[ $input_name ] ) {
						continue;
					}

					// if $uploaded_files array is not set for this form at all, init as array
					if ( ! isset( GFFormsModel::$uploaded_files[ $form['id'] ] ) ) {
						GFFormsModel::$uploaded_files[ $form['id'] ] = array();
					}

					// check if this field's key has been set in the $uploaded_files array, if not add this file (otherwise, a new image may have been uploaded so don't overwrite)
					if ( ! isset( GFFormsModel::$uploaded_files[ $form['id'] ]["input_{$field->id}"] ) ) {
						GFFormsModel::$uploaded_files[ $form['id'] ]["input_{$field->id}"] = $path_info['basename'];
					}

					break;

				case 'checkbox':

					$value     = rgar( $mapped_fields, $field->id );
					$cb_values = array();

					if ( is_array( $value ) ) {
						$cb_values = $value;
					} else {
						$inputs = $field->inputs;
						foreach ( $inputs as &$input ) {
							$cb_values[] = rgar( $mapped_fields, (string) $input['id'] );
						}
						$field->inputs = $inputs;
					}

					$value = implode( ',', $cb_values );

					break;

				case 'list':

					$value       = maybe_unserialize( rgar( $mapped_fields, $field->id ) );
					$list_values = array();

					if ( is_array( $value ) ) {
						foreach ( $value as $vals ) {
							if ( ! is_array( $vals ) ) {
								$vals = array( $vals );
							}
							$list_values = array_merge( $list_values, array_values( $vals ) );
						}
						$value = $list_values;
					}

					break;

				case 'date':
					$value = GFCommon::date_display( rgar( $mapped_fields, $field->id ), $field->dateFormat, false );
					break;

				default:

					// handle complex fields
					$inputs = $field->get_entry_inputs();
					if ( is_array( $inputs ) ) {
						foreach ( $inputs as &$input ) {
							$filter_name              = self::prepopulate_input( $input['id'], rgar( $mapped_fields, (string) $input['id'] ) );
							$field->allowsPrepopulate = true;
							$input['name']            = $filter_name;
						}
						$field->inputs = $inputs;
					} else {

						$value = is_array( rgar( $mapped_fields, $field->id ) ) ? implode( ',', rgar( $mapped_fields, $field->id ) ) : rgar( $mapped_fields, $field->id );

					}

			}

			if ( rgblank( $value ) ) {
				continue;
			}

			$value                    = self::maybe_get_category_id( $field, $value );
			$filter_name              = self::prepopulate_input( $field->id, $value );
			$field->allowsPrepopulate = true;
			$field->inputName         = $filter_name;

		}

		return $form;
	}

	public static function prepopulate_input( $input_id, $value ) {

		$filter_name = 'gfur_field_' . str_replace( '.', '_', $input_id );
		add_filter( "gform_field_value_{$filter_name}", create_function( "", "return maybe_unserialize('" . str_replace( "'", "\'", maybe_serialize( $value ) ) . "');" ) );

		return $filter_name;
	}

	/**
	 * Update the user based on the currently submitted lead.
	 *
	 * Update the user meta first as the display name is dependent on the first and last name user meta. Afterwards,
	 * update the "core" user properties.
	 *
	 * @param $entry
	 * @param $form
	 * @param bool $feed
	 * @return array
	 */
	public function update_user( $entry, $form, $feed = false ) {

		if ( ! $feed ) {
			$feed = $this->get_single_submission_feed( $entry, $form );
		}

		$meta    = rgar( $feed, 'meta' );
		$user_id = gf_apply_filters( 'gform_user_registration_update_user_id', $form['id'], $entry['created_by'], $entry, $form, $feed );

		// update user meta before display name due to dependency
		$this->add_user_meta( $user_id, $feed, $form, $entry, array() );

		// refreshing $user variable because it might have changed during add_user_meta
		$user_obj  = new WP_User( $user_id );
		$user      = get_object_vars( $user_obj->data );
		$user_data = $this->get_user_data( $entry, $form, $feed, true );

		$user['user_email']   = $user_data['user_email'];
		$user['display_name'] = $this->get_display_name( $user['ID'], $feed );

		// if password provided, store it for update in $user array
		if ( $user_data['password'] ) {
			$user['user_pass'] = addslashes( $user_data['password'] );
		} else {
			unset( $user['user_pass'] );
		}

		$user_id = wp_update_user( $user );
		$role    = rgar( $meta, 'role' );

		// if a role is provied and it is not the 'preserve' option, update the role
		if ( rgar( $meta, 'role' ) && $role != 'gfur_preserve_role' ) {
			$user_obj->set_role( rgar( $meta, 'role' ) );
		}

		do_action( 'gform_user_updated', $user_id, $feed, $entry, $user_data['password'] );

		// return array with user_id, user_login, user_email, and password
		return array_merge( array( 'user_id' => $user_id ), $user_data );
	}

	public function handle_existing_images_submission( $form ) {

		$feed = $this->get_update_feed( $form['id'] );

		if ( ! $feed ) {
			return;
		}

		$user_id = gf_apply_filters( 'gform_user_registration_update_user_id', $form['id'], get_current_user_id(), false, $form, $feed );
		if ( empty( $user_id ) ) {
			return;
		}

		$meta = $this->get_custom_meta( $feed );
		if ( ! empty( $meta ) ) {
			$this->set_gf_uploaded_files( $meta, $form, false, $user_id );
		}

		if ( $this->is_bp_active() ) {
			$bp_meta = $this->get_buddypress_meta( $feed );
			if ( ! empty( $bp_meta ) ) {
				$this->set_gf_uploaded_files( $bp_meta, $form, 'buddypress', $user_id );
			}
		}

	}

	public function set_gf_uploaded_files( $meta, $form, $format = false, $user_id = false ) {
		global $_gf_uploaded_files;

		// get UR config
		// get all fileupload fields mapped in the UR config
		// foreach loop through and see if the image has been:
		//  - resubmitted           populate the existing image data into the $_gf_uploaded_files
		//  - deleted               do nothing
		//  - new image submitted   do nothing

		if ( empty( $_gf_uploaded_files ) ) {
			$_gf_uploaded_files = array();
		}

		foreach ( $meta as $meta_key => $meta_value ) {

			$field = GFFormsModel::get_field( $form, $meta_value );

			if ( ! is_object( $field ) || $field->get_input_type() != 'fileupload' ) {
				continue;
			}

			$input_name = "input_{$field->id}";

			if ( $this->is_prepopulated_file_upload( $form['id'], $input_name ) ) {
				if ( $format == 'buddypress' ) {
					$_gf_uploaded_files[ $input_name ] = BP_XProfile_ProfileData::get_value_byid( $meta_key, $user_id );
				} else {
					$_gf_uploaded_files[ $input_name ] = get_user_meta( $user_id, $meta_key, true );
				}
			}

		}

	}

	public function is_new_file_upload( $form_id, $input_name ) {

		$file_info     = GFFormsModel::get_temp_filename( $form_id, $input_name );
		$temp_filepath = GFFormsModel::get_upload_path( $form_id ) . '/tmp/' . $file_info['temp_filename'];

		// check if file has already been uploaded by previous step
		if ( $file_info && file_exists( $temp_filepath ) ) {
			return true;
		} // check if file is uplaoded on current step
		elseif ( ! empty( $_FILES[ $input_name ]['name'] ) ) {
			return true;
		}

		return false;
	}

	public function is_prepopulated_file_upload( $form_id, $input_name ) {

		// prepopulated files will be stored in the 'gform_uploaded_files' field
		$uploaded_files = json_decode( rgpost( 'gform_uploaded_files' ), ARRAY_A );

		// file is prepopulated if it is present in the 'gform_uploaded_files' field AND is not a new file upload
		$in_uploaded_files = is_array( $uploaded_files ) && array_key_exists( $input_name, $uploaded_files ) && ! empty( $uploaded_files[ $input_name ] );
		$is_prepopulated   = $in_uploaded_files && ! $this->is_new_file_upload( $form_id, $input_name );

		return $is_prepopulated;
	}

	public static function maybe_get_category_id( $field, $category_name ) {

		if ( $field['type'] == 'post_category' ) {

			if ( in_array( $field->get_input_type(), array( 'multiselect', 'checkbox' ) ) ) {
				$category_names = explode( ',', $category_name );
			} else {
				$category_names = array( $category_name );
			}

			$cat_ids = array();
			foreach ( $category_names as $name ) {
				$id = get_cat_ID( $name );
				if ( ! empty( $id ) ) {
					$cat_ids[] = $id;
				}
			}

			return implode( ',', $cat_ids );
		}

		return $category_name;
	}





	// # MULTISITE FUNCTIONALITY ---------------------------------------------------------------------------------------

	public function validate_multisite_submission( $form, $feed, $pagenum ) {

		$meta = $feed['meta'];

		// make sure multisite create site option is set
		if ( empty( $meta['createSite'] ) ) {
			return $form;
		}

		$entry = GFFormsModel::get_current_lead();

		$site_address_field = GFFormsModel::get_field( $form, $meta['siteAddress'] );
		$site_address       = $this->get_meta_value( 'siteAddress', $meta, $form, $entry );

		$site_title_field = GFFormsModel::get_field( $form, $meta['siteTitle'] );
		$site_title       = $this->get_meta_value( 'siteTitle', $meta, $form, $entry );

		// get validation result for multi-site fields
		$validation_result = wpmu_validate_blog_signup( $site_address, $site_title, wp_get_current_user() );

		// site address validation, only if on correct page
		if ( $pagenum == 0 || $site_address_field->pageNumber == $pagenum ) {

			$error_msg = isset( $validation_result['errors']->errors['blogname'][0] ) ? $validation_result['errors']->errors['blogname'][0] : false;

			if ( $error_msg != false ) {
				$form = $this->add_validation_error( $meta['siteAddress'], $form, $error_msg );
			}

		}

		// site title validation, only if on correct page
		if ( $pagenum == 0 || $site_title_field->pageNumber == $pagenum ) {

			$error_msg = isset( $validation_result['errors']->errors['blog_title'][0] ) ? $validation_result['errors']->errors['blog_title'][0] : false;

			if ( $error_msg != false ) {
				$form = $this->add_validation_error( $meta['siteTitle'], $form, $error_msg );
			}

		}

		return $form;
	}

	public function create_site( $user_id, $feed, $entry, $password ) {
		global $current_site;

		$form         = GFFormsModel::get_form_meta( $entry['form_id'] );
		$meta         = $feed['meta'];
		$set_password = false;

		if ( ! $password ) {
			$password     = $this->get_meta_value( 'password', $meta, $form, $entry );
			$set_password = $password == true;
		}

		// @review, verify what this is doing and notate here
		if ( ! $set_password ) {
			remove_filter( 'update_welcome_email', 'bp_core_filter_blog_welcome_email' );
		}

		// is create site option enabled?
		if ( ! rgar( $meta, 'createSite' ) ) {
			return false;
		}

		$site_data = $this->get_site_data( $entry, $form, $feed, $this->is_update_feed( $feed ) );
		if ( ! $site_data ) {
			return false;
		}

		// create the new site!
		/**
		 * Allows modifications to the new site meta
		 *
		 * @param array An array of new site arguments (ex. if the site is public => 1)
		 * @param array $form The Form Object to filter through
		 * @param array $entry The Entry Object to filter through
		 * @param array $feed The Feed Object to filter through
		 * @param int $user_id Filer through the ID of the user who creates the site
		 */
		$site_meta = apply_filters( 'gform_user_registration_new_site_meta', array( 'public' => 1 ), $form, $entry, $feed, $user_id, $this->is_update_feed( $feed ) );
		$blog_id   = wpmu_create_blog( $site_data['domain'], $site_data['path'], $site_data['title'], $user_id, $site_meta, $current_site->id );

		if ( is_wp_error( $blog_id ) ) {
			return false;
		}

		// backwords compat
		update_blog_option( $blog_id, 'entry_id', $entry['id'] );

		// future use
		update_blog_option( $blog_id, '_gform-entry-id', $entry['id'] );

		if ( ! is_super_admin( $user_id ) && get_user_option( 'primary_blog', $user_id ) == $current_site->blog_id ) {
			update_user_option( $user_id, 'primary_blog', $blog_id, true );
		}

		$site_role = rgar( $meta, 'siteRole' );
		if ( $site_role ) {
			$user = new WP_User( $user_id, null, $blog_id );
			$user->set_role( $site_role );
		}

		$root_role = rgar( $meta, 'rootRole' );
		// if no root role, remove user from current site
		if ( ! $root_role ) {
			remove_user_from_blog( $user_id );
		} // preserve role, aka do nothing
		elseif ( $root_role == 'gfur_preserve_role' ) {
		} // otherwise, update their role on current site
		else {
			$user = new WP_User( $user_id );
			$user->set_role( $root_role );
		}

		$this->log( sprintf( 'Calling wpmu_welcome_notification to send multisite welcome - blog_id: %d user_id: %d', $blog_id, $user_id ) );
		wpmu_welcome_notification( $blog_id, $user_id, $password, $site_data['title'], array( 'public' => 1 ) );
		$this->log( 'Done with wpmu_welcome_notification().' );

		do_action( 'gform_site_created', $blog_id, $user_id, $entry, $feed, $password );

		// return new blog ID
		return $blog_id;
	}





	// # USER ACTIVATION -----------------------------------------------------------------------------------------------

	public function handle_user_activation( $entry, $form, $feed ) {
		global $wpdb;

		require_once( $this->get_base_path() . '/includes/signups.php' );
		GFUserSignups::prep_signups_functionality();

		$user_data = $this->get_user_data( $entry, $form, $feed );

		$meta = array(
			'lead_id'    => $entry['id'],
			'entry_id'   => $entry['id'],
			'user_login' => $user_data['user_login'],
			'email'      => $user_data['user_email'],
			'password'   => GFCommon::encrypt( $user_data['password'] ),
		);

		/**
		 * A filter that allows modification of the signup meta data
		 *
		 * @param int $form['id'] The current form ID (Or one you can specify)
		 * @param array $meta All the metadata in an array (user login, email, password, etc)
		 * @param array $form The current Form object
		 * @param array $entry The Entry object (To pull the meta from the entry array)
		 * @param array $feed The Feed object
		 */
		$meta       = gf_apply_filters( 'gform_user_registration_signup_meta', $form['id'], $meta, $form, $entry, $feed );
		$ms_options = rgars( $feed, 'meta/multisite_options' );

		// save current user details in wp_signups for future activation
		if ( is_multisite() && rgar( $ms_options, 'create_site' ) && $site_data = $this->get_site_data( $entry, $form, $feed ) ) {

			/*
			 * In WP 4.4, wpmu_signup_blog() no longer automatically sends user notification email unless in MS
			 * Let's check if the action has been bound, if not, let's bind it ourselves
			 */
			if( ! has_action( 'after_signup_site', 'wpmu_signup_blog_notification' ) ) {
				add_action( 'after_signup_site', 'wpmu_signup_blog_notification', 10, 7 );
			}

			wpmu_signup_blog( $site_data['domain'], $site_data['path'], $site_data['title'], $user_data['user_login'], $user_data['user_email'], $meta );

		} else {

			// wpmu_signup_user() does the following sanitization of the user_login before saving it to the database,
			// we can run this same code here to allow successful retrievel of the activation_key without actually
			// changing the user name when it is activated. 'd smith' => 'dsmith', but when activated, username is 'd smith'.
			$user_data['user_login'] = preg_replace( '/\s+/', '', sanitize_user( $user_data['user_login'], true ) );

			$this->log( sprintf( 'Calling wpmu_signup_user() (sends email with activation link) with login: %s; email: %s; meta: %s', $user_data['user_login'], $user_data['user_email'], print_r( $meta, true ) ) );

			/*
			 * In WP 4.4, wpmu_signup_user() no longer automatically sends user notification email unless in MS
			 * Let's check if the action has been bound, if not, let's bind it ourselves
			 */
			if( ! has_action( 'after_signup_user', 'wpmu_signup_user_notification' ) ) {
				add_action( 'after_signup_user', 'wpmu_signup_user_notification', 10, 4 );
			}

			wpmu_signup_user( $user_data['user_login'], $user_data['user_email'], $meta );

			$this->log( 'Done with wpmu_signup_user()' );

		}

		$sql            = $wpdb->prepare( "SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s ORDER BY registered DESC LIMIT 1", $user_data['user_login'] );
		$activation_key = $wpdb->get_var( $sql );

		// used for filtering on activation listing UI
		GFUserSignups::add_signup_meta( $entry['id'], $activation_key );

	}

	public function maybe_activate_user() {

		if ( rgget( 'page' ) == 'gf_activation' ) {
			require_once( $this->get_base_path() . '/includes/activate.php' );
			exit();
		}

	}





	// # BUDDYPRESS FUNCTIONALITY --------------------------------------------------------------------------------------

	public static function get_buddypress_fields() {

		require_once( WP_PLUGIN_DIR . '/buddypress/bp-xprofile/bp-xprofile-classes.php' );

		// get BP field groups
		$groups = BP_XProfile_Group::get( array( 'fetch_fields' => true ) );

		$buddypress_fields = array();
		$i                 = 0;
		foreach ( $groups as $group ) {

			if ( ! is_array( $group->fields ) ) {
				continue;
			}

			foreach ( $group->fields as $field ) {
				$buddypress_fields[ $i ]['label'] = $field->name;
				$buddypress_fields[ $i ]['value'] = $field->id;
				$i ++;
			}
		}

		$empty_choice = array(
			'label' => esc_html__( 'Select BuddyPress Field', 'gravityformsuserregistration' ),
			'value' => ''
		);

		return array_merge( array( $empty_choice ), $buddypress_fields );
	}

	public function update_buddypress_data( $user_id, $feed, $entry ) {

		// required for user to display in the directory
		if ( function_exists( 'bp_update_user_last_activity' ) ) {
			bp_update_user_last_activity( $user_id );
		} else {
			bp_update_user_meta( $user_id, 'last_activity', true );
		}

		$bp_data = $this->prepare_buddypress_data( $user_id, $feed, $entry );

		$this->insert_buddypress_data( $bp_data );

	}

	public function insert_buddypress_data( $bp_data ) {
		global $wpdb, $bp;

		require_once( WP_PLUGIN_DIR . '/buddypress/bp-xprofile/bp-xprofile-functions.php' );

		foreach ( $bp_data as $item ) {
			$success = xprofile_set_field_data( $item['field_id'], $item['user_id'], $item['value'] );
			xprofile_set_field_visibility_level( $item['field_id'], $item['user_id'], $item['field']->default_visibility );
		}

	}

	public function prepare_buddypress_data( $user_id, $feed, $entry ) {

		$bp_data = array();
		$meta    = $this->get_buddypress_meta( $feed );

		if ( empty( $meta ) ) {
			return $bp_data;
		}

		$form = GFFormsModel::get_form_meta( $entry['form_id'] );

		foreach ( $meta as $bp_field_id => $gf_field_id ) {

			if ( empty( $bp_field_id ) || empty( $gf_field_id ) ) {
				continue;
			}

			$item             = array();
			$item['field_id'] = $bp_field_id;
			$item['user_id']  = $user_id;

			// get GF and BP fields
			$gform_field = GFFormsModel::get_field( $form, $gf_field_id );

			if ( version_compare( BP_VERSION, '1.6', '<' ) ) {
				$bp_field = new BP_XProfile_Field();
				$bp_field->bp_xprofile_field( $bp_field_id );
			} else {
				require_once( WP_PLUGIN_DIR . '/buddypress/bp-xprofile/bp-xprofile-classes.php' );
				$bp_field = new BP_XProfile_Field( $bp_field_id );
			}

			// if BuddyPress field is a checkbox AND GF field is a checkbox, get array of input values
			$input_type = is_object( $gform_field ) ? $gform_field->get_input_type() : '';

			if ( in_array( $bp_field->type, array(
					'checkbox',
					'multiselectbox'
				) ) && in_array( $input_type, array( 'checkbox', 'multiselect' ) )
			) {

				$meta_value = GFFormsModel::get_lead_field_value( $entry, $gform_field );

				if ( ! is_array( $meta_value ) ) {
					$meta_value = explode( ',', $meta_value );
				}

				$meta_value = $this->maybe_get_category_name( $gform_field, $meta_value );
				$meta_value = array_values( array_filter( $meta_value, array( $this, 'not_empty' ) ) );

			} elseif ( $bp_field->type == 'datebox' && $input_type == 'date' ) {
				if ( version_compare( BP_VERSION, '2.1.1', '<' ) ) {
					$meta_value = strtotime( $this->get_meta_value( $bp_field_id, $meta, $form, $entry ) );
				} else {
					$meta_value = $this->get_meta_value( $bp_field_id, $meta, $form, $entry ) . ' 00:00:00';
				}
			} else {
				$meta_value = $this->get_meta_value( $bp_field_id, $meta, $form, $entry );
			}

			$item['value']       = $meta_value;
			$item['last_update'] = date( 'Y-m-d H:i:s' );
			$item['field']       = $bp_field;

			$bp_data[] = $item;

		}

		return $bp_data;
	}

	/**
	 * Believe this was added to trigger a notification in the BP activity feed for new signups.
	 *
	 * @param mixed $user_id
	 */
	public function do_buddypress_user_signup( $user_id ) {

		// this function overwrites the default meta we've just added
		remove_action( 'bp_core_activated_user', 'xprofile_sync_wp_profile' );

		do_action( 'bp_core_activated_user', $user_id, null, new WP_User( $user_id ) );
	}

	public function not_empty( $value ) {
		return rgblank( $value ) ? false : $value;
	}

	public function get_buddypress_meta( $feed ) {
		return $this->prepare_dynamic_meta( rgars( $feed, 'meta/bpMeta' ) );
	}





	// # AJAX FUNCTIONS ------------------------------------------------------------------------------------------------

	public function init_ajax() {

		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_user_registration_menu', array( $this, 'ajax_dismiss_menu' ) );
		add_action( 'wp_ajax_gf_user_activate', array( __class__, 'activate_user' ) );

	}

	public function ajax_dismiss_menu() {
		$current_user = wp_get_current_user();
		update_metadata( 'user', $current_user->ID, 'dismiss_user_registration_menu', '1' );
	}

	public static function activate_user() {
		require_once( gf_user_registration()->get_base_path() . '/includes/signups.php' );

		GFUserSignups::prep_signups_functionality();

		$key           = rgpost( 'key' );
		$userdata      = GFUserSignups::activate_signup( $key );
		$error_message = '';

		if ( is_wp_error( $userdata ) ) {
			$error_message = $userdata->get_error_message();
		}

		echo $error_message;

		exit;
	}





	// # TEMPORARY MENU PAGE (for 2.x to 3.x upgrade) ------------------------------------------------------------------

	public function maybe_create_temporary_menu_item( $menus ) {
		$current_user = wp_get_current_user();
		$dismiss_menu = get_metadata( 'user', $current_user->ID, 'dismiss_user_registration_menu', true );
		if ( $dismiss_menu != '1' ) {
			$menus[] = array(
				'name'       => $this->_slug,
				'label'      => $this->get_short_title(),
				'callback'   => array( $this, 'temporary_plugin_page' ),
				'permission' => $this->_capabilities_form_settings,
			);
		}

		return $menus;
	}

	public function temporary_plugin_page() {
		?>
		<script type="text/javascript">
			function dismissMenu(){
				jQuery('#gf_spinner').show();
				jQuery.post(ajaxurl, {
						action : "gf_dismiss_user_registration_menu"
					},
					function (response) {
						document.location.href='?page=gf_edit_forms';
						jQuery('#gf_spinner').hide();
					}
				);

			}
		</script>

		<div class="wrap about-wrap">
			<h1><?php esc_html_e( 'User Registration Add-On v3.0', 'gravityformsuserregistration' ) ?></h1>
			<div class="about-text"><?php esc_html_e( 'Thank you for updating! The new version of the Gravity Forms User Registration Add-On makes changes to how you manage your User Registration feeds.', 'gravityformsuserregistration' ) ?></div>
			<div class="changelog">

				<hr/>

				<div class="feature-section col two-col">
					<div class="col-1">
						<h3><?php esc_html_e( 'Manage User Registration Contextually', 'gravityformsuserregistration' ) ?></h3>
						<p><?php esc_html_e( 'User Registration Feeds are now accessed via the User Registration sub-menu within the Form Settings for the Form with which you would like to register a user.', 'gravityformsuserregistration' ) ?></p>
					</div>
					<div class="col-2 last-feature">
						<img src="http://gravityforms.s3.amazonaws.com/webimages/AddonNotice/NewUserRegistration3.png" style="margin-top:20px;">
					</div>
				</div>

				<hr/>

				<form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
					<input type="checkbox" name="dismiss_user_registration_menu" id="dismiss_user_registration_menu" value="1" onclick="dismissMenu();"> <label for="dismiss_user_registration_menu"><?php esc_html_e( 'I understand this change, dismiss this message!', 'gravityformsuserregistration' ) ?></label>
					<img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif'?>" alt="<?php esc_html_e( 'Please wait...', 'gravityformsuserregistration' ) ?>" style="display:none;"/>
				</form>

			</div>
		</div>
	<?php
	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'Global Settings', 'gravityformsuserregistration' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'    => 'custom_registration_page_enable',
						'label'   => esc_html__( 'Custom Registration Page', 'gravityformsuserregistration' ),
						'type'    => 'checkbox',
						'choices' => array( array(
							'label' => esc_html__( 'Enable Custom Registration Page', 'gravityformsuserregistration' ),
							'name'  => 'custom_registration_page_enable'
						) ),
						'tooltip' => sprintf( '<h6>%s</h6> %s', esc_html__( 'Custom Registration Page', 'gravityformsuserregistration' ), esc_html__( 'When users attempt to access the default WordPress registration page, they will be redirected to this custom page instead.', 'gravityformsuserregistration' ) ),
						'onclick' => 'jQuery( "form#gform-settings" ).submit();'
					),
					array(
						'name'    => 'custom_registration_page',
						'label'   => esc_html__( 'Registration Page', 'gravityformsuserregistration' ),
						'type'    => 'select',
						'choices' => $this->get_pages_as_choices( esc_html__( 'Select a Page', 'gravityformsuserregistration' ) ),
						'dependency'  => array(
							'field'   => 'custom_registration_page_enable',
							'values'  => '1'
						),
						'required' => true
					)
				)
			),
		);
	}

	public function get_pages_as_choices( $default_option = false ) {

		$pages = get_pages( array(
			'post_status' => 'publish',
			'nopaging'    => true
		) );

		require_once( $this->get_base_path() . '/includes/class-gf-page-choice-walker.php' );

		$walker = new GF_Page_Choice_Walker;

		$choices = $walker->walk( $pages, 9 );

		if( $default_option ) {
			if( ! is_array( $default_option ) ) {
				$default_option = array(
					'label'  => $default_option,
					'value' => ''
				);
			}
			array_unshift( $choices, $default_option );
		}

		return $choices;
	}





	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	public function feed_settings_fields() {

		$is_update_feed = $this->is_update_feed( $this->get_current_feed() ) || $this->get_setting( 'feedType' ) == 'update';

		$fields = array();

		$fields['feed_settings'] = array(
			'title'       => esc_html__( 'Feed Settings', 'gravityformsuserregistration' ),
			'description' => '',
			'fields'      => array(
				array(
					'name'     => 'feedName',
					'label'    => esc_html__( 'Name', 'gravityformsuserregistration' ),
					'type'     => 'text',
					'required' => true,
					'class'    => 'medium',
					'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Name', 'gravityformsuserregistration' ), esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsuserregistration' ) )
				),
				array(
					'name'     => 'feedType',
					'label'    => esc_html__( 'Action', 'gravityformsuserregistration' ),
					'type'     => 'select',
					'required' => true,
					'tooltip'  => sprintf(
						'<h6>%s</h6> %s <p><em>%s</em></p>',
						esc_html__( 'Action', 'gravityformsuserregistration' ),
						esc_html__( 'Select the type of feed you would like to create. "Create" feeds will create a new user. "Update" feeds will update users.', 'gravityformsuserregistration' ),
						__( 'A form can have multiple "Create" feeds <strong>or</strong> a single "Update" feed. A form cannot have both a "Create" feed and an "Update" feed.', 'gravityformsuserregistration' )
					),
					'choices'  => array_merge(
						array( array(
							'label' => esc_html__( 'Select an Action', 'gravityformsuserregistration' ),
							'value' => ''
						) ),
						$this->get_available_feed_actions()
					),
					'onchange' => 'jQuery( this ).parents( "form" ).submit();'
				)
			)
		);

		$fields['user_settings'] = array(
			'title'       => esc_html__( 'User Settings', 'gravityformsuserregistration' ),
			'description' => '',
			'dependency'  => array(
				'field'   => 'feedType',
				'values'  => '_notempty_'
			),
			'fields'      => array(
				array(
					'name'     => 'username',
					'label'    => esc_html__( 'Username', 'gravityformsuserregistration' ),
					'type'     => 'field_select',
					'args'     => array(
						'callback' => array( $this, 'is_applicable_field_for_field_select' )
					),
					'required' => true,
					'class'    => 'medium',
					'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Username', 'gravityformsuserregistration' ), esc_html__( 'Select the form field that should be used for the user\'s username.', 'gravityformsuserregistration' ) ),
					'dependency' => array(
						'field'  => 'feedType',
						'values' => 'create'
					)
				),
				array(
					'name'     => 'first_name',
					'label'    => esc_html__( 'First Name', 'gravityformsuserregistration' ),
					'type'     => 'field_select',
					'args'     => array(
						'callback' => array( $this, 'is_applicable_field_for_field_select' )
					),
					'class'    => 'medium',
					'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'First Name', 'gravityformsuserregistration' ), esc_html__( 'Select the form field that should be used for the user\'s first name.', 'gravityformsuserregistration' ) )
				),
				array(
					'name'     => 'last_name',
					'label'    => esc_html__( 'Last Name', 'gravityformsuserregistration' ),
					'type'     => 'field_select',
					'args'     => array(
						'callback' => array( $this, 'is_applicable_field_for_field_select' )
					),
					'class'    => 'medium',
					'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Last Name', 'gravityformsuserregistration' ), esc_html__( 'Select the form field that should be used for the user\'s last name.', 'gravityformsuserregistration' ) )
				),
				array(
					'name'     => 'displayname',
					'label'    => esc_html__( 'Display Name', 'gravityformsuserregistration' ),
					'type'     => 'select',
					'class'    => 'medium',
					'choices'  => $this->get_display_name_choices(),
					'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Display Name', 'gravityformsuserregistration' ), esc_html__( 'Select how the user\'s name should be displayed publicly.', 'gravityformsuserregistration' ) )
				),
				array(
					'name'        => 'email',
					'label'       => esc_html__( 'Email Address', 'gravityformsuserregistration' ),
					'type'        => 'field_select',
					'args'        => array(
						'disable_first_choice' => true,
						'input_types'          => array( 'email' )
					),
					'required'    => ! $is_update_feed ,
					'class'       => 'medium',
					'tooltip'     => sprintf( '<h6>%s</h6> %s', esc_html__( 'Email Address', 'gravityformsuserregistration' ), esc_html__( 'Select the form field that should be used for the user\'s email address.', 'gravityformsuserregistration' ) )
				),
				array(
					'name'     => 'password',
					'label'    => esc_html__( 'Password', 'gravityformsuserregistration' ),
					'type'     => 'field_select',
					'class'    => 'medium',
					'args'     => array(
						'disable_first_choice' => true,
						'input_types'          => array( 'password' ),
						'append_choices'       => $this->get_extra_password_choices()
					),
					'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Password', 'gravityformsuserregistration' ), esc_html__( 'Select the form field that should be used for the user\'s password.', 'gravityformsuserregistration' ) )
				),
				array(
					'name'     => 'role',
					'label'    => esc_html__( 'Role', 'gravityformsuserregistration' ),
					'type'     => 'select',
					'class'    => 'medium',
					'choices'  => $this->get_roles_as_choices(),
					'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Role', 'gravityformsuserregistration' ), esc_html__( 'Select the role the user should be assigned.', 'gravityformsuserregistration' ) ),
					'default_value'    => 'gfur_preserve_role',
					'dependency' => array(
						'field'  => 'createSite',
						'values' => false
					)
				),
			)
		);

		$fields['user_meta'] = array(
			'title'       => esc_html__( 'User Meta', 'gravityformsuserregistration' ),
			'description' => '',
			'dependency'  => array(
				'field'   => 'feedType',
				'values'  => '_notempty_'
			),
			'fields'      => array(
				array(
					'name'      => 'userMeta',
					'label'     => '',
					'type'      => 'dynamic_field_map',
					'field_map' => $this->get_user_meta_choices(),
					'class'     => 'medium'
				)
			)
		);

		if ( $this->is_bp_active() ) {

			$fields['bp_meta'] = array(
				'title'       => esc_html__( 'BuddyPress Profile', 'gravityformsuserregistration' ),
				'description' => '',
				'dependency'  => array(
					'field'   => 'feedType',
					'values'  => '_notempty_'
				),
				'fields'      => array(
					array(
						'name'      => 'bpMeta',
						'label'     => '',
						'type'      => 'dynamic_field_map',
						'field_map' => $this->get_buddypress_fields(),
						'class'     => 'medium',
						'disable_custom' => true
					)
				)
			);
		}

		$enable_multisite_section = apply_filters( 'gform_user_registration_enable_multisite_section', $this->is_root_site() );

		if ( is_multisite() && $enable_multisite_section ) {
			$fields['network_settings'] = array(
				'title'       => esc_html__( 'Network Options', 'gravityformsuserregistration' ),
				'description' => '',
				'dependency'  => array(
					'field'   => 'feedType',
					'values'  => '_notempty_'
				),
				'fields'      => array(
					array(
						'name'      => 'createSite',
						'label'     => esc_html__( 'Create Site', 'gravityformsuserregistraiton' ),
						'type'      => 'checkbox',
						'choices'   => array(
							array(
								'label' => esc_html__( 'Create new site when a user register.', 'gravityformsuserregistration' ),
								'name'  => 'createSite',
								'value' => 1,
								'onclick' => 'jQuery( this ).parents( "form" ).attr( "action", "#gaddon-setting-row-createSite" ).submit();'
							)
						)
					),
					array(
						'label'    => esc_html__( 'Site Address', 'gravityformsuserregistration' ),
						'name'     => 'siteAddress',
						'required' => true,
						'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Site Address', 'gravityformsuserregistration' ), esc_html__( 'Select the form field that should be used for the site address.', 'gravityformsuserregistration' ) ),
						'type'     => 'field_select',
						'class'    => 'medium',
						'dependency'  => array(
							'field'   => 'createSite',
							'values'  => 1
						)
					),
					array(
						'label'    => esc_html__( 'Site Title', 'gravityformsuserregistration' ),
						'name'     => 'siteTitle',
						'required' => true,
						'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Site Title', 'gravityformsuserregistration' ), esc_html__( 'Select the form field that should be used for the site title.', 'gravityformsuserregistration' ) ),
						'type'     => 'field_select',
						'class'    => 'medium',
						'dependency'  => array(
							'field'   => 'createSite',
							'values'  => 1
						)
					),
					array(
						'name'     => 'siteRole',
						'label'    => esc_html__( 'Site Role', 'gravityformsuserregistration' ),
						'type'     => 'select',
						'class'    => 'medium',
						'choices'  => $this->get_roles_as_choices(),
						'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Site Role', 'gravityformsuserregistration' ), esc_html__( 'Select role the user should be assigned on the newly created site.', 'gravityformsuserregistration' ) ),
						'required' => true,
						'dependency'  => array(
							'field'   => 'createSite',
							'values'  => 1
						)
					),
					array(
						'name'     => 'rootRole',
						'label'    => esc_html__( 'Current Site Role', 'gravityformsuserregistration' ),
						'type'     => 'select',
						'class'    => 'medium',
						'choices'  => $this->get_roles_as_choices( true ),
						'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Current Site Role', 'gravityformsuserregistration' ), esc_html__( 'Select role the user should be assigned on the site they registered from. This option overrides the "Role" option under User Settings.', 'gravityformsuserregistration' ) ),
						'default_value' => $is_update_feed ? 'gfur_preserve_role' : '',
						'dependency'  => array(
							'field'   => 'createSite',
							'values'  => 1
						)
					)
				)
			);
		}

		$fields['additional_settings'] = array(
			'title'       => esc_html__( 'Additional Options', 'gravityformsuserregistration' ),
			'description' => '',
			'dependency'  => array(
				'field'   => 'feedType',
				'values'  => '_notempty_'
			),
			'fields'      => array(
				array(
					'name'      => 'sendEmail',
					'label'     => esc_html__( 'Send Email?', 'gravityformsuserregistration' ),
					'type'      => 'checkbox',
					'choices'   => array(
						array(
							'label'         => esc_html__( 'Send this password to the new user by email.', 'gravityformsuserregistration' ),
							'value'         => 1,
							'name'          => 'sendEmail',
							'default_value' => 1
						)
					),
					'tooltip' => sprintf( '<h6>%s</h6> %s', esc_html__( 'Send Email?', 'gravityformsuserregistration' ), sprintf( esc_html__( 'Specify whether to send the password to the new user by email. %sEnabled by default.%s', 'gravityformsuserregistration' ), '<em class="enabled-by-default">', '</em>' ) ),
					'dependency'  => array(
						'field'   => 'feedType',
						'values'  => 'create'
					),
				),
				array(
					'name'      => 'userActivation',
					'label'     => esc_html__( 'User Activation', 'gravityformsuserregistration' ),
					'type'      => 'checkbox_and_select',
					'checkbox'  => array(
						'label' => esc_html__( 'Enable user activation.', 'gravityformsuserregistration' )
					),
					'select'    => array(
						'choices' => array(
							array(
								'label' => esc_html__( 'by email', 'gravityformsuserregistration' ),
								'value' => 'email'
							),
							array(
								'label' => esc_html__( 'manually', 'gravityformsuserregistration' ),
								'value' => 'manual'
							)
						),
						'tooltip' => sprintf( '<h6>%s</h6> %s', esc_html__( 'User Activation Type', 'gravityformsuserregistration' ), sprintf( esc_html__( '%sBy Email:%s Send the user an email with an activation link.%s%1$sManually:%2$s Activate each user manually from the Pending Activations page.', 'gravityformsuserregistration' ), '<strong>', '</strong>', '<br />' ) )
					),
					'tooltip'     => sprintf( '<h6>%s</h6> %s', esc_html__( 'User Activation', 'gravityformsuserregistration' ), esc_html__( 'Send users an email with an activation link. Users are only registered once they have activated their accounts.', 'gravityformsuserregistration' ) ),
					'dependency'  => array(
						'field'   => 'feedType',
						'values'  => 'create'
					),
				),
				array(
					'name'           => 'registrationCondition',
					'label'          => $is_update_feed ? esc_html__( 'Update Condition', 'gravityformsuserregistration' ) : esc_html__( 'Registration Condition', 'gravityformsuserregistration' ),
					'type'           => 'feed_condition',
					'checkbox_label' => esc_html__( 'Enable', 'gravityformsmailchimp' ),
					'instructions'   => $is_update_feed ? esc_html__( 'Update user if', 'gravityformsmailchimp' ) : esc_html__( 'Register user if', 'gravityformsmailchimp' ),
					'tooltip'        => $is_update_feed ?
						sprintf( '<h6>%s</h6> %s', esc_html__( 'Update Condition', 'gravityformsuserregistration' ), esc_html__( 'When the update condition is enabled, form submissions will only update the user when the condition is met. The user data will always be populated into the form.', 'gravityformsuserregistration' ) ) :
						sprintf( '<h6>%s</h6> %s', esc_html__( 'Registration Condition', 'gravityformsuserregistration' ), esc_html__( 'When the registration condition is enabled, form submissions will only register the user when the condition is met.', 'gravityformsuserregistration' ) )
				)
			)
		);

		$form = $this->get_current_form();

		if ( GFCommon::has_post_field( $form['fields'] ) ) {
			$set_post_author_field = array(
				'name'      => 'setPostAuthor',
				'label'     => esc_html__( 'Set Post Author', 'gravityformsuserregistration' ),
				'type'      => 'checkbox',
				'choices'   => array(
					array(
						'label'         => esc_html__( 'Set user as post author.', 'gravityformsuserregistration' ),
						'name'          => 'setPostAuthor',
						'value'         => 1,
						'default_value' => 1
					)
				),
				'tooltip' => sprintf( '<h6>%s</h6> %s', esc_html__( 'Set Post Author', 'gravityformsuserregistration' ), sprintf( esc_html__( 'When a form submission creates a post and registers a user, set the new user as the post author. %sEnabled by default.%s', 'gravityformsuserregistration' ), '<em class="enabled-by-default">', '</em>' ) ),
				'dependency'  => array(
					'field'   => 'feedType',
					'values'  => 'create'
				),
			);
			$fields = $this->add_field_after( 'sendEmail', $set_post_author_field, $fields );
		}

		$fields['save'] = array(
			'fields' => array(
				array(
					'type' => 'save',
					'onclick' => '( function( $, elem, event ) {
						var $form       = $( elem ).parents( "form" ),
							action      = $form.attr( "action" ),
							hashlessUrl = document.URL.replace( window.location.hash, "" );

						if( ! action && hashlessUrl != document.URL ) {
							event.preventDefault();
							$form.attr( "action", hashlessUrl );
							$( elem ).click();
						};

					} )( jQuery, this, event );'
				)
			)
		);

		/**
		 * Filter the setting fields that appears on the feed page.
		 *
		 * @since 3.0.beta1.1
		 *
		 * @param array $fields An array of setting fields.
		 * @param array $form Form object to which the current feed belongs.
		 *
		 * @see https://gist.github.com/spivurno/15592a66497096338864
		 */
		$fields = apply_filters( 'gform_userregistration_feed_settings_fields', $fields, $form );

		// sections cannot be an associative array
		return array_values( $fields );
	}

	function get_update_user_actions_choices() {

		$choices = $this->get_roles_as_choices();

		foreach ( $choices as &$choice ) {
			$choice['label'] = sprintf( esc_html__( 'Set as %s', 'gravityformsuserregistration' ), $choice['label'] );
		}

		return $choices;
	}

	function get_update_site_actions_choices() {

		$choices = array(
			array(
				'label' => esc_html__( 'Deactivate site', 'gravityformsuserregistration' ),
				'value' => 'deactivate'
			),
			array(
				'label' => esc_html__( 'Delete site', 'gravityformsuserregistration' ),
				'value' => 'delete'
			)
		);

		return $choices;
	}

	function is_applicable_field_for_field_select( $is_applicable_field, $field ) {

		if ( rgobj( $field, 'multipleFiles' ) ) {
			$is_applicable_field = false;
		}

		return $is_applicable_field;
	}

	public function get_user_meta_choices() {
		global $wpdb;

		// standard meta
		$standard_meta = array(
			'label'   => esc_html__( 'Standard User Meta', 'gravityformsuserregistration' ),
			'choices' => array(
				array(
					'label' => esc_html__( 'Website', 'gravityformsuserregistration' ),
					'value' => 'user_url'
				),
				array(
					'label' => esc_html__( 'AIM', 'gravityformsuserregistration' ),
					'value' => 'aim'
				),
				array(
					'label' => esc_html__( 'Yahoo', 'gravityformsuserregistration' ),
					'value' => 'yim'
				),
				array(
					'label' => esc_html__( 'Jabber / Google Talk', 'gravityformsuserregistration' ),
					'value' => 'jabber'
				),
				array(
					'label' => esc_html__( 'Biographical Information', 'gravityformsuserregistration' ),
					'value' => 'description'
				)
			)
		);

		// other meta
		$keys     = array();
		$raw_keys = $wpdb->get_results( sprintf( 'select distinct meta_key from %s where meta_key not like "%s" order by meta_key asc', $wpdb->usermeta, '\_%' ) );
		$exclude  = array( 'user_url', 'aim', 'yim', 'jabber', 'description' );

		foreach ( $raw_keys as $key ) {
			if ( ! in_array( $key->meta_key, $exclude ) ) {
				$keys[] = array(
					'name'     => $key->meta_key,
					'label'    => $key->meta_key,
					'required' => false
				);
			}
		}

		$other_meta = array(
			'label'   => esc_html__( 'Other User Meta', 'gravityformsuserregistration' ),
			'choices' => $keys
		);

		// custom option to add custom meta key
		$add_custom_meta = array(
			'label' => esc_html__( 'Add Custom Meta', 'gravityformsuserregistration' ),
			'value' => 'gf_custom'
		);

		$empty_choice = array(
			'label' => esc_html__( 'Select Meta Key', 'gravityformsuserregistration' ),
			'value' => ''
		);

		return array_merge( array( $empty_choice ), array( $standard_meta ), array( $other_meta ), array( $add_custom_meta ) );
	}

	public static function get_field_map_choices( $form_id, $field_type = null, $exclude_field_types = null ) {

		$choices = parent::get_field_map_choices( $form_id, $field_type );
		$form    = GFAPI::get_form( $form_id );

		for ( $i = count( $choices ) - 1; $i >= 0; $i -- ) {

			if ( ! is_numeric( $choices[ $i ]['value'] ) ) {
				continue;
			}

			$field = GFFormsModel::get_field( $form, $choices[ $i ]['value'] );
			if ( ! gf_user_registration()->is_applicable_field_for_field_select( true, $field ) ) {
				unset( $choices[ $i ] );
			}

		}

		return $choices;
	}

	public function no_output_field_properties( $props ) {
		$props[] = 'args';
		return $props;
	}

	public function feed_list_title() {

		$title = '';

		if ( $this->is_feed_list_page() ) {

			$title = sprintf( esc_html__( '%s Feeds', 'gravityforms' ), $this->get_short_title() );
			$form  = GFAPI::get_form( rgget( 'id' ) );

			if ( ! $this->has_feed_type( 'update', $form ) ) {
				$title .= sprintf( ' <a class="add-new-h2" href="%s">%s</a>', add_query_arg( array( 'fid' => '0' ) ), esc_html__( 'Add New', 'gravityforms' ) );
			}

		}

		return $title;
	}

	public function get_available_feed_actions() {

		// forms can have multiple "create" feeds
		// forms can have only a single "update" feed
		// any given form can only have one type of feed, a form with a "create" feed can not have an "update" feed and vice versa
		$actions = $this->get_feed_actions();

		$form = GFAPI::get_form( rgget( 'id' ) );
		if ( is_wp_error( $form ) ) {
			return $actions;
		}

		$feed = $this->get_current_feed();

		if ( $this->has_feed_type( 'create', $form, $feed['id'] ) ) {
			$actions['update']['disabled'] = true;
		}

		return $actions;
	}

	public function get_extra_password_choices() {

		$choices   = array();
		$feed_type = $this->get_setting( 'feedType' );

		if ( $feed_type == 'update' ) {
			$choices[] = array(
				'label' => esc_html__( '&mdash; Preserve current password &mdash;', 'gravityformsuserregistration' ),
				'value' => ''
			);
		} elseif ( $feed_type == 'create' ) {
			$choices[] = array(
				'label' => esc_html__( 'Auto Generate Password', 'gravityformsuserregistration' ),
				'value' => 'generatepass'
			);
		}


		return $choices;
	}

	public function get_feed_actions() {
		return array(
			'create' => array(
				'label' => esc_html__( 'Create User', 'gravityformsuserregistration' ),
				'value' => 'create'
			),
			'update' => array(
				'label' => esc_html__( 'Update User', 'gravityformsuserregistration' ),
				'value' => 'update'
			)
		);
	}

	public function has_feed_type( $feed_type, $form, $current_feed_id = false ) {

		$feeds = $this->get_feeds( $form['id'] );

		foreach ( $feeds as $feed ) {

			// skip current feed as it may be changing feed type
			if ( $current_feed_id && $feed['id'] == $current_feed_id ) {
				continue;
			}

			// if there is no feed type specified, default to "create"
			if ( ! rgars( $feed, 'meta/feedType' ) ) {
				$feed['meta']['feedType'] = 'create';
			}

			if ( rgars( $feed, 'meta/feedType' ) == $feed_type ) {
				return true;
			}

		}

		return false;
	}

	public function get_display_name_choices() {

		$display_names = $this->get_display_names();
		$choices       = array();

		foreach ( $display_names as $value => $label ) {
			$choices[] = array(
				'label' => $label,
				'value' => $value
			);
		}

		return $choices;
	}

	public function get_display_names() {
		return array(
			'username'  => esc_html__( '{username}', 'gravityformsuserregistration' ),
			'firstname' => esc_html__( '{first name}', 'gravityformsuserregistration' ),
			'lastname'  => esc_html__( '{last name}', 'gravityformsuserregistration' ),
			'firstlast' => esc_html__( '{first name} {last name}', 'gravityformsuserregistration' ),
			'lastfirst' => esc_html__( '{last name} {first name}', 'gravityformsuserregistration' )
		);
	}

	public function get_roles_as_choices( $include_no_role_option = false ) {

		$roles   = get_editable_roles();
		$choices = array();

		foreach ( $roles as $role => $details ) {
			$name      = translate_user_role( $details['name'] );
			$choices[] = array(
				'label' => $name,
				'value' => $role
			);
		}

		$feed_type = $this->get_setting( 'feedType' );

		if ( $feed_type == 'update' ) {
			$choices[] = array(
				'label' => esc_html__( '&mdash; Preserve current role &mdash;', 'gravityformsuserregistration' ),
				'value' => 'gfur_preserve_role'
			);
		}

		if ( $include_no_role_option ) {
			$choices[] = array(
				'label' => esc_html__( '&mdash; No role for this site &mdash;', 'gravityformsuserregistration' ),
				'value' => ''
			);
		}

		return $choices;
	}

	public function feed_list_columns() {

		$columns = array(
			'feedName' => esc_html__( 'Name', 'gravityformsuserregistration' ),
			'feedType' => esc_html__( 'Action', 'gravityformsuserregistration' )
		);

		return $columns;
	}

	public function get_column_value_feedType( $item ) {

		$feed_types        = $this->get_feed_actions();
		$feed_type         = $item['meta']['feedType'];
		$feed_type_options = $feed_types[ $feed_type ];

		return $feed_type_options['label'];
	}

	public function get_select_option( $choice, $selected_value ) {

		if ( is_array( $selected_value ) ) {
			$selected = in_array( $choice['value'], $selected_value ) ? "selected='selected'" : '';
		} else {
			$selected = selected( $selected_value, $choice['value'], false );
		}

		$disabled = rgar( $choice, 'disabled' ) == true ? 'disabled="disabled"' : '';

		return sprintf( '<option value="%1$s" %2$s %4$s>%3$s</option>', esc_attr( $choice['value'] ), $selected, $choice['label'], $disabled );
	}

	public function settings_dynamic_field_map( $field, $echo = true ) {
		$html = parent::settings_dynamic_field_map( $field, false );
		ob_start();
		?>

		<style type="text/css">
			.settings-field-map-table .value { width: 308px; max-width: 100%; }
			.settings-field-map-table .repeater-buttons { margin-left: 16px; }
		</style>

		<?php
		$html .= ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function single_setting_row( $field ) {

		$display = rgar( $field, 'hidden' ) || rgar( $field, 'type' ) == 'hidden' ? 'style="display:none;"' : '';

		?>

		<tr id="gaddon-setting-row-<?php echo $field['name'] ?>" <?php echo $display; ?>>
			<?php if( $field['type'] != 'dynamic_field_map' ): ?>
				<th>
					<?php $this->single_setting_label( $field ); ?>
				</th>
			<?php endif; ?>
			<td>
				<?php $this->single_setting( $field ); ?>
			</td>
		</tr>

	<?php
	}

	public function add_paypal_settings( $settings, $form ) {

		$ur_settings = array(
			'title'   => esc_html__( 'User Registration Options', 'gravityformsuserregistration' ),
			'tooltip' => sprintf( '<h6>%s</h6> %s', esc_html__( 'User Registration Options', 'gravityformsuserregistration' ), esc_html__( 'The selected form also has a User Registration feed. These options allow you to specify how you would like the PayPal and User Registration Add-ons to work together.', 'gravityformuserregistration' ) ),
			'fields'  => array()
		);

		$ur_settings['fields'][] = array(
			'name'    => "delay_{$this->_slug}",
			'label'   => esc_html__( 'Delay User Registration', 'gravityformsuserregistration' ),
			'type'    => 'checkbox',
			'choices' => array(
				array(
					'name'  => "delay_{$this->_slug}",
					'label' => rgar( $this->delayed_payment_integration, 'option_label' ),
					'value' => 1
				)
			)
		);

		$ur_settings['fields'][] = array(
			'name'     => 'cancellationActionUser',
			'label'    => esc_html__( 'Update User on Cancel', 'gravityformsuserregistration' ),
			'type'     => 'checkbox_and_select',
			'checkbox' => array(
				'label' => esc_html__( 'Update user when subscription is cancelled.', 'gravityformsuserregistration' )
			),
			'select'   => array(
				'choices' => $this->get_update_user_actions_choices()
			)
		);

		if ( is_multisite() ) {
			$ur_settings['fields'][] = array(
				'name'     => 'cancellationActionSite',
				'label'    => esc_html__( 'Update Site on Cancel', 'gravityformsuserregistration' ),
				'type'     => 'checkbox_and_select',
				'checkbox' => array(
					'label' => esc_html__( 'Update site when subscription is cancelled.', 'gravityformsuserregistration' )
				),
				'select'   => array(
					'choices' => $this->get_update_site_actions_choices()
				)
			);
		}

		$settings = array_merge( $settings, array( $ur_settings ) );

		return $settings;
	}





	// # HELPERS -------------------------------------------------------------------------------------------------------

	public function log( $message ) {
		$calling_method = $this->get_calling_method( 2 );
		$this->log_debug( sprintf( '%s(): %s', $calling_method, $message ) );
	}

	/**
	 * Return the name of the method which called the current method.
	 *
	 * @props http://stackoverflow.com/a/9133897/227711
	 *
	 * @param int $back_trace_count Defaults to 1. Set to a higher number to get methods higher in the stack trace.
	 *
	 * @return mixed
	 */
	public function get_calling_method( $back_trace_count = 1 ) {

		$e         = new Exception();
		$trace     = $e->getTrace();
		$last_call = $trace[ $back_trace_count ];

		return sprintf( '%s::%s', $last_call['class'], $last_call['function'] );
	}

	public function is_bp_active() {
		return defined( 'BP_VERSION' );
	}

	public function user_login_exists( $user_login ) {

		if ( ! function_exists( 'username_exists' ) ) {
			require_once( ABSPATH . WPINC . '/registration.php' );
		}

		return username_exists( $user_login );
	}

	public function is_users_email( $email, $user_id = false ) {

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = new WP_User( $user_id );

		return $user->get( 'user_email' ) == $email;
	}

	public function is_meta_key_mapped( $meta_key, $feed ) {
		return rgars( $feed, "meta/$meta_key" ) == true;
	}

	public function add_validation_error( $field_id, $form, $message ) {

		foreach ( $form['fields'] as &$field ) {
			if ( $field->id == $field_id && ! $field->failed_validation ) {
				$field->failed_validation  = true;
				$field->validation_message = apply_filters( 'gform_user_registration_validation_message', $message, $form );
				break;
			}
		}

		return $form;
	}

	public function is_form_valid( $form ) {

		foreach ( $form['fields'] as $field ) {
			if ( $field->failed_validation ) {
				return false;
			}
		}

		return true;
	}

	public static function is_root_site() {
		global $current_blog, $current_site;

		return ! is_multisite() || $current_site->blog_id == $current_blog->blog_id;
	}

	public function is_update_feed( $feed ) {
		return rgars( $feed, 'meta/feedType' ) == 'update';
	}

	public function get_active_feed( $entry, $form = false ) {

		$submission_feed = false;

		if ( $entry['id'] ) {
			$feeds           = $this->get_feeds_by_entry( $entry['id'] );
			$submission_feed = empty( $feeds ) ? false : $this->get_feed( $feeds[0] );
		} else if ( $form ) {

			// getting all feeds
			$feeds = $this->get_feeds( $form['id'] );

			foreach ( $feeds as $feed ) {
				if ( $feed['is_active'] && $this->is_feed_condition_met( $feed, $form, $entry ) ) {
					$submission_feed = $feed;
					break;
				}
			}

		}

		return $submission_feed;
	}

	public function get_update_feed( $form_id ) {

		$feeds = $this->get_feeds( $form_id );

		foreach ( $feeds as $feed ) {
			if ( $feed['is_active'] && rgars( $feed, 'meta/feedType' ) == 'update' ) {
				return $feed;
			}
		}

		return false;
	}

	public function get_user_data( $entry, $form, $feed ) {

		$user_email = $this->get_meta_value( 'email', $feed, $form, $entry );

		if ( $this->is_update_feed( $feed ) ) {
			$user       = new WP_User( $entry['created_by'] );
			$user_login = $user->get( 'user_login' );
			$user_email = $user_email ? $user_email : $user->get( 'user_email' );
		} else {
			$user_login = $this->get_meta_value( 'username', $feed, $form, $entry );
			$user_login = gf_apply_filters( 'gform_username', $form['id'], $user_login, $feed, $form, $entry );
			/* @deprecated */
			$user_login = gf_apply_filters( 'gform_user_registration_username', $form['id'], $user_login, $feed, $form, $entry );
		}

		// password will be stored in entry meta for delayed feeds, check there first
		if ( $password = gform_get_meta( $entry['id'], 'userregistration_password' ) ) {
			$password = GFCommon::decrypt( $password );
		} else {
			$password = $this->get_meta_value( 'password', $feed, $form, $entry );
		}

		if ( empty( $user_login ) || empty( $user_email ) ) {
			return false;
		}

		return array(
			'user_login' => $user_login,
			'user_email' => $user_email,
			'password'   => $password
		);
	}

	public function get_site_data( $entry, $form, $feed ) {
		global $current_site;

		$blog_address = '';
		$user_data    = $this->get_user_data( $entry, $form, $feed );
		$address      = $this->get_meta_value( 'siteAddress', $feed, $form, $entry );

		if ( ! preg_match( '/(--)/', $address ) && preg_match( '|^([a-zA-Z0-9-])+$|', $address ) ) {
			$blog_address = strtolower( $address );
		}

		$blog_title = $this->get_meta_value( 'siteTitle', $feed, $form, $entry );

		if ( empty( $blog_address ) || empty( $user_data['user_email'] ) || ! is_email( $user_data['user_email'] ) ) {
			return array();
		}

		if ( is_subdomain_install() ) {
			$blog_domain = $blog_address . '.' . preg_replace( '|^www\.|', '', $current_site->domain );
			$path        = $current_site->path;
		} else {
			$blog_domain = $current_site->domain;
			$path        = trailingslashit( $current_site->path ) . $blog_address . '/';
		}

		return array(
			'domain' => $blog_domain,
			'path'   => $path,
			'title'  => $blog_title,
			'email'  => $user_data['user_email']
		);
	}

	/**
	 * Retrieves value from post to be populated as user meta.
	 *
	 * @param mixed $meta_key The meta key as specified in the $feed
	 * @param mixed $meta The array of meta mappings stored in the $feed
	 * @param mixed $form The current form object
	 * @param mixed $entry The current lead object
	 * @return mixed The value matching the meta mapping for the given meta key or if not found, an empty string
	 */
	public function get_meta_value( $meta_key, $meta, $form, $entry ) {
		return $this->get_prepared_value( $meta_key, $meta, $form, $entry );
	}

	public function get_prepared_value( $meta_key, $meta, $form, $entry, $is_username = null ) {

		// support legacy usage where feed was passed as $meta parameter
		$meta = isset( $meta['meta'] ) ? $meta['meta'] : $meta;

		if ( $is_username === null ) {
			$is_username = $meta_key == 'username';
		}

		$input_id = rgar( $meta, $meta_key );
		$field    = GFFormsModel::get_field( $form, $input_id );
		$value    = $this->get_mapped_field_value( $meta_key, $form, $entry, $meta );

		// Post Category fields come with Category Name and ID in the value (i.e. Austin:51); only return the name
		$value = $this->maybe_get_category_name( $field, $value );

		if ( $this->is_bp_active() && $is_username ) {
			$value = str_replace( ' ', '', $value );
		}

		$value = apply_filters( 'gform_user_registration_prepared_value', $value, $field, $input_id, $entry, $is_username );
		$value = apply_filters( 'gform_user_registration_meta_value', $value, $meta_key, $meta, $form, $entry, $is_username );

		return $value;
	}

	public function get_custom_meta( $feed ) {
		return $this->prepare_dynamic_meta( rgars( $feed, 'meta/userMeta' ) );
	}

	/**
	 * Takes array like:
	 *
	 *  array(
	 *      array(
	 *          'key'        => 'key1',
	 *          'value'      => 'value1',
	 *          'custom_key' => ''
	 *      ),
	 *      array(
	 *          'key'        => '',
	 *          'value'      => 'value2',
	 *          'custom_key' => 'my_custom_key'
	 *      )
	 *  )
	 *
	 * And converts it to:
	 *
	 * array(
	 *      'key1'          => 'value1',
	 *      'my_custom_key' => 'value2'
	 *  )
	 *
	 */
	public function prepare_dynamic_meta( $dyn_meta ) {

		$meta = array();

		if ( empty( $dyn_meta ) ) {
			return $meta;
		}

		foreach ( $dyn_meta as $meta_item ) {
			list( $meta_key, $meta_value, $custom_meta_key ) = array_pad( array_values( $meta_item ), 3, false );
			$meta_key          = $custom_meta_key ? $custom_meta_key : $meta_key;
			$meta[ $meta_key ] = $meta_value;
		}

		return $meta;
	}

	public function update_user_property( $user_id, $prop_key, $prop_value ) {

		if ( ! $user_id ) {
			return false;
		}

		$user = new WP_User( $user_id );

		$new_data            = new stdClass();
		$new_data->ID        = $user->ID;
		$new_data->$prop_key = $prop_value;

		$result = wp_update_user( get_object_vars( $new_data ) );

		return $result;
	}

	public function get_display_name( $user_id, $feed ) {

		$display_format = rgars( $feed, 'meta/displayname' );
		$user           = new WP_User( $user_id );

		switch( $display_format ) {
			case 'firstname':
				$display_name = $user->first_name;
				break;
			case 'lastname':
				$display_name = $user->last_name;
				break;
			case 'firstlast':
				$display_name = $user->first_name . ' ' . $user->last_name;
				break;
			case 'lastfirst':
				$display_name = $user->last_name . ' ' . $user->first_name;
				break;
			default:
				$display_name = $user->user_login;
				break;
		}

		return $display_name;
	}

	public function attribute_post_author( $user_id, $post_id ) {

		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return false;
		}

		$post->post_author = $user_id;
		$result            = wp_update_post( $post );

		return $result;
	}

	public static function get_user_id_by_meta( $key, $value ) {
		global $wpdb;

		$user = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s", $key, $value ) );

		return $user ? $user : false;
	}

	public static function is_pending_activation_enabled( $feed ) {
		return rgars( $feed, 'meta/userActivationEnable' ) == true;
	}

	public static function get_user_by_entry_id( $entry_id ) {
		global $wpdb;

		$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE ( meta_key = 'entry_id' OR meta_key = '_gform-entry-id' ) AND meta_value = %d LIMIT 1", $entry_id ) );

		if ( ! $user_id ) {
			return false;
		}

		$user = new WP_User( $user_id );

		return $user;
	}

	public static function get_site_by_entry_id( $entry_id ) {
		global $wpdb;

		$site_id = $wpdb->get_var( $wpdb->prepare( "SELECT site_id FROM $wpdb->sitemeta WHERE ( meta_key = 'entry_id' OR meta_key = '_gform-entry_id' ) AND meta_value = %d", $entry_id ) );

		return $site_id;
	}

	/**
	 * Used to check if a pending activation exists for the specified user_login or user_email.
	 *
	 * @param string $key user_login or user_email.
	 * @param string $value
	 *
	 * @return bool
	 */
	public function pending_activation_exists( $key, $value ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'signups';

		if ( $this->table_exists( $table_name ) && in_array( $key, array( 'user_login', 'user_email' ) ) ) {
			if ( $key == 'user_login' ) {
				$value = preg_replace( '/\s+/', '', sanitize_user( $value, true ) );
			}

			$signup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE active=0 AND {$key}=%s", $value ) );
			if ( $signup != null ) {
				$diff = current_time( 'timestamp', true ) - mysql2date( 'U', $signup->registered );
				// If registered more than two days ago, cancel registration and let this signup go through.
				if ( $diff > 2 * DAY_IN_SECONDS ) {
					$wpdb->delete( $table_name, array( $key => $value ) );
				} else {
					return true;
				}
			}
		}

		return false;
	}

	public function add_message_once( $message, $is_error = false ) {
		global $ur_messages;

		if( ! is_array( $ur_messages ) ) {
			$ur_messages = array();
		}

		if( in_array( $message, $ur_messages ) ) {
			return false;
		}

		if( $is_error ) {
			GFCommon::add_error( $message );
		} else {
			GFCommon::add_message( $message );
		}

		$ur_messages[] = $message;

	}

	public function add_error_message_once( $error ) {
		return $this->add_message_once( $error, true );
	}

	/**
	 * Prevent the multiselect field value being formatted by $field->get_value_export().
	 *
	 * @param array $entry The Entry currently being processed.
	 * @param string $field_id The ID of the Field currently being processed.
	 * @param GF_Field_MultiSelect $field The Field currently being processed.
	 *
	 * @return string
	 */
	public function get_multiselect_field_value( $entry, $field_id, $field ) {

		return rgar( $entry, $field_id );
	}

	public static function maybe_get_category_name( $field, $entry_value ) {

		if ( is_object( $field ) && $field->type == 'post_category' ) {
			if ( is_array( $entry_value ) ) {
				foreach ( $entry_value as &$value ) {
					list( $value, $cat_id ) = explode( ':', $value );
				}
			} else {
				list( $entry_value, $cat_id ) = explode( ':', $entry_value );
			}
		}

		return $entry_value;
	}


	// # UPGRADE PAYPAL FEEDS ------------------------------------------------------------------------------------------

	public function upgrade( $previous_version ) {

		if ( empty( $previous_version ) ) {
			$previous_version = get_option( 'gf_user_registration_version' );
		}

		$previous_is_pre_addon_framework = ! empty( $previous_version ) && version_compare( $previous_version, '3.0.dev1', '<' );
		if ( $previous_is_pre_addon_framework ) {
			$this->upgrade_from_pre_addon_framework();
		}

		// create signups table for non-multisite installs
		if( ! is_multisite() ) {
			require_once( $this->get_base_path() . '/includes/signups.php' );
			GFUserSignups::create_signups_table();
		}

	}

	public function upgrade_from_pre_addon_framework() {

		//get old feeds
		$old_feeds = $this->get_old_feeds();

		if ( $old_feeds ) {

			$counter = 1;
			foreach ( $old_feeds as $old_feed ) {

				$feed_name  = 'Feed ' . $counter;
				$form_id    = $old_feed['form_id'];
				$is_active  = rgar( $old_feed, 'is_active' ) ? '1' : '0';

				$new_meta = array(
					'feedName'             => $feed_name,
					'feedType'             => rgars( $old_feed, 'meta/feed_type' ),
					'username'             => rgars( $old_feed, 'meta/username' ),
					'first_name'           => rgars( $old_feed, 'meta/firstname' ),
					'last_name'            => rgars( $old_feed, 'meta/lastname' ),
					'email'                => rgars( $old_feed, 'meta/email' ),
					'displayname'          => rgars( $old_feed, 'meta/displayname' ),
					'password'             => rgars( $old_feed, 'meta/password' ),
					'role'                 => rgars( $old_feed, 'meta/role' ),
					'sendEmail'            => rgars( $old_feed, 'meta/notification' ),
					'setPostAuthor'        => rgars( $old_feed, 'meta/set_post_author' ),
					'userActivationEnable' => rgars( $old_feed, 'meta/user_activation' ),
					'userActivationValue'  => rgars( $old_feed, 'meta/user_activation_type' ),
					'createSite'           => rgars( $old_feed, 'meta/multisite_options/create_site' ),
					'siteAddress'          => rgars( $old_feed, 'meta/multisite_options/site_address' ),
					'siteTitle'            => rgars( $old_feed, 'meta/multisite_options/site_title' ),
					'siteRole'             => rgars( $old_feed, 'meta/multisite_options/site_role' ),
					'rootRole'             => rgars( $old_feed, 'meta/multisite_options/root_role' )
				);

				$user_meta     = array_filter( (array) rgars( $old_feed, 'meta/user_meta' ) );
				$new_user_meta = array();

				foreach ( $user_meta as $_meta ) {
					if ( $_meta['meta_name'] && $_meta['meta_value'] ) {
						$new_user_meta[] = array(
							'key'        => $_meta['meta_name'],
							'value'      => $_meta['meta_value'],
							'custom_key' => rgar( $_meta, 'custom' ) ? $_meta['meta_name'] : ''
						);
					}
				}

				if ( ! empty( $new_user_meta ) ) {
					$new_meta['userMeta'] = $new_user_meta;
				}

				$bp_meta     = array_filter( (array) rgars( $old_feed, 'meta/buddypress_meta' ) );
				$new_bp_meta = array();

				foreach ( $bp_meta as $_meta ) {
					if ( $_meta['meta_name'] && $_meta['meta_value'] ) {
						$new_bp_meta[] = array(
							'key'        => $_meta['meta_name'],
							'value'      => $_meta['meta_value'],
							'custom_key' => rgar( $_meta, 'custom' ) ? $_meta['meta_name'] : ''
						);
					}
				}

				if ( ! empty( $new_bp_meta ) ) {
					$new_meta['bpMeta'] = $new_bp_meta;
				}

				//add conditional logic, legacy only allowed one condition
				$conditional_enabled = rgars( $old_feed, 'meta/reg_condition_enabled' );
				if ( $conditional_enabled ) {
					$new_meta['feed_condition_conditional_logic']        = 1;
					$new_meta['feed_condition_conditional_logic_object'] = array(
						'conditionalLogic' =>
							array(
								'actionType' => 'show',
								'logicType'  => 'all',
								'rules'      => array(
									array(
										'fieldId'  => rgar( $old_feed['meta'], 'reg_condition_field_id' ),
										'operator' => rgar( $old_feed['meta'], 'reg_condition_operator' ),
										'value'    => rgar( $old_feed['meta'], 'reg_condition_value' )
									),
								)
							)
					);
				} else {
					$new_meta['feed_condition_conditional_logic'] = 0;
				}

				$this->insert_feed( $form_id, $is_active, $new_meta );
				$counter ++;

			}

			// set paypal delay setting
			$this->update_paypal_settings( array(
				"delay_{$this->_slug}"         => 'delay_registration',
				'cancellationActionUserEnable' => 'update_site_action',
				'cancellationActionUserValue'  => 'update_site_action',
				'cancellationActionSiteEnable' => 'update_user_action',
				'cancellationActionSiteValue'  => 'update_user_action'
			) );

		}

	}

	public function update_paypal_settings( $settings ) {
		global $wpdb;

		$this->log( 'Checking to see if there are any settings that need to be migrated for PayPal Standard.' );

		//get paypal feeds from new framework table
		$paypal_feeds = $this->get_feeds_by_slug( 'gravityformspaypal' );
		if ( empty( $paypal_feeds ) ) {
			return;
		}

		$this->log( 'New feeds found for ' . $this->_slug . ' - copying over delay settings.' );

		foreach ( $paypal_feeds as $feed ) {

			$meta            = $feed['meta'];
			$requires_update = false;

			foreach ( $settings as $new_key => $old_key ) {
				if ( isset( $meta[ $old_key ] ) ) {
					$meta[ $new_key ] = $meta[ $old_key ];
					$requires_update  = true;
				}
			}

			if ( $requires_update ) {
				$this->update_feed_meta( $feed['id'], $meta );
			}

		}

	}

	public function get_old_feeds() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rg_userregistration';
		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		$form_table_name = GFFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
							FROM $table_name s
							INNER JOIN $form_table_name f ON s.form_id = f.id";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$count = sizeof( $results );
		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;
	}




	// # DEPRECATED ----------------------------------------------------------------------------------------------------

	public static function get_active_config( $form, $entry = false ) {

		_deprecated_function( __FUNCTION__, '3.0', 'get_single_submission_feed' );

		return gf_user_registration()->get_single_submission_feed( $entry, $form );
	}

	public static function get_config( $form_id ) {

		_deprecated_function( __FUNCTION__, '3.0', 'get_feeds' );

		$feeds = gf_user_registration()->get_feeds( $form_id );
		$feed  = false;

		if ( is_array( $feeds ) ) {
			$feed = array_shift( $feeds );
		}

		return $feed;
	}

	public static function add_validation_failure( $field_id, $form, $message ) {

		_deprecated_function( __FUNCTION__, '3.0', 'add_validation_error' );

		return gf_user_registration()->add_validation_error( $field_id, $form, $message );
	}

	public static function create_new_multisite( $user_id, $feed, $entry, $password ) {

		_deprecated_function( __FUNCTION__, '3.0', 'create_site' );

		return gf_user_registration()->create_site( $user_id, $feed, $entry, $password );
	}

	public static function get_pending_activation_forms() {

		_deprecated_function( __FUNCTION__, '3.0' );

		$forms = GFFormsModel::get_forms( null, 'title' );
		$feeds = gf_user_registration()->get_feeds();

		$available_form_ids = array();
		foreach ( $feeds as $feed ) {
			if ( self::is_pending_activation_enabled( $feed ) ) {
				$available_form_ids[] = $feed['form_id'];
			}
		}

		$available_form_ids = array_unique( $available_form_ids );

		$available_forms = array();
		foreach ( $forms as $form ) {
			if ( in_array( $form->id, $available_form_ids ) ) {
				$available_forms[] = $form;
			}
		}

		return $available_forms;
	}

	public static function gf_create_user( $entry, $form ) {

		_deprecated_function( __FUNCTION__, '3.0', 'maybe_process_feed' );

		return gf_user_registration()->maybe_process_feed( $entry, $form );
	}

}

class GFUser extends GF_User_Registration {}
