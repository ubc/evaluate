<?php
/*
Plugin Name: Evaluate
Plugin URI: 
Description: Simple evaluation / Ratings plugin, includes voting, bookmarking etc.
Version: 0.1
Author: 
Author URI: 
License: GPLv2 or later.
*/
if ( !defined('ABSPATH') )
	die('-1');

global $wpdb;

define( 'EVALUATE_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'EVALUATE_BASENAME', plugin_basename(__FILE__) );
define( 'EVALUATE_DIR_URL',  plugins_url( ''  , EVALUATE_BASENAME ) );
define( 'EVALUATE_DB_VERSION', 1 );
define( 'EVALUATE_DB_TABLE', $wpdb->prefix .'evaluate' );


require( 'lib/class.evaluate.php' );
require( 'lib/class.evaluate-admin.php' );

if(!class_exists('WP_List_Table')) {
  require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php');
}
require( 'lib/class.evaluate-content-list-table.php');
require( 'lib/class.evaluate-users-list-table.php');
require( 'lib/class.evaluate-votes-list-table.php');

add_action( 'init',       array( 'Evaluate', 'init' ) );
add_action( 'wp_print_styles', array( 'Evaluate', 'enqueue_style' ) );
add_action( 'admin_menu', array( 'Evaluate_Admin', 'admin_menu' ) );
add_action( 'admin_init', array( 'Evaluate_Admin', 'init' ) );
add_filter( 'the_content',array( 'Evaluate', 'the_content' ), 999 );

// install and uninstall
register_activation_hook( __FILE__, array( 'Evaluate', 'install' ) );

add_filter('wpmu_drop_tables', array('Evaluate', 'remove_custom_table'));
