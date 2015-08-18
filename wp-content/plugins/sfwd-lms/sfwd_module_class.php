<?php
/**
 * @package Semper-Fi 
 */

if(!function_exists('str_getcsv')) {
    function str_getcsv($input, $delimiter = ",", $enclosure = '"', $escape = "\\") {
        $fp = fopen("php://memory", 'r+');
        fputs($fp, $input);
        rewind($fp);
        $data = fgetcsv($fp, null, $delimiter, $enclosure); // $escape only got added in 5.3.0
        fclose($fp);
        return $data;
    }
}

/**
 * The module base class; handles settings, options, menus, metaboxes, etc.
 */
if ( !class_exists( 'Semper_Fi_Module' ) ) {
	abstract class Semper_Fi_Module {
		public static $instance = null;
		protected $plugin_name;
		protected $name;
		protected $menu_name;
		protected $prefix;
		protected $file;
		protected $options;
		protected $option_name;
		protected $network_options = false;
		protected $default_options;
		protected $locations = null;	// organize settings into settings pages with a menu items and/or metaboxes on post types edit screen; optional
		protected $layout = null;		// organize settings on a settings page into multiple, separate metaboxes; optional
		protected $tabs = null;			// organize layouts on a settings page into multiple, separate tabs; optional
		protected $current_tab = null;	// the current tab
		protected $pagehook = null;		// the current page hook
		protected $store_option = false;
		protected $parent_option = 'sfwd_cpt_options';
		protected $post_metaboxes = Array();
		protected $tabbed_metaboxes = false;
		protected $credentials = false; // used for WP Filesystem
		protected $script_data = null;	// used for passing data to JavaScript
		protected $plugin_path = null;
		protected $pointers = Array();
		
		/**
		 * Handles calls to display_settings_page_{$location}, does error checking.
		 */
		function __call( $name, $arguments ) {
			if ( strpos( $name, "display_settings_page_" ) === 0 ) {
				$location = substr( $name, 22 );
				return $this->display_settings_page( $location );
			}
			throw new InvalidArgumentException( __( sprintf( "Method %s doesn't exist", $name ), 'learndash' ) );
		}
		
		function __construct() {
			if ( empty( $this->file ) ) $this->file = __FILE__;
			$this->plugin_path = Array();
			$this->plugin_path['dir'] = plugin_dir_path( $this->file );
			$this->plugin_path['basename'] = plugin_basename( $this->file );
			$this->plugin_path['dirname'] = dirname( $this->plugin_path['basename'] );
			$this->plugin_path['url'] = plugin_dir_url( $this->file );
			$this->plugin_path['images_url'] = $this->plugin_path['url'] . 'images';
		}
		
		/**
		 * Adds support for getting network options.
		 */
		function get_option( $name, $default = false, $use_cache = true ) {
			if ( $this->network_options )
				return get_site_option( $name, $default, $use_cache );
			else
				return get_option( $name, $default );
		}
		
		/**
		 * Adds support for updating network options.
		 */
		function update_option( $option, $newvalue ) {
			if ( $this->network_options )
				return update_site_option( $option, $newvalue );
			else
				return update_option( $option, $newvalue );
		}
		
		/**
		 * Adds support for deleting network options.
		 */
		function delete_option( $option ) {
			if ( $this->network_options )
				return delete_site_option( $option );
			else
				return delete_option( $option );
		}

		/**
		 * Get options for module, stored individually or together.
		 */
		function get_class_option( ) {
			$option_name = $this->get_option_name();
			if ( $this->store_option ) {
				return $this->get_option( $option_name );
			} else {
				$option = $this->get_option( $this->parent_option );
				if ( isset( $option['modules'] ) && isset( $option['modules'][$option_name] ) )
					return $option['modules'][$option_name];
			}
			return false;
		}

		/**
		 * Update options for module, stored individually or together.
		 */
		function update_class_option( $option_data, $option_name = false ) {
			if ( $option_name == false )
				$option_name = $this->get_option_name();
			if ( $this->store_option ) {
				return $this->update_option( $option_name, $option_data );
			} else {
				$option = $this->get_option( $this->parent_option );
				if ( !isset( $option['modules'] ) ) $option['modules'] = Array();
				$option['modules'][$option_name] = $option_data;
				return $this->update_option( $this->parent_option, $option );
			}
		}
		
		/**
		 * Delete options for module, stored individually or together.
		 */
		function delete_class_option( $delete = false ) {
			$option_name = $this->get_option_name();
			if ( $this->store_option || $delete ) {
				$this->delete_option( $option_name );
			} else {
				$option = $this->get_option( $this->parent_option );
				if ( isset( $option['modules'] ) && isset( $option['modules'][$option_name] ) ) {
					unset( $option['modules'][$option_name] );
					return $this->update_option( $this->parent_option, $option );
				}
			}
			return false;
		}

		/**
		 * Get the option name with prefix.
		 */
		function get_option_name() {
			if ( !isset( $this->option_name ) || empty($this->option_name ) )
				$this->option_name = $this->prefix . 'options';
			return $this->option_name;
		}

		/**
		 * Convenience function to see if an option is set.
		 */
		function option_isset( $option, $location = null ) {
			$prefix = $this->get_prefix( $location );
			$opt = $prefix . $option;
			return ( ( isset( $this->options[$opt] ) ) && $this->options[$opt] );
		}
		
		/**
		 * Displays tabs for tabbed locations on a settings page.
		 */
		function display_tabs( $location ) {
			if ( ( $location != null ) && isset( $locations[$location]['tabs'] ) )
				$tabs = $locations['location']['tabs'];
			else
				$tabs = $this->tabs;
			if ( !empty( $tabs ) ) {
					?><div class="sfwd_tabs_div"><label class="sfwd_head_nav">
					<?php
					foreach ( $tabs as $k => $v ) {
					?>
						<a class="sfwd_head_nav_tab sfwd_head_nav_<?php if ( $this->current_tab != $k ) echo "in"; ?>active" href="<?php echo add_query_arg( 'tab', $k ); ?>"><?php echo $v['name']; ?></a>
					<?php
					}
					?>
					</label></div>
				<?php
			}
		}
		
		/**
		 * Handles exporting settings data for a module.
		 */		
		function settings_export( $buf ){
			global $sfwd_options, $sfp; 
			$post_types = null;
			$has_data = null;
			$general_settings = null;
			$exporter_choices = $_REQUEST[ 'sfp_importer_exporter_export_choices' ];
			
			if ( !empty( $exporter_choices ) && is_array( $exporter_choices ) ) {
				foreach( $exporter_choices as $ex ) {
					if ( $ex == 1 ) $general_settings = true;
					if ( $ex == 2 ) {
						if ( isset( $_REQUEST[ 'sfp_importer_exporter_export_post_types' ] ) ) 
							$post_types = $_REQUEST[ 'sfp_importer_exporter_export_post_types' ];
					}
				}
			}
			
			if( $post_types != null ) {
				$posts_query = new WP_Query( Array( 'post_type' => $post_types ) );
				$export_data = Array( );
				if ( ( $this === $sfp ) || ( $this->locations !== null ) ) {
					while ($posts_query->have_posts() ) : $posts_query->the_post();
					
						global $post;
						$guid = $post->guid; $type = $post->post_type; $title = $post->post_title; $date = $post->post_date;
						$data = '';
						/* Add Module Meta Data */
						if ( $this->locations !== null ) {
							foreach( $this->locations as $k => $v ) {
								if ( isset($v['type'] ) && isset($v['options'] ) && ( $v['type'] === 'metabox' ) ) {
									$value = $this->get_prefix($k) . $k;
									$post_meta = get_post_meta( $post->ID, '_' . $value, true );
									if ( $post_meta ) $data .= "$value = '" . str_replace( Array( "'", "\n", "\r" ), Array( "\'", '\n', '\r' ), trim( serialize( $post_meta ) ) ) . "'";
								}
							}
						} else {
							
							/* Add Post Field Data */
							$post_custom_fields = get_post_custom( $post->ID );
							$has_data = null;
							if( is_array( $post_custom_fields ) ){ 
								foreach( $post_custom_fields as $field_name => $field ){
									if( ( substr( $field_name, 1, 7) == 'sfwd' ) && ( $field[0] ) ){ 
										$has_data = true;
										$data .= $field_name . " = '" . $field[0] . "'\n";
									} 
								}
							}
						}
						
						/* Print post data to file */
						if( $has_data != null ){
							$post_info = "\n[post_data]\n\n";
							$post_info .= "post_title = '" . $title . "'\n";
							$post_info .= "post_guid = '" . $guid . "'\n";
							$post_info .= "post_date = '" . $date . "'\n";
							$post_info .= "post_type = '" . $type . "'\n";
							if ( $data ) $buf .= $post_info . $data . "\n";
						}
					endwhile;
					wp_reset_postdata();
				}
			} 
			
			/* Add all active settings to settings file */
			$name = $this->get_option_name();
			$options = $this->get_class_option();
			if( !empty( $options ) && $general_settings != null ) {
				$buf .= "\n[$name]\n\n";
				foreach ( $options as $key => $value ) {
					if ( ( $name == $this->parent_option ) && ( $key == 'modules' ) ) continue; // don't re-export all module settings -- pdb
					if ( is_array($value) )
						$value = "'" . str_replace( Array( "'", "\n", "\r" ), Array( "\'", '\n', '\r' ), trim( serialize( $value ) ) ) . "'";
					else
						$value = str_replace( Array( "\n", "\r" ), Array( '\n', '\r' ), trim( var_export($value, true) ) );
					$buf .= "$key = $value\n";
				}
			}
			return $buf;
		}
		
		/**
		 * Print a basic error message.
		 */
		function output_error( $error ) {
			echo "<div class='sfwd_module error' style='text-align:center;'>$error</div>";
			return FALSE;
		}
		
		/***
		 * Helper function to convert csv in key/value pair format to an associative array.
		 */
		function csv_to_array( $csv ) {
			$args = Array();
			$v = str_getcsv( $csv );
			$size = count( $v );
			if ( is_array( $v ) && isset( $v[0] ) && $size >= 2 )
				for( $i = 0; $i < $size; $i += 2 )
					$args[ $v[ $i ] ] = $v[ $i + 1 ];
			return $args;
		}
		
		/** crude approximization of whether current user is an admin */
		function is_admin() {
			return current_user_can('manage_options');
		}
		
		/**
		 * Load styles for module.
		 */
		function enqueue_styles( ) {
			wp_enqueue_style( 'thickbox' );
			if ( !empty( $this->pointers ) ) wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_style(  'sfwd-module-style',  $this->plugin_path['url'] . 'assets/sfwd_module.css' );
		}
		
		/**
		 * Load scripts for module, can pass data to module script.
		 */
		function enqueue_scripts( ) {
			wp_enqueue_script('jquery');
			if ( is_admin() ) {
				wp_enqueue_script('jquery-ui-core');
				wp_enqueue_script('jquery-ui-datepicker');
				wp_enqueue_script('media-upload');
				wp_enqueue_script('thickbox');
				if ( !empty( $this->pointers ) ) {
					wp_enqueue_script( 'wp-pointer', false, array('jquery') );
					$this->script_data['pointers'] = $this->pointers;
				}
			}
			$this->script_data['learndash_categories_lang'] = __("LearnDash Categories", "learndash");
			$this->script_data['loading_lang'] = __("Loading...", "learndash");
			$this->script_data['select_a_lesson_lang'] = __('-- Select a Lesson --', 'learndash');
			$this->script_data['select_a_lesson_or_topic_lang'] = __('-- Select a Lesson or Topic --', 'learndash');
			$this->script_data['advanced_quiz_preview_link'] = admin_url("admin.php?page=ldAdvQuiz&module=preview&id=");
			global $post;
			if($post->post_type == "sfwd-quiz")
			$this->script_data['quiz_pro'] = intval(learndash_get_setting($post->ID, "quiz_pro"));
			

			wp_enqueue_script( 'sfwd-module-script', $this->plugin_path['url'] . 'assets/sfwd_module.js' );
			$data = Array();
			if ( !empty( $this->script_data ) ) $data = $this->script_data;
			$data = Array( 'json' => json_encode( $data ) );
			wp_localize_script( 'sfwd-module-script', 'sfwd_data', $data );


			$filepath = locate_template(array("learndash/learndash_template_script.js"));
			if($filepath && file_exists($filepath)) {
				wp_enqueue_script( 'sfwd_template_js', get_stylesheet_directory_uri()."/learndash/learndash_template_script.js");
			}
			else
			{
				$filepath = locate_template("learndash_template_script.js");
				if($filepath &&  file_exists($filepath)) {
					wp_enqueue_script( 'sfwd_template_js', get_stylesheet_directory_uri()."/learndash_template_script.js");
				}
				else if(file_exists(dirname(__FILE__) .'/templates/learndash_template_script.js'))
				wp_enqueue_script( 'sfwd_template_js', plugins_url( 'templates/learndash_template_script.js', __FILE__ ) );
			}
		}
		
		/**
		 * Override this to run code at the beginning of the settings page.
		 */
		function settings_page_init() {
			
		}
		
		/**
		 * Filter out admin pointers that have already been clicked.
		 */
		function filter_pointers() {
			if ( !empty( $this->pointers ) ) {
				$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
				foreach( $dismissed as $d )
					if ( isset( $this->pointers[$d] ) )
						unset( $this->pointers[$d] );
			}
		}
		
		/**
		 * Add basic hooks when on the module's page.
		 */
		function add_page_hooks() {
			$hookname = current_filter();
			if ( strpos( $hookname, 'load-' ) === 0 )
				$this->pagehook = substr( $hookname, 5 );
			$this->filter_pointers();
			add_action( "admin_print_scripts", Array( $this, 'enqueue_scripts' ) );
			add_action( "admin_print_styles", Array( $this, 'enqueue_styles' ) );
			add_action( $this->prefix . 'settings_header', Array( $this, 'display_tabs' ) );
		}
		
		/**
		 * Collect metabox data together for tabbed metaboxes.
		 */
		function filter_return_metaboxes( $args ) {
			return array_merge( $args, $this->post_metaboxes );
		}
		
		/** Add submenu for module, call page hooks, set up metaboxes. */
		function add_menu( $parent_slug ) {
			if ( empty( $parent_slug ) ) $parent_slug = 'options-general.php';
			if ( !empty( $this->menu_name ) )
				$name = $this->menu_name;
			else
				$name = $this->name;

			$default_options_page = "sfwd-lms_sfwd_lms.php_post_type_".$this->post_type;

			if ( $this->locations === null ) {
				$hookname = add_submenu_page( $parent_slug, $name, $name, 'manage_options', $default_options_page, Array( $this, 'display_settings_page' ) );
				add_action( "load-{$hookname}", Array( $this, 'add_page_hooks' ) );
				return true;
			}
			foreach( $this->locations as $k => $v ) {
				if ( $v['type'] === 'settings' ) {
					if ( $k === 'default' ) {
						if ( !empty( $this->menu_name ) )
							$name = $this->menu_name;
						else
							$name = $this->name;

						$hookname = add_submenu_page( $parent_slug, $name, $name, 'manage_options', $default_options_page, Array( $this, 'display_settings_page' ) );
					} else {
						if ( !empty( $v['menu_name'] ) )
							$name = $v['menu_name'];
						else
							$name = $v['name'];
						$hookname = add_submenu_page( $parent_slug, $name, $name, 'manage_options', $this->get_prefix( $k ) . $k, Array( $this, "display_settings_page_$k" ) );
					}
					add_action( "load-{$hookname}", Array( $this, 'add_page_hooks' ) );
				} elseif ( $v['type'] === 'metabox' ) {
					add_action( 'save_post', Array( $this, 'save_post_data' ) );
					if ( isset( $v['display'] ) && !empty( $v['display'] ) ) {
						foreach ( $v['display'] as $posttype ) {
							$v['location'] = $k;
							$v['posttype'] = $posttype;
							if ( !isset($v['context']  ) ) $v['context']  = 'advanced';
							if ( !isset($v['priority'] ) ) $v['priority'] = 'default';
							
							if ( $this->tabbed_metaboxes ) {
								$this->post_metaboxes[] = Array( 'id' => $v['prefix'] . $k, 'title' => $v['name'], 'callback' => Array($this, 'display_metabox'),
																 'post_type' => $posttype, 'context' => $v['context'], 'priority' => $v['priority'], 'callback_args' => $v );
								add_filter( 'sfwd_add_post_metabox', Array( $this, 'filter_return_metaboxes' ) );
							} else {
								$title = $v['name'];
								if ( $title != $this->plugin_name ) $title = $this->plugin_name . ' - ' . $title;
								$title = apply_filters( "semperfi_metabox_title", $title, $v['prefix'] . $k );
								add_meta_box( $v['prefix'] . $k, $title, Array($this, 'display_metabox'), $posttype, $v['context'], $v['priority'], $v );
							}
							
							add_action( 'admin_print_scripts-post.php', Array( $this, 'enqueue_scripts' ) );
							add_action( 'admin_print_scripts-post-new.php', Array( $this, 'enqueue_scripts' ) );
							add_action( 'admin_print_styles-post.php', Array( $this, 'enqueue_styles' ) );
							add_action( 'admin_print_styles-post-new.php', Array( $this, 'enqueue_styles' ) );
						}
					}
				}
			}	
		}
		
		/**
		 * Update postmeta for metabox.
		 */		
		function save_post_data( $post_id ) {
			if ( $this->locations !== null ) {
				foreach( $this->locations as $k => $v ) {
					if ( isset($v['type']) && ( $v['type'] === 'metabox' ) ) {
						$opts = $this->default_options( $k );
						$options = Array();
						$update = false;
						foreach ( $opts as $l => $o ) {
							if ( isset($_POST[$l] ) ) {
								if( get_magic_quotes_gpc() )
									$options[$l] = stripslashes_deep( $_POST[$l] );
								else
									$options[$l] = $_POST[$l];
								$options[$l] = esc_attr( $options[$l] );
								$update = true;
							}
						}
						if ( $update ) update_post_meta( $post_id, '_' . $this->get_prefix($k) . $k, $options );
					}
				}
			}
		}
		
		/**
		 * Outputs radio buttons, checkboxes, selects, multiselects, handles groups.
		 */		
		function do_multi_input( $args ) {
			extract( $args );
			$buf1 = '';
			$type = $options['type'];
			if ( ( $type == 'radio' ) || ( $type == 'checkbox' ) ) {
				$strings = Array(
					'block'		=> "%s\n",
					'group'		=> "\t<b>%s</b><br>\n%s\n",
					'item'		=> "\t<label class='sfwd_option_setting_label'><input type='$type' %s name='%s' value='%s' %s> %s</label>\n",
					'item_args' => Array( 'sel', 'name', 'v', 'attr', 'subopt' ),
					'selected'	=> 'checked '
					);
			} else {
				$strings = Array(
						'block'		=> "<select name='$name' $attr>%s\n</select>\n",
						'group'		=> "\t<optgroup label='%s'>\n%s\t</optgroup>\n",
						'item'		=> "\t<option %s value='%s'>%s</option>\n",
						'item_args' => Array( 'sel', 'v', 'subopt' ),
						'selected'	=> 'selected '
					);
			}
			$setsel = $strings['selected'];
			if ( isset($options['initial_options'] ) && is_array($options['initial_options']) ) {
				foreach ( $options['initial_options'] as $l => $option ) {
					$is_group = is_array( $option );
					if ( !$is_group ) $option = Array( $l => $option );
					$buf2 = '';
					foreach ( $option as $v => $subopt ) {
						$sel = '';
						$is_arr = is_array( $value );
						if ( is_string( $v ) || is_string( $value ) )
							$cmp = !strcmp( (string)$v, (string)$value );
						else
							$cmp = ( $value == $v );
						if ( ( !$is_arr && $cmp ) || ( $is_arr && in_array( $v, $value ) ) )
							$sel = $setsel;
						$item_arr = Array();
						foreach( $strings['item_args'] as $arg ) $item_arr[] = $$arg;
						$buf2 .= vsprintf( $strings['item'], $item_arr );
					}
					if ( $is_group )
						$buf1 .= sprintf( $strings['group'], $l, $buf2);
					else
						$buf1 .= $buf2;
				}				
				$buf1 = sprintf( $strings['block'], $buf1 );
			}
			return $buf1;
		}
		
		/**
		 * Outputs a setting item for settings pages and metaboxes.
		 */
		function get_option_html( $args ) {
			static $n = 0;
			extract( $args );
			if ( $options['type'] == 'custom' )
				return apply_filters( "{$this->prefix}output_option", '', $args );
			if ( in_array( $options['type'], Array( 'multiselect', 'select', 'multicheckbox', 'radio', 'checkbox', 'textarea', 'text', 'submit', 'hidden' ) ) )
				$value = esc_attr( $value );
			$buf = '';
			if ( !empty( $options['count'] ) ) {
				$n++;
				$attr .= " onKeyDown='countChars(document.post.$name,document.post.length$n)' onKeyUp='countChars(document.post.$name,document.post.length$n)'";
			}
			switch ( $options['type'] ) {
				case 'multiselect':   $attr .= ' MULTIPLE';
									  $args['attr'] = $attr;
									  $args['name'] = $name = "{$name}[]";
				case 'select':		  $buf .= $this->do_multi_input( $args ); break;
				case 'multicheckbox': $args['name'] = $name = "{$name}[]";
									  $args['options']['type'] = $options['type'] = 'checkbox';
				case 'radio':		  $buf .= $this->do_multi_input( $args ); break;
				case 'checkbox':	  if ( $value ) $attr .= ' CHECKED';
									  $buf .= "<input name='$name' type='{$options['type']}' $attr>\n"; break;
				case 'textarea':	  $buf .= "<textarea name='$name' $attr>$value</textarea>"; break;
				case 'image':		  $buf .= "<input class='sfwd_upload_image_button' type='button' value='" . __('Upload Image', 'learndash') . "' style='float:left;' />" .
											  "<input class='sfwd_upload_image_label' name='$name' type='text' readonly $attr value='$value' size=57 style='float:left;clear:left;'>\n";
									  break;
				case 'html':		  $buf .= $value; break;
				default:			  $buf .= "<input name='$name' type='{$options['type']}' $attr value='$value'>\n";
			}
			if ( !empty( $options['count'] ) ) {
				$size = 60;
				if ( isset( $options['size'] ) ) $size = $options['size'];
				elseif ( isset( $options['rows'] ) && isset( $options['cols'] ) ) $size = $options['rows'] * $options['cols'];				
				$buf .= "<input readonly type='text' name='length$n' size='3' maxlength='3' style='width:53px;height:23px;margin:0px;padding:0px;' value='" . strlen($value) . "' />"
					 . sprintf( __(' characters. Most search engines use a maximum of %s chars for the %s.', 'learndash'), $size, strtolower( $options['name'] ) );
			}
			return $buf;
		}
		
		const DISPLAY_HELP_START	= '<a class="sfwd_help_text_link" style="cursor:pointer;" title="%s" onclick="toggleVisibility(\'%s_tip\');"><img src="%s/question.png" /><label class="sfwd_label textinput">%s</label></a>';
		const DISPLAY_HELP_END		= '<div class="sfwd_help_text_div" style="display:none" id="%s_tip"><label class="sfwd_help_text">%s</label></div>';
		const DISPLAY_LABEL_FORMAT  = '<span class="sfwd_option_label" style="text-align:%s;vertical-align:top;">%s</span>';
		const DISPLAY_TOP_LABEL		= "</div>\n<div class='sfwd_input sfwd_top_label'>\n";
		const DISPLAY_ROW_TEMPLATE	= '<div class="sfwd_input %s" id="%s">%s<span class="sfwd_option_input"><div class="sfwd_option_div" %s>%s</div>%s</span><p style="clear:left"></p></div>';
		
		/**
		 * Format a row for an option on a settings page.
		 */
		function get_option_row( $name, $opts, $args ) {
			$label_text = $input_attr = $help_text_2 = $id_attr = '';
			if ( $opts['label'] == 'top' )
				$align	= 'left';
			else
				$align = 'right';
			if ( isset( $opts['id'] ) ) $id_attr .= " id=\"{$opts['id']}\" ";
			if ( $opts['label'] != 'none' ) { 
				if ( isset( $opts['help_text'] ) ) {
					$help_text = sprintf(	Semper_Fi_Module::DISPLAY_HELP_START, __( 'Click for Help!', 'learndash' ), $name, $this->plugin_path['images_url'], $opts['name'] );
					$help_text_2 = sprintf(	Semper_Fi_Module::DISPLAY_HELP_END, $name, $opts['help_text'] );
				} else $help_text = $opts['name'];
				$label_text = sprintf( Semper_Fi_Module::DISPLAY_LABEL_FORMAT, $align, $help_text );
			} else $input_attr .= 'sfwd_no_label ';
			if ( $opts['label'] == 'top' ) $label_text .= Semper_Fi_Module::DISPLAY_TOP_LABEL;
			if ( $opts['type'] == 'hidden' ) $input_attr .= 'sfwd_hidden_type';
			return sprintf( Semper_Fi_Module::DISPLAY_ROW_TEMPLATE, $input_attr, $name, $label_text, $id_attr, $this->get_option_html( $args ), $help_text_2 );
		}
		
		/**
		 * Display options for settings pages and metaboxes, allows for filtering settings, custom display options.
		 */
		function display_options( $location = null, $meta_args = null ) {
				static $location_settings = Array();
				$defaults = null;
				$prefix = $this->get_prefix( $location );
				if ( is_array( $meta_args['args'] ) && !empty( $meta_args['args']['default_options'] ) )
					$defaults = $meta_args['args']['default_options'];
				if ( !isset( $location_settings[$prefix] ) ) {
					$current_options = apply_filters( "{$this->prefix}display_options",  $this->get_current_options( Array(), $location, $defaults ), $location );
					$settings		 = apply_filters( "{$this->prefix}display_settings", $this->setting_options( $location, $defaults ), $location, $current_options );
					$location_settings[$prefix]['current_options'] = $current_options;
					$location_settings[$prefix]['settings']		   = $settings;
				} else {
					$current_options = $location_settings[$prefix]['current_options'];
					$settings		 = $location_settings[$prefix]['settings'];
				}
				$container = "<div class='sfwd sfwd_options {$this->prefix}settings'>";
				if ( is_array( $meta_args['args'] ) && !empty( $meta_args['args']['options'] ) ) {
					$args = Array();
					$arg_keys = Array();
					foreach ( $meta_args['args']['options'] as $a ) {
						if ( !empty($location) ) {
							$key = $prefix . $location . '_' . $a;
							if ( !isset( $settings[$key] ) ) $key = $a;
						} else $key = $prefix . $a;
						if ( isset( $settings[$key] ) ) $arg_keys[$key] = 1;
					}
					$setting_keys = array_keys( $settings );
					foreach ( $setting_keys as $s )
						if ( !empty( $arg_keys[$s] ) ) $args[$s] = $settings[$s];
				} else $args = $settings;
				foreach ( $args as $name => $opts ) {
					$attr_list = Array( 'class', 'style', 'readonly', 'disabled', 'size', 'placeholder' );
					if ( $opts['type'] == 'textarea' ) $attr_list = array_merge( $attr_list, Array('rows', 'cols') );
					$attr = '';
					foreach ( $attr_list as $a )
						if ( isset( $opts[$a] ) ) $attr .= " $a=\"{$opts[$a]}\" ";
					$opt = '';
					if ( isset( $current_options[$name] ) ) $opt = $current_options[$name];
 					if ( $opts['label'] == 'none' && $opts['type'] == 'submit' && $opts['save'] == false ) $opt = $opts['name'];
					if ( $opts['type'] == 'html' && empty( $opt ) && $opts['save'] == false ) $opt = $opts['default'];

					$args = Array( 'name' => $name, 'options' => $opts, 'attr' => $attr, 'value' => $opt, 'prefix' => $prefix );
					
					if ( !empty( $opts['nowrap'] ) )
						echo $this->get_option_html( $args );
					else {
						if ( $container ) {
							echo $container;
							$container = '';
						}
						echo $this->get_option_row( $name, $opts, $args );
					}
				}
			if ( !$container ) echo "</div>";
		}

		/** Sanitize options */
		function sanitize_options( $location = null ) {
			foreach ( $this->setting_options( $location ) as $k => $v ) {
				if ( isset( $this->options[$k] ) ) {
					if ( !empty( $v['sanitize'] ) )
						$type = $v['sanitize'];
					else
						$type = $v['type'];
					switch ( $type ) {
						case 'multiselect':
						case 'multicheckbox': $this->options[$k] = urlencode_deep( $this->options[$k] );
											  break;
						case 'textarea':	  $this->options[$k] = wp_kses_post( $this->options[$k] );
											  $this->options[$k] = esc_textarea( $this->options[$k] );
											  break;
						case 'filename':	  $this->options[$k] = sanitize_file_name( $this->options[$k] );
											  break;
						case 'text':		  $this->options[$k] = wp_kses_post( $this->options[$k] );
						case 'checkbox':
						case 'radio':
						case 'select':
						default:			  $this->options[$k] = esc_attr( $this->options[$k] );
					}
					
				}
			}
		}

		/**
		 * Display metaboxes with display_options()
		 */
		function display_metabox( $post, $metabox ) {
			$this->display_options( $metabox['args']['location'], $metabox );
		}

		/**
		 * Handle resetting options to defaults.
		 */
		function reset_options( $location = null, $delete = false ) {
			if ( $delete === true ) {
				$this->delete_class_option( $delete );
				$this->options = Array();
			}
			$default_options = $this->default_options( $location );
			foreach ( $default_options as $k => $v ) {
				$this->options[$k] = $v;
			}
			$this->update_class_option( $this->options );
		}
		
		/** handle option resetting and updating */
		function handle_settings_updates( $location = null ) {
			if ( (isset($_POST['action']) && $_POST['action'] == 'sfp_update_module' &&
			     ( isset( $_POST['Submit_Default'] ) || isset( $_POST['Submit_All_Default'] ) || !empty( $_POST['Submit'] ) ) ) ) {
				$nonce = $_POST['nonce-sfwd'];
				if (!wp_verify_nonce($nonce, 'sfwd-nonce')) die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				if ( isset( $_POST['Submit_Default'] ) || isset( $_POST['Submit_All_Default'] ) ) {
					$message = __( "Options Reset.", 'learndash' );
					if ( isset($_POST['Submit_All_Default']) ) {
						$this->reset_options( $location, true );
						do_action( 'sfwd_options_reset' );
					} else {
						$this->reset_options( $location );
					}
				}
				if ( !empty( $_POST['Submit'] ) ) {
					$message = __("Options Updated.", 'learndash');
					$default_options = $this->default_options( $location );
					foreach($default_options as $k => $v) {
						if ( isset( $_POST[$k] ) ) {
							if( get_magic_quotes_gpc() )
								$this->options[$k] = stripslashes_deep( $_POST[$k] );
							else
								$this->options[$k] = $_POST[$k];
						}
						else
							$this->options[$k] = '';
					}
					$this->sanitize_options( $location );
					$this->options = apply_filters( $this->prefix . 'update_options', $this->options, $location );
					$this->update_class_option( $this->options );
					wp_cache_flush();
				}
				do_action( $this->prefix . 'settings_update', $this->options, $location );
			}
		}

		/** Update / reset settings, printing options, sanitizing, posting back */
		function display_settings_page( $location = null ) {
				if ( $location != null ) $location_info = $this->locations[$location];
				$name = null;
				if ( ( $location ) && ( isset( $location_info['name'] ) ) ) $name = $location_info['name'];
				if ( !$name ) $name = $this->name;
				$message = $this->handle_settings_updates( $location );
				$this->settings_page_init();
				if ( !empty( $message ) ) echo "<div id=\"message\" class=\"updated fade\"><p>$message</p></div>";
	?>			<div id="dropmessage" class="updated" style="display:none;"></div>
				<div class="wrap">
					<h2><?php echo $name; ?></h2>
<?php				do_action( 'sfwd_global_settings_header', $location );
					do_action( $this->prefix . 'settings_header', $location );	?>
	<form id="sfp_settings_form" name="dofollow" enctype="multipart/form-data" action="" method="post">
					<div class="sfwd_options_wrapper sfwd_settings_left">
					<?php $opts = $this->get_class_option();
						  if ($opts !== FALSE) $this->options = $opts;						
						if ( is_array( $this->layout ) ) {
							foreach( $this->layout as $l => $lopts ) {
								if ( !isset( $lopts['tab'] ) || ( $this->current_tab == $lopts['tab'] ) )
									add_meta_box( $this->get_prefix( $location ) . $l . "_metabox", $lopts['name'], array($this, 'display_options' ),
												  "{$this->prefix}settings", 'advanced', 'default', $lopts );
							}
						} else add_meta_box( $this->get_prefix( $location ) . "metabox", $name, array($this, 'display_options'), "{$this->prefix}settings", 'advanced');
						do_meta_boxes( "{$this->prefix}settings", 'advanced', $location );
?>					<p class="submit" style="clear:both;"><?php
					$submit_options = Array('action'		=> Array( 'type' => 'hidden', 'value' => 'sfp_update_module' ),
											'nonce-sfwd'	=> Array( 'type' => 'hidden', 'value' => wp_create_nonce('sfwd-nonce') ),
											'page_options'	=> Array( 'type' => 'hidden', 'value' => 'sfp_home_description' ),
											'Submit'		=> Array( 'type' => 'submit', 'class' => 'button-primary', 'value' => __('Update Options', 'learndash') . ' &raquo;' ),
											'Submit_Default'=> Array( 'type' => 'submit', 'class' => 'button-primary', 'value' =>  sprintf( __('Reset %s Settings to Defaults', 'learndash'), $name ) . ' &raquo;' )
										   );
					$submit_options = apply_filters( "{$this->prefix}submit_options", $submit_options, $location );
					foreach ( $submit_options as $k => $s ) {
						$class = '';
						if ( isset( $s['class'] ) ) $class = " class='{$s['class']}' ";
						echo $this->get_option_html( Array( 'name' => $k, 'options' => $s, 'attr' => $class, 'value' => $s['value'] ) );
					}	?>
					</p>
					</div>
					</form>
					<?php 	do_action( $this->prefix . 'settings_footer', $location ); 
							do_action( 'sfwd_global_settings_footer', $location ); ?>
				</div>
<?php	}
		
		/**
		 * Get the prefix used for a given location.
		 */
		function get_prefix( $location = null ) {
			if ( ($location != null ) && isset($this->locations[$location]['prefix'] ) )
				return $this->locations[$location]['prefix'];
			return $this->prefix;
		}

		/** Sets up initial settings */
		function setting_options( $location = null, $defaults = null ) {
			if ( $defaults === null )
				$defaults = $this->default_options;
			$prefix = $this->get_prefix( $location );
			$opts = Array();
			if ( $location == null || $this->locations[$location]['options'] === null )
				$options = $defaults;
			else {
				$options = Array();
				$prefix = "{$prefix}{$location}_";
				if ( !empty( $this->locations[$location]['default_options'] ) )
					$options = $this->locations[$location]['default_options'];
				foreach( $this->locations[$location]['options'] as $opt ) {
					if ( isset( $defaults[$opt] ) )
						$options[$opt] = $defaults[$opt];
				}
			}
			if ( !$prefix ) $prefix = $this->prefix;
			if ( !empty( $options ) )
				foreach ($options as $k => $v) {
					if ( !isset( $v['name'] ) )		$v['name'] = ucwords( strtr( $k, '_', ' ' ) );
					if ( !isset( $v['type'] ) )		$v['type'] = 'checkbox';
					if ( !isset( $v['default'] ) )	$v['default'] = null;
					if ( !isset( $v['initial_options'] ) ) $v['initial_options'] = $v['default'];
					if ( $v['type'] == 'custom' && ( !isset( $v['nowrap'] ) ) ) $v['nowrap'] = true;
					elseif ( !isset( $v['nowrap'] ) ) $v['nowrap'] = null;
					if ( isset( $v['condshow'] ) ) {
						if ( !is_array( $this->script_data ) ) $this->script_data = Array();
						if ( !isset( $this->script_data['condshow'] ) ) $this->script_data['condshow'] = Array();
						$this->script_data['condshow'][$prefix . $k] = $v['condshow'];
					}
					if ( $v['type'] == 'submit' ) {
						if ( !isset($v['save']) )  $v['save']  = false;
						if ( !isset($v['label']) ) $v['label'] = 'none';
						if ( !isset($v['prefix']) ) $v['prefix'] = false;
					} else {
						if ( !isset($v['label'] ) ) $v['label'] = null;
					}
					if ( $v['type'] == 'hidden' ) {
						if ( !isset($v['label']) ) $v['label'] = 'none';
						if ( !isset($v['prefix']) ) $v['prefix'] = false;
					}
					if ( $v['type'] == 'text' ) {
						if ( !isset($v['size'])) $v['size'] = 57;
					}
					if ( $v['type'] == 'textarea' ) {
						if ( !isset($v['cols'])) $v['cols'] = 57;
						if ( !isset($v['rows'])) $v['rows'] = 2;
					}
					if ( !isset($v['save']) ) $v['save'] = true;
					if ( !isset($v['prefix']) ) $v['prefix'] = true;
					if ( $v['prefix'] )
						$opts[$prefix . $k] = $v;
					else
						$opts[$k] = $v;
				}
			return $opts;
		}

		/** Generates just the default option names and values */
		function default_options( $location = null, $defaults = null ) {
			$options = $this->setting_options( $location, $defaults );
			$opts = Array();
			foreach ( $options as $k => $v ) if ($v['save']) $opts[$k] = $v['default'];
			return $opts;
		}
		
		/** Gets the current options stored for a given location. */
		function get_current_options( $opts = Array(), $location = null, $defaults = null, $post = null ) {
			$prefix = $this->get_prefix( $location );
			$get_opts = '';
			if ( empty( $location ) )
				$type = 'settings';
			else
				$type = $this->locations[$location]['type'];
			if ( $type === 'settings' ) {
				$get_opts = $this->get_class_option();
			} elseif ( $type == 'metabox' ) {
				if ( $post == null ) {
					global $post;
				}
				if ( isset( $post ) ) {
					$get_opts = '_' . $prefix . $location;
					$get_opts = get_post_meta( $post->ID, $get_opts, true );
				}
			}
			$defs = $this->default_options( $location, $defaults );
			if ($get_opts == '')
				$get_opts = $defs;
			else
				$get_opts = wp_parse_args( $get_opts, $defs );
			$opts = wp_parse_args( $opts, $get_opts );
			return $opts;
		}

		/** Updates the options array in the module; loads saved settings with get_option() or uses defaults */
		function update_options( $opts = Array(), $location = null, $defaults = null ) {
			if ($location === null )
				$type = 'settings';
			else
				$type = $this->locations[$location][$type];
			if ( $type === 'settings' ) {
				$get_opts = $this->get_class_option();
			}
			if ($get_opts === FALSE)
				$get_opts = $this->default_options( $location, $defaults );
			else
				$this->setting_options( $location, $defaults ); // hack -- make sure this runs anyhow, for now -- pdb
			$this->options = wp_parse_args( $opts, $get_opts );
		}
	}
}
