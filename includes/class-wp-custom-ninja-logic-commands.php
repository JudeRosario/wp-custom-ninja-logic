<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access allowed' );
}

if ( ! class_exists( 'WP_Custom_Ninja_Logic_Plugin_Commands' ) ) :

	/**
	 * Class which takes care of AJAX processing and supplying table data to the front end.
	 */
	class WP_Custom_Ninja_Logic_Plugin_Commands {

		public $instance;

		const DATE_FIELD_ID = 5; 

		public function __construct($instance) {
			$this->instance = $instance;
			add_filter( 'ninja_forms_submit_data', array( $this, 'validate_form' ) );
		}

		/**
		 * Reads data sent in via AJAX and returns a list of users
		 *
		 * @param array $data - Data that is sent in via AJAX after cleaning.
		 *
		 * @return array - validated form data
		 */
		public function validate_form( $form_data = array() ) {
			$menu_options = get_option( 'wpcnl_settings' );
			$ids = explode(',', $menu_options['form_id']); 
			$ids = array_filter(array_map('intval', $ids), 'is_int');


			if (!in_array($form_data['id'], $ids)) {
				return $form_data;
			}
			
			$mapping = get_option('wpcnl_field_mapping');

			foreach ($form_data['fields'] as $field) {

				if (isset($mapping[$field['id']])) {
					$flag = false;
					foreach ($mapping[$field['id']]['conditionally_require'] as $id) {
						if(isset($form_data['fields'][$id])) {
							$flag = true;
						}
					}
					if (!$flag) {
						$form_data['errors']['fields'][$field['id']] = __('Please check the data submitted');
					}
				}
			}
			
			return $form_data;
		}
	}
endif;
