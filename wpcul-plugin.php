<?php

/**
Plugin Name: WP Ninja Forms Menu Customizer
Plugin URI: https://codeable.io
Description: Allows for admins to control what gets ordered and on which dates
Version: 1.0.0
Author: Jude
Author URI: https://codeable.io
Domain Path: /languages
License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access allowed' );
}

/**  Use these to avoid hard coding any paths/urls later. */
if ( ! defined( 'WPCNL_DIR' ) ) {
	define( 'WPCNL_DIR', dirname( __FILE__ ) );
}
if ( ! defined( 'WPCNL_URL' ) ) {
	define( 'WPCNL_URL', plugins_url( '', __FILE__ ) );
}

if ( is_file( WPCNL_DIR . '/includes/class-wp-custom-ninja-logic.php' ) ) {
	include_once WPCNL_DIR . '/includes/class-wp-custom-ninja-logic.php';
	$GLOBALS['wp_cul'] = WP_Custom_Ninja_Logic::instance();
}
