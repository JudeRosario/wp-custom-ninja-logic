<?php
/**
 * The core plugin class, written with extensibility and re-usability in mind.
 *
 * @package WP_Custom_Ninja_Logic
 * @version 0.0.1
 */

if ( ! class_exists( 'WP_Custom_Ninja_Logic' ) ) :
	/**
	 * Class which handles templates, scripts, the ajax handler and general plugin setup
	 */
	class WP_Custom_Ninja_Logic {

		const VERSION = '0.0.1';

		/**
		 * Stores a reference to a singleton of this class
		 *
		 * @var $instance
		 */
		protected static $instance = null;

		/**
		 * Stores a reference to the ajax handler
		 *
		 * @var $instance
		 */
		protected $commands_instance = null;

		/**
		 * Directory where the plugin templates are stored
		 *
		 * @var mixed
		 */
		private $template_directories;

		/**
		 * Constructor
		 */
		public function __construct() {

			if ( is_file( WPCNL_DIR . '/includes/class-wp-custom-ninja-logic-commands.php' ) ) {
				include_once WPCNL_DIR . '/includes/class-wp-custom-ninja-logic-commands.php';
				$this->commands_instance = new WP_Custom_Ninja_Logic_Plugin_Commands($this);
			}

			// Initialize plugin.
			add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
			add_action( 'wp_ajax_wpcnl_ajax', array( $this, 'ajax_handler' ) );
			add_action( 'admin_menu', array( $this, 'wpcnl_add_plugin_page' ) );
			add_action( 'admin_init', array( $this, 'wpcnl_page_init' ) );
			add_action( 'ninja_forms_enqueue_scripts', array( $this, 'ninja_forms_enqueue_scripts' ) );
			add_action( 'load-toplevel_page_menu-customiser', array( $this, 'enqueue_scripts' ) );

			do_action( 'wpcnl_loaded', $this );
		}

		/**
		 * Returns the only instance of this class
		 *
		 * @return WP_Custom_Ninja_Logic
		 */
		public static function instance() {
			if ( empty( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * The AJAX handler (i.e. commands via admin-ajax.php
		 */
		public function ajax_handler() {
			// @codingStandardsIgnoreStart
			$nonce = empty( $_POST['nonce'] ) ? '' : filter_var( $_POST['nonce'], FILTER_SANITIZE_STRING );

			if ( wp_verify_nonce( $nonce, 'wpcnl_ajax_nonce' ) ) {
				wp_send_json( array( 'error' => 'Security check failed, you are not authorized to view this table' ) );
			}

			if ( ! $this->user_has_access() ) {
				wp_send_json( array( 'error' => $this->unauthorized_access_message() ) );
			}

			if ( empty( $_POST['subaction'] ) ) {
				wp_send_json( array( 'error' => 'No subaction set, AJAX request failed' ) );
			}

			$subaction = filter_var( $_POST['subaction'], FILTER_SANITIZE_STRING );

			$data = filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );

			if ( ! is_callable( array( $this->commands_instance, $subaction ) ) ) {
				error_log( "wpcnl: ajax_handler: no such command ($subaction)" );
				wp_send_json( array( 'error' => "Invalid subaction - {$subaction}, request failed" ) );
			} else {
				$results = call_user_func( array( $this->commands_instance, $subaction ), $data );

				if ( is_wp_error( $results ) ) {
					$results = array(
						'result' => false,
						'error_code' => $results->get_error_code(),
						'error_message' => $results->get_error_message(),
						'error_data' => $results->get_error_data(),
					);
				}

				wp_send_json( $results );
			}
			// @codingStandardsIgnoreEnd
		}

		/**
		 * Gets valid dates
		 */
		public function get_available_dates( $start_date = 'now', $end_date = '+2 weeks', $format = 'Y-m-d' ) {

			$dates = array();
			$this->wpcnl_options = get_option( 'wpcnl_settings' );
			$start_date = isset( $this->wpcnl_options['start_date'] ) ? $this->wpcnl_options['start_date'] : $start_date;
			$end_date = isset( $this->wpcnl_options['end_date'] ) ? $this->wpcnl_options['end_date'] : $end_date;
			$cutoff_time = isset( $this->wpcnl_options['cutoff_time'] ) ? 'today '.$this->wpcnl_options['cutoff_time'] : 'today 6:00PM';

			$cutoff_time = strtotime($cutoff_time);
			$starting_soon = (
				date('Y-m-d', strtotime($start_date)) == date('Y-m-d') || 
				date('Y-m-d', strtotime($start_date)) == date('Y-m-d', strtotime('tomorrow'))
				) ? true : false;


			if (strtotime($start_date) < current_time('timestamp') || $starting_soon) {
				$start_date = current_time('timestamp') <  $cutoff_time ? '+1 day' : '+2 days';
			}

			$current = strtotime($start_date);
			$last = strtotime($end_date);

			$step = '+1 day';

			while( $current <= $last ) {
				$dates[] = date("Y-m-d", $current);
				$current = strtotime($step, $current);
			}

			return $dates;
		}

		/**
		 * Runs on the plugins_loaded WordPress action
		 */
		public function get_wpcnl_data() {
			$this->wpcnl_options = get_option( 'wpcnl_settings' );
			return $this->wpcnl_options['menu_items_observed'];
		}

		/**
		 * Runs on the plugins_loaded WordPress action
		 */
		public function plugins_loaded() {
			// Load translations from the languages directory.
			$locale = get_locale();

			// This filter is documented in /wp-includes/l10n.php.
			$locale = apply_filters( 'plugin_locale', $locale, 'wp-custom-ninja-logic' );
			load_textdomain( 'wp-custom-ninja-logic', WP_LANG_DIR . '/plugins/wp-custom-ninja-logic' . $locale . '.mo' );
			load_plugin_textdomain( 'wp-custom-ninja-logic', false, WPCNL_DIR . '/languages' );
		}

		/**
		 * Runs on the ninja_forms_enqueue_scripts WordPress action
		 */
		public function ninja_forms_enqueue_scripts($form) {
			$this->wpcnl_options = get_option( 'wpcnl_settings' );
			$ids = explode(',', $this->wpcnl_options['form_id']);
			if (in_array($form['form_id'], $ids)) {
				$this->enqueue_scripts($form['form_id']);
			}
		}

		public function wpcnl_add_plugin_page() {
			add_menu_page(
				'Menu Logic Customiser', // page_title
				'Menu Logic Customiser', // menu_title
				'manage_options', // capability
				'menu-customiser', // menu_slug
				array( $this, 'wpcnl_create_admin_page' ), // function
				'dashicons-admin-tools', // icon_url
				3 // position
			);
		}

		public function wpcnl_create_admin_page() {
			$this->wpcnl_options = get_option( 'wpcnl_settings' ); ?>

            <div class="wrap">
                <h2>Menu Customiser</h2>
                <p>Use these options to customise the menu</p>
				<?php settings_errors(); ?>

                <form method="post" action="options.php">
					<?php
					settings_fields( 'wpcnl_option_group' );
					do_settings_sections( 'menu-customiser-admin' );
					submit_button();
					?>
                </form>
            </div>
		<?php }

		public function wpcnl_page_init() {
			register_setting(
				'wpcnl_option_group', // option_group
				'wpcnl_settings', // option_name
				array( $this, 'wpcnl_sanitize' ) // sanitize_callback
			);

			add_settings_section(
				'wpcnl_setting_section', // id
				'Settings', // title
				array( $this, 'wpcnl_section_info' ), // callback
				'menu-customiser-admin' // page
			);

			add_settings_field(
				'form_id', // id
				'From IDs', // title
				array( $this, 'form_id_callback' ), // callback
				'menu-customiser-admin', // page
				'wpcnl_setting_section' // section
			);

			add_settings_field(
				'start_date', // id
				'Start Date', // title
				array( $this, 'start_date_callback' ), // callback
				'menu-customiser-admin', // page
				'wpcnl_setting_section' // section
			);

			add_settings_field(
				'end_date', // id
				'End Date', // title
				array( $this, 'end_date_callback' ), // callback
				'menu-customiser-admin', // page
				'wpcnl_setting_section' // section
			);

			add_settings_field(
				'cutoff_time', // id
				'Cut off time', // title
				array( $this, 'cutoff_time_callback' ), // callback
				'menu-customiser-admin', // page
				'wpcnl_setting_section' // section
			);

			add_settings_field(
				'menu_items_observed', // id
				'Menu Items Rules', // title
				array( $this, 'menu_items_observed_callback' ), // callback
				'menu-customiser-admin', // page
				'wpcnl_setting_section' // section
			);
		}

		public function wpcnl_sanitize($input) {
			$sanitized_values = array();

			if ( isset( $input['form_id'] ) ) {
				 $ids = explode(',', sanitize_text_field( $input['form_id'] ));
				 $ids = array_filter(array_map('intval', $ids), 'is_int');
				 $sanitized_values['form_id'] = implode(',', $ids);
			}

			if ( isset( $input['start_date'] ) ) {
				$sanitized_values['start_date'] = sanitize_text_field( $input['start_date'] );
			}

			if ( isset( $input['end_date'] ) ) {
				$sanitized_values['end_date'] = sanitize_text_field( $input['end_date'] );
			}

			if ( isset( $input['cutoff_time'] ) ) {
				$sanitized_values['cutoff_time'] = sanitize_text_field( $input['cutoff_time'] );
			}

			if ( isset( $input['menu_items_observed'] ) ) {

				$rows = preg_split( '/\r\n|\r|\n/',  esc_textarea( $input['menu_items_observed'] ));
				$rows = array_filter($rows, 'trim');
				$mapping = array();

				foreach ($rows as $row) {
					$values = explode("|", $row);
					$require = explode(",", $values[1]);

					if (array_key_exists($values[0], $mapping)) continue;

					foreach ($require as $id) {
						if (in_array($id, $mapping)) continue;
						$mapping[$values[0]]['conditionally_require'][] = filter_var($id, FILTER_VALIDATE_INT);
					}


					$mapping[$values[0]]['error_message'] = empty($values[2]) ? "-" : filter_var($values[2], FILTER_SANITIZE_STRING);

					if (!isset($mapping[$values[0]]['conditionally_require'])) {
						unset($mapping[$values[0]]);
					}
				}

				update_option('wpcnl_field_mapping', $mapping);

				$sanitized = array();
				foreach ($mapping as $key => $value) {
					$sanitized[] = $key . '|' . implode(',', $value['conditionally_require']) . '|' . $value['error_message'];
				}

				if (count($rows) != count($sanitized)) {
					add_settings_error(
						'menu_items_observed',
						'invalid_field_ids',
						"Invalid or empty rows detected, only the valid ones will be saved.",
						'error'
					);
				}

				$sanitized_values['menu_items_observed'] = implode('&#13;&#10;' , $sanitized);
			}

			if ( isset( $input['menu_items_required'] ) ) {
				$ids = explode(',', esc_textarea( $input['menu_items_required'] ));
				$valid = array_filter(array_map('trim',  $ids), function ($email) {
					return (filter_var($email, FILTER_VALIDATE_INT))
						? true
						: false;
				});

				if (count($ids) != count($valid)) {
					add_settings_error(
						'menu_items_required',
						'invalid_field_ids',
						"Invalid or empty field ids detected, only the valid ones will be saved.",
						'error'
					);
				}

				$sanitized_values['menu_items_required'] = implode(',' , $valid);
			}

			return $sanitized_values;
		}

		public function wpcnl_section_info() {

		}

		public function form_id_callback() {
			printf(
				'<input class="regular-text" type="text" name="wpcnl_settings[form_id]" id="form_id" value="%s">',
				isset( $this->wpcnl_options['form_id'] ) ? esc_attr( $this->wpcnl_options['form_id']) : ''
			);
			printf('<p><small>Form IDs to Observe</small></p>');
		}

		public function start_date_callback() {
			printf(
				'<input class="regular-text" readonly="readonly" type="text" name="wpcnl_settings[start_date]" id="start_date" value="%s">',
				isset( $this->wpcnl_options['start_date'] ) ? esc_attr( $this->wpcnl_options['start_date']) : ''
			);
			printf('<p><small>Event Start Date</small></p>');
		}

		public function end_date_callback() {
			printf(
				'<input class="regular-text" type="text" readonly="readonly" name="wpcnl_settings[end_date]" id="end_date" value="%s">',
				isset( $this->wpcnl_options['end_date'] ) ? esc_attr( $this->wpcnl_options['end_date']) : ''
			);
			printf('<p><small>Event End Date</small></p>');
		}

		public function menu_items_observed_callback() {
			printf('<p><small>Input Field IDs, separated by a comma, in groups separated by a pipe | followed by the error text for these fields</small></p>');
			printf(
				'<textarea class="large-text" rows="6" cols="100" name="wpcnl_settings[menu_items_observed]" id="menu_items_observed">%s</textarea>',
				isset( $this->wpcnl_options['menu_items_observed'] ) ? esc_attr( $this->wpcnl_options['menu_items_observed']) : ''
			);
			printf('<p><small>Eg - 12|13,14|Either rice or nan is needed with this dish</small></p>');
		}

		public function cutoff_time_callback() {

			$output = '';
			$interval = '+60 minutes';
		    $current = strtotime( '00:00' );
		    $end = strtotime( '23:59' );

		    while( $current <= $end ) {
		        $time = date( 'H:i', $current );
		        $sel = ( $time == $this->wpcnl_options['cutoff_time'] ) ? ' selected' : '';

		        $output .= "<option value=\"{$time}\"{$sel}>" . date( 'h.i A', $current ) .'</option>';
		        $current = strtotime( $interval, $current );
		    }

			printf(
				'<select class="regular-text" type="text" name="wpcnl_settings[cutoff_time]" id="cutoff_time" value="%s">'
				. $output .

				'</select>',
				isset( $this->wpcnl_options['cutoff_time'] ) ? esc_attr( $this->wpcnl_options['cutoff_time']) : ''
			);
			printf('<p><small>Specify cut off time for the day</small></p>');
		}

		/**
		 * Renders or returns the requested template
		 *
		 * @param String  $path the path, relative to this plugin's template directory, of the template to use.
		 * @param boolean $return_instead_of_echo whether to return the output instead of echoing it.
		 * @param array   $extract_these an array of key/value pairs of variables to provide in the variable scope when the template runs.
		 *
		 * @return String|void - the results of running the template, if $return_instead_of_echo was set.
		 */
		public function include_template( $path, $return_instead_of_echo = false, $extract_these = array() ) {

			// Lazy-load: get them the first time that we need them.
			if ( ! is_array( $this->template_directories ) ) {
				$this->register_template_directories();
			}

			if ( $return_instead_of_echo ) {
				ob_start();
			}

			if ( preg_match( '#^([^/]+)/(.*)$#', $path, $matches ) ) {
				$prefix = $matches[1];
				$suffix = $matches[2];
				if ( isset( $this->template_directories[ $prefix ] ) ) {
					$template_file = $this->template_directories[ $prefix ] . '/' . $suffix;
				}
			}

			if ( ! isset( $template_file ) ) {
				$template_file = WPCNL_DIR . '/templates/' . $path;
			}

			$template_file = apply_filters( 'wpcnl_template', $template_file, $path );

			do_action( 'wpcnl_before_template', $path, $template_file, $return_instead_of_echo, $extract_these );

			if ( isset( $template_file ) && file_exists( $template_file ) ) {
				include_once $template_file;
			} else {
				// @codingStandardsIgnoreLine
				error_log( "WPCNL: template not found: $template_file" );
			}

			do_action( 'wpcnl_after_template', $path, $template_file, $return_instead_of_echo, $extract_these );

			if ( $return_instead_of_echo ) {
				return ob_get_clean();
			}
		}

		/**
		 * Get the directory to find templates in
		 *
		 * @return String - the template directory
		 */
		public function get_templates_dir() {
			return apply_filters( 'wpcnl_templates_dir', wp_normalize_path( WPCNL_DIR . '/templates' ) );
		}

		/**
		 * This method is run to build up an internal list of available templates
		 */
		private function register_template_directories() {

			$template_directories = array();

			$templates_dir = $this->get_templates_dir();
			// @codingStandardsIgnoreLine
			if ( $dh = opendir( $templates_dir ) ) {
				// @codingStandardsIgnoreLine
				while ( false !== ( $file = readdir( $dh ) ) ) {
					if ( '.' === $file || '..' === $file ) {
						continue;
					}
					if ( is_dir( $templates_dir . '/' . $file ) ) {
						$template_directories[ $file ] = $templates_dir . '/' . $file;
					}
				}
				closedir( $dh );
			}

			// This is the optimal hook for most extensions to hook into.
			$this->template_directories = apply_filters( 'wpcnl_template_directories', $template_directories );

		}

		/**
		 * Run this method to enqueue scripts needed for the tables
		 */
		public function enqueue_scripts($form = false) {

			$script_version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? self::VERSION . '.' . time() : self::VERSION;
			$min_or_not = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			$scripts = array();

			if ( is_admin() ) {
				$scripts[] = array(
					'handle'  => 'wpcnl-admin-js',
					'script'  => 'wpcnl-admin',
					'version' => '0.0.1',
					'deps' => array( 'jquery', 'jquery-ui-datepicker'),
				);

				$scripts[] = array(
					'handle'  => 'wpcnl-admin-css',
					'script'  => 'wpcnl-admin',
					'version' => '0.0.1',
					'type' => 'css',
				);

			} else {
				$scripts[] = array(
					'handle'  => 'wpcnl-js',
					'script'  => 'wpcnl',
					'version' => '0.0.1',
					'deps' => array( 'jquery'),
				);
			}



			$scripts = apply_filters( 'wpcnl_enqueue_scripts', $scripts, $script_version, $min_or_not );

			do_action( 'wpcnl_before_enqueue_scripts', $script_version, $min_or_not, $scripts );

			foreach ( $scripts as $script ) {

				$version = isset( $script['version'] ) ? $script['version'] : $script_version;
				$deps = isset( $script['deps'] ) ? $script['deps'] : array();
				$min_ext = ! empty( $script['has_min_only'] ) ? '.min' : ( empty( $script['has_min'] ) ? '' : $min_or_not );
				$type = empty( $script['type'] ) ? 'js' : $script['type'];
				$url_prefix = empty( $script['url_prefix'] ) ? WPCNL_URL : $script['url_prefix'];

				if ( 'css' === $type ) {
					wp_enqueue_style( $script['handle'], $url_prefix . '/css/' . $script['script'] . $min_ext . '.css', $deps, $version );
				} else {
					wp_enqueue_script( $script['handle'], $url_prefix . '/js/' . $script['script'] . $min_ext . '.js', $deps, $version, true );
				}
			}

			do_action( 'wpcnl_after_enqueue_scripts', $script_version, $min_or_not );

			$this->wpcnl_options = get_option( 'wpcnl_settings' );

			$localize = array(
				'ajax_url' => admin_url( 'admin-ajax.php', 'relative' ),
				'wpcnl_ajax_nonce' => wp_create_nonce( 'ajax_nonce' ),
				'wpcnl_available_dates' => $this->get_available_dates(),
				'wpcnl_time_zones' 	=> date_default_timezone_get(),
				'wpcnl_data' => $this->get_wpcnl_data(),
				'wpcnl_form' => $form,
			);

			wp_localize_script( 'wpcnl-js', 'wpcnl', $localize );
		}


		/**
		 * Displays the unauthorized access message on the front end
		 */
		private function unauthorized_access_message() {
			return esc_attr( 'You are not allowed to access this content', 'wp-custom-ninja-logic' );
		}

		/**
		 * Checks if a user has access to the tables
		 *
		 * @see - https://core.trac.wordpress.org/ticket/22624
		 * @return boolean - true if yes, false otherwise
		 */
		private function user_has_access() {
			$user = wp_get_current_user();

			if ( in_array( 'administrator', (array) $user->roles, true ) ) {
				return true;
			}

			return false;
		}
	}
endif;
